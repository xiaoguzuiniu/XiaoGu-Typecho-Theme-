<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$profileName = trim((string) $this->options->profileName);
$profileSignature = trim((string) $this->options->profileSignature);
$profileAvatarUrl = trim((string) $this->options->profileAvatarUrl);
$heroImageUrl = trim((string) $this->options->heroImageUrl);
$browserTitle = trim((string) $this->options->browserTitle);

if ($profileName === '') {
    $profileName = (string) $this->options->title;
}

if ($profileSignature === '') {
    $profileSignature = (string) $this->options->description;
}

if ($browserTitle === '') {
    $browserTitle = (string) $this->options->title;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($browserTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/svg+xml"
          href="<?php $this->options->themeUrl('assets/favicon.svg?v=' . filemtime(__DIR__ . '/assets/favicon.svg')); ?>">
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
                            <?php if ($heroImageUrl !== ''): ?>
                                <img class="hero-image" src="<?php echo htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="" decoding="async">
                            <?php else: ?>
                                <img class="hero-image" src="<?php $this->options->themeUrl('assets/mountain-hero.jpg'); ?>"
                                     alt="" decoding="async">
                            <?php endif; ?>
                            <div class="hero-profile">
                                <?php if ($profileAvatarUrl !== ''): ?>
                                    <img class="hero-avatar hero-avatar-image"
                                         src="<?php echo htmlspecialchars($profileAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="" decoding="async">
                                <?php else: ?>
                                    <span class="hero-avatar" aria-hidden="true">X</span>
                                <?php endif; ?>
                                <div class="hero-copy">
                                    <h1><?php echo htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8'); ?></h1>
                                </div>
                            </div>
                        </section>

                        <div class="hero-bio">
                            <p><?php echo htmlspecialchars($profileSignature, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <nav class="topic-strip" aria-label="文章标签">
                            <a<?php if ($this->is('index')): ?> class="is-active"<?php endif; ?>
                                href="<?php $this->options->siteUrl(); ?>">全部</a>
                            <?php \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1')->to($homeTags); ?>
                            <?php while ($homeTags->next()): ?>
                                <a<?php if ($this->is('tag', $homeTags->slug)): ?> class="is-active"<?php endif; ?>
                                    href="<?php $homeTags->permalink(); ?>"><?php $homeTags->name(); ?></a>
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

                    <div class="post-list-end" role="status" aria-live="polite">
                        <span class="post-list-end-text">
                        <?php if ($this->getCurrentPage() >= $this->getTotalPage()): ?>
                            — 已经到底了 —
                        <?php else: ?>
                            加载更多文章…
                        <?php endif; ?>
                        </span>
                        <?php if ($this->getCurrentPage() < $this->getTotalPage()): ?>
                            <span class="post-list-next" aria-hidden="true"><?php $this->pageLink('下一页', 'next'); ?></span>
                        <?php endif; ?>
                    </div>
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
        mainColumn.addEventListener('wheel', function (event) {
            if (!desktop.matches || postList.contains(event.target) || event.ctrlKey) return;
            if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;

            let delta = event.deltaY;
            if (event.deltaMode === 1) {
                delta *= 16;
            } else if (event.deltaMode === 2) {
                delta *= postList.clientHeight;
            }

            const previousScrollTop = postList.scrollTop;
            postList.scrollTop += delta;

            if (postList.scrollTop !== previousScrollTop) {
                event.preventDefault();
            }
        }, { passive: false });

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

    (function () {
        const postList = document.querySelector('.post-list');
        const endMarker = postList ? postList.querySelector('.post-list-end') : null;
        const statusText = endMarker ? endMarker.querySelector('.post-list-end-text') : null;
        const desktop = window.matchMedia('(min-width: 901px)');
        let observer = null;
        let loading = false;

        if (!postList || !endMarker || !statusText) return;

        function nextLink() {
            return endMarker.querySelector('.post-list-next a');
        }

        function finish() {
            const next = endMarker.querySelector('.post-list-next');
            if (next) next.remove();
            statusText.textContent = '— 已经到底了 —';
            endMarker.classList.remove('is-error');
            if (observer) observer.disconnect();
        }

        async function loadNextPage() {
            const link = nextLink();
            if (loading || !link) {
                if (!link) finish();
                return;
            }

            loading = true;
            endMarker.classList.remove('is-error');
            statusText.textContent = '加载更多文章…';

            try {
                const response = await window.fetch(link.href, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('Unable to load the next page');

                const documentText = await response.text();
                const nextDocument = new window.DOMParser().parseFromString(documentText, 'text/html');
                const nextList = nextDocument.querySelector('.post-list');
                const nextMarker = nextList ? nextList.querySelector('.post-list-end') : null;

                if (!nextList || !nextMarker) throw new Error('Invalid article list response');

                Array.from(nextList.children).forEach(function (item) {
                    if (item.classList.contains('post-card')) {
                        postList.insertBefore(document.importNode(item, true), endMarker);
                    }
                });

                const followingLink = nextMarker.querySelector('.post-list-next a');
                if (followingLink) {
                    link.href = followingLink.href;
                    statusText.textContent = '继续向下滚动';
                } else {
                    finish();
                }
            } catch (error) {
                statusText.textContent = '加载失败，点击重试';
                endMarker.classList.add('is-error');
            } finally {
                loading = false;
            }
        }

        function observeEndMarker() {
            if (observer) observer.disconnect();
            if (!nextLink()) {
                finish();
                return;
            }

            observer = new window.IntersectionObserver(function (entries) {
                if (entries[0] && entries[0].isIntersecting) loadNextPage();
            }, {
                root: desktop.matches ? postList : null,
                rootMargin: '0px 0px 160px 0px',
                threshold: 0.01
            });
            observer.observe(endMarker);
        }

        endMarker.addEventListener('click', function () {
            if (endMarker.classList.contains('is-error')) loadNextPage();
        });

        if (typeof desktop.addEventListener === 'function') {
            desktop.addEventListener('change', observeEndMarker);
        } else {
            desktop.addListener(observeEndMarker);
        }

        if ('IntersectionObserver' in window && 'fetch' in window) {
            observeEndMarker();
        } else if (nextLink()) {
            statusText.textContent = '请继续浏览下一页';
            endMarker.classList.add('is-error');
            endMarker.addEventListener('click', function () {
                window.location.href = nextLink().href;
            });
        }
    }());
</script>

<?php $this->footer(); ?>
</body>
</html>
