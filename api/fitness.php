<?php

declare(strict_types=1);

const FITNESS_API_MAX_IMAGE_BYTES = 12 * 1024 * 1024;
const FITNESS_API_MAX_ENCODED_BYTES = 18 * 1024 * 1024;

function fitnessApiRespond(int $status, string $message, ?array $data = null): void
{
    http_response_code($status);
    if (class_exists(\Typecho\Response::class, false)) {
        \Typecho\Response::getInstance()->setStatus($status);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = ['success' => $status >= 200 && $status < 300, 'message' => $message];
    if ($data !== null) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fitnessApiAuthorizationHeader(): string
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

function fitnessApiRequireToken(\Typecho\Db $db): void
{
    $themeRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme', 0)->limit(1));
    $theme = trim((string) ($themeRow['value'] ?? ''));
    if ($theme === '') fitnessApiRespond(503, '当前主题尚未配置');
    $settingsRow = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme:' . $theme, 0)->limit(1));
    $settings = json_decode((string) ($settingsRow['value'] ?? ''), true);
    $configured = is_array($settings) ? trim((string) ($settings['fitnessApiToken'] ?? '')) : '';
    if (strlen($configured) < 24) fitnessApiRespond(503, '健身打卡快捷指令密钥尚未配置');
    $header = fitnessApiAuthorizationHeader();
    if (strncasecmp($header, 'Bearer ', 7) !== 0 || !hash_equals($configured, trim(substr($header, 7)))) {
        header('WWW-Authenticate: Bearer');
        fitnessApiRespond(401, '密钥不正确');
    }
}

function fitnessApiPayload(): array
{
    $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($type, 'application/json') === false) return $_POST;
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw === false ? '' : $raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) fitnessApiRespond(400, '请求 JSON 格式错误');
    return $payload;
}

function fitnessApiFile(array $payload): ?array
{
    if (isset($_FILES['image']) && is_array($_FILES['image'])) {
        $file = $_FILES['image'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) fitnessApiRespond(400, '照片上传失败');
        return $file;
    }
    if (!isset($payload['image_base64']) || $payload['image_base64'] === '') return null;
    if (!is_string($payload['image_base64'])) fitnessApiRespond(400, 'image_base64 参数格式错误');
    $encoded = trim($payload['image_base64']);
    if (preg_match('#^data:image/[^;]+;base64,#i', $encoded, $matches)) {
        $encoded = substr($encoded, strlen($matches[0]));
    }
    $encoded = preg_replace('/\s+/u', '', $encoded);
    if (!is_string($encoded) || strlen($encoded) > FITNESS_API_MAX_ENCODED_BYTES) fitnessApiRespond(413, '照片大小超过限制');
    $bytes = base64_decode($encoded, true);
    if ($bytes === false || $bytes === '') fitnessApiRespond(400, '照片编码无效');
    if (strlen($bytes) > FITNESS_API_MAX_IMAGE_BYTES) fitnessApiRespond(413, '照片不能超过 12MB');
    $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $extension = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'image/avif' => 'avif',
        'image/heic' => 'heic', 'image/heif' => 'heif',
    ][$mime] ?? null;
    if ($extension === null) fitnessApiRespond(400, '请选择支持的图片文件');
    return ['name' => 'fitness-' . date('Ymd-His') . '.' . $extension, 'bytes' => $bytes, 'size' => strlen($bytes), 'type' => $mime];
}

function fitnessApiDeleteUpload(?array $upload): void
{
    if (!$upload) return;
    try {
        \Widget\Upload::deleteHandle(['attachment' => new \Typecho\Config($upload)]);
    } catch (\Throwable $error) {
        error_log('[fitness-api] upload cleanup failed: ' . $error->getMessage());
    }
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    fitnessApiRespond(405, 'Method Not Allowed');
}

$upload = null;
$saved = false;
$transaction = null;
try {
    require_once dirname(__DIR__) . '/config.inc.php';
    require_once dirname(__DIR__) . '/usr/themes/XiaoGu/fitness.php';
    \Widget\Init::alloc();
    $db = \Typecho\Db::get();
    xiaoguFitnessEnsureTable($db);

    if ($method === 'GET') {
        $date = isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : '';
        if (!xiaoguFitnessValidDate($date)) fitnessApiRespond(400, '日期格式应为 YYYY-MM-DD');
        $entries = xiaoguFitnessEntriesForDate($db, $date);
        $data = array_map(static function (array $entry): array {
            $imageUrl = (string) $entry['image_url'];
            if ($imageUrl !== '' && function_exists('getXiaoGuQiniuDeliveryUrl')) {
                $imageUrl = getXiaoGuQiniuDeliveryUrl($imageUrl);
            }
            $thumbnailUrl = $imageUrl !== '' && function_exists('getXiaoGuMomentThumbnailUrl')
                ? getXiaoGuMomentThumbnailUrl($imageUrl, 960) : $imageUrl;
            return [
                'id' => (int) $entry['id'],
                'note' => (string) $entry['note'],
                'image_url' => $imageUrl,
                'thumbnail_url' => $thumbnailUrl,
                'created_at' => (string) $entry['created_at'],
            ];
        }, $entries);
        fitnessApiRespond(200, 'success', ['date' => $date, 'entries' => $data]);
    }

    fitnessApiRequireToken($db);
    $payload = fitnessApiPayload();
    $note = isset($payload['note']) && is_string($payload['note']) ? trim($payload['note']) : '';
    if (mb_strlen($note, 'UTF-8') > 2000) fitnessApiRespond(400, '文字不能超过 2000 字');
    $file = fitnessApiFile($payload);
    if ($file === null) fitnessApiRespond(400, '请上传一张健身照片');
    if ((int) ($file['size'] ?? 0) <= 0) fitnessApiRespond(400, '照片内容为空');
    if ((int) $file['size'] > FITNESS_API_MAX_IMAGE_BYTES) fitnessApiRespond(413, '照片不能超过 12MB');
    $mime = isset($file['bytes'])
        ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($file['bytes'])
        : (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic', 'image/heif'], true)) {
        fitnessApiRespond(400, '请选择支持的图片文件');
    }
    $upload = \Widget\Upload::uploadHandle($file);
    if (!is_array($upload)) throw new \RuntimeException('照片保存失败');
    $imageUrl = \Widget\Upload::attachmentHandle(new \Typecho\Config($upload));
    if ($imageUrl === '') throw new \RuntimeException('照片地址生成失败');

    $page = $db->fetchRow(
        $db->select('cid', 'authorId')->from('table.contents')
            ->where('slug = ? AND type = ? AND status = ?', 'fitness', 'page', 'publish')->limit(1)
    );
    if (!$page) throw new \RuntimeException('找不到已发布的健身页面');
    $now = \Typecho\Date::time();
    $date = date('Y-m-d', $now);
    $createdAt = date('Y-m-d H:i:s', $now);
    $transaction = $db->selectDb(\Typecho\Db::WRITE);
    if ($transaction instanceof \PDO) $transaction->beginTransaction();
    $attachmentCid = $db->query($db->insert('table.contents')->rows([
        'title' => htmlspecialchars((string) $upload['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'slug' => 'fitness-photo-' . bin2hex(random_bytes(6)),
        'created' => $now, 'modified' => $now,
        'text' => json_encode($upload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'order' => 0, 'authorId' => (int) $page['authorId'], 'template' => null,
        'type' => 'attachment', 'status' => 'publish', 'password' => null,
        'commentsNum' => 0, 'allowComment' => 0, 'allowPing' => 0, 'allowFeed' => 0,
        'parent' => (int) $page['cid'],
    ]));
    $id = $db->query($db->insert('table.fitness_checkins')->rows([
        'checkin_date' => $date,
        'note' => $note,
        'image_url' => $imageUrl,
        'attachment_cid' => $attachmentCid,
        'created_at' => $createdAt,
    ]));
    if ($transaction instanceof \PDO && $transaction->inTransaction()) $transaction->commit();
    $saved = true;
    fitnessApiRespond(200, '打卡成功，今天已标记完成', ['id' => (int) $id, 'date' => $date]);
} catch (\RuntimeException $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) $transaction->rollBack();
    if (!$saved) fitnessApiDeleteUpload($upload);
    error_log('[fitness-api] ' . $error->getMessage());
    fitnessApiRespond(400, $error->getMessage());
} catch (\Throwable $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) $transaction->rollBack();
    if (!$saved) fitnessApiDeleteUpload($upload);
    error_log('[fitness-api] ' . $error->getMessage());
    fitnessApiRespond(500, '健身打卡处理失败');
}
