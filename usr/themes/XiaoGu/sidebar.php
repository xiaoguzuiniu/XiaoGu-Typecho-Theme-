<?php
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
$activityCalendar = getSiteActivityCalendar();
$healthRecord = null;

try {
    require_once __TYPECHO_ROOT_DIR__ . '/api/health.php';
    $healthRecord = xiaoguHealthFindByDate(\Typecho\Db::get(), date('Y-m-d'));
} catch (\Throwable $error) {
    // 首次同步前数据表可能尚未创建，侧边栏继续以未同步状态展示。
    $healthRecord = null;
}

$healthSteps = $healthRecord ? (int) $healthRecord['steps'] : 0;
$healthEnergy = $healthRecord ? (float) $healthRecord['active_energy'] : 0.0;
$healthEnergyDisplay = rtrim(rtrim(number_format($healthEnergy, 2, '.', ','), '0'), '.');
$healthSyncTime = $healthRecord ? substr((string) $healthRecord['update_time'], 0, 5) : '未同步';
$hopeGames = getXiaoGuHopeGames((string) $this->options->hopeGames);
?>

<aside class="site-sidebar" aria-label="站点侧边栏">
    <section class="sidebar-block date-card">
        <div class="date-line">
            <strong><?php echo date('d'); ?></strong>
            <span><?php echo date('Y / m'); ?></span>
        </div>
        <span class="weekday">星期<?php echo $weekdays[(int) date('w')]; ?></span>
    </section>

    <section class="sidebar-block hope-block" aria-label="我的盼头">
        <div class="hope-heading">
            <h2>我的盼头</h2>
        </div>
        <?php if ($hopeGames): ?>
            <div class="hope-games" tabindex="0" aria-label="关注的篮球比赛">
                <?php foreach ($hopeGames as $game): ?>
                    <article class="hope-game">
                        <div class="hope-team">
                            <?php if ($game['awayLogo'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($game['awayLogo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                            <?php else: ?><span class="hope-logo-fallback" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($game['away'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            <strong><?php echo htmlspecialchars($game['away'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small>客</small>
                        </div>
                        <div class="hope-game-time">
                            <span><?php echo htmlspecialchars($game['league'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <time datetime="<?php echo htmlspecialchars($game['datetime'], ENT_QUOTES, 'UTF-8'); ?>">
                                <small><?php echo date('m月d日', $game['timestamp']); ?></small>
                                <strong><?php echo date('H:i', $game['timestamp']); ?></strong>
                            </time>
                            <em><?php echo htmlspecialchars($game['status'], ENT_QUOTES, 'UTF-8'); ?></em>
                        </div>
                        <div class="hope-team">
                            <?php if ($game['homeLogo'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($game['homeLogo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                            <?php else: ?><span class="hope-logo-fallback" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($game['home'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                            <strong><?php echo htmlspecialchars($game['home'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small>主场</small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="hope-empty">赛程尚未添加</div>
        <?php endif; ?>
    </section>

    <section class="sidebar-block health-summary" aria-label="今日健康数据">
        <div class="health-summary-head">
            <h2>今日健康</h2>
            <div class="health-sync-time" aria-label="<?php echo $healthRecord ? '本次同步时间 ' . htmlspecialchars($healthSyncTime, ENT_QUOTES, 'UTF-8') : '今日健康数据尚未同步'; ?>">
                <?php if ($healthRecord): ?>
                    <time datetime="<?php echo htmlspecialchars((string) $healthRecord['date'] . 'T' . (string) $healthRecord['update_time'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($healthSyncTime, ENT_QUOTES, 'UTF-8'); ?></time>
                <?php else: ?>
                    <span>未同步</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="health-metrics">
            <div class="health-metric">
                <div class="health-metric-label"><span aria-hidden="true">🚶</span>今日步数</div>
                <p><strong><?php echo number_format($healthSteps); ?></strong><span>步</span></p>
            </div>
            <div class="health-metric">
                <div class="health-metric-label"><span aria-hidden="true">🔥</span>活动消耗</div>
                <p><strong><?php echo htmlspecialchars($healthEnergyDisplay, ENT_QUOTES, 'UTF-8'); ?></strong><span>kcal</span></p>
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
        document.querySelectorAll('.hope-games').forEach(function (games) {
            let startX = 0;
            let startScrollLeft = 0;
            let currentX = 0;
            let dragging = false;
            let animationFrame = 0;
            const cards = Array.from(games.querySelectorAll('.hope-game'));
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            function updateCardMotion() {
                const width = Math.max(games.clientWidth, 1);
                cards.forEach(function (card, index) {
                    const distance = Math.min(1, Math.abs(index - games.scrollLeft / width));
                    card.style.opacity = String(1 - distance * .28);
                    card.style.transform = 'scale(' + (1 - distance * .035) + ')';
                });
            }

            function animateToPage(page) {
                window.cancelAnimationFrame(animationFrame);
                const maxPage = Math.max(0, cards.length - 1);
                const targetPage = Math.max(0, Math.min(page, maxPage));
                const from = games.scrollLeft;
                const target = targetPage * games.clientWidth;
                const distance = target - from;
                const duration = reduceMotion ? 0 : 560;

                if (!duration || Math.abs(distance) < 1) {
                    games.scrollLeft = target;
                    games.classList.remove('is-settling');
                    updateCardMotion();
                    return;
                }

                games.classList.add('is-settling');
                const startedAt = performance.now();
                function step(now) {
                    const progress = Math.min(1, (now - startedAt) / duration);
                    const eased = 1 - Math.pow(1 - progress, 4);
                    games.scrollLeft = from + distance * eased;
                    updateCardMotion();
                    if (progress < 1) {
                        animationFrame = window.requestAnimationFrame(step);
                    } else {
                        games.classList.remove('is-settling');
                    }
                }
                animationFrame = window.requestAnimationFrame(step);
            }

            games.addEventListener('pointerdown', function (event) {
                if (event.pointerType === 'mouse' && event.button !== 0) return;
                window.cancelAnimationFrame(animationFrame);
                games.classList.remove('is-settling');
                startX = event.clientX;
                currentX = event.clientX;
                startScrollLeft = games.scrollLeft;
                dragging = true;
                games.classList.add('is-dragging');
                games.setPointerCapture(event.pointerId);
            });

            games.addEventListener('pointermove', function (event) {
                if (!dragging) return;
                currentX = event.clientX;
                games.scrollLeft = startScrollLeft - (event.clientX - startX) * .7;
                updateCardMotion();
            });

            function finishDrag(event) {
                if (!dragging) return;
                dragging = false;
                games.classList.remove('is-dragging');
                if (games.hasPointerCapture(event.pointerId)) {
                    games.releasePointerCapture(event.pointerId);
                }
                const startPage = Math.round(startScrollLeft / Math.max(games.clientWidth, 1));
                const dragDistance = startX - currentX;
                const threshold = Math.min(72, games.clientWidth * .22);
                const targetPage = Math.abs(dragDistance) < threshold
                    ? startPage
                    : startPage + (dragDistance > 0 ? 1 : -1);
                animateToPage(targetPage);
            }

            games.addEventListener('pointerup', finishDrag);
            games.addEventListener('pointercancel', finishDrag);
            games.addEventListener('keydown', function (event) {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                event.preventDefault();
                const direction = event.key === 'ArrowRight' ? 1 : -1;
                const currentPage = Math.round(games.scrollLeft / Math.max(games.clientWidth, 1));
                animateToPage(currentPage + direction);
            });

            games.addEventListener('scroll', function () {
                if (!dragging && !games.classList.contains('is-settling')) updateCardMotion();
            }, {passive: true});
            window.addEventListener('resize', function () {
                animateToPage(Math.round(games.scrollLeft / Math.max(games.clientWidth, 1)));
            });
            updateCardMotion();
        });

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
