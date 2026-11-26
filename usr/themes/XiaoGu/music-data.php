<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function xiaoguMusicEnsureTable(\Typecho\Db $db): void
{
    static $ready = false;
    if ($ready) return;
    $prefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) throw new \RuntimeException('Invalid database prefix');
    $table = '`' . $prefix . 'music_tracks`';
    $db->query("CREATE TABLE IF NOT EXISTS {$table} (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(200) NOT NULL,
        `artist` VARCHAR(200) NOT NULL DEFAULT '',
        `album` VARCHAR(200) NOT NULL DEFAULT '',
        `source` VARCHAR(32) NOT NULL DEFAULT 'upload',
        `source_id` VARCHAR(100) DEFAULT NULL,
        `attachment` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_source` (`source`, `source_id`),
        KEY `idx_title` (`title`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", \Typecho\Db::WRITE, '');
    $ready = true;
}

function xiaoguMusicPublicTrack(array $row): array
{
    $siteUrl = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
    return [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'artist' => (string) $row['artist'],
        'album' => (string) $row['album'],
        'source' => (string) $row['source'],
        'song_id' => $row['source_id'] === null ? null : (string) $row['source_id'],
        'audio_url' => $siteUrl . '/api/music/tracks/' . (int) $row['id'] . '/audio',
    ];
}
