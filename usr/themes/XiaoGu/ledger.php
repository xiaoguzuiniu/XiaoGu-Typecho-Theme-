<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 创建账单表。页面和接口首次使用时自动执行。
 */
function xiaoguLedgerEnsureTable(\Typecho\Db $db): void
{
    static $ready = false;
    if ($ready) return;

    $prefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
        throw new \RuntimeException('Invalid database prefix');
    }

    $table = '`' . $prefix . 'ledger_entries`';
    $db->query("CREATE TABLE IF NOT EXISTS {$table} (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `amount` DECIMAL(12,2) UNSIGNED NOT NULL,
        `category` VARCHAR(64) NOT NULL,
        `spent_at` DATETIME NOT NULL,
        `note` VARCHAR(255) NOT NULL DEFAULT '',
        `receipt_url` VARCHAR(500) NOT NULL,
        `attachment_cid` BIGINT UNSIGNED DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_spent_at` (`spent_at`),
        KEY `idx_category_spent_at` (`category`, `spent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", \Typecho\Db::WRITE, '');

    $ready = true;
}

function xiaoguLedgerCategories($raw): array
{
    $categories = [];
    foreach (preg_split('/\R/u', trim((string) $raw)) ?: [] as $line) {
        $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $line)), 0, 60);
        if ($name !== '' && !in_array($name, $categories, true)) {
            $categories[] = $name;
        }
    }

    return $categories ?: ['餐饮', '交通', '购物', '娱乐', '居住', '医疗', '其他'];
}

function xiaoguLedgerMonthlyBudgets($raw): array
{
    $budgets = [];
    foreach (preg_split('/\R/u', trim((string) $raw)) ?: [] as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) !== 2 || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $parts[0])) {
            continue;
        }
        if (!is_numeric($parts[1])) {
            continue;
        }
        $budgets[$parts[0]] = max(0, round((float) $parts[1], 2));
    }
    ksort($budgets);
    return $budgets;
}

function xiaoguLedgerBudgetForMonth($options, string $month): float
{
    $defaultBudget = max(0, round((float) $options->ledgerDefaultBudget, 2));
    $budgets = xiaoguLedgerMonthlyBudgets((string) $options->ledgerMonthlyBudgets);
    return array_key_exists($month, $budgets) ? $budgets[$month] : $defaultBudget;
}

function xiaoguLedgerNormalizeMonth(?string $month): string
{
    $month = trim((string) $month);
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
        ? $month
        : xiaoguLedgerCurrentPeriodMonth();
}

function xiaoguLedgerCurrentPeriodMonth(): string
{
    $today = new \DateTimeImmutable('today');
    return xiaoguLedgerPeriodMonthForDate($today);
}

function xiaoguLedgerPeriodMonthForDate(\DateTimeInterface $date): string
{
    $periodDate = \DateTimeImmutable::createFromInterface($date);
    if ((int) $periodDate->format('d') < 10) {
        $periodDate = $periodDate->modify('first day of previous month');
    }
    return $periodDate->format('Y-m');
}

function xiaoguLedgerPeriodRange(string $month): array
{
    $start = new \DateTimeImmutable($month . '-10 00:00:00');
    $end = $start->modify('+1 month');
    return ['start' => $start, 'end' => $end];
}

function xiaoguLedgerMonthUrl(string $permalink, string $month): string
{
    return $permalink . (strpos($permalink, '?') === false ? '?' : '&') . 'month=' . rawurlencode($month);
}

function xiaoguLedgerMonthEntries(\Typecho\Db $db, string $month): array
{
    xiaoguLedgerEnsureTable($db);
    $range = xiaoguLedgerPeriodRange($month);
    $start = $range['start']->format('Y-m-d H:i:s');
    $next = $range['end']->format('Y-m-d H:i:s');

    return $db->fetchAll(
        $db->select('id', 'amount', 'category', 'spent_at', 'note', 'receipt_url')
            ->from('table.ledger_entries')
            ->where('spent_at >= ? AND spent_at < ?', $start, $next)
            ->order('spent_at', \Typecho\Db::SORT_DESC)
            ->order('id', \Typecho\Db::SORT_DESC)
    );
}

function xiaoguLedgerMonthSummary(array $entries, array $configuredCategories): array
{
    $spent = 0.0;
    $categoryTotals = array_fill_keys($configuredCategories, 0.0);
    foreach ($entries as $entry) {
        $amount = round((float) ($entry['amount'] ?? 0), 2);
        $category = (string) ($entry['category'] ?? '其他');
        $spent += $amount;
        if (!array_key_exists($category, $categoryTotals)) {
            $categoryTotals[$category] = 0.0;
        }
        $categoryTotals[$category] += $amount;
    }
    $categoryTotals = array_filter($categoryTotals, static fn($amount) => $amount > 0);
    arsort($categoryTotals);

    return [
        'spent' => round($spent, 2),
        'categories' => $categoryTotals,
    ];
}
