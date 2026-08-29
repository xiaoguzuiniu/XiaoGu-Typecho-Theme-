<?php

if (!defined('__TYPECHO_ROOT_DIR__')) {
    http_response_code(404);
    exit;
}

/**
 * 创建每日健康数据表。接口首次收到数据时自动执行。
 */
function xiaoguHealthEnsureTable(\Typecho\Db $db): void
{
    $prefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
        throw new \RuntimeException('Invalid database prefix');
    }

    $table = '`' . $prefix . 'health_daily`';
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `date` DATE NOT NULL,
        `update_time` TIME NOT NULL,
        `steps` INT UNSIGNED NOT NULL DEFAULT 0,
        `active_energy` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_date` (`date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $db->query($sql, \Typecho\Db::WRITE, '');
}

/**
 * 获取指定日期的健康记录。
 */
function xiaoguHealthFindByDate(\Typecho\Db $db, string $date): ?array
{
    return $db->fetchRow(
        $db->select('date', 'update_time', 'steps', 'active_energy', 'updated_at')
            ->from('table.health_daily')
            ->where('date = ?', $date)
            ->limit(1)
    );
}

/**
 * 按日期新增或覆盖健康记录，不累计客户端提交的数值。
 */
function xiaoguHealthUpsert(
    \Typecho\Db $db,
    string $date,
    string $updateTime,
    int $steps,
    float $activeEnergy
): array {
    xiaoguHealthEnsureTable($db);

    $adapter = $db->getAdapter();
    $prefix = $db->getPrefix();
    $table = '`' . $prefix . 'health_daily`';
    $dateSql = $adapter->quoteValue($date);
    $timeSql = $adapter->quoteValue($updateTime);
    $energySql = number_format($activeEnergy, 2, '.', '');

    $sql = "INSERT INTO {$table} (`date`, `update_time`, `steps`, `active_energy`)
        VALUES ({$dateSql}, {$timeSql}, {$steps}, {$energySql})
        ON DUPLICATE KEY UPDATE
            `steps` = IF(VALUES(`update_time`) >= `update_time`, VALUES(`steps`), `steps`),
            `active_energy` = IF(VALUES(`update_time`) >= `update_time`, VALUES(`active_energy`), `active_energy`),
            `update_time` = IF(VALUES(`update_time`) >= `update_time`, VALUES(`update_time`), `update_time`)";
    $db->query($sql, \Typecho\Db::WRITE, '');

    $record = xiaoguHealthFindByDate($db, $date);
    if (!$record) {
        throw new \RuntimeException('Health data was not saved');
    }

    return $record;
}
