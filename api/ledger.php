<?php

declare(strict_types=1);

const LEDGER_API_MAX_IMAGE_BYTES = 12 * 1024 * 1024;
const LEDGER_API_MAX_ENCODED_BYTES = 18 * 1024 * 1024;

function ledgerApiRespond(int $status, int $code, string $message, ?array $data = null): void
{
    http_response_code($status);
    if (class_exists(\Typecho\Response::class, false)) {
        \Typecho\Response::getInstance()->setStatus($status);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = ['code' => $code, 'message' => $message];
    if ($data !== null) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ledgerApiAuthorizationHeader(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) return trim((string) $value);
        }
    }
    return '';
}

function ledgerApiThemeSettings(\Typecho\Db $db): array
{
    $themeRow = $db->fetchRow(
        $db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme', 0)->limit(1)
    );
    $theme = trim((string) ($themeRow['value'] ?? ''));
    if ($theme === '') throw new \RuntimeException('Active theme is not configured');
    $settingsRow = $db->fetchRow(
        $db->select('value')->from('table.options')
            ->where('name = ? AND user = ?', 'theme:' . $theme, 0)->limit(1)
    );
    $settings = json_decode((string) ($settingsRow['value'] ?? ''), true);
    return is_array($settings) ? $settings : [];
}

function ledgerApiRequireToken(array $settings): void
{
    $configured = trim((string) ($settings['ledgerApiToken'] ?? ''));
    if (strlen($configured) < 24) ledgerApiRespond(503, 503, '记账快捷指令密钥尚未配置');
    $header = ledgerApiAuthorizationHeader();
    if (strncasecmp($header, 'Bearer ', 7) !== 0
        || !hash_equals($configured, trim(substr($header, 7)))) {
        header('WWW-Authenticate: Bearer');
        ledgerApiRespond(401, 401, '密钥不正确');
    }
}

function ledgerApiCategories(array $settings): array
{
    return xiaoguLedgerCategories((string) ($settings['ledgerCategories'] ?? ''));
}

function ledgerApiPayload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') === false) return $_POST;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw === false ? '' : $raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
        ledgerApiRespond(400, 400, '请求 JSON 格式错误');
    }
    return $payload;
}

function ledgerApiBase64File(array $payload): ?array
{
    if (!isset($payload['image_base64'])) return null;
    if (!is_string($payload['image_base64'])) ledgerApiRespond(400, 400, 'image_base64 参数格式错误');
    $encoded = trim($payload['image_base64']);
    if (preg_match('#^data:image/[^;]+;base64,#i', $encoded, $matches)) {
        $encoded = substr($encoded, strlen($matches[0]));
    }
    $encoded = preg_replace('/\s+/u', '', $encoded);
    if (!is_string($encoded) || $encoded === '') return null;
    if (strlen($encoded) > LEDGER_API_MAX_ENCODED_BYTES) ledgerApiRespond(413, 413, '照片大小超过限制');
    $bytes = base64_decode($encoded, true);
    if ($bytes === false || $bytes === '') ledgerApiRespond(400, 400, '照片编码无效');
    if (strlen($bytes) > LEDGER_API_MAX_IMAGE_BYTES) ledgerApiRespond(413, 413, '照片不能超过 12MB');
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $extension = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'image/avif' => 'avif',
        'image/heic' => 'heic', 'image/heif' => 'heif',
    ][$mime] ?? null;
    if ($extension === null) ledgerApiRespond(400, 400, '请选择支持的图片文件');
    return [
        'name' => 'ledger-' . date('Ymd-His') . '.' . $extension,
        'bytes' => $bytes,
        'size' => strlen($bytes),
        'type' => $mime,
    ];
}

function ledgerApiMultipartFile(): ?array
{
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) return null;
    return $_FILES['image'];
}

function ledgerApiSpentAt($value): string
{
    $value = trim((string) $value);
    if ($value === '') return date('Y-m-d H:i:s', \Typecho\Date::time());
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    $errors = \DateTimeImmutable::getLastErrors();
    if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        ledgerApiRespond(400, 400, '消费时间格式应为 YYYY-MM-DD HH:MM');
    }
    return $date->format('Y-m-d H:i:s');
}

function ledgerApiDeleteUpload(?array $upload): void
{
    if (!$upload) return;
    try {
        \Widget\Upload::deleteHandle(['attachment' => new \Typecho\Config($upload)]);
    } catch (\Throwable $error) {
        error_log('[ledger-api] upload cleanup failed: ' . $error->getMessage());
    }
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    ledgerApiRespond(405, 405, 'Method Not Allowed');
}

$upload = null;
$saved = false;
$transaction = null;

try {
    require_once dirname(__DIR__) . '/config.inc.php';
    require_once dirname(__DIR__) . '/usr/themes/XiaoGu/ledger.php';
    \Widget\Init::alloc();

    $db = \Typecho\Db::get();
    $settings = ledgerApiThemeSettings($db);
    ledgerApiRequireToken($settings);
    $categories = ledgerApiCategories($settings);
    xiaoguLedgerEnsureTable($db);

    $page = $db->fetchRow(
        $db->select('cid', 'slug', 'authorId')->from('table.contents')
            ->where('slug = ? AND type = ? AND status = ?', 'ledger', 'page', 'publish')->limit(1)
    );
    if (!$page) throw new \RuntimeException('找不到已发布的 ledger 记账页面');
    $options = \Widget\Options::alloc();
    $pagePath = \Typecho\Router::url('page', ['cid' => (int) $page['cid'], 'slug' => (string) $page['slug']]);
    $pageUrl = \Typecho\Common::url($pagePath, (string) $options->siteUrl);

    if ($method === 'GET') {
        $month = xiaoguLedgerCurrentPeriodMonth();
        $period = xiaoguLedgerPeriodRange($month);
        $budgets = xiaoguLedgerMonthlyBudgets((string) ($settings['ledgerMonthlyBudgets'] ?? ''));
        $defaultBudget = max(0, round((float) ($settings['ledgerDefaultBudget'] ?? 0), 2));
        ledgerApiRespond(200, 0, 'success', [
            'categories' => $categories,
            'current_month' => $month,
            'period_start' => $period['start']->format('Y-m-d H:i:s'),
            'period_end' => $period['end']->format('Y-m-d H:i:s'),
            'monthly_budget' => $budgets[$month] ?? $defaultBudget,
            'ledger_url' => $pageUrl,
        ]);
    }

    $payload = ledgerApiPayload();
    $amountRaw = isset($payload['amount']) && is_scalar($payload['amount']) ? trim((string) $payload['amount']) : '';
    if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $amountRaw) || (float) $amountRaw <= 0) {
        ledgerApiRespond(400, 400, '请输入正确的消费金额，最多保留两位小数');
    }
    $amount = round((float) $amountRaw, 2);
    $category = isset($payload['category']) && is_string($payload['category'])
        ? trim((string) preg_replace('/\s+/u', ' ', $payload['category'])) : '';
    if (!in_array($category, $categories, true)) ledgerApiRespond(400, 400, '请选择后台已配置的消费类型');
    $note = isset($payload['note']) && is_scalar($payload['note'])
        ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $payload['note'])), 0, 255) : '';
    $spentAt = ledgerApiSpentAt($payload['spent_at'] ?? '');

    $file = ledgerApiMultipartFile() ?? ledgerApiBase64File($payload);
    if (!$file) ledgerApiRespond(400, 400, '没有收到消费照片');
    $hasBytes = isset($file['bytes']) && is_string($file['bytes']);
    if (!$hasBytes && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new \RuntimeException('消费照片上传失败');
    }
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > LEDGER_API_MAX_IMAGE_BYTES) {
        ledgerApiRespond(413, 413, '照片不能超过 12MB');
    }
    $mime = $hasBytes
        ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($file['bytes'])
        : (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic', 'image/heif'], true)) {
        ledgerApiRespond(400, 400, '请选择支持的图片文件');
    }

    $upload = \Widget\Upload::uploadHandle($file);
    if (!is_array($upload)) throw new \RuntimeException('消费照片保存失败');
    $receiptUrl = \Widget\Upload::attachmentHandle(new \Typecho\Config($upload));
    if ($receiptUrl === '') throw new \RuntimeException('消费照片地址生成失败');

    $now = \Typecho\Date::time();
    $transaction = $db->selectDb(\Typecho\Db::WRITE);
    if ($transaction instanceof \PDO) $transaction->beginTransaction();

    $attachmentCid = $db->query($db->insert('table.contents')->rows([
        'title' => htmlspecialchars((string) $upload['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'slug' => 'ledger-receipt-' . bin2hex(random_bytes(6)),
        'created' => $now, 'modified' => $now,
        'text' => json_encode($upload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'order' => 0, 'authorId' => (int) $page['authorId'], 'template' => null,
        'type' => 'attachment', 'status' => 'publish', 'password' => null,
        'commentsNum' => 0, 'allowComment' => 0, 'allowPing' => 0, 'allowFeed' => 0,
        'parent' => (int) $page['cid'],
    ]));
    $entryId = $db->query($db->insert('table.ledger_entries')->rows([
        'amount' => number_format($amount, 2, '.', ''),
        'category' => $category,
        'spent_at' => $spentAt,
        'note' => $note,
        'receipt_url' => $receiptUrl,
        'attachment_cid' => (int) $attachmentCid,
    ]));

    if ($transaction instanceof \PDO && $transaction->inTransaction()) $transaction->commit();
    $saved = true;
    $entryPeriodMonth = xiaoguLedgerPeriodMonthForDate(new \DateTimeImmutable($spentAt));
    ledgerApiRespond(200, 0, '记账成功', [
        'id' => (int) $entryId,
        'amount' => number_format($amount, 2, '.', ''),
        'category' => $category,
        'spent_at' => $spentAt,
        'ledger_url' => $pageUrl . (strpos($pageUrl, '?') === false ? '?' : '&') . 'month=' . $entryPeriodMonth,
    ]);
} catch (\RuntimeException $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) $transaction->rollBack();
    if (!$saved) ledgerApiDeleteUpload($upload);
    error_log('[ledger-api] ' . $error->getMessage());
    ledgerApiRespond(400, 400, $error->getMessage());
} catch (\Throwable $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) $transaction->rollBack();
    if (!$saved) ledgerApiDeleteUpload($upload);
    error_log('[ledger-api] ' . $error->getMessage());
    ledgerApiRespond(500, 500, '服务器处理账单失败');
}
