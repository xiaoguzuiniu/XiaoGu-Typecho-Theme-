<?php

namespace TypechoPlugin\R2Backup;

use RuntimeException;
use Typecho\Db;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class RestoreService
{
    private const STATUS = 'r2_restore_status';

    public static function snapshots(): array
    {
        [$client, $prefix] = self::client();
        $result = [];
        foreach ($client->list($prefix . '/snapshots/') as $object) {
            $key = (string) $object['key'];
            if (preg_match('~^' . preg_quote($prefix, '~') . '/snapshots/(\d{4}-\d{2}-\d{2}/\d{6})/manifest\.json$~', $key, $match)) {
                $result[] = ['snapshot' => $match[1], 'modified' => $object['lastModified']];
            }
        }
        usort($result, static fn($a, $b) => strcmp($b['snapshot'], $a['snapshot']));
        return $result;
    }

    public static function summary(string $snapshot): array
    {
        self::validateSnapshot($snapshot);
        [$client, $prefix] = self::client();
        $path = tempnam(sys_get_temp_dir(), 'xiaogu-restore-summary-');
        if ($path === false) throw new RuntimeException('无法创建临时清单文件');
        try {
            $client->getFile($prefix . '/snapshots/' . $snapshot . '/manifest.json', $path);
            $manifest = json_decode((string) file_get_contents($path), true);
            if (!is_array($manifest) || ($manifest['version'] ?? null) !== 1 || !is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('快照清单无效');
            }
            $uploadCount = 0;
            foreach ($manifest['files'] as $entry) {
                if (strpos((string) ($entry['key'] ?? ''), $prefix . '/snapshots/' . $snapshot . '/files/usr/uploads/') === 0) $uploadCount++;
            }
            return [
                'createdAt' => (string) ($manifest['createdAt'] ?? ''),
                'site' => (string) ($manifest['site'] ?? ''),
                'tables' => (int) ($manifest['database']['tables'] ?? 0),
                'localUploads' => $uploadCount,
            ];
        } finally {
            @unlink($path);
        }
    }

    public static function status(): array
    {
        $db = Db::get();
        $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', self::STATUS)->limit(1));
        $value = json_decode((string) ($row['value'] ?? ''), true);
        return is_array($value) ? $value : [];
    }

    public static function setStatus(array $status): void
    {
        $db = Db::get();
        $value = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row = $db->fetchRow($db->select('name')->from('table.options')->where('name = ?', self::STATUS)->limit(1));
        if ($row) {
            $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ?', self::STATUS));
        } else {
            $db->query($db->insert('table.options')->rows(['name' => self::STATUS, 'user' => 0, 'value' => $value]));
        }
    }

    public static function run(string $snapshot): array
    {
        self::validateSnapshot($snapshot);
        $work = sys_get_temp_dir() . '/xiaogu-restore-' . bin2hex(random_bytes(8));
        if (!mkdir($work, 0700)) {
            throw new RuntimeException('无法建立恢复临时目录');
        }
        $lock = null;
        $protectionSnapshot = null;
        try {
            [$client, $prefix] = self::client();
            $base = $prefix . '/snapshots/' . $snapshot;
            $manifestFile = $work . '/manifest.json';
            $client->getFile($base . '/manifest.json', $manifestFile);
            $manifest = json_decode((string) file_get_contents($manifestFile), true);
            if (!is_array($manifest) || ($manifest['version'] ?? null) !== 1 || !is_array($manifest['database'] ?? null) || !is_array($manifest['files'] ?? null)) {
                throw new RuntimeException('快照清单无效或版本不支持');
            }
            $database = $manifest['database'];
            if (($database['key'] ?? '') !== $base . '/database/typecho.sql.gz') {
                throw new RuntimeException('数据库备份路径与快照不匹配');
            }
            $dbFile = $work . '/database.sql.gz';
            $client->getFile($database['key'], $dbFile);
            self::verify($dbFile, $database);
            $tableCount = self::readSql($dbFile);
            if ($tableCount !== (int) ($database['tables'] ?? -1)) {
                throw new RuntimeException('数据库表数量与快照清单不符');
            }
            $stagedUploads = [];
            foreach ($manifest['files'] as $index => $entry) {
                $key = (string) ($entry['key'] ?? '');
                $start = $base . '/files/usr/uploads/';
                if (strpos($key, $start) !== 0) {
                    continue; // 不覆盖现有服务器的 config.inc.php、.env 等运行配置。
                }
                $relative = substr($key, strlen($start));
                $segments = explode('/', $relative);
                if ($relative === '' || strpos($relative, "\0") !== false || strpos($relative, '\\') !== false
                    || in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
                    throw new RuntimeException('快照包含不安全的上传文件路径');
                }
                $path = $work . '/upload-' . $index;
                $client->getFile($key, $path);
                self::verify($path, $entry);
                $stagedUploads[$relative] = $path;
            }

            // 先保留当前状态；保护快照失败时绝不触碰现有数据。
            $protection = BackupService::run('pre-restore', true);
            $protectionSnapshot = $protection['snapshot'];
            $lock = fopen(__DIR__ . '/BackupService.php', 'rb');
            if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('备份任务正在运行，请稍后重试恢复');
            }
            self::setStatus(['running' => true, 'stage' => '正在导入数据库和本地上传文件', 'snapshot' => $snapshot, 'startedAt' => time()]);
            $db = Db::get();
            self::readSql($dbFile, static function (string $sql) use ($db): void {
                $db->query($sql, Db::WRITE, '');
            });
            foreach ($stagedUploads as $relative => $path) {
                $target = __TYPECHO_ROOT_DIR__ . '/usr/uploads/' . $relative;
                $directory = dirname($target);
                $current = __TYPECHO_ROOT_DIR__ . '/usr/uploads';
                if (is_link($current)) throw new RuntimeException('上传根目录是符号链接，已拒绝恢复');
                foreach (explode('/', dirname($relative)) as $segment) {
                    if ($segment !== '.') {
                        $current .= '/' . $segment;
                        if (is_link($current)) throw new RuntimeException('上传目录包含符号链接：' . $relative);
                    }
                }
                if (is_link($target)) throw new RuntimeException('目标文件是符号链接：' . $relative);
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new RuntimeException('无法创建上传目录：' . $relative);
                }
                $stagingTarget = $target . '.r2-restore-' . bin2hex(random_bytes(4));
                if (!copy($path, $stagingTarget)) {
                    @unlink($stagingTarget);
                    throw new RuntimeException('无法暂存本地文件：' . $relative);
                }
                if (!rename($stagingTarget, $target)) {
                    @unlink($stagingTarget);
                    throw new RuntimeException('无法替换本地文件：' . $relative);
                }
            }
            $result = ['success' => true, 'snapshot' => $snapshot, 'protectionSnapshot' => $protection['snapshot'], 'filesRestored' => count($stagedUploads), 'finishedAt' => date(DATE_ATOM)];
            self::setStatus($result);
            return $result;
        } catch (\Throwable $error) {
            try {
                self::setStatus(['success' => false, 'snapshot' => $snapshot, 'protectionSnapshot' => $protectionSnapshot, 'message' => $error->getMessage(), 'finishedAt' => date(DATE_ATOM)]);
            } catch (\Throwable $ignored) {
                error_log('[R2Backup] 无法写入恢复状态：' . $ignored->getMessage());
            }
            throw $error;
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            foreach (glob($work . '/*') ?: [] as $file) {
                if (is_file($file)) @unlink($file);
            }
            @rmdir($work);
        }
    }

    public static function validateSnapshot(string $snapshot): void
    {
        if (!preg_match('~^\d{4}-\d{2}-\d{2}/\d{6}$~D', $snapshot)) {
            throw new RuntimeException('无效的快照编号');
        }
    }

    private static function client(): array
    {
        $options = Options::alloc()->plugin('R2Backup');
        $prefix = trim((string) $options->prefix, '/ ');
        if ($prefix === '') $prefix = 'xiaogu-backup';
        return [new R2Client((string) $options->accountId, (string) $options->bucket, (string) $options->accessKey, (string) $options->secretKey), $prefix];
    }

    private static function verify(string $path, array $entry): void
    {
        $sha = (string) ($entry['sha256'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $sha) || !hash_equals($sha, hash_file('sha256', $path)) || filesize($path) !== (int) ($entry['size'] ?? -1)) {
            throw new RuntimeException('快照文件校验失败：' . basename($path));
        }
    }

    private static function readSql(string $path, ?callable $consumer = null): int
    {
        $gz = gzopen($path, 'rb');
        if ($gz === false) throw new RuntimeException('无法打开数据库备份');
        $statementCount = 0;
        $tableCount = 0;
        $ended = false;
        $sql = '';
        $prefix = Db::get()->getPrefix();
        try {
            while (!gzeof($gz)) {
                $line = gzgets($gz);
                if ($line === false) break;
                $sql .= $line;
                if (!preg_match('/;\s*$/', $line)) continue;
                $trimmed = trim($sql);
                $sql = '';
                if ($trimmed === '') continue;
                if ($ended) throw new RuntimeException('数据库备份末尾存在意外语句');
                if (!preg_match('/^SET (?:NAMES utf8mb4|FOREIGN_KEY_CHECKS=[01]);$/i', $trimmed)
                    && !preg_match('/^(?:DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO)\s+`?' . preg_quote($prefix, '/') . '[a-zA-Z0-9_]+`?/i', $trimmed)) {
                    throw new RuntimeException('备份含有不受支持的 SQL 语句');
                }
                $statementCount++;
                if (stripos($trimmed, 'CREATE TABLE ') === 0) $tableCount++;
                if (strcasecmp($trimmed, 'SET FOREIGN_KEY_CHECKS=1;') === 0) $ended = true;
                if ($consumer !== null) $consumer($trimmed);
            }
        } finally {
            gzclose($gz);
        }
        if (trim($sql) !== '' || $statementCount < 3 || $tableCount < 1 || !$ended) {
            throw new RuntimeException('数据库备份不完整');
        }
        return $tableCount;
    }
}
