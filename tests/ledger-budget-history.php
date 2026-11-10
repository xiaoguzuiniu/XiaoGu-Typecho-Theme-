<?php

declare(strict_types=1);

define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
require_once __DIR__ . '/../usr/themes/XiaoGu/ledger.php';

function assertBudgets(array $expected, string $old, string $new, float $oldDefault, string $current, ?string $first): void
{
    $actual = xiaoguLedgerMonthlyBudgets(xiaoguLedgerPreservePastBudgets($old, $new, $oldDefault, $current, $first));
    if ($actual !== $expected) {
        throw new \RuntimeException('账期预算不符：' . json_encode($actual, JSON_UNESCAPED_UNICODE));
    }
}

// 改动默认金额后，先前有记录的账期继续使用旧金额。
assertBudgets(['2026-09' => 1500.0, '2026-10' => 1500.0], '', '', 1500, '2026-11', '2026-09');
// 管理员本次明确设置的账期不应被旧默认金额覆盖。
assertBudgets(['2026-10' => 1700.0], '', '2026-10|1700', 1500, '2026-11', null);
// 已有的指定金额保持不变，其他历史账期才补齐旧默认金额。
assertBudgets(['2026-09' => 1200.0, '2026-10' => 1500.0], '2026-09|1200', '2026-09|1200', 1500, '2026-11', '2026-09');
// 管理员明确删除指定金额时，不悄悄把它作为历史快照写回去。
assertBudgets([], '2026-10|1200', '', 1500, '2026-11', null);

echo "ledger budget history: OK\n";
