<?php

declare(strict_types=1);

const GALLERY_API_MAX_IMAGES = 20;
const GALLERY_API_MAX_IMAGE_BYTES = 12 * 1024 * 1024;
const GALLERY_API_MAX_ENCODED_BYTES = 320 * 1024 * 1024;

function galleryApiRespond(int $status, int $code, string $message, ?array $data = null): void
{
    http_response_code($status);
    if (class_exists(\Typecho\Response::class, false)) {
        \Typecho\Response::getInstance()->setStatus($status);
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $response = ['code' => $code, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function galleryApiAuthorizationHeader(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                return trim((string) $value);
            }
        }
    }
    return '';
}

function galleryApiThemeSettings(\Typecho\Db $db): array
{
    $activeTheme = $db->fetchRow(
        $db->select('value')->from('table.options')
            ->where('name = ? AND user = ?', 'theme', 0)
            ->limit(1)
    );
    $theme = trim((string) ($activeTheme['value'] ?? ''));
    if ($theme === '') {
        throw new \RuntimeException('Active theme is not configured');
    }
    $row = $db->fetchRow(
        $db->select('value')->from('table.options')
            ->where('name = ? AND user = ?', 'theme:' . $theme, 0)
            ->limit(1)
    );
    $settings = json_decode((string) ($row['value'] ?? ''), true);
    return is_array($settings) ? $settings : [];
}

function galleryApiAlbums(array $settings): array
{
    $albums = [];
    foreach (preg_split('/\R/u', trim((string) ($settings['galleryAlbums'] ?? ''))) ?: [] as $line) {
        $name = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $line)), 0, 60);
        if ($name !== '' && !in_array($name, $albums, true)) {
            $albums[] = $name;
        }
    }
    return $albums ?: ['生活片刻'];
}

function galleryApiRequireToken(array $settings): void
{
    $configuredToken = trim((string) ($settings['galleryApiToken'] ?? ''));
    if (strlen($configuredToken) < 24) {
        galleryApiRespond(503, 503, '相册快捷指令密钥尚未配置');
    }
    $authorization = galleryApiAuthorizationHeader();
    $prefix = 'Bearer ';
    if (strncasecmp($authorization, $prefix, strlen($prefix)) !== 0
        || !hash_equals($configuredToken, trim(substr($authorization, strlen($prefix))))) {
        header('WWW-Authenticate: Bearer');
        galleryApiRespond(401, 401, '密钥不正确');
    }
}

function galleryApiFiles(): array
{
    if (!isset($_FILES['images']) || !is_array($_FILES['images'])) {
        return [];
    }
    $input = $_FILES['images'];
    if (!isset($input['name'])) {
        return [];
    }
    if (!is_array($input['name'])) {
        return [$input];
    }
    $files = [];
    foreach ($input['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $input['type'][$index] ?? '',
            'tmp_name' => $input['tmp_name'][$index] ?? '',
            'error' => $input['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $input['size'][$index] ?? 0,
        ];
    }
    return $files;
}

function galleryApiBase64Images(array $payload): array
{
    if (!isset($payload['images_base64'])) {
        return [];
    }
    if (!is_string($payload['images_base64'])) {
        galleryApiRespond(400, 400, 'images_base64 参数格式错误');
    }
    $value = trim($payload['images_base64']);
    if ($value === '') {
        return [];
    }
    if (strlen($value) > GALLERY_API_MAX_ENCODED_BYTES) {
        galleryApiRespond(413, 413, '图片总大小超过限制');
    }
    $encodedImages = explode('|XIAOGU_IMAGE|', $value);
    if (count($encodedImages) > GALLERY_API_MAX_IMAGES) {
        galleryApiRespond(400, 400, '一次最多上传 ' . GALLERY_API_MAX_IMAGES . ' 张照片');
    }

    $images = [];
    foreach ($encodedImages as $index => $encodedImage) {
        $encodedImage = preg_replace('/\s+/u', '', trim((string) $encodedImage));
        $bytes = is_string($encodedImage) ? base64_decode($encodedImage, true) : false;
        if ($bytes === false || $bytes === '') {
            galleryApiRespond(400, 400, '第 ' . ($index + 1) . ' 张照片编码无效');
        }
        if (strlen($bytes) > GALLERY_API_MAX_IMAGE_BYTES) {
            galleryApiRespond(413, 413, '第 ' . ($index + 1) . ' 张照片超过 12MB');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $extension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ][$mime] ?? null;
        if ($extension === null) {
            galleryApiRespond(400, 400, '第 ' . ($index + 1) . ' 个文件不是支持的图片');
        }
        $images[] = [
            'name' => 'gallery-' . date('Ymd-His') . '-' . ($index + 1) . '.' . $extension,
            'bytes' => $bytes,
            'size' => strlen($bytes),
            'type' => $mime,
        ];
    }
    return $images;
}

function galleryApiDeleteUploads(array $uploads): void
{
    foreach ($uploads as $upload) {
        try {
            \Widget\Upload::deleteHandle(['attachment' => new \Typecho\Config($upload)]);
        } catch (\Throwable $error) {
            error_log('[gallery-api] upload cleanup failed: ' . $error->getMessage());
        }
    }
}

function galleryApiMarkdownAlt(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $value);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    galleryApiRespond(405, 405, 'Method Not Allowed');
}

$uploads = [];
$saved = false;
$transaction = null;

try {
    require_once dirname(__DIR__) . '/config.inc.php';
    \Widget\Init::alloc();

    $db = \Typecho\Db::get();
    $settings = galleryApiThemeSettings($db);
    galleryApiRequireToken($settings);
    $albums = galleryApiAlbums($settings);
    $options = \Widget\Options::alloc();
    $page = $db->fetchRow(
        $db->select('cid', 'slug', 'text', 'authorId')->from('table.contents')
            ->where('slug = ? AND type = ? AND status = ?', 'gallery', 'page', 'publish')
            ->limit(1)
    );
    if (!$page) {
        throw new \RuntimeException('找不到已发布的 gallery 相册页面');
    }
    $galleryPath = \Typecho\Router::url('page', [
        'cid' => (int) $page['cid'],
        'slug' => (string) $page['slug'],
    ]);
    $galleryUrl = \Typecho\Common::url($galleryPath, (string) $options->siteUrl);

    if ($method === 'GET') {
        galleryApiRespond(200, 0, 'success', [
            'albums' => $albums,
            'max_images' => GALLERY_API_MAX_IMAGES,
            'gallery_url' => $galleryUrl,
        ]);
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'application/json') !== false) {
        $rawBody = file_get_contents('php://input');
        $payload = json_decode($rawBody === false ? '' : $rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            galleryApiRespond(400, 400, '请求 JSON 格式错误');
        }
    } else {
        $payload = $_POST;
    }

    $album = isset($payload['album']) && is_string($payload['album'])
        ? trim(preg_replace('/\s+/u', ' ', $payload['album']))
        : '';
    if ($album === '' || !in_array($album, $albums, true)) {
        galleryApiRespond(400, 400, '请选择后台已配置的相册分类');
    }

    $files = array_merge(galleryApiFiles(), galleryApiBase64Images($payload));
    if (!$files) {
        galleryApiRespond(400, 400, '没有收到照片');
    }
    if (count($files) > GALLERY_API_MAX_IMAGES) {
        galleryApiRespond(400, 400, '一次最多上传 ' . GALLERY_API_MAX_IMAGES . ' 张照片');
    }

    $imageUrls = [];
    foreach ($files as $index => $file) {
        $number = $index + 1;
        $hasBytes = isset($file['bytes']) && is_string($file['bytes']);
        if (!$hasBytes && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('第 ' . $number . ' 张照片上传失败');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > GALLERY_API_MAX_IMAGE_BYTES) {
            throw new \RuntimeException('第 ' . $number . ' 张照片超过 12MB');
        }
        $mime = $hasBytes
            ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($file['bytes'])
            : (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
            'image/heic', 'image/heif'
        ], true)) {
            throw new \RuntimeException('第 ' . $number . ' 个文件不是支持的图片');
        }
        $upload = \Widget\Upload::uploadHandle($file);
        if (!is_array($upload)) {
            throw new \RuntimeException('第 ' . $number . ' 张照片保存失败');
        }
        $uploads[] = $upload;
        $imageUrls[] = \Widget\Upload::attachmentHandle(new \Typecho\Config($upload));
    }

    $now = \Typecho\Date::time();
    $transaction = $db->selectDb(\Typecho\Db::WRITE);
    if ($transaction instanceof \PDO) {
        $transaction->beginTransaction();
    }

    foreach ($uploads as $upload) {
        $db->query($db->insert('table.contents')->rows([
            'title' => htmlspecialchars((string) $upload['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'slug' => 'attachment-' . bin2hex(random_bytes(6)),
            'created' => $now,
            'modified' => $now,
            'text' => json_encode($upload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'order' => 0,
            'authorId' => (int) $page['authorId'],
            'template' => null,
            'type' => 'attachment',
            'status' => 'publish',
            'password' => null,
            'commentsNum' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 1,
            'parent' => (int) $page['cid'],
        ]));
    }

    $imageLines = [];
    foreach ($uploads as $index => $upload) {
        $imageLines[] = '![' . galleryApiMarkdownAlt((string) $upload['name']) . '](' . $imageUrls[$index] . ')';
    }
    $prefix = trim((string) $page['text']) === '' ? '<!--markdown-->' : '';
    $block = $prefix . "\n\n## " . $album . "\n\n" . implode("\n\n", $imageLines);
    $adapter = $db->getAdapter();
    $tablePrefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $tablePrefix)) {
        throw new \RuntimeException('Invalid database prefix');
    }
    $blockSql = $adapter->quoteValue($block);
    $contentsTable = '`' . $tablePrefix . 'contents`';
    $db->query("UPDATE {$contentsTable} SET `text` = CONCAT(RTRIM(`text`), {$blockSql}),
        `modified` = {$now} WHERE `cid` = " . (int) $page['cid'], \Typecho\Db::WRITE, '');

    if ($transaction instanceof \PDO && $transaction->inTransaction()) {
        $transaction->commit();
    }
    $saved = true;

    galleryApiRespond(200, 0, '上传成功', [
        'album' => $album,
        'images' => count($imageUrls),
        'gallery_url' => $galleryUrl,
    ]);
} catch (\RuntimeException $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) {
        $transaction->rollBack();
    }
    if (!$saved && $uploads) {
        galleryApiDeleteUploads($uploads);
    }
    error_log('[gallery-api] ' . $error->getMessage());
    galleryApiRespond(400, 400, $error->getMessage());
} catch (\Throwable $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) {
        $transaction->rollBack();
    }
    if (!$saved && $uploads) {
        galleryApiDeleteUploads($uploads);
    }
    error_log('[gallery-api] ' . $error->getMessage());
    galleryApiRespond(500, 500, '服务器处理照片失败');
}
