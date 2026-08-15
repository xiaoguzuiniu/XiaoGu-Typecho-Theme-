<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$browserTitle = trim((string) $this->options->browserTitle);
if ($browserTitle === '') {
    $browserTitle = (string) $this->options->title;
}

$pageTitle = (string) $this->title;
$pageSlug = (string) $this->slug;
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
            <main class="page-column" aria-label="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
                <article class="page-article page-<?php echo htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?>">
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

                    <?php if ($this->is('page', 'guestbook')): ?>
                        <?php $this->need('comments.php'); ?>
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
