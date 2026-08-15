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
        const storageKey = 'xiaogu-book-open';

        if (!stage || !toggle || !panels.length) return;

        function setBookOpen(open, persist) {
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

            if (persist) {
                try {
                    window.localStorage.setItem(storageKey, open ? '1' : '0');
                } catch (error) {
                    // 本地存储不可用时仍保留当前页面内的展开状态。
                }
            }
        }

        toggle.addEventListener('click', function () {
            setBookOpen(!stage.classList.contains('is-book-open'), true);
        });

        let savedOpen = false;
        try {
            savedOpen = window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            savedOpen = false;
        }

        stage.classList.add('is-restoring-book-state');
        setBookOpen(savedOpen, false);
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                stage.classList.remove('is-restoring-book-state');
            });
        });
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
        const desktop = window.matchMedia('(min-width: 901px)');
        let endMarker = null;
        let statusText = null;
        let observer = null;
        let loading = false;
        let generation = 0;

        if (!postList) return;

        function nextLink() {
            return endMarker ? endMarker.querySelector('.post-list-next a') : null;
        }

        function finish() {
            if (!endMarker || !statusText) return;
            const next = endMarker.querySelector('.post-list-next');
            if (next) next.remove();
            statusText.textContent = '— 已经到底了 —';
            endMarker.classList.remove('is-error', 'is-fallback');
            if (observer) observer.disconnect();
        }

        async function loadNextPage() {
            const link = nextLink();
            if (loading || !link) {
                if (!link) finish();
                return;
            }

            const requestGeneration = generation;
            const requestMarker = endMarker;
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
                if (requestGeneration !== generation) return;

                Array.from(nextList.children).forEach(function (item) {
                    if (item.classList.contains('post-card')) {
                        postList.insertBefore(document.importNode(item, true), requestMarker);
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
                if (requestGeneration !== generation) return;
                statusText.textContent = '加载失败，点击重试';
                endMarker.classList.add('is-error');
            } finally {
                if (requestGeneration === generation) loading = false;
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

        function reset() {
            generation += 1;
            loading = false;
            if (observer) observer.disconnect();

            endMarker = postList.querySelector('.post-list-end');
            statusText = endMarker ? endMarker.querySelector('.post-list-end-text') : null;
            if (!endMarker || !statusText) return;

            endMarker.classList.remove('is-error', 'is-fallback');
            if ('IntersectionObserver' in window && 'fetch' in window) {
                observeEndMarker();
            } else if (nextLink()) {
                statusText.textContent = '请继续浏览下一页';
                endMarker.classList.add('is-fallback');
            }
        }

        postList.addEventListener('click', function (event) {
            if (!endMarker || !event.target.closest('.post-list-end')) return;
            if (endMarker.classList.contains('is-error')) {
                loadNextPage();
            } else if (endMarker.classList.contains('is-fallback') && nextLink()) {
                window.location.href = nextLink().href;
            }
        });

        if (typeof desktop.addEventListener === 'function') {
            desktop.addEventListener('change', reset);
        } else {
            desktop.addListener(reset);
        }

        window.XiaoGuInfiniteScroll = { reset: reset };
        reset();
    }());

    (function () {
        const topicStrip = document.querySelector('.topic-strip');
        const postList = document.querySelector('.post-list');
        const categoryList = document.querySelector('.nav-category-list');
        const categoryToggle = document.querySelector('.nav-category-toggle');
        const homeNavLink = document.querySelector('.top-nav-home');
        let requestController = null;
        let requestSequence = 0;

        if (!topicStrip || !postList || !('fetch' in window)) return;

        function updateActiveTag(nextStrip) {
            const currentLinks = topicStrip.querySelectorAll('a');
            const nextLinks = nextStrip ? nextStrip.querySelectorAll('a') : [];

            currentLinks.forEach(function (link, index) {
                const nextLink = nextLinks[index];
                link.classList.toggle('is-active', Boolean(nextLink && nextLink.classList.contains('is-active')));
            });
        }

        function updateActiveCategory(nextDocument) {
            if (!categoryList) return;

            const nextList = nextDocument.querySelector('.nav-category-list');
            const nextToggle = nextDocument.querySelector('.nav-category-toggle');
            const activeLink = nextList ? nextList.querySelector('a.is-current') : null;
            const activeUrl = activeLink ? activeLink.href : '';

            categoryList.querySelectorAll('a').forEach(function (link) {
                link.classList.toggle('is-current', activeUrl !== '' && link.href === activeUrl);
            });

            if (categoryToggle) {
                categoryToggle.classList.toggle('is-current', Boolean(nextToggle && nextToggle.classList.contains('is-current')));
            }

            if (homeNavLink) {
                const nextHomeLink = nextDocument.querySelector('.top-nav-home');
                homeNavLink.classList.toggle('is-current', Boolean(nextHomeLink && nextHomeLink.classList.contains('is-current')));
            }
        }

        function selectTagImmediately(selectedLink) {
            topicStrip.querySelectorAll('a').forEach(function (link) {
                link.classList.toggle('is-active', link === selectedLink);
            });

            if (categoryList) {
                categoryList.querySelectorAll('a').forEach(function (link) {
                    link.classList.remove('is-current');
                });
            }
            if (categoryToggle) categoryToggle.classList.remove('is-current');
            if (homeNavLink) homeNavLink.classList.toggle('is-current', selectedLink === topicStrip.querySelector('a'));
        }

        function selectCategoryImmediately(selectedLink) {
            if (!categoryList) return;

            categoryList.querySelectorAll('a').forEach(function (link) {
                link.classList.toggle('is-current', link === selectedLink);
            });
            if (categoryToggle) categoryToggle.classList.add('is-current');
            if (homeNavLink) homeNavLink.classList.remove('is-current');
            topicStrip.querySelectorAll('a').forEach(function (link) {
                link.classList.remove('is-active');
            });
        }

        async function loadFilter(url, addHistory) {
            requestSequence += 1;
            const sequence = requestSequence;

            if (requestController) requestController.abort();
            requestController = new window.AbortController();

            postList.classList.add('is-filtering');
            postList.setAttribute('aria-busy', 'true');

            try {
                const response = await window.fetch(url, {
                    credentials: 'same-origin',
                    signal: requestController.signal
                });
                if (!response.ok) throw new Error('Unable to load tag results');

                const documentText = await response.text();
                const nextDocument = new window.DOMParser().parseFromString(documentText, 'text/html');
                const nextList = nextDocument.querySelector('.post-list');
                const nextStrip = nextDocument.querySelector('.topic-strip');
                if (!nextList || !nextStrip) throw new Error('Invalid tag response');
                if (sequence !== requestSequence) return;

                const nextItems = Array.from(nextList.children).map(function (item) {
                    return document.importNode(item, true);
                });

                postList.replaceChildren.apply(postList, nextItems);
                postList.scrollTop = 0;
                postList.dispatchEvent(new Event('scroll'));
                updateActiveTag(nextStrip);
                updateActiveCategory(nextDocument);

                const nextTitle = nextDocument.querySelector('title');
                if (nextTitle) document.title = nextTitle.textContent;
                if (addHistory) window.history.pushState({ xiaoGuFilter: true }, '', url);

                if (window.XiaoGuInfiniteScroll) window.XiaoGuInfiniteScroll.reset();
            } catch (error) {
                if (error.name === 'AbortError') return;
                window.location.href = url;
            } finally {
                if (sequence === requestSequence) {
                    postList.classList.remove('is-filtering');
                    postList.removeAttribute('aria-busy');
                }
            }
        }

        topicStrip.addEventListener('click', function (event) {
            const link = event.target.closest('a');
            if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            if (new URL(link.href).origin !== window.location.origin) return;

            event.preventDefault();
            if (!link.classList.contains('is-active')) {
                selectTagImmediately(link);
                loadFilter(link.href, true);
            }
        });

        if (categoryList) {
            categoryList.addEventListener('click', function (event) {
                const link = event.target.closest('a');
                if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                if (new URL(link.href).origin !== window.location.origin) return;

                event.preventDefault();
                if (!link.classList.contains('is-current')) {
                    selectCategoryImmediately(link);
                    loadFilter(link.href, true);
                }
            });
        }

        window.addEventListener('popstate', function () {
            loadFilter(window.location.href, false);
        });
    }());
</script>

<?php $this->footer(); ?>
</body>
</html>
