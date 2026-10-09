<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$ledgerMonth = xiaoguLedgerNormalizeMonth(isset($_GET['month']) ? (string) $_GET['month'] : null);
$ledgerDate = new \DateTimeImmutable($ledgerMonth . '-01');
$ledgerPreviousMonth = $ledgerDate->modify('-1 month')->format('Y-m');
$ledgerNextMonth = $ledgerDate->modify('+1 month')->format('Y-m');
$ledgerCurrentMonth = date('Y-m');
$ledgerCategories = xiaoguLedgerCategories((string) $this->options->ledgerCategories);
$ledgerBudget = xiaoguLedgerBudgetForMonth($this->options, $ledgerMonth);
$ledgerEntries = [];
$ledgerError = '';

try {
    $ledgerEntries = xiaoguLedgerMonthEntries(\Typecho\Db::get(), $ledgerMonth);
} catch (\Throwable $error) {
    $ledgerError = '账单暂时无法读取，请稍后重试。';
    error_log('[ledger-page] ' . $error->getMessage());
}

$ledgerSummary = xiaoguLedgerMonthSummary($ledgerEntries, $ledgerCategories);
$ledgerSpent = (float) $ledgerSummary['spent'];
$ledgerRemaining = round($ledgerBudget - $ledgerSpent, 2);
$ledgerProgress = $ledgerBudget > 0 ? min(100, ($ledgerSpent / $ledgerBudget) * 100) : 0;
$ledgerPermalink = (string) $this->permalink;
?>

<section class="ledger-page">
    <header class="ledger-hero">
        <div>
            <span class="ledger-eyebrow">MONTHLY LEDGER</span>
            <h1><?php $this->title(); ?></h1>
            <p>认真记录每一笔，也安心过好每一天。</p>
        </div>
        <nav class="ledger-month-nav" aria-label="切换账单月份">
            <a href="<?php echo htmlspecialchars(xiaoguLedgerMonthUrl($ledgerPermalink, $ledgerPreviousMonth), ENT_QUOTES, 'UTF-8'); ?>"
               aria-label="查看上个月">‹</a>
            <strong><?php echo htmlspecialchars($ledgerDate->format('Y 年 m 月'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <?php if ($ledgerMonth < $ledgerCurrentMonth): ?>
                <a href="<?php echo htmlspecialchars(xiaoguLedgerMonthUrl($ledgerPermalink, $ledgerNextMonth), ENT_QUOTES, 'UTF-8'); ?>"
                   aria-label="查看下个月">›</a>
            <?php else: ?>
                <span aria-hidden="true">›</span>
            <?php endif; ?>
        </nav>
    </header>

    <section class="ledger-summary-grid" aria-label="本月账单概览">
        <article class="ledger-summary-card ledger-budget-card">
            <span>本月生活费</span>
            <strong><small>¥</small><?php echo number_format($ledgerBudget, 2); ?></strong>
            <div class="ledger-progress" aria-label="已使用 <?php echo round($ledgerProgress); ?>%">
                <i style="width:<?php echo number_format($ledgerProgress, 2, '.', ''); ?>%"></i>
            </div>
            <p>已使用 <?php echo round($ledgerProgress); ?>%</p>
        </article>
        <article class="ledger-summary-card">
            <span>本月消费</span>
            <strong class="ledger-spent"><small>¥</small><?php echo number_format($ledgerSpent, 2); ?></strong>
            <p><?php echo count($ledgerEntries); ?> 笔账单</p>
        </article>
        <article class="ledger-summary-card<?php echo $ledgerRemaining < 0 ? ' is-negative' : ''; ?>">
            <span><?php echo $ledgerRemaining < 0 ? '本月超支' : '剩余生活费'; ?></span>
            <strong><small>¥</small><?php echo number_format(abs($ledgerRemaining), 2); ?></strong>
            <p><?php echo $ledgerRemaining < 0 ? '下个月对自己温柔一点' : '继续保持，从容生活'; ?></p>
        </article>
    </section>

    <?php if ($ledgerSummary['categories']): ?>
        <section class="ledger-category-panel">
            <div class="ledger-section-heading">
                <div><span>消费分布</span><h2>钱都花在了哪里</h2></div>
                <small><?php echo count($ledgerSummary['categories']); ?> 个类型</small>
            </div>
            <div class="ledger-category-list">
                <?php foreach ($ledgerSummary['categories'] as $category => $amount): ?>
                    <?php $ratio = $ledgerSpent > 0 ? min(100, ((float) $amount / $ledgerSpent) * 100) : 0; ?>
                    <div class="ledger-category-row">
                        <div><strong><?php echo htmlspecialchars((string) $category, ENT_QUOTES, 'UTF-8'); ?></strong><span>¥<?php echo number_format((float) $amount, 2); ?></span></div>
                        <i><b style="width:<?php echo number_format($ratio, 2, '.', ''); ?>%"></b></i>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="ledger-records">
        <div class="ledger-section-heading">
            <div><span>消费明细</span><h2><?php echo htmlspecialchars($ledgerDate->format('m 月'), ENT_QUOTES, 'UTF-8'); ?>的每一笔记录</h2></div>
            <small><?php echo count($ledgerEntries); ?> 笔</small>
        </div>

        <?php if ($ledgerError !== ''): ?>
            <div class="ledger-empty"><strong>读取失败</strong><p><?php echo htmlspecialchars($ledgerError, ENT_QUOTES, 'UTF-8'); ?></p></div>
        <?php elseif (!$ledgerEntries): ?>
            <div class="ledger-empty">
                <span aria-hidden="true">◎</span>
                <strong>这个月还没有账单</strong>
                <p>从 iPhone 相册分享消费照片，用“记一笔”快捷指令开始记录。</p>
            </div>
        <?php else: ?>
            <div class="ledger-entry-grid">
                <?php foreach ($ledgerEntries as $entry): ?>
                    <?php
                    $receiptOriginal = getXiaoGuQiniuDeliveryUrl((string) $entry['receipt_url']);
                    $receiptThumbnail = getXiaoGuMomentThumbnailUrl($receiptOriginal);
                    $spentAt = new \DateTimeImmutable((string) $entry['spent_at']);
                    ?>
                    <article class="ledger-entry-card">
                        <a class="ledger-receipt" href="<?php echo htmlspecialchars($receiptOriginal, ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener noreferrer" aria-label="查看消费凭证原图">
                            <img src="<?php echo htmlspecialchars($receiptThumbnail, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" loading="lazy" decoding="async">
                        </a>
                        <div class="ledger-entry-copy">
                            <div class="ledger-entry-top">
                                <span><?php echo htmlspecialchars((string) $entry['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <strong>− ¥<?php echo number_format((float) $entry['amount'], 2); ?></strong>
                            </div>
                            <?php if (trim((string) $entry['note']) !== ''): ?>
                                <p><?php echo htmlspecialchars((string) $entry['note'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <time datetime="<?php echo htmlspecialchars($spentAt->format('c'), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($spentAt->format('m 月 d 日 H:i'), ENT_QUOTES, 'UTF-8'); ?>
                            </time>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
