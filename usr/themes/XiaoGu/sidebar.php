<?php
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
$activityCalendar = getSiteActivityCalendar();
?>

<aside class="site-sidebar" aria-label="站点侧边栏">
    <section class="sidebar-block date-card">
        <div class="date-line">
            <strong><?php echo date('d'); ?></strong>
            <span><?php echo date('Y / m'); ?></span>
        </div>
        <span class="weekday">星期<?php echo $weekdays[(int) date('w')]; ?></span>
    </section>

    <div class="sidebar-reserved-space" aria-hidden="true"></div>

    <section class="sidebar-block health-summary" aria-label="今日健康数据展示示例">
        <div class="health-summary-head">
            <h2>今日健康</h2>
            <div class="health-sync-time" aria-label="本次同步时间 14:00">
                <time datetime="2026-08-24T14:00:00+08:00">14:00</time>
            </div>
        </div>

        <div class="health-metrics">
            <div class="health-metric">
                <div class="health-metric-label"><span aria-hidden="true">🚶</span>今日步数</div>
                <p><strong>6,528</strong><span>步</span></p>
            </div>
            <div class="health-metric">
                <div class="health-metric-label"><span aria-hidden="true">🔥</span>活动消耗</div>
                <p><strong>286</strong><span>kcal</span></p>
            </div>
        </div>
    </section>

    <section class="sidebar-block activity-block" aria-label="创作活动">
        <div class="activity-calendar" style="--activity-weeks: <?php echo $activityCalendar['weeks']; ?>;">
            <div class="activity-months" aria-hidden="true">
                <?php foreach ($activityCalendar['months'] as $month): ?>
                    <span style="grid-column: <?php echo $month['column']; ?>;"><?php echo $month['label']; ?></span>
                <?php endforeach; ?>
            </div>
            <div class="activity-body">
                <div class="activity-weekdays" aria-hidden="true">
                    <span>一</span><span></span><span>三</span><span></span><span>五</span><span></span><span>日</span>
                </div>
                <div class="activity-grid" role="grid" aria-label="最近 <?php echo $activityCalendar['weeks']; ?> 周创作活动">
                    <?php foreach ($activityCalendar['days'] as $day): ?>
                        <?php if ($day['count'] > 0 && !$day['future']): ?>
                            <button type="button"
                                    class="activity-day activity-level-<?php echo $day['level']; ?>"
                                    data-date="<?php echo $day['date']; ?>"
                                    data-activities="<?php echo htmlspecialchars(json_encode($day['activities'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="<?php echo $day['date']; ?>，<?php echo $day['count']; ?> 次活动"></button>
                        <?php else: ?>
                            <span class="activity-day<?php if ($day['future']): ?> is-future<?php endif; ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="activity-legend" aria-label="活动量图例">
                <span>少</span>
                <i class="activity-level-0"></i>
                <i class="activity-level-1"></i>
                <i class="activity-level-2"></i>
                <i class="activity-level-3"></i>
                <i class="activity-level-4"></i>
                <i class="activity-level-5"></i>
                <span>多</span>
            </div>
            <div class="activity-tooltip" role="tooltip" hidden>
                <strong></strong>
                <ul></ul>
            </div>
        </div>
    </section>

    <section class="sidebar-block recent-block">
        <h2>最近文章</h2>
        <ol class="recent-list">
            <?php $recentArticleNumber = 0; ?>
            <?php \Widget\Contents\Post\Recent::alloc('pageSize=6')->to($recentPosts); ?>
            <?php while ($recentArticleNumber < 6 && $recentPosts->next()): ?>
                <?php $recentDisplayMode = (string) $recentPosts->fields->displayMode; ?>
                <?php $recentArticleNumber++; ?>
                <li>
                    <span><?php echo str_pad((string) $recentArticleNumber, 2, '0', STR_PAD_LEFT); ?></span>
                    <?php if ($recentDisplayMode === 'moment'): ?>
                        <a class="recent-moment-title" href="<?php $recentPosts->permalink(); ?>"><?php
                            echo htmlspecialchars(
                                getRecentMomentTitle((int) $recentPosts->cid),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?></a>
                    <?php else: ?>
                        <a href="<?php $recentPosts->permalink(); ?>"><?php $recentPosts->title(); ?></a>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ol>
    </section>

    <?php $this->need('footer.php'); ?>
</aside>

<script>
    (function () {
        document.querySelectorAll('.activity-calendar').forEach(function (calendar) {
            const tooltip = calendar.querySelector('.activity-tooltip');
            const heading = tooltip.querySelector('strong');
            const list = tooltip.querySelector('ul');
            document.body.appendChild(tooltip);

            function hideTooltip() {
                tooltip.hidden = true;
            }

            function showTooltip(cell) {
                let activities;
                try {
                    activities = JSON.parse(cell.dataset.activities);
                } catch (error) {
                    hideTooltip();
                    return;
                }

                heading.textContent = cell.dataset.date + ' (' + activities.length + '次活动)';
                list.replaceChildren();
                activities.forEach(function (activity) {
                    const item = document.createElement('li');
                    const badge = document.createElement('span');
                    const title = document.createElement('b');
                    badge.className = 'activity-type activity-type-' + activity.type;
                    badge.textContent = activity.type === 'new' ? '新' : '改';
                    title.textContent = activity.title;
                    item.append(badge, title);
                    list.appendChild(item);
                });
                tooltip.hidden = false;
                tooltip.hidden = false;

                const cellRect = cell.getBoundingClientRect();
                const tooltipRect = tooltip.getBoundingClientRect();
                let left = cellRect.left + cellRect.width / 2 - tooltipRect.width / 2;
                let top = cellRect.top - tooltipRect.height - 10;
                left = Math.max(8, Math.min(left, window.innerWidth - tooltipRect.width - 8));
                if (top < 8) top = cellRect.bottom + 10;
                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
            }

            calendar.querySelectorAll('button.activity-day').forEach(function (cell) {
                cell.addEventListener('mouseenter', function () {
                    showTooltip(cell);
                });
                cell.addEventListener('mouseleave', hideTooltip);
                cell.addEventListener('focus', function () {
                    showTooltip(cell);
                });
                cell.addEventListener('blur', hideTooltip);
                cell.addEventListener('click', function (event) {
                    event.stopPropagation();
                    showTooltip(cell);
                });
            });

            document.addEventListener('click', function (event) {
                if (!calendar.contains(event.target)) hideTooltip();
            });
            window.addEventListener('scroll', hideTooltip, true);
            window.addEventListener('resize', hideTooltip);
        });
    }());
</script>
