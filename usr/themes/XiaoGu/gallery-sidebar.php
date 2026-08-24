<?php
$weekdays = ['日', '一', '二', '三', '四', '五', '六'];
?>

<aside class="site-sidebar gallery-sidebar" aria-label="相册侧边栏">
    <section class="sidebar-block date-card">
        <div class="date-line">
            <strong><?php echo date('d'); ?></strong>
            <span><?php echo date('Y / m'); ?></span>
        </div>
        <span class="weekday">星期<?php echo $weekdays[(int) date('w')]; ?></span>
    </section>

    <section class="sidebar-block gallery-album-block">
        <h2>相册集</h2>
        <div class="gallery-album-list" data-gallery-albums>
            <p class="gallery-album-loading">正在整理照片…</p>
        </div>
    </section>

    <p class="gallery-sidebar-note">点开照片即可全屏浏览，也可以使用方向键切换。</p>

    <?php $this->need('footer.php'); ?>
</aside>
