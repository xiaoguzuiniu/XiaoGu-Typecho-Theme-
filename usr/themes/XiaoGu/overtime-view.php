<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$overtimeAccess = $GLOBALS['xiaogu_overtime_access'] ?? ['allowed' => false, 'message' => '暂时无法验证访问密码，请稍后重试。'];
if (empty($overtimeAccess['allowed'])):
?>
<section class="overtime-locked" aria-labelledby="overtime-locked-title">
    <span class="overtime-eyebrow">PRIVATE PAGE</span>
    <h1 id="overtime-locked-title">加班</h1>
    <p>请输入访问密码，查看加班记录。</p>
    <?php if (!empty($overtimeAccess['message'])): ?>
        <p class="overtime-lock-message" role="alert"><?php echo htmlspecialchars((string) $overtimeAccess['message'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if (trim((string) $this->options->overtimePagePassword) !== ''): ?>
        <form class="overtime-lock-form" method="post" action="<?php echo htmlspecialchars((string) $this->permalink, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="overtime-password">访问密码</label>
            <input id="overtime-password" type="password" name="overtime_password" autocomplete="current-password" required autofocus>
            <button type="submit">进入加班页面</button>
        </form>
    <?php endif; ?>
</section>
<?php
return;
endif;

require_once __TYPECHO_ROOT_DIR__ . '/api/overtime-data.php';
$today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Shanghai'));
$currentMonth = $today->format('Y-m');
$requestedMonth = $_GET['month'] ?? $currentMonth;
$month = is_string($requestedMonth) && xiaoguOvertimeValidMonth($requestedMonth) ? $requestedMonth : $currentMonth;
$monthDate = new \DateTimeImmutable($month . '-01');
$previousMonth = $monthDate->modify('-1 month')->format('Y-m');
$nextMonth = $monthDate->modify('+1 month')->format('Y-m');
$pageUrl = (string) $this->permalink;
$monthUrl = static function (string $value) use ($pageUrl): string {
    return $pageUrl . (strpos($pageUrl, '?') === false ? '?' : '&') . 'month=' . rawurlencode($value);
};
$entries = [];
$error = '';
try {
    $db = \Typecho\Db::get();
    $entries = xiaoguOvertimeMonthEntries($db, $month);
} catch (\Throwable $exception) {
    $error = '加班记录暂时无法读取，请稍后重试。';
    error_log('[overtime-page] ' . $exception->getMessage());
}
$summary = xiaoguOvertimeMonthSummary($entries);
$entriesByDate = [];
foreach ($entries as $entry) $entriesByDate[(string) $entry['work_date']] = $entry;
$firstWeekday = (int) $monthDate->format('N') - 1;
$daysInMonth = (int) $monthDate->format('t');
$initialDate = $month === $currentMonth ? $today->format('Y-m-d') : ($entries[0]['work_date'] ?? $month . '-01');
$jsonEntries = [];
foreach ($entries as $entry) {
    $jsonEntries[(string) $entry['work_date']] = [
        'weekday_hours' => (float) $entry['weekday_hours'],
        'weekend_hours' => (float) $entry['weekend_hours'],
        'pay' => (float) $entry['pay'],
        'note' => (string) $entry['note'],
    ];
}
$jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<div class="overtime-page" id="overtime-page" data-initial-date="<?php echo htmlspecialchars($initialDate, ENT_QUOTES, 'UTF-8'); ?>">
    <header class="overtime-hero">
        <div>
            <h1>加班</h1>
            <p>每天的一点忙碌，都有迹可循。</p>
        </div>
    </header>

    <section class="overtime-overview" aria-label="本月加班统计">
        <div><span>日常加班</span><strong><?php echo rtrim(rtrim(number_format($summary['weekday_hours'], 2, '.', ','), '0'), '.'); ?><small> h</small></strong></div>
        <div><span>周末加班</span><strong><?php echo rtrim(rtrim(number_format($summary['weekend_hours'], 2, '.', ','), '0'), '.'); ?><small> h</small></strong></div>
        <div><span>本月加班费</span><strong><small>¥ </small><?php echo number_format($summary['monthly_pay'], 2, '.', ','); ?></strong></div>
        <div><span>记录天数</span><strong><?php echo (int) $summary['recorded_days']; ?><small> 天</small></strong></div>
    </section>

    <section class="overtime-calendar-panel" aria-label="加班日历">
        <div class="overtime-calendar-top">
            <h2><?php echo htmlspecialchars($monthDate->format('Y 年 n 月'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="overtime-month-controls">
                <a href="<?php echo htmlspecialchars($monthUrl($previousMonth), ENT_QUOTES, 'UTF-8'); ?>" aria-label="上个月">‹</a>
                <a href="<?php echo htmlspecialchars($monthUrl($nextMonth), ENT_QUOTES, 'UTF-8'); ?>" aria-label="下个月">›</a>
            </div>
        </div>
        <?php if ($error !== ''): ?><p class="overtime-error" role="status"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <div class="overtime-weekdays" aria-hidden="true"><span>一</span><span>二</span><span>三</span><span>四</span><span>五</span><span>六</span><span>日</span></div>
        <div class="overtime-calendar-days" aria-label="选择日期查看加班详情">
            <?php for ($blank = 0; $blank < $firstWeekday; $blank++): ?><span class="overtime-calendar-blank" aria-hidden="true"></span><?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $date = $month . '-' . sprintf('%02d', $day);
                $entry = $entriesByDate[$date] ?? null;
                $hours = $entry ? (float) $entry['weekday_hours'] + (float) $entry['weekend_hours'] : 0.0;
                $level = $hours >= 6 ? 4 : ($hours >= 4 ? 3 : ($hours >= 2 ? 2 : ($hours > 0 ? 1 : 0)));
                ?>
                <button type="button" class="overtime-calendar-day level-<?php echo $level; ?><?php if ($date === $today->format('Y-m-d')): ?> is-today<?php endif; ?>" data-date="<?php echo $date; ?>" aria-label="<?php echo $date; ?><?php echo $entry ? '，加班 ' . $hours . ' 小时' : '，无加班记录'; ?>">
                    <span><?php echo $day; ?></span>
                    <?php if ($entry): ?><i aria-hidden="true"></i><?php endif; ?>
                </button>
            <?php endfor; ?>
        </div>
        <div class="overtime-calendar-legend" aria-hidden="true"><span>少</span><i class="level-0"></i><i class="level-1"></i><i class="level-2"></i><i class="level-3"></i><i class="level-4"></i><span>多</span></div>
    </section>

    <section class="overtime-detail-panel" aria-live="polite" aria-label="所选日期的加班详情">
        <span class="overtime-eyebrow">DAILY NOTE</span>
        <h2 id="overtime-detail-date"></h2>
        <div id="overtime-detail-content"></div>
    </section>
</div>
<script type="application/json" id="overtime-page-data"><?php echo json_encode($jsonEntries, $jsonOptions); ?></script>
