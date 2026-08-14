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
<div class="book-stage" id="book-stage">
    <?php $this->need('book-panels.php'); ?>

    <div class="site-shell">
        <?php $this->need('topbar.php'); ?>

        <div class="content-grid">
            <section class="main-column">
                <div class="home-intro">
                    <div class="home-intro-content">
                        <section class="hero" aria-label="站点横幅">
                            <div class="hero-profile">
                                <span class="hero-avatar" aria-hidden="true">X</span>
                                <div class="hero-copy">
                                    <h1><?php $this->options->title(); ?></h1>
                                </div>
                            </div>
                        </section>

                        <div class="hero-bio">
                            <p><?php $this->options->description(); ?></p>
                        </div>

                        <nav class="topic-strip" aria-label="文章标签">
                            <a class="is-active" href="<?php $this->options->siteUrl(); ?>">全部</a>
                            <?php \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1')->to($homeTags); ?>
                            <?php while ($homeTags->next()): ?>
                                <a href="<?php $homeTags->permalink(); ?>"><?php $homeTags->name(); ?></a>
                            <?php endwhile; ?>
                        </nav>
                    </div>
                </div>

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
    </div>

    <button class="book-toggle" id="book-toggle" type="button"
            aria-controls="book-panel-left book-panel-right" aria-expanded="false">
        <span class="book-toggle-icon" aria-hidden="true">⇆</span>
        <span class="book-toggle-label">展开两侧书页</span>
    </button>
</div>

<script>
    (function () {
        const stage = document.getElementById('book-stage');
        const toggle = document.getElementById('book-toggle');
        const panels = stage ? stage.querySelectorAll('.book-panel') : [];

        if (!stage || !toggle || !panels.length) return;

        function setBookOpen(open) {
            stage.classList.toggle('is-book-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            toggle.querySelector('.book-toggle-label').textContent = open ? '收起两侧书页' : '展开两侧书页';

            panels.forEach(function (panel) {
                panel.setAttribute('aria-hidden', String(!open));
                if (open) {
                    panel.removeAttribute('inert');
                } else {
                    panel.setAttribute('inert', '');
                }
            });
        }

        toggle.addEventListener('click', function () {
            setBookOpen(!stage.classList.contains('is-book-open'));
        });

        setBookOpen(false);
    }());

    (function () {
        const mainColumn = document.querySelector('.main-column');
        const intro = mainColumn ? mainColumn.querySelector('.home-intro') : null;
        const introContent = intro ? intro.querySelector('.home-intro-content') : null;
        const hero = intro ? intro.querySelector('.hero') : null;
        const postList = mainColumn ? mainColumn.querySelector('.post-list') : null;
        const desktop = window.matchMedia('(min-width: 901px)');
        let expandedHeight = 0;
        let maxCollapse = 0;
        let frame = 0;

        if (!intro || !introContent || !hero || !postList) return;

        function render() {
            frame = 0;

            if (!desktop.matches) {
                intro.style.removeProperty('height');
                introContent.style.removeProperty('transform');
                return;
            }

            const collapse = Math.min(postList.scrollTop, maxCollapse);
            intro.style.height = Math.max(0, expandedHeight - collapse) + 'px';
            introContent.style.transform = 'translate3d(0, ' + (-collapse) + 'px, 0)';
        }

        function requestRender() {
            if (!frame) frame = window.requestAnimationFrame(render);
        }

        function measure() {
            if (!desktop.matches) {
                render();
                return;
            }

            expandedHeight = introContent.offsetHeight;
            maxCollapse = Math.min(180, Math.max(0, hero.offsetHeight - 110));
            render();
        }

        postList.addEventListener('scroll', requestRender, { passive: true });
        window.addEventListener('resize', function () {
            window.requestAnimationFrame(measure);
        });

        if (typeof desktop.addEventListener === 'function') {
            desktop.addEventListener('change', measure);
        } else {
            desktop.addListener(measure);
        }

        window.requestAnimationFrame(measure);
    }());
</script>

<?php $this->footer(); ?>
</body>
</html>
