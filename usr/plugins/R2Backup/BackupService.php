<?php

namespace TypechoPlugin\R2Backup;

use RuntimeException;
use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class BackupService
{
    private const RETENTION_DAYS = 14;
    private const STATUS_OPTION = 'r2_backup_status';

    public static function run(string $source = 'manual'): array
    {
        self::useSiteTimezone();
        $lockPath = sys_get_temp_dir() . '/xiaogu-r2-backup.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('已有备份任务正在运行');
        }

        $temporaryFiles = [];
        $startedAt = time();

        try {
            $config = self::config();
            $client = new R2Client(
                $config['accountId'],
                $config['bucket'],
                $config['accessKey'],
                $config['secretKey']
            );
            $snapshot = date('Y-m-d/His');
            $snapshotPrefix = $config['prefix'] . '/snapshots/' . $snapshot;
            $manifest = [
                'version' => 1,
                'site' => (string) Options::alloc()->siteUrl,
                'createdAt' => date(DATE_ATOM),
                'source' => $source,
                'retentionDays' => self::RETENTION_DAYS,
                'files' => [],
                'qiniu' => ['enabled' => $config['syncQiniu'], 'uploaded' => 0, 'skipped' => 0],
            ];

            $databasePath = self::temporaryPath('xiaogu-db-', '.sql.gz');
            $temporaryFiles[] = $databasePath;
            $tableCount = self::dumpDatabase($databasePath);
            $databaseKey = $snapshotPrefix . '/database/typecho.sql.gz';
            $client->putFile($databaseKey, $databasePath, 'application/gzip');
            $manifest['database'] = [
                'key' => $databaseKey,
                'tables' => $tableCount,
                'size' => filesize($databasePath),
                'sha256' => hash_file('sha256', $databasePath),
            ];

            foreach (self::importantFiles() as $relative => $path) {
                if (!is_file($path) || !is_readable($path)) {
                    continue;
                }
                $key = $snapshotPrefix . '/files/' . $relative;
                $client->putFile($key, $path, self::contentType($path));
                $manifest['files'][] = [
                    'key' => $key,
                    'size' => filesize($path),
                    'sha256' => hash_file('sha256', $path),
                ];
            }

            $uploadsRoot = __TYPECHO_ROOT_DIR__ . '/usr/uploads';
            if (is_dir($uploadsRoot)) {
                foreach (self::recursiveFiles($uploadsRoot) as $path) {
                    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($uploadsRoot))), '/');
                    $key = $snapshotPrefix . '/files/usr/uploads/' . $relative;
                    $client->putFile($key, $path, self::contentType($path));
                    $manifest['files'][] = [
                        'key' => $key,
                        'size' => filesize($path),
                        'sha256' => hash_file('sha256', $path),
                    ];
                }
            }

            if ($config['syncQiniu']) {
                $manifest['qiniu'] = self::syncQiniuBucket($client, $config['prefix'], $temporaryFiles);
            }

            $manifestPath = self::temporaryPath('xiaogu-manifest-', '.json');
            $temporaryFiles[] = $manifestPath;
            $manifest['durationSeconds'] = time() - $startedAt;
            $encodedManifest = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if (!is_string($encodedManifest) || file_put_contents($manifestPath, $encodedManifest) === false) {
                throw new RuntimeException('无法生成备份清单');
            }
            $client->putFile($snapshotPrefix . '/manifest.json', $manifestPath, 'application/json');

            $deleted = self::removeExpiredSnapshots($client, $config['prefix']);
            $result = [
                'success' => true,
                'snapshot' => $snapshot,
                'databaseTables' => $tableCount,
                'files' => count($manifest['files']),
                'qiniuUploaded' => $manifest['qiniu']['uploaded'],
                'qiniuSkipped' => $manifest['qiniu']['skipped'],
                'expiredObjectsDeleted' => $deleted,
                'durationSeconds' => time() - $startedAt,
                'finishedAt' => date(DATE_ATOM),
            ];
            self::saveStatus($result);
            return $result;
        } catch (\Throwable $exception) {
            self::saveStatus([
                'success' => false,
                'message' => $exception->getMessage(),
                'finishedAt' => date(DATE_ATOM),
            ]);
            throw $exception;
        } finally {
            foreach ($temporaryFiles as $path) {
                if (is_string($path) && strpos($path, sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xiaogu-') === 0 && is_file($path)) {
                    @unlink($path);
                }
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function status(): array
    {
        $db = Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', self::STATUS_OPTION)->limit(1));
        if (!$row) {
            return [];
        }
        $status = json_decode((string) $row['value'], true);
        return is_array($status) ? $status : [];
    }

    private static function config(): array
    {
        $options = Options::alloc()->plugin('R2Backup');
        $config = [
            'accountId' => trim((string) $options->accountId),
            'bucket' => trim((string) $options->bucket),
            'accessKey' => trim((string) $options->accessKey),
            'secretKey' => trim((string) $options->secretKey),
            'prefix' => trim((string) $options->prefix, '/ '),
            'syncQiniu' => (string) $options->syncQiniu === '1',
        ];
        if ($config['prefix'] === '') {
            $config['prefix'] = 'xiaogu-backup';
        }
        foreach (['accountId', 'bucket', 'accessKey', 'secretKey'] as $name) {
            if ($config[$name] === '') {
                throw new RuntimeException('请先填写完整的 Cloudflare R2 配置');
            }
        }
        return $config;
    }

    private static function useSiteTimezone(): void
    {
        $db = Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')
                ->where('name = ? AND user = ?', 'timezone', 0)
                ->limit(1)
        );
        $offset = max(-43200, min(50400, (int) ($row['value'] ?? 0)));
        $timezone = timezone_name_from_abbr('', $offset, 0);
        if (is_string($timezone) && $timezone !== '') {
            date_default_timezone_set($timezone);
        }
    }

    private static function dumpDatabase(string $path): int
    {
        $db = Db::get();
        $prefix = $db->getPrefix();
        $handle = gzopen($path, 'wb9');
        if ($handle === false) {
            throw new RuntimeException('无法创建数据库备份文件');
        }

        $tableCount = 0;
        try {
            gzwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = $db->fetchAll('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
            foreach ($tables as $tableRow) {
                $table = (string) reset($tableRow);
                if ($table === '' || strpos($table, $prefix) !== 0 || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    continue;
                }
                $tableCount++;
                $createRow = $db->fetchRow('SHOW CREATE TABLE `' . $table . '`');
                $createSql = $createRow ? (string) array_values($createRow)[1] : '';
                if ($createSql === '') {
                    throw new RuntimeException('无法读取数据表结构：' . $table);
                }
                gzwrite($handle, 'DROP TABLE IF EXISTS `' . $table . "`;\n" . $createSql . ";\n\n");

                $offset = 0;
                do {
                    $rows = $db->fetchAll('SELECT * FROM `' . $table . '` LIMIT ' . $offset . ', 500');
                    foreach ($rows as $row) {
                        $columns = array_map(static fn($column) => '`' . str_replace('`', '``', (string) $column) . '`', array_keys($row));
                        $values = array_map([self::class, 'sqlValue'], array_values($row));
                        gzwrite($handle, 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
                    }
                    $offset += count($rows);
                } while (count($rows) === 500);
                gzwrite($handle, "\n");
            }
            gzwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($handle);
        }

        if ($tableCount === 0 || !is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('数据库备份为空');
        }
        return $tableCount;
    }

    private static function sqlValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        $value = str_replace(
            ["\\", "\0", "\n", "\r", "'", '"', "\x1a"],
            ["\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"],
            (string) $value
        );
        return "'" . $value . "'";
    }

    private static function importantFiles(): array
    {
        return [
            'config.inc.php' => __TYPECHO_ROOT_DIR__ . '/config.inc.php',
            '.env' => __TYPECHO_ROOT_DIR__ . '/.env',
            'docker-compose.yml' => __TYPECHO_ROOT_DIR__ . '/docker-compose.yml',
        ];
    }

    private static function recursiveFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile() && $item->isReadable()) {
                $files[] = $item->getPathname();
            }
        }
        return $files;
    }

    private static function syncQiniuBucket(R2Client $client, string $prefix, array &$temporaryFiles): array
    {
        $qiniu = Options::alloc()->plugin('QiniuStorage');
        $bucket = trim((string) $qiniu->bucket);
        $accessKey = trim((string) $qiniu->accessKey);
        $secretKey = trim((string) $qiniu->secretKey);
        $domain = rtrim(trim((string) $qiniu->domain), '/');
        if ($bucket === '' || $accessKey === '' || $secretKey === '' || $domain === '') {
            throw new RuntimeException('已开启七牛同步，但七牛云插件配置不完整');
        }
        if (!preg_match('#^https?://#i', $domain)) {
            $domain = (preg_match('#(?:^|\.)clouddn\.com(?:/|$)#i', $domain) ? 'http://' : 'https://') . $domain;
        }

        $uploaded = 0;
        $skipped = 0;
        $objects = 0;

        foreach (self::listQiniuObjects($bucket, $accessKey, $secretKey) as $item) {
            $objectKey = (string) ($item['key'] ?? '');
            if ($objectKey === '') {
                continue;
            }
            $objects++;
            $size = max(0, (int) ($item['fsize'] ?? 0));
            $hash = (string) ($item['hash'] ?? '');
            $putTime = (string) ($item['putTime'] ?? '');
            $r2Key = $prefix . '/qiniu/' . ltrim($objectKey, '/');
            $existing = $client->head($r2Key);
            if (
                $existing !== null
                && (string) ($existing['x-amz-meta-source-size'] ?? '') === (string) $size
                && (string) ($existing['x-amz-meta-source-hash'] ?? '') === $hash
            ) {
                $skipped++;
                continue;
            }

            $path = self::temporaryPath('xiaogu-qiniu-', '.bin');
            $temporaryFiles[] = $path;
            self::download($domain . '/' . implode('/', array_map('rawurlencode', explode('/', $objectKey))), $path);
            $client->putFile($r2Key, $path, (string) ($item['mimeType'] ?? 'application/octet-stream'), [
                'source-size' => (string) $size,
                'source-hash' => $hash,
                'source-put-time' => $putTime,
            ]);
            $uploaded++;
            @unlink($path);
        }

        return ['enabled' => true, 'objects' => $objects, 'uploaded' => $uploaded, 'skipped' => $skipped];
    }

    private static function listQiniuObjects(string $bucket, string $accessKey, string $secretKey): \Generator
    {
        $marker = '';
        do {
            $query = ['bucket' => $bucket, 'limit' => '1000'];
            if ($marker !== '') {
                $query['marker'] = $marker;
            }
            $url = 'https://rsf.qiniuapi.com/list?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $date = gmdate('Ymd\THis\Z');
            $parts = parse_url($url);
            $path = (string) ($parts['path'] ?? '/');
            if (!empty($parts['query'])) {
                $path .= '?' . $parts['query'];
            }
            $contentType = 'application/x-www-form-urlencoded';
            $signingData = 'GET ' . $path
                . "\nHost: rsf.qiniuapi.com"
                . "\nContent-Type: " . $contentType
                . "\nX-Qiniu-Date: " . $date
                . "\n\n";
            $signature = self::urlSafeBase64(hash_hmac('sha1', $signingData, $secretKey, true));

            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Qiniu ' . $accessKey . ':' . $signature,
                    'Content-Type: ' . $contentType,
                    'X-Qiniu-Date: ' . $date,
                ],
                CURLOPT_USERAGENT => 'Typecho-R2Backup/1.0',
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($body === false || $status !== 200) {
                $detail = $error !== '' ? $error : self::qiniuError((string) $body, $status);
                throw new RuntimeException('列出七牛云空间对象失败：' . $detail);
            }

            $response = json_decode((string) $body, true);
            if (!is_array($response)) {
                throw new RuntimeException('无法解析七牛云空间对象列表');
            }
            foreach (($response['items'] ?? []) as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }
            $marker = (string) ($response['marker'] ?? '');
        } while ($marker !== '');
    }

    private static function urlSafeBase64(string $value): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($value));
    }

    private static function qiniuError(string $body, int $status): string
    {
        $response = json_decode($body, true);
        if (is_array($response) && !empty($response['error'])) {
            return 'HTTP ' . $status . ' ' . (string) $response['error'];
        }
        return 'HTTP ' . $status;
    }

    private static function download(string $url, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('无法创建七牛附件临时文件');
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_USERAGENT => 'Typecho-R2Backup/1.0',
        ]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if ($ok === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('下载七牛附件失败：' . ($error !== '' ? $error : 'HTTP ' . $status));
        }
    }

    private static function removeExpiredSnapshots(R2Client $client, string $prefix): int
    {
        $cutoff = time() - self::RETENTION_DAYS * 86400;
        $deleted = 0;
        foreach ($client->list($prefix . '/snapshots/') as $object) {
            $modified = strtotime((string) $object['lastModified']);
            if ($modified !== false && $modified < $cutoff) {
                $client->delete((string) $object['key']);
                $deleted++;
            }
        }
        return $deleted;
    }

    private static function temporaryPath(string $prefix, string $suffix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new RuntimeException('无法创建备份临时文件');
        }
        $path = $base . $suffix;
        if (!rename($base, $path)) {
            @unlink($base);
            throw new RuntimeException('无法初始化备份临时文件');
        }
        return $path;
    }

    private static function contentType(string $path): string
    {
        $mime = function_exists('mime_content_type') ? mime_content_type($path) : false;
        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    private static function saveStatus(array $status): void
    {
        $db = Db::get();
        $value = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', self::STATUS_OPTION)->limit(1));
        if ($exists) {
            $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ?', self::STATUS_OPTION));
        } else {
            $db->query($db->insert('table.options')->rows(['name' => self::STATUS_OPTION, 'user' => 0, 'value' => $value]));
        }
    }
}
