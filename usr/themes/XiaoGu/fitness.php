<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function xiaoguFitnessCompletedDates(): array
{
    $db = \Typecho\Db::get();
    $rows = $db->fetchAll(
        $db->select('name')->from('table.options')
            ->where('name LIKE ? AND user = ? AND value = ?', 'xgfit:%', 0, '1')
    );
    $dates = [];
    foreach ($rows as $row) {
        $date = substr((string) $row['name'], 6);
        if (xiaoguFitnessValidDate($date)) {
            $dates[] = $date;
        }
    }
    return $dates;
}

function xiaoguFitnessValidDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
        return false;
    }
    $parts = array_map('intval', explode('-', $date));
    return checkdate($parts[1], $parts[2], $parts[0]);
}

function xiaoguFitnessHandleStatus(): void
{
    header('Content-Type: application/json; charset=utf-8');
    $send = static function (int $code, string $message, array $extra = []): void {
        \Typecho\Response::getInstance()->setStatus($code);
        http_response_code($code);
        echo json_encode(array_merge(['success' => $code === 200, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    };

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        $send(405, '仅支持 POST 请求');
    }
    if (!\Widget\User::alloc()->pass('administrator', true)) {
        $send(403, '只有站长可以修改健身记录');
    }

    $referer = \Typecho\Request::getInstance()->getReferer();
    $siteHost = parse_url((string) \Widget\Options::alloc()->siteUrl, PHP_URL_HOST);
    $refererHost = parse_url((string) $referer, PHP_URL_HOST);
    if (!$siteHost || !$refererHost || strcasecmp($siteHost, $refererHost) !== 0) {
        $send(403, '请求来源验证失败');
    }
    $submittedToken = isset($_POST['_']) && is_string($_POST['_']) ? $_POST['_'] : '';
    $expectedToken = \Widget\Security::alloc()->getToken($referer);
    if ($submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
        $send(403, '安全验证失败，请刷新页面重试');
    }

    $date = isset($_POST['date']) && is_string($_POST['date']) ? $_POST['date'] : '';
    $completed = isset($_POST['completed']) && is_string($_POST['completed']) ? $_POST['completed'] : '';
    if (!xiaoguFitnessValidDate($date) || !in_array($completed, ['0', '1'], true)) {
        $send(400, '日期或完成状态不正确');
    }

    try {
        $db = \Typecho\Db::get();
        $name = 'xgfit:' . $date;
        $row = $db->fetchRow(
            $db->select('name')->from('table.options')->where('name = ? AND user = ?', $name, 0)->limit(1)
        );
        if ($row) {
            $db->query($db->update('table.options')->rows(['value' => $completed])->where('name = ? AND user = ?', $name, 0));
        } else {
            $db->query($db->insert('table.options')->rows(['name' => $name, 'user' => 0, 'value' => $completed]));
        }
        $send(200, '已保存', ['date' => $date, 'completed' => $completed === '1']);
    } catch (\Throwable $error) {
        error_log('[fitness-status] ' . $error->getMessage());
        $send(500, '保存失败，请稍后重试');
    }
}
