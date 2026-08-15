<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$isGuestbookComments = $this->is('page', 'guestbook');
$commentsLabel = $isGuestbookComments ? '留言' : '评论';
?>

<section id="comments" class="comments-area" aria-label="<?php echo $commentsLabel; ?>区">
    <?php $this->comments()->to($comments); ?>

    <?php if ($comments->have()): ?>
        <h2><?php $this->commentsNum('还没有' . $commentsLabel, '已有 1 条' . $commentsLabel, '已有 %d 条' . $commentsLabel); ?></h2>
        <?php $comments->listComments(); ?>
        <?php $comments->pageNav('上一页', '下一页'); ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div id="<?php $this->respondId(); ?>" class="respond">
            <div class="cancel-comment-reply"><?php $comments->cancelReply(); ?></div>
            <h2 id="response">写下你的<?php echo $commentsLabel; ?></h2>

            <form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form" role="form">
                <?php if ($this->user->hasLogin()): ?>
                    <p>当前身份：<a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a></p>
                <?php else: ?>
                    <div class="comment-fields">
                        <label>称呼<input type="text" name="author" value="<?php $this->remember('author'); ?>" required></label>
                        <label>Email<input type="email" name="mail" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>></label>
                        <label>网站<input type="url" name="url" value="<?php $this->remember('url'); ?>"></label>
                    </div>
                <?php endif; ?>

                <label class="comment-textarea">内容
                    <textarea rows="6" name="text" required><?php $this->remember('text'); ?></textarea>
                </label>
                <button type="submit" class="comment-submit">提交<?php echo $commentsLabel; ?></button>
            </form>
        </div>
    <?php else: ?>
        <p class="comments-closed"><?php echo $commentsLabel; ?>已关闭。</p>
    <?php endif; ?>
</section>
