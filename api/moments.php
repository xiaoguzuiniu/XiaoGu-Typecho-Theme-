<?php

declare(strict_types=1);

function momentsApiRespond(int $status, int $code, string $message, ?array $data = null): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $response = ['code' => $code, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function momentsApiAuthorizationHeader(): string
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

function momentsApiRequireToken(): void
{
    $configuredToken = trim((string) getenv('MOMENTS_API_TOKEN'));
    if ($configuredToken === '') {
        momentsApiRespond(503, 503, 'Moment API token is not configured');
    }

    $authorization = momentsApiAuthorizationHeader();
    $prefix = 'Bearer ';
    if (strncasecmp($authorization, $prefix, strlen($prefix)) !== 0
        || !hash_equals($configuredToken, trim(substr($authorization, strlen($prefix))))) {
        header('WWW-Authenticate: Bearer');
        momentsApiRespond(401, 401, 'Unauthorized');
    }
}

function momentsApiFiles(): array
{
    if (!isset($_FILES['images'])) {
        return [];
    }

    $input = $_FILES['images'];
    if (!is_array($input) || !isset($input['name'])) {
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

function momentsApiContentHtml(string $content, array $imageUrls): string
{
    $paragraphs = preg_split('/\R{2,}/u', trim($content)) ?: [];
    $html = [];
    foreach ($paragraphs as $paragraph) {
        $escaped = htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($escaped !== '') {
            $html[] = '<p>' . nl2br($escaped, false) . '</p>';
        }
    }

    if ($imageUrls) {
        $images = [];
        foreach ($imageUrls as $url) {
            $images[] = '<img src="'
                . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" alt="" loading="lazy">';
        }
        $html[] = '<p>' . implode('', $images) . '</p>';
    }

    return implode("\n", $html);
}

function momentsApiDeleteUploads(array $uploads): void
{
    foreach ($uploads as $upload) {
        try {
            \Widget\Upload::deleteHandle([
                'attachment' => new \Typecho\Config($upload),
            ]);
        } catch (\Throwable $error) {
            error_log('[moments-api] upload cleanup failed: ' . $error->getMessage());
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    momentsApiRespond(405, 405, 'Method Not Allowed');
}

momentsApiRequireToken();

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = [];
if (strpos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
        momentsApiRespond(400, 400, 'Invalid JSON');
    }
} else {
    $payload = $_POST;
}

$content = isset($payload['content']) && is_string($payload['content'])
    ? trim($payload['content'])
    : '';
if ($content === '') {
    momentsApiRespond(400, 400, 'Missing parameter: content');
}
if (strlen($content) > 100000) {
    momentsApiRespond(400, 400, 'Content is too long');
}

$files = momentsApiFiles();
if (count($files) > 9) {
    momentsApiRespond(400, 400, 'A maximum of 9 images is allowed');
}

$uploads = [];
$imageUrls = [];
$postSaved = false;
$transaction = null;

try {
    require_once dirname(__DIR__) . '/config.inc.php';
    \Widget\Init::alloc();

    foreach ($files as $index => $file) {
        $number = $index + 1;
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Image ' . $number . ' upload failed');
        }
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 12 * 1024 * 1024) {
            throw new \RuntimeException('Image ' . $number . ' exceeds the 12 MB limit');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
            'image/heic', 'image/heif'
        ], true)) {
            throw new \RuntimeException('Invalid image type: ' . $number);
        }

        $upload = \Widget\Upload::uploadHandle($file);
        if (!is_array($upload)) {
            throw new \RuntimeException('Image ' . $number . ' could not be stored');
        }
        $uploads[] = $upload;
        $imageUrls[] = \Widget\Upload::attachmentHandle(new \Typecho\Config($upload));
    }

    $db = \Typecho\Db::get();
    $options = \Widget\Options::alloc();
    $author = $db->fetchRow(
        $db->select('uid')->from('table.users')
            ->where('`group` = ?', 'administrator')
            ->order('uid', \Typecho\Db::SORT_ASC)
            ->limit(1)
    );
    if (!$author) {
        throw new \RuntimeException('No administrator account is available');
    }

    $now = \Typecho\Date::time();
    $slug = 'moment-' . date('Ymd-His', $now) . '-' . bin2hex(random_bytes(3));
    $transaction = $db->selectDb(\Typecho\Db::WRITE);
    if ($transaction instanceof \PDO) {
        $transaction->beginTransaction();
    }

    $cid = (int) $db->query(
        $db->insert('table.contents')->rows([
            'title' => null,
            'slug' => $slug,
            'created' => $now,
            'modified' => $now,
            'text' => momentsApiContentHtml($content, $imageUrls),
            'order' => 0,
            'authorId' => (int) $author['uid'],
            'template' => null,
            'type' => 'post',
            'status' => 'publish',
            'password' => null,
            'commentsNum' => 0,
            'allowComment' => 1,
            'allowPing' => 0,
            'allowFeed' => 1,
            'parent' => 0,
        ])
    );
    if ($cid <= 0) {
        throw new \RuntimeException('Post could not be created');
    }

    $db->query($db->insert('table.fields')->rows([
        'cid' => $cid,
        'name' => 'displayMode',
        'type' => 'str',
        'str_value' => 'moment',
        'int_value' => 0,
        'float_value' => 0,
    ]));

    foreach ($uploads as $upload) {
        $db->query($db->insert('table.contents')->rows([
            'title' => htmlspecialchars((string) $upload['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'slug' => 'attachment-' . bin2hex(random_bytes(6)),
            'created' => $now,
            'modified' => $now,
            'text' => json_encode($upload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'order' => 0,
            'authorId' => (int) $author['uid'],
            'template' => null,
            'type' => 'attachment',
            'status' => 'publish',
            'password' => null,
            'commentsNum' => 0,
            'allowComment' => 0,
            'allowPing' => 0,
            'allowFeed' => 1,
            'parent' => $cid,
        ]));
    }

    if ($transaction instanceof \PDO && $transaction->inTransaction()) {
        $transaction->commit();
    }
    $postSaved = true;

    $postPath = \Typecho\Router::url('post', ['cid' => $cid, 'slug' => $slug]);
    $url = \Typecho\Common::url($postPath, (string) $options->siteUrl);
    momentsApiRespond(200, 0, 'success', [
        'cid' => $cid,
        'url' => $url,
        'images' => count($imageUrls),
    ]);
} catch (\RuntimeException $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) {
        $transaction->rollBack();
    }
    if (!$postSaved && $uploads) {
        momentsApiDeleteUploads($uploads);
    }
    error_log('[moments-api] ' . $error->getMessage());
    momentsApiRespond(400, 400, $error->getMessage());
} catch (\Throwable $error) {
    if ($transaction instanceof \PDO && $transaction->inTransaction()) {
        $transaction->rollBack();
    }
    if (!$postSaved && $uploads) {
        momentsApiDeleteUploads($uploads);
    }
    error_log('[moments-api] ' . $error->getMessage());
    momentsApiRespond(500, 500, 'Internal Server Error');
}
