<?php

declare(strict_types=1);

function musicRespond(int $status, string $message, ?array $data = null): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = ['code' => $status < 400 ? 0 : $status, 'message' => $message];
    if ($data !== null) $body['data'] = $data;
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function musicSettings(\Typecho\Db $db): array
{
    $theme = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme', 0)->limit(1));
    $name = trim((string) ($theme['value'] ?? ''));
    if ($name === '') throw new \RuntimeException('Theme unavailable');
    $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', 'theme:' . $name, 0)->limit(1));
    $settings = json_decode((string) ($row['value'] ?? ''), true);
    return is_array($settings) ? $settings : [];
}

function musicRequireWriteAccess(array $settings): void
{
    $token = trim((string) ($settings['musicApiToken'] ?? ''));
    if (strlen($token) < 24) musicRespond(503, '音乐接口密钥尚未配置');
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $key => $value) {
            if (strcasecmp((string) $key, 'Authorization') === 0) $header = (string) $value;
        }
    }
    if (strncasecmp($header, 'Bearer ', 7) !== 0 || !hash_equals($token, trim(substr($header, 7)))) {
        header('WWW-Authenticate: Bearer');
        musicRespond(401, '音乐接口密钥不正确');
    }
}

function musicText($value, int $limit, string $label): string
{
    if (!is_string($value)) musicRespond(400, $label . '格式错误');
    $value = trim($value);
    if ($value === '' || mb_strlen($value, 'UTF-8') > $limit) musicRespond(400, $label . '不能为空或过长');
    return $value;
}

function musicUpload(): array
{
    $file = $_FILES['audio'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) musicRespond(400, '请上传已获授权的 audio 音频文件');
    $size = (int) ($file['size'] ?? 0);
    $path = (string) ($file['tmp_name'] ?? '');
    if ($size < 1 || $size > 50 * 1024 * 1024 || !is_uploaded_file($path)) musicRespond(413, '音频须为 50 MB 以内的文件');
    $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    $extensions = ['audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/flac' => 'flac', 'audio/x-flac' => 'flac', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav'];
    $extension = $extensions[$mime] ?? null;
    if ($extension === null) musicRespond(400, '仅支持 MP3、M4A、AAC、FLAC、OGG、WAV 音频');
    $file['name'] = 'music-' . bin2hex(random_bytes(8)) . '.' . $extension;
    // 音频只能存到已启用的七牛插件，防止误存于服务器的 usr/uploads。
    if (!\Typecho\Plugin::exists('QiniuStorage') || !class_exists('TypechoPlugin\\QiniuStorage\\Plugin')) {
        musicRespond(503, '请先启用并配置七牛云存储插件');
    }
    $upload = \TypechoPlugin\QiniuStorage\Plugin::uploadAuthorizedAudio($file);
    if (!is_array($upload) || strpos((string) ($upload['path'] ?? ''), 'qiniu://') !== 0) musicRespond(502, '七牛云上传失败');
    return $upload;
}

$path = rtrim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''), '/');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    require_once dirname(__DIR__) . '/config.inc.php';
    require_once dirname(__DIR__) . '/usr/themes/XiaoGu/music-data.php';
    \Widget\Init::alloc();
    $db = \Typecho\Db::get();
    xiaoguMusicEnsureTable($db);

    if ($path === '/api/music/tracks' && $method === 'GET') {
        $q = isset($_GET['q']) && is_scalar($_GET['q']) ? trim((string) $_GET['q']) : '';
        if (mb_strlen($q, 'UTF-8') > 100) musicRespond(400, '搜索词过长');
        $query = $db->select('id', 'title', 'artist', 'album', 'source', 'source_id')->from('table.music_tracks')->order('id', \Typecho\Db::SORT_DESC)->limit(200);
        if ($q !== '') $query->where('(title LIKE ? OR artist LIKE ? OR album LIKE ?)', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
        $rows = $db->fetchAll($query);
        musicRespond(200, 'success', ['tracks' => array_map('xiaoguMusicPublicTrack', $rows)]);
    }

    if (preg_match('#^/api/music/tracks/([1-9][0-9]*)/audio$#', $path, $match) && $method === 'GET') {
        $row = $db->fetchRow($db->select('attachment')->from('table.music_tracks')->where('id = ?', (int) $match[1])->limit(1));
        if (!$row) musicRespond(404, '歌曲不存在');
        $attachment = json_decode((string) $row['attachment'], true);
        if (!is_array($attachment) || strpos((string) ($attachment['path'] ?? ''), 'qiniu://') !== 0) musicRespond(502, '音频存储信息无效');
        $url = \TypechoPlugin\QiniuStorage\Plugin::attachmentHandle(new \Typecho\Config($attachment));
        if (!is_string($url) || !preg_match('#^https://#i', $url)) musicRespond(502, '音频访问地址不可用');
        header('Cache-Control: public, max-age=300');
        header('Location: ' . $url, true, 302);
        exit;
    }

    if (($path === '/api/music/tracks' || $path === '/api/music/imports') && $method === 'POST') {
        musicRequireWriteAccess(musicSettings($db));
        $isImport = $path === '/api/music/imports';
        $songId = null;
        if ($isImport) {
            $songId = isset($_POST['song_id']) && is_scalar($_POST['song_id']) ? trim((string) $_POST['song_id']) : '';
            if (!preg_match('/^[1-9][0-9]{0,19}$/', $songId)) musicRespond(400, '请提供有效的网易云 song_id');
            $existing = $db->fetchRow($db->select('id')->from('table.music_tracks')->where('source = ? AND source_id = ?', 'netease', $songId)->limit(1));
            if ($existing) musicRespond(409, '这首歌已经导入');
        }
        $title = musicText($_POST['title'] ?? '', 200, '歌曲名');
        $artist = isset($_POST['artist']) && is_string($_POST['artist']) ? mb_substr(trim($_POST['artist']), 0, 200) : '';
        $album = isset($_POST['album']) && is_string($_POST['album']) ? mb_substr(trim($_POST['album']), 0, 200) : '';
        $upload = musicUpload();
        try {
            $id = $db->query($db->insert('table.music_tracks')->rows([
                'title' => $title, 'artist' => $artist, 'album' => $album,
                'source' => $isImport ? 'netease' : 'upload', 'source_id' => $songId,
                'attachment' => json_encode($upload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]));
        } catch (\Throwable $error) {
            \TypechoPlugin\QiniuStorage\Plugin::deleteHandle(['attachment' => new \Typecho\Config($upload)]);
            throw $error;
        }
        musicRespond(201, '已加入歌单', ['track' => xiaoguMusicPublicTrack([
            'id' => (int) $id, 'title' => $title, 'artist' => $artist, 'album' => $album,
            'source' => $isImport ? 'netease' : 'upload', 'source_id' => $songId,
        ])]);
    }

    header('Allow: GET, POST');
    musicRespond(405, '不支持的请求方式或路径');
} catch (\Throwable $error) {
    error_log('[music-api] ' . $error->getMessage());
    musicRespond(500, '音乐接口暂时不可用');
}
