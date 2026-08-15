<?php
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
\Widget\Stat::alloc()->to($stat);
?>

<aside class="site-sidebar" aria-label="站点侧边栏">
    <section class="sidebar-block date-card">
        <div class="date-line">
            <strong><?php echo date('d'); ?></strong>
            <span><?php echo date('Y / m'); ?></span>
        </div>
        <span class="weekday">星期<?php echo $weekdays[(int) date('w')]; ?></span>
        <p>认真记录每一段生活，也认真收藏每一次成长。</p>
    </section>

    <section class="sidebar-block">
        <h2>About Site</h2>
        <p class="about-copy"><?php $this->options->description(); ?></p>
        <ul class="site-links">
            <li><a href="<?php $this->options->feedUrl(); ?>">◌ RSS 订阅</a></li>
            <li><a href="<?php $this->options->siteUrl(); ?>">⌂ <?php $this->options->title(); ?></a></li>
            <li><a href="<?php $this->options->adminUrl(); ?>">◇ 管理站点</a></li>
        </ul>
    </section>

    <section class="sidebar-block">
        <h2>站点统计</h2>
        <div class="stat-grid">
            <div><strong><?php echo $stat->publishedPostsNum; ?></strong><span>文章</span></div>
            <div><strong><?php echo $stat->categoriesNum; ?></strong><span>分类</span></div>
            <div><strong><?php echo $stat->tagsNum; ?></strong><span>标签</span></div>
        </div>
    </section>

    <section class="sidebar-block recent-block">
        <h2>最近文章</h2>
        <ol class="recent-list">
            <?php \Widget\Contents\Post\Recent::alloc('pageSize=5')->to($recentPosts); ?>
            <?php while ($recentPosts->next()): ?>
                <?php $recentDisplayMode = (string) $recentPosts->fields->displayMode; ?>
                <li>
                    <span><?php echo str_pad((string) $recentPosts->sequence, 2, '0', STR_PAD_LEFT); ?></span>
                    <?php if ($recentDisplayMode === 'moment'): ?>
                        <span class="recent-moment-title"><?php $recentPosts->title(); ?></span>
                    <?php else: ?>
                        <a href="<?php $recentPosts->permalink(); ?>"><?php $recentPosts->title(); ?></a>
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ol>
    </section>

    <?php $this->need('footer.php'); ?>
</aside>
