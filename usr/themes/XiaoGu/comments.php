<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$isGuestbookComments = $this->is('page', 'guestbook');
$commentsLabel = $isGuestbookComments ? '留言' : '评论';
?>

<section id="comments" class="comments-area" aria-label="<?php echo $commentsLabel; ?>区">
    <?php $this->comments()->to($comments); ?>

    <?php if ($comments->have()): ?>
        <?php if ($isGuestbookComments): ?>
            <h2><?php $this->commentsNum('还没有' . $commentsLabel, '已有 1 条' . $commentsLabel, '已有 %d 条' . $commentsLabel); ?></h2>
            <?php $comments->listComments(); ?>
            <?php $comments->pageNav('上一页', '下一页'); ?>
        <?php else: ?>
            <div class="post-comments-source" id="post-comments-source">
                <?php $comments->listComments([
                    'dateFormat' => 'm-d H:i',
                    'avatarSize' => 24
                ]); ?>
                <?php $comments->pageNav('上一页', '下一页'); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div id="<?php $this->respondId(); ?>" class="respond">
            <div class="cancel-comment-reply"><?php $comments->cancelReply(); ?></div>
            <?php if ($isGuestbookComments): ?>
                <h2 id="response">写下你的<?php echo $commentsLabel; ?></h2>
            <?php endif; ?>

            <form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form" role="form"
                  class="comment-card<?php if (!$isGuestbookComments): ?> post-comment-card<?php endif; ?>">
                <div class="comment-fields">
                    <?php if ($this->user->hasLogin()): ?>
                        <input type="text" name="author" placeholder="昵称">
                        <input type="email" name="mail" placeholder="邮箱">
                        <input type="url" name="url" placeholder="网址">
                    <?php else: ?>
                        <input type="text" name="author" placeholder="昵称" value="<?php $this->remember('author'); ?>" required>
                        <input type="email" name="mail" placeholder="邮箱" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                        <input type="url" name="url" placeholder="网址" value="<?php $this->remember('url'); ?>">
                    <?php endif; ?>
                </div>

                <div class="comment-textarea">
                    <textarea rows="4" name="text" placeholder="写点什么..." required><?php $this->remember('text'); ?></textarea>
                </div>
                <div class="comment-actions">
                    <button type="button" class="emoji-toggle" title="更多表情" aria-label="展开或收起更多表情"
                            aria-expanded="false">☺</button>
                    <button type="submit" class="comment-submit">写好了</button>
                </div>
                <div class="emoji-picker"<?php if ($isGuestbookComments): ?> hidden<?php endif; ?>>
                    <span>😂</span><span>😎</span><span>😏</span><span>😅</span><span>😄</span><span>😜</span><span>🤣</span><span>😭</span><span>🙄</span><span>😳</span><span>😊</span><span>🥶</span><span>🤡</span><span>😴</span><span>😣</span><span>🍉</span><span>😱</span><span>👋</span><span>🔨</span><span>🐶</span>
                    <span>👋</span><span>🙈</span><span>😓</span><span>😍</span><span>🤝</span><span>🥺</span><span>😔</span><span>😢</span><span>😲</span><span>🤷</span><span>😛</span><span>🤭</span><span>🤢</span><span>🥹</span><span>🙄</span><span>😈</span><span>😀</span><span>😯</span><span>😡</span><span>😵</span>
                    <span>💪</span><span>👍</span><span>👎</span><span>😡</span><span>🤬</span><span>😖</span><span>🌹</span><span>🏃</span><span>😆</span><span>💵</span><span>😘</span><span>😂</span><span>🤕</span><span>🎉</span><span>❤️</span><span>💔</span><span>😣</span><span>😘</span><span>💩</span><span>🤩</span>
                </div>
                <?php if (!$isGuestbookComments): ?>
                    <div class="emoji-picker emoji-picker-more" hidden>
                        <span>🥰</span><span>🥳</span><span>🤓</span><span>🧐</span><span>🤠</span><span>🫡</span><span>🤐</span><span>🤨</span><span>😐</span><span>😑</span><span>😶</span><span>🫠</span><span>🫢</span><span>🫣</span><span>🤫</span><span>🫤</span><span>😬</span><span>😮‍💨</span><span>🤤</span><span>😪</span>
                        <span>🤧</span><span>🤒</span><span>🤢</span><span>🤮</span><span>🥴</span><span>😵‍💫</span><span>🤯</span><span>🫨</span><span>🥸</span><span>🤑</span><span>🤭</span><span>🫶</span><span>🤲</span><span>🙌</span><span>👐</span><span>🤞</span><span>✌️</span><span>🤟</span><span>🤘</span><span>🤙</span>
                        <span>👊</span><span>✊</span><span>🤛</span><span>🤜</span><span>🫷</span><span>🫸</span><span>👏</span><span>🫵</span><span>👀</span><span>👁️</span><span>👄</span><span>💋</span><span>💯</span><span>✨</span><span>⚡</span><span>🌟</span><span>🎈</span><span>🎁</span><span>🍀</span><span>🐱</span>
                    </div>
                <?php endif; ?>
            </form>

            <script>
            (function(){
                var form = document.getElementById('comment-form');
                var toggle = form && form.querySelector('.emoji-toggle');
                var picker = form && form.querySelector('.emoji-picker');
                var morePicker = form && form.querySelector('.emoji-picker-more');
                var textarea = form && form.querySelector('textarea[name="text"]');
                if (!toggle || !picker || !textarea) return;
                toggle.addEventListener('click', function(e){
                    e.preventDefault();
                    var target = morePicker || picker;
                    target.hidden = !target.hidden;
                    toggle.setAttribute('aria-expanded', String(!target.hidden));
                });
                form.addEventListener('click', function(e){
                    if (e.target.tagName === 'SPAN') {
                        var pos = textarea.selectionStart;
                        var val = textarea.value;
                        textarea.value = val.slice(0, pos) + e.target.textContent + val.slice(pos);
                        textarea.focus();
                        textarea.selectionStart = textarea.selectionEnd = pos + e.target.textContent.length;
                    }
                });
            })();
            </script>
        </div>
    <?php else: ?>
        <p class="comments-closed"><?php echo $commentsLabel; ?>已关闭。</p>
    <?php endif; ?>
</section>
