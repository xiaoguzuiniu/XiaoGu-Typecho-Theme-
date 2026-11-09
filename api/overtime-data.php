<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    http_response_code(404);
    exit;
}

function xiaoguOvertimeEnsureTable(\Typecho\Db $db): void
{
    static $ready = false;
    if ($ready) return;
    $prefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) throw new \RuntimeException('Invalid database prefix');
    $table = '`' . $prefix . 'overtime_daily`';
    $db->query("CREATE TABLE IF NOT EXISTS {$table} (
        `work_date` DATE NOT NULL,
        `weekday_hours` DECIMAL(6,2) UNSIGNED NOT NULL DEFAULT 0,
        `weekend_hours` DECIMAL(6,2) UNSIGNED NOT NULL DEFAULT 0,
        `pay` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0,
        `note` VARCHAR(1000) NOT NULL DEFAULT '',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`work_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", \Typecho\Db::WRITE, '');
    $ready = true;
}

function xiaoguOvertimeValidDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function xiaoguOvertimeValidMonth(string $month): bool
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) === 1;
}

function xiaoguOvertimeMonthEntries(\Typecho\Db $db, string $month): array
{
    xiaoguOvertimeEnsureTable($db);
    $nextMonth = (new \DateTimeImmutable($month . '-01'))->modify('+1 month')->format('Y-m-d');
    return $db->fetchAll($db->select('work_date', 'weekday_hours', 'weekend_hours', 'pay', 'note', 'updated_at')
        ->from('table.overtime_daily')
        ->where('work_date >= ? AND work_date < ?', $month . '-01', $nextMonth)
        ->order('work_date', \Typecho\Db::SORT_DESC));
}

function xiaoguOvertimeRecordedMonths(\Typecho\Db $db): array
{
    xiaoguOvertimeEnsureTable($db);
    $rows = $db->fetchAll($db->select('work_date')->from('table.overtime_daily')
        ->order('work_date', \Typecho\Db::SORT_DESC));
    $months = [];
    foreach ($rows as $row) $months[substr((string) $row['work_date'], 0, 7)] = true;
    return array_keys($months);
}

function xiaoguOvertimeMonthSummary(array $entries): array
{
    $summary = ['weekday_hours' => 0.0, 'weekend_hours' => 0.0, 'monthly_pay' => 0.0, 'recorded_days' => count($entries)];
    foreach ($entries as $entry) {
        $summary['weekday_hours'] += (float) $entry['weekday_hours'];
        $summary['weekend_hours'] += (float) $entry['weekend_hours'];
        $summary['monthly_pay'] += (float) $entry['pay'];
    }
    return $summary;
}

function xiaoguOvertimeUpsert(\Typecho\Db $db, string $date, float $weekdayHours, float $weekendHours, float $pay, string $note): void
{
    xiaoguOvertimeEnsureTable($db);
    $adapter = $db->getAdapter();
    $table = '`' . $db->getPrefix() . 'overtime_daily`';
    $dateSql = $adapter->quoteValue($date);
    $noteSql = $adapter->quoteValue($note);
    $weekdaySql = number_format($weekdayHours, 2, '.', '');
    $weekendSql = number_format($weekendHours, 2, '.', '');
    $paySql = number_format($pay, 2, '.', '');
    $db->query("INSERT INTO {$table} (`work_date`, `weekday_hours`, `weekend_hours`, `pay`, `note`)
        VALUES ({$dateSql}, {$weekdaySql}, {$weekendSql}, {$paySql}, {$noteSql})
        ON DUPLICATE KEY UPDATE
            `weekday_hours` = VALUES(`weekday_hours`),
            `weekend_hours` = VALUES(`weekend_hours`),
            `pay` = VALUES(`pay`),
            `note` = VALUES(`note`)", \Typecho\Db::WRITE, '');
}
