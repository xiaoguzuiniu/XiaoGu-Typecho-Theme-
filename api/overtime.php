<?php

declare(strict_types=1);

function overtimeApiRespond(int $status, string $message, ?array $data = null): void
{
    http_response_code($status);
    if (class_exists(\Typecho\Response::class, false)) \Typecho\Response::getInstance()->setStatus($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = ['success' => $status >= 200 && $status < 300, 'message' => $message];
    if ($data !== null) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function overtimeApiAuthorizationHeader(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) return trim($_SERVER[$key]);
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) return trim((string) $value);
        }
    }
    return '';
}

function overtimeApiRequireToken(\Typecho\Db $db): void
{
    $themeRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme', 0)->limit(1));
    $theme = trim((string) ($themeRow['value'] ?? ''));
    if ($theme === '') overtimeApiRespond(503, '当前主题尚未配置');
    $settingsRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme:' . $theme, 0)->limit(1));
    $settings = json_decode((string) ($settingsRow['value'] ?? ''), true);
    $token = is_array($settings) ? trim((string) ($settings['overtimeApiToken'] ?? '')) : '';
    if (strlen($token) < 24) overtimeApiRespond(503, '请先在外观设置中配置加班接口密钥');
    $authorization = overtimeApiAuthorizationHeader();
    if (strncasecmp($authorization, 'Bearer ', 7) !== 0
        || !hash_equals($token, trim(substr($authorization, 7)))) {
        header('WWW-Authenticate: Bearer');
        overtimeApiRespond(401, '密钥不正确');
    }
}

function overtimeApiAmount($value, string $field, float $maximum): float
{
    if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
        || (float) $value < 0 || (float) $value > $maximum
        || round((float) $value, 2) !== (float) $value) {
        overtimeApiRespond(400, $field . ' 必须是非负、最多两位小数的数字');
    }
    return (float) $value;
}

function overtimeApiEntry(array $entry): array
{
    return [
        'date' => (string) $entry['work_date'],
        'weekday_hours' => (float) $entry['weekday_hours'],
        'weekend_hours' => (float) $entry['weekend_hours'],
        'pay' => (float) $entry['pay'],
        'note' => (string) $entry['note'],
    ];
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    overtimeApiRespond(405, 'Method Not Allowed');
}

try {
    require_once dirname(__DIR__) . '/config.inc.php';
    require_once __DIR__ . '/overtime-data.php';
    $db = \Typecho\Db::get();
    overtimeApiRequireToken($db);

    $currentMonth = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai')))->format('Y-m');
    if ($method === 'GET') {
        $month = $_GET['month'] ?? $currentMonth;
        if (!is_string($month) || !xiaoguOvertimeValidMonth($month)) overtimeApiRespond(400, 'month 格式应为 YYYY-MM');
        $entries = xiaoguOvertimeMonthEntries($db, $month);
        $summary = xiaoguOvertimeMonthSummary($entries);
        overtimeApiRespond(200, 'success', array_merge(['month' => $month], $summary, [
            'entries' => array_map('overtimeApiEntry', $entries),
        ]));
    }

    if (strpos(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json') === false) {
        overtimeApiRespond(415, '请使用 application/json');
    }
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw === false ? '' : $raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) overtimeApiRespond(400, '请求 JSON 格式错误');
    $date = $payload['date'] ?? null;
    if (!is_string($date) || !xiaoguOvertimeValidDate($date)) overtimeApiRespond(400, 'date 格式应为 YYYY-MM-DD');
    foreach (['weekday_hours', 'weekend_hours', 'pay'] as $field) {
        if (!array_key_exists($field, $payload)) overtimeApiRespond(400, '缺少参数：' . $field);
    }
    $weekdayHours = overtimeApiAmount($payload['weekday_hours'], 'weekday_hours', 9999.99);
    $weekendHours = overtimeApiAmount($payload['weekend_hours'], 'weekend_hours', 9999.99);
    $pay = overtimeApiAmount($payload['pay'], 'pay', 99999999.99);
    $note = $payload['note'] ?? '';
    if (!is_string($note) || mb_strlen($note, 'UTF-8') > 300 || strlen($note) > 1000) overtimeApiRespond(400, '备注不能超过 300 字');

    if ($weekdayHours + $weekendHours === 0.0 && $pay !== 0.0) {
        overtimeApiRespond(400, '加班时长为 0 时，加班费也必须为 0');
    }
    xiaoguOvertimeUpsert($db, $date, $weekdayHours, $weekendHours, $pay, trim($note));
    $month = substr($date, 0, 7);
    $entries = xiaoguOvertimeMonthEntries($db, $month);
    overtimeApiRespond(200, 'success', array_merge(['month' => $month], xiaoguOvertimeMonthSummary($entries), [
        'date' => $date,
        'entries' => array_map('overtimeApiEntry', $entries),
    ]));
} catch (\Throwable $error) {
    error_log('[overtime-api] ' . $error->getMessage());
    overtimeApiRespond(500, 'Internal Server Error');
}
