<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function xiaoguFitnessEnsureTable(\Typecho\Db $db): void
{
    static $ready = false;
    if ($ready) return;

    $prefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
        throw new \RuntimeException('Invalid database prefix');
    }
    $table = '`' . $prefix . 'fitness_checkins`';
    $db->query("CREATE TABLE IF NOT EXISTS {$table} (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `checkin_date` DATE NOT NULL,
        `note` TEXT NOT NULL,
        `image_url` VARCHAR(500) NOT NULL DEFAULT '',
        `attachment_cid` BIGINT UNSIGNED DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_checkin_date` (`checkin_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", \Typecho\Db::WRITE, '');
    $ready = true;
}

function xiaoguFitnessValidDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
    $parts = array_map('intval', explode('-', $date));
    return checkdate($parts[1], $parts[2], $parts[0]);
}

function xiaoguFitnessCompletedDates(): array
{
    $db = \Typecho\Db::get();
    xiaoguFitnessEnsureTable($db);
    $dates = [];
    $rows = $db->fetchAll($db->select('checkin_date')->from('table.fitness_checkins')->group('checkin_date'));
    foreach ($rows as $row) {
        $date = (string) $row['checkin_date'];
        if (xiaoguFitnessValidDate($date)) $dates[$date] = true;
    }
    // Preserve dates marked before photo check-ins were introduced.
    $legacyRows = $db->fetchAll(
        $db->select('name')->from('table.options')
            ->where('name LIKE ? AND user = ? AND value = ?', 'xgfit:%', 0, '1')
    );
    foreach ($legacyRows as $row) {
        $date = substr((string) $row['name'], 6);
        if (xiaoguFitnessValidDate($date)) $dates[$date] = true;
    }
    $result = array_keys($dates);
    sort($result);
    return $result;
}

function xiaoguFitnessEntriesForDate(\Typecho\Db $db, string $date): array
{
    xiaoguFitnessEnsureTable($db);
    return $db->fetchAll(
        $db->select('id', 'checkin_date', 'note', 'image_url', 'created_at')
            ->from('table.fitness_checkins')
            ->where('checkin_date = ?', $date)
            ->order('created_at', \Typecho\Db::SORT_DESC)
            ->order('id', \Typecho\Db::SORT_DESC)
    );
}
