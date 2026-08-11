<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php $this->options->title(); ?></title>
    <link rel="stylesheet"
          href="<?php $this->options->themeUrl('style.css?v=' . filemtime(__DIR__ . '/style.css')); ?>">
    <?php $this->header(); ?>
</head>

<body>
<div class="site-shell">
    <?php $this->need('topbar.php'); ?>

    <div class="content-grid">
        <section class="main-column">
            <section class="hero" aria-label="站点横幅">
                <div class="hero-orb hero-orb-one"></div>
                <div class="hero-orb hero-orb-two"></div>
                <div class="hero-copy">
                    <span class="hero-kicker">WELCOME TO MY BLOG</span>
                    <h1><?php $this->options->title(); ?></h1>
                    <p><?php $this->options->description(); ?></p>
                </div>
            </section>

            <nav class="topic-strip" aria-label="文章标签">
                <a class="is-active" href="<?php $this->options->siteUrl(); ?>">全部</a>
                <?php \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1')->to($homeTags); ?>
                <?php while ($homeTags->next()): ?>
                    <a href="<?php $homeTags->permalink(); ?>"><?php $homeTags->name(); ?></a>
                <?php endwhile; ?>
            </nav>

            <main class="post-list" aria-label="文章列表">
                <?php while ($this->next()): ?>
                    <article class="post-card">
                        <a class="post-cover" href="<?php $this->permalink(); ?>" aria-hidden="true" tabindex="-1">
                            <span>XG</span>
                        </a>

                        <div class="post-content">
                            <h2><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h2>

                            <div class="post-meta">
                                <span><?php $this->author(); ?></span>
                                <span><?php $this->date('Y-m-d'); ?></span>
                            </div>

                            <div class="post-excerpt">
                                <?php $this->excerpt(120, '...'); ?>
                            </div>

                            <div class="post-foot">
                                <span><?php $this->commentsNum('暂无评论', '1 条评论', '%d 条评论'); ?></span>
                                <span class="post-category"><?php $this->category(' · '); ?></span>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </main>
        </section>

        <?php $this->need('sidebar.php'); ?>
    </div>

    <?php $this->need('footer.php'); ?>
</div>

<?php $this->footer(); ?>
</body>
</html>
