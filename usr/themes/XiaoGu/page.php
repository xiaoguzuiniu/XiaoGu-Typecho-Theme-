<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if ($this->is('post')) {
    recordPostView($this);
}

$browserTitle = trim((string) $this->options->browserTitle);
if ($browserTitle === '') {
    $browserTitle = (string) $this->options->title;
}

$pageTitle = (string) $this->title;
$pageSlug = (string) $this->slug;
$isPost = $this->is('post');
$isGuestbook = $this->is('page', 'guestbook');

$profileName = trim((string) $this->options->profileName);
$profileSignature = trim((string) $this->options->profileSignature);
$profileAvatarUrl = trim((string) $this->options->profileAvatarUrl);
$heroImageUrl = trim((string) $this->options->heroImageUrl);

if ($profileName === '') {
    $profileName = (string) $this->options->title;
}

if ($profileSignature === '') {
    $profileSignature = (string) $this->options->description;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle . ' · ' . $browserTitle, ENT_QUOTES, 'UTF-8'); ?></title>
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

        <div class="content-grid page-layout">
            <main class="page-column<?php if ($isPost): ?> post-column<?php endif; ?>" aria-label="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
                <article class="page-article <?php echo $isPost ? 'post-detail' : 'page-' . htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($isPost): ?>
                        <section class="post-detail-hero" aria-label="文章头图">
                            <?php if ($heroImageUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" decoding="async">
                            <?php else: ?>
                                <img src="<?php $this->options->themeUrl('assets/mountain-hero.jpg'); ?>" alt="" decoding="async">
                            <?php endif; ?>

                            <div class="post-detail-profile">
                                <div class="post-detail-profile-copy">
                                    <strong><?php echo htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if ($profileSignature !== ''): ?>
                                        <span><?php echo htmlspecialchars($profileSignature, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($profileAvatarUrl !== ''): ?>
                                    <img class="post-detail-avatar" src="<?php echo htmlspecialchars($profileAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="" decoding="async">
                                <?php else: ?>
                                    <span class="post-detail-avatar post-detail-avatar-fallback" aria-hidden="true">X</span>
                                <?php endif; ?>
                            </div>
                        </section>

                        <div class="post-detail-body">
                            <header class="post-detail-heading">
                                <h1><?php $this->title(); ?></h1>
                                <div class="post-detail-heading-row">
                                    <div class="post-detail-meta">
                                        <span><?php $this->author(); ?></span>
                                        <time datetime="<?php $this->date('c'); ?>"><?php $this->date('Y-m-d'); ?></time>
                                        <span><?php $this->commentsNum('暂无评论', '1 条评论', '%d 条评论'); ?></span>
                                    </div>
                                    <span class="post-detail-category"><?php $this->category(' · '); ?></span>
                                </div>
                            </header>

                            <div class="page-content">
                                <?php $this->content(); ?>
                            </div>

                            <?php $this->need('comments.php'); ?>
                        </div>
                    <?php else: ?>
                        <header class="page-heading">
                            <span class="page-heading-mark" aria-hidden="true">◇</span>
                            <div>
                                <p>INDEPENDENT PAGE</p>
                                <h1><?php $this->title(); ?></h1>
                            </div>
                        </header>

                        <div class="page-content">
                            <?php $this->content(); ?>
                        </div>

                        <?php if ($isGuestbook): ?>
                            <?php $this->need('comments.php'); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            </main>

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
                if (open) panel.removeAttribute('inert');
                else panel.setAttribute('inert', '');
            });

            if (persist) {
                try {
                    window.localStorage.setItem(storageKey, open ? '1' : '0');
                } catch (error) {
                    // 本地存储不可用时仍保留当前页面状态。
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
        const pageColumn = document.querySelector('.page-column');
        if (!pageColumn) return;

        function resetOuterScroll() {
            window.scrollTo(0, 0);
            document.documentElement.scrollTop = 0;
            document.body.scrollTop = 0;
        }

        function positionCommentAnchor() {
            if (!window.location.hash || window.location.hash.indexOf('#comment-') !== 0) return;

            const target = document.getElementById(window.location.hash.slice(1));
            if (!target) return;

            resetOuterScroll();
            const columnTop = pageColumn.getBoundingClientRect().top;
            const targetTop = target.getBoundingClientRect().top;
            pageColumn.scrollTop = Math.max(0, pageColumn.scrollTop + targetTop - columnTop - 18);
            resetOuterScroll();
        }

        function hideBrokenAvatars() {
            document.querySelectorAll('.comment-author .avatar').forEach(function (avatar) {
                function hideAvatar() {
                    avatar.style.display = 'none';
                }

                avatar.addEventListener('error', hideAvatar, { once: true });
                if (avatar.complete && avatar.naturalWidth === 0) hideAvatar();
            });
        }

        function restoreCommentPosition() {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    positionCommentAnchor();
                    hideBrokenAvatars();
                });
            });
        }

        window.addEventListener('load', restoreCommentPosition);
        window.addEventListener('hashchange', restoreCommentPosition);
    }());
</script>

<?php $this->footer(); ?>
</body>
</html>
