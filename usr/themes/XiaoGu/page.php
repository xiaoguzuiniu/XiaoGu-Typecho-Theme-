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
$bookPanelsOpen = $isPost && (int) $this->commentsNum > 0;

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
<div class="book-stage<?php if ($bookPanelsOpen): ?> is-book-open<?php endif; ?>" id="book-stage">
    <?php $this->need('book-panels.php'); ?>

    <div class="site-shell">
        <?php $this->need('topbar.php'); ?>

        <div class="content-grid page-layout<?php if ($isGuestbook): ?> guestbook-layout<?php endif; ?>">
            <main class="page-column<?php if ($isPost): ?> post-column<?php endif; ?>" aria-label="<?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>" data-damped-scroll>
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

</div>

<script src="<?php $this->options->themeUrl('assets/damped-scroll.js?v=' . filemtime(__DIR__ . '/assets/damped-scroll.js')); ?>"></script>
<script>
    (function () {
        const commentForm = document.getElementById('comment-form');
        const response = commentForm ? commentForm.closest('.respond') : null;
        const cancelLink = document.getElementById('cancel-comment-reply-link');
        const pageColumn = document.querySelector('.page-column');
        if (!response || !commentForm) return;

        function setReplyParent(coid) {
            let parentInput = commentForm.querySelector('input[name="parent"]');
            if (coid) {
                if (!parentInput) {
                    parentInput = document.createElement('input');
                    parentInput.type = 'hidden';
                    parentInput.name = 'parent';
                    commentForm.appendChild(parentInput);
                }
                parentInput.value = coid;
            } else if (parentInput) {
                parentInput.remove();
            }
        }

        function focusResponse() {
            const textarea = commentForm.querySelector('textarea[name="text"]');
            if (pageColumn) {
                const columnTop = pageColumn.getBoundingClientRect().top;
                const responseTop = response.getBoundingClientRect().top;
                pageColumn.scrollTop = Math.max(0, pageColumn.scrollTop + responseTop - columnTop - 18);
            }
            if (textarea) textarea.focus();
        }

        function beginReply(htmlId, coid) {
            setReplyParent(coid);
            response.dataset.replyTo = htmlId;
            if (cancelLink) cancelLink.style.display = '';
            focusResponse();
            return false;
        }

        function cancelReply() {
            setReplyParent(null);
            delete response.dataset.replyTo;
            if (cancelLink) cancelLink.style.display = 'none';
            focusResponse();
            return false;
        }

        document.addEventListener('click', function (event) {
            const replyLink = event.target.closest('.book-comment-list .comment-reply a');
            if (!replyLink) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            const comment = replyLink.closest('.comment-body');
            const replyUrl = new URL(replyLink.href, window.location.href);
            const coid = replyUrl.searchParams.get('replyTo');
            if (comment && coid) beginReply(comment.id, coid);
        }, true);

        if (cancelLink) {
            cancelLink.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();
                cancelReply();
            }, true);
        }

        if (window.TypechoComment) {
            window.TypechoComment.reply = function (htmlId, coid) {
                return beginReply(htmlId, coid);
            };
            window.TypechoComment.cancelReply = cancelReply;
        }
    }());

    (function () {
        const source = document.getElementById('post-comments-source');
        const sourceList = source ? source.querySelector('.comment-list') : null;
        const leftPage = document.querySelector('.book-comments-page-left');
        const rightPage = document.querySelector('.book-comments-page-right');
        const leftList = leftPage ? leftPage.querySelector('.book-comment-list') : null;
        const rightList = rightPage ? rightPage.querySelector('.book-comment-list') : null;
        const desktop = window.matchMedia('(min-width: 1520px)');
        if (!source || !sourceList || !leftPage || !rightPage || !leftList || !rightList) return;

        const comments = Array.from(sourceList.children);
        const navigator = source.querySelector('.page-navigator');

        function restoreComments() {
            comments.forEach(function (comment) {
                sourceList.appendChild(comment);
            });
            if (navigator) source.appendChild(navigator);
            leftList.replaceChildren();
            rightList.replaceChildren();
            leftPage.scrollTop = 0;
            rightPage.scrollTop = 0;
        }

        function distributeComments() {
            restoreComments();
            if (!desktop.matches) return;

            let useRightPage = false;
            comments.forEach(function (comment) {
                if (useRightPage) {
                    rightList.appendChild(comment);
                    return;
                }

                leftList.appendChild(comment);
                if (leftPage.scrollHeight > leftPage.clientHeight) {
                    leftList.removeChild(comment);
                    rightList.appendChild(comment);
                    useRightPage = true;
                }
            });

            if (navigator) rightPage.appendChild(navigator);
        }

        distributeComments();
        desktop.addEventListener('change', distributeComments);
        window.addEventListener('load', distributeComments, { once: true });
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
            const bookPage = target.closest('.book-comments-page');
            if (bookPage) {
                const pageTop = bookPage.getBoundingClientRect().top;
                const targetTop = target.getBoundingClientRect().top;
                bookPage.scrollTop = Math.max(0, bookPage.scrollTop + targetTop - pageTop - 18);
                resetOuterScroll();
                return;
            }

            const columnTop = pageColumn.getBoundingClientRect().top;
            const targetTop = target.getBoundingClientRect().top;
            pageColumn.scrollTop = Math.max(0, pageColumn.scrollTop + targetTop - columnTop - 18);
            resetOuterScroll();
        }

        function restoreBrokenAvatars() {
            const fallbackAvatarUrl = '<?php $this->options->themeUrl('assets/favicon.svg'); ?>';

            document.querySelectorAll('.comment-author .avatar').forEach(function (avatar) {
                function useFallbackAvatar() {
                    if (avatar.dataset.fallbackApplied === 'true') return;
                    avatar.dataset.fallbackApplied = 'true';
                    avatar.src = fallbackAvatarUrl;
                    avatar.style.display = '';
                }

                avatar.addEventListener('error', useFallbackAvatar, { once: true });
                if (avatar.complete && avatar.naturalWidth === 0) useFallbackAvatar();
            });
        }

        function restoreCommentPosition() {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    positionCommentAnchor();
                    restoreBrokenAvatars();
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
