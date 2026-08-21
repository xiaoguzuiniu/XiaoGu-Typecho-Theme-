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

                <svg class="moment-icon-sprite" aria-hidden="true">
                    <symbol id="moment-icon-view" viewBox="0 0 1024 1024">
                        <path d="M512 280.901818c171.752727 0 328.145455 167.098182 387.956364 238.545455C839.68 591.127273 683.287273 757.992727 512 757.992727S183.389091 591.127273 123.578182 519.447273C183.389091 448 339.781818 280.901818 512 280.901818zM512 209.454545C298.356364 209.454545 117.76 412.858182 56.785455 490.123636a46.545455 46.545455 0 0 0 0 58.647273C117.76 626.036364 298.356364 829.44 512 829.44s393.309091-203.403636 454.283636-280.669091a46.545455 46.545455 0 0 0 0-58.647273C905.309091 412.858182 724.712727 209.454545 512 209.454545z m0 238.545455a71.68 71.68 0 1 1-69.818182 71.447273 69.818182 69.818182 0 0 1 69.818182-71.447273z m0-71.68A139.636364 139.636364 0 0 0 382.603636 465.454545a146.385455 146.385455 0 0 0 30.254546 155.927273 137.076364 137.076364 0 0 0 151.970909 30.254546A143.127273 143.127273 0 0 0 651.636364 519.447273a141.265455 141.265455 0 0 0-139.636364-143.127273z"/>
                    </symbol>
                    <symbol id="moment-icon-comment" viewBox="0 0 1024 1024">
                        <path d="M405.97 530.4m-40.81 0a40.81 40.81 0 1 0 81.62 0 40.81 40.81 0 1 0-81.62 0Z"/>
                        <path d="M618.04 530.4m-40.81 0a40.81 40.81 0 1 0 81.62 0 40.81 40.81 0 1 0-81.62 0Z"/>
                        <path d="M512.01 959.33c-70.48 0-140.41-16.79-202.89-48.62H93.23V669.25c-18.96-50.4-28.56-103.26-28.56-157.26 0-246.66 200.68-447.32 447.34-447.32s447.32 200.66 447.32 447.32-200.66 447.34-447.32 447.34zM166.85 837.09h160.56l8.16 4.39c53.89 28.94 114.89 44.23 176.43 44.23 206.06 0 373.7-167.65 373.7-373.72 0-206.06-167.65-373.7-373.7-373.7-206.07 0-373.72 167.65-373.72 373.7 0 47.09 8.75 93.16 25.99 136.91l2.57 6.51v181.68z"/>
                    </symbol>
                    <symbol id="moment-icon-like" viewBox="0 0 1024 1024">
                        <path d="M886.777 610.948c-94.827 148.789-331.909 298.14-342.099 305.854-9.679 7.665-20.911 11.278-32.118 11.278-11.208 0-22.439-3.613-32.119-11.278-10.189-7.715-247.805-156.576-342.123-305.854C105.69 559.635 65.42 495.505 65.42 404.693c0-181.525 112.683-276.537 251.346-276.537 76.998 0 148.877 38.986 195.793 103.116 47.402-64.13 118.796-103.116 195.769-103.116 138.688 0 251.371 99.161 251.371 276.537 0.001 90.812-40.293 154.942-72.922 206.255zM711.289 189.151c-169.109 0-198.729 193.981-198.729 193.981S482.2 189.151 313.832 189.151c-109.747 0-186.729 78.543-186.729 188.981 0 8.495 0.704 16.796 1.723 24.998h-1.723s-5.744 102.949 91.364 218.978C319.99 743.435 512.56 867.085 512.56 867.085s183.281-115.352 290.093-242.977c90.583-108.241 95.364-220.978 95.364-220.978h-1.723c1.02-8.202 1.723-16.503 1.723-24.998 0.001-110.438-76.981-188.981-186.728-188.981z"/>
                    </symbol>
                </svg>

                <main class="post-list" aria-label="文章列表">
                    <?php while ($this->next()): ?>
                        <?php recordPostView($this); ?>
                        <?php $displayMode = (string) $this->fields->displayMode; ?>
                        <?php if ($displayMode === 'moment'): ?>
                            <?php \Widget\Comments\Archive::allocWithAlias(
                                'moment-comments-' . (int) $this->cid,
                                [
                                    'parentId' => (int) $this->cid,
                                    'parentContent' => $this,
                                    'respondId' => 'moment-respond-' . (int) $this->cid,
                                    'commentPage' => 0,
                                    'allowComment' => $this->allow('comment')
                                ]
                            )->to($momentComments); ?>
                            <article class="moment-card">
                                <header class="moment-header">
                                    <?php if ($profileAvatarUrl !== ''): ?>
                                        <img class="moment-avatar" src="<?php echo htmlspecialchars($profileAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                             alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span class="moment-avatar moment-avatar-fallback" aria-hidden="true">X</span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php $this->author(); ?></strong>
                                        <time datetime="<?php $this->date('c'); ?>"><?php $this->date('Y-m-d H:i'); ?></time>
                                    </div>
                                </header>

                                <div class="moment-content">
                                    <?php echo $this->content; ?>
                                </div>

                                <footer class="moment-foot">
                                    <div class="moment-stats" aria-label="动态数据">
                                        <span title="浏览量"><svg class="moment-stat-icon" aria-hidden="true"><use href="#moment-icon-view"></use></svg><b><?php echo getPostViews($this); ?></b></span>
                                        <span class="moment-comment-toggle" title="评论" data-moment-comment-toggle
                                              role="button" tabindex="0">
                                            <svg class="moment-stat-icon" aria-hidden="true"><use href="#moment-icon-comment"></use></svg>
                                            <b><?php $this->commentsNum('0', '1', '%d'); ?></b>
                                        </span>
                                        <span class="moment-like<?php echo isPostLiked($this->cid) ? ' is-liked' : ''; ?>" title="点赞" data-xiaogu-like="<?php $this->cid(); ?>" role="button" tabindex="0">
                                            <svg class="moment-stat-icon" aria-hidden="true"><use href="#moment-icon-like"></use></svg>
                                            <b data-xiaogu-like-count="<?php $this->cid(); ?>"><?php echo getPostLikes($this); ?></b>
                                        </span>
                                    </div>
                                    <?php if (!empty($this->tags)): ?>
                                        <span class="moment-tags"><?php $this->tags(' ', true); ?></span>
                                    <?php endif; ?>
                                </footer>

                                <?php if ($momentComments->have()): ?>
                                    <div class="moment-social">
                                        <div class="moment-comments">
                                            <?php $momentComments->listComments([
                                                'before' => '<ul class="moment-comment-list">',
                                                'after' => '</ul>',
                                                'avatarSize' => 0,
                                                'dateFormat' => 'm-d H:i'
                                            ]); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($this->allow('comment')): ?>
                                    <form method="post"
                                          action="<?php echo htmlspecialchars(rtrim($this->options->siteUrl, '/') . '/?xiaogu_action=moment_comment&cid=' . (int) $this->cid, ENT_QUOTES, 'UTF-8'); ?>"
                                          class="moment-comment-form" hidden>
                                        <?php if (!$this->user->hasLogin()): ?>
                                            <div class="moment-comment-identity">
                                                <input type="text" name="author" placeholder="昵称"
                                                       value="<?php $this->remember('author'); ?>" required>
                                                <input type="email" name="mail" placeholder="邮箱"
                                                       value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                                                <input type="url" name="url" placeholder="网址"
                                                       value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?>>
                                            </div>
                                        <?php endif; ?>
                                        <div class="moment-comment-compose">
                                            <textarea name="text" rows="1" placeholder="评论" required></textarea>
                                            <button type="button" class="moment-comment-cancel" hidden>取消回复</button>
                                            <button type="submit">发送</button>
                                        </div>
                                        <span class="moment-comment-status" role="status"></span>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php else: ?>
                            <?php $postCoverUrl = getPostCover($this); ?>
                            <article class="post-card">
                                <a class="post-cover" href="<?php $this->permalink(); ?>" aria-hidden="true" tabindex="-1">
                                    <?php if ($postCoverUrl !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($postCoverUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                                    <?php else: ?>
                                        <span>XG</span>
                                    <?php endif; ?>
                                </a>

                                <div class="post-content">
                                    <h2><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h2>

                                    <div class="post-meta">
                                        <span class="post-author">@<?php $this->author(); ?></span>
                                    </div>

                                    <div class="post-excerpt">
                                        <?php $this->excerpt(120, '...'); ?>
                                    </div>

                                    <div class="post-foot">
                                        <div class="post-stats" aria-label="文章数据">
                                            <span title="浏览量"><svg class="post-stat-icon" aria-hidden="true"><use href="#moment-icon-view"></use></svg><b><?php echo getPostViews($this); ?></b></span>
                                            <span title="评论数"><svg class="post-stat-icon" aria-hidden="true"><use href="#moment-icon-comment"></use></svg><b><?php $this->commentsNum('0', '1', '%d'); ?></b></span>
                                            <span class="post-like<?php echo isPostLiked($this->cid) ? ' is-liked' : ''; ?>" title="点赞" data-xiaogu-like="<?php $this->cid(); ?>" role="button" tabindex="0">
                                                <svg class="post-stat-icon" aria-hidden="true"><use href="#moment-icon-like"></use></svg>
                                                <b data-xiaogu-like-count="<?php $this->cid(); ?>"><?php echo getPostLikes($this); ?></b>
                                            </span>
                                        </div>
                                        <?php if (!empty($this->tags)): ?>
                                            <span class="post-tags"><?php $this->tags(' ', true); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endif; ?>
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

</div>

<script>
    (function () {
        const mainColumn = document.querySelector('.main-column');
        const intro = mainColumn ? mainColumn.querySelector('.home-intro') : null;
        const introContent = intro ? intro.querySelector('.home-intro-content') : null;
        const hero = intro ? intro.querySelector('.hero') : null;
        const postList = mainColumn ? mainColumn.querySelector('.post-list') : null;
        const desktop = window.matchMedia('(min-width: 901px)');
        let expandedHeight = 0;
        let maxCollapse = 0;
        let collapse = 0;
        let frame = 0;

        if (!mainColumn || !intro || !introContent || !hero || !postList) return;

        function render() {
            frame = 0;

            if (!desktop.matches) {
                intro.style.removeProperty('height');
                introContent.style.removeProperty('transform');
                return;
            }

            intro.style.height = Math.max(0, expandedHeight - collapse) + 'px';
            introContent.style.transform = 'translate3d(0, ' + (-collapse) + 'px, 0)';
        }

        function requestRender() {
            if (!frame) frame = window.requestAnimationFrame(render);
        }

        function measure() {
            if (!desktop.matches) {
                collapse = 0;
                render();
                return;
            }

            expandedHeight = introContent.offsetHeight;
            maxCollapse = Math.min(180, Math.max(0, hero.offsetHeight - 110));
            collapse = Math.min(collapse, maxCollapse);
            render();
        }

        mainColumn.addEventListener('wheel', function (event) {
            if (!desktop.matches || event.ctrlKey) return;
            if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;

            let delta = event.deltaY;
            if (event.deltaMode === 1) {
                delta *= 16;
            } else if (event.deltaMode === 2) {
                delta *= postList.clientHeight;
            }

            if (delta > 0 && collapse < maxCollapse) {
                collapse = Math.min(maxCollapse, collapse + delta);
                requestRender();
                event.preventDefault();
                return;
            }

            if (delta < 0 && postList.scrollTop <= 0 && collapse > 0) {
                collapse = Math.max(0, collapse + delta);
                requestRender();
                event.preventDefault();
                return;
            }

            const previousScrollTop = postList.scrollTop;
            postList.scrollTop += delta;
            if (postList.scrollTop !== previousScrollTop) {
                event.preventDefault();
            }
        }, { passive: false });

        postList.addEventListener('scroll', function (event) {
            if (!event.isTrusted && postList.scrollTop === 0 && collapse !== 0) {
                collapse = 0;
                requestRender();
            }
        }, { passive: true });

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
        function enhanceMoments(root) {
            const scope = root || document;

            scope.querySelectorAll('.moment-content:not([data-gallery-ready])').forEach(function (content) {
                content.setAttribute('data-gallery-ready', 'true');
                const images = Array.from(content.querySelectorAll('img'));
                if (!images.length) return;

                const gallery = document.createElement('div');
                gallery.className = 'moment-gallery is-count-' + images.length;
                const movedItems = [];

                images.forEach(function (image) {
                    image.loading = 'lazy';
                    image.decoding = 'async';

                    const imageLink = image.parentElement && image.parentElement.tagName === 'A'
                        ? image.parentElement
                        : null;
                    const item = imageLink || image;
                    if (movedItems.indexOf(item) !== -1) return;

                    const oldParent = item.parentElement;
                    movedItems.push(item);
                    gallery.appendChild(item);

                    if (oldParent && oldParent.tagName === 'P' && oldParent.textContent.trim() === '' && !oldParent.children.length) {
                        oldParent.remove();
                    }
                });

                content.appendChild(gallery);
            });

            scope.querySelectorAll('.moment-card:not([data-comments-ready])').forEach(function (card) {
                card.setAttribute('data-comments-ready', 'true');
                card.querySelectorAll('.moment-comment-list .comment-child').forEach(function (comment) {
                    const children = comment.closest('.comment-children');
                    const parentComment = children ? children.closest('.comment-body') : null;
                    const parentAuthor = parentComment
                        ? parentComment.querySelector(':scope > .comment-author .fn')
                        : null;
                    const author = comment.querySelector(':scope > .comment-author');
                    if (!parentAuthor || !author || author.nextElementSibling?.classList.contains('moment-reply-label')) return;

                    const label = document.createElement('span');
                    const name = document.createElement('b');
                    label.className = 'moment-reply-label';
                    label.append(' 回复 ');
                    name.textContent = parentAuthor.textContent.trim();
                    label.append(name, '：');
                    author.after(label);
                });
            });
        }

        window.XiaoGuEnhanceMoments = enhanceMoments;
        enhanceMoments(document);
    }());

    (function () {
        const commentRequestReferrer = <?php echo json_encode($this->request->getRequestUrl()); ?>;
        const commentSecurityToken = <?php echo json_encode($this->security->getToken($this->request->getRequestUrl())); ?>;

        function openCommentForm(card, comment) {
            const form = card.querySelector('.moment-comment-form');
            if (!form) return;

            const textarea = form.querySelector('textarea[name="text"]');
            const cancel = form.querySelector('.moment-comment-cancel');
            let parent = form.querySelector('input[name="parent"]');

            if (comment) {
                const coid = comment.id.replace(/^comment-/, '');
                const author = comment.querySelector(':scope > .comment-author .fn');
                if (!parent) {
                    parent = document.createElement('input');
                    parent.type = 'hidden';
                    parent.name = 'parent';
                    form.appendChild(parent);
                }
                parent.value = coid;
                textarea.placeholder = author ? '回复 ' + author.textContent.trim() : '回复';
                cancel.hidden = false;
            } else {
                if (parent) parent.remove();
                textarea.placeholder = '评论';
                cancel.hidden = true;
            }

            form.hidden = false;
            textarea.focus();
        }

        function closeCommentForm(form) {
            const parent = form.querySelector('input[name="parent"]');
            const textarea = form.querySelector('textarea[name="text"]');
            const cancel = form.querySelector('.moment-comment-cancel');
            const status = form.querySelector('.moment-comment-status');

            if (parent) parent.remove();
            if (textarea) {
                textarea.placeholder = '评论';
                textarea.blur();
            }
            if (cancel) cancel.hidden = true;
            if (status) status.textContent = '';
            form.hidden = true;
        }

        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('[data-moment-comment-toggle]');
            if (toggle) {
                event.preventDefault();
                const card = toggle.closest('.moment-card');
                const form = card && card.querySelector('.moment-comment-form');
                if (!form) return;
                if (form.hidden) openCommentForm(card, null);
                else closeCommentForm(form);
                return;
            }

            const comment = event.target.closest('.moment-comments .comment-body');
            if (comment && !event.target.closest('a')) {
                openCommentForm(comment.closest('.moment-card'), comment);
                return;
            }

            const cancel = event.target.closest('.moment-comment-cancel');
            if (cancel) {
                event.preventDefault();
                closeCommentForm(cancel.closest('.moment-comment-form'));
            }
        });

        document.addEventListener('keydown', function (event) {
            const toggle = event.target.closest('[data-moment-comment-toggle]');
            if (toggle && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                toggle.click();
            }
        });

        document.addEventListener('submit', async function (event) {
            const form = event.target.closest('.moment-comment-form');
            if (!form || !('fetch' in window)) return;

            event.preventDefault();
            const submit = form.querySelector('button[type="submit"]');
            const status = form.querySelector('.moment-comment-status');
            submit.disabled = true;
            status.textContent = '发送中…';

            try {
                const formData = new FormData(form);
                formData.set('_', commentSecurityToken);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    referrer: commentRequestReferrer
                });
                let data;
                try {
                    data = await response.json();
                } catch (error) {
                    throw new Error('服务器返回异常，请稍后重试');
                }
                if (!response.ok || !data.success) {
                    throw new Error(data.message || '评论发送失败');
                }

                if (data.status === 'approved') {
                    window.location.reload();
                    return;
                }

                form.querySelector('textarea[name="text"]').value = '';
                status.textContent = data.message;
                submit.disabled = false;
            } catch (error) {
                status.textContent = error.message || '发送失败，请稍后重试';
                submit.disabled = false;
            }
        });
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
                    if (item.classList.contains('post-card') || item.classList.contains('moment-card')) {
                        postList.insertBefore(document.importNode(item, true), requestMarker);
                    }
                });

                if (window.XiaoGuEnhanceMoments) window.XiaoGuEnhanceMoments(postList);
                if (window.XiaoGuBindLikes) window.XiaoGuBindLikes(postList);

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
                if (window.XiaoGuEnhanceMoments) window.XiaoGuEnhanceMoments(postList);
                if (window.XiaoGuBindLikes) window.XiaoGuBindLikes(postList);
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

    (function () {
        var siteUrl = '<?php echo rtrim($this->options->siteUrl, '/'); ?>/';

        function bindLikes(root) {
            var scope = root || document;
            scope.querySelectorAll('[data-xiaogu-like]').forEach(function (el) {
                if (el.dataset.xiaoguLikeBound) return;
                el.dataset.xiaoguLikeBound = 'true';

                function trigger() {
                    if (el.classList.contains('is-processing')) return;
                    var cid = el.getAttribute('data-xiaogu-like');
                    var countEls = document.querySelectorAll('[data-xiaogu-like-count="' + cid + '"]');
                    el.classList.add('is-processing');

                    fetch(siteUrl + '?xiaogu_action=like&cid=' + encodeURIComponent(cid), { credentials: 'same-origin' })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            if (data.success) {
                                el.classList.toggle('is-liked', data.liked);
                                countEls.forEach(function (countEl) {
                                    countEl.textContent = String(data.count);
                                });
                            }
                        })
                        .catch(function () {})
                        .finally(function () {
                            el.classList.remove('is-processing');
                        });
                }

                el.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    trigger();
                });
                el.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        trigger();
                    }
                });
            });
        }

        window.XiaoGuBindLikes = bindLikes;
        bindLikes(document);
    }());
</script>

<?php $this->footer(); ?>
</body>
</html>
