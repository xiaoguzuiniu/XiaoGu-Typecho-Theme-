<?php
$bookPanelsOpen = $this->is('post') && (int) $this->commentsNum > 0;
?>

<aside class="book-panel book-panel-left" id="book-panel-left" aria-label="文章评论前页"
       aria-hidden="<?php echo $bookPanelsOpen ? 'false' : 'true'; ?>">
    <div class="book-page-inner book-comments-page book-comments-page-left">
        <ol class="comment-list book-comment-list" data-comment-page="left"></ol>
    </div>
</aside>

<aside class="book-panel book-panel-right" id="book-panel-right" aria-label="文章评论续页"
       aria-hidden="<?php echo $bookPanelsOpen ? 'false' : 'true'; ?>">
    <div class="book-page-inner book-comments-page book-comments-page-right">
        <ol class="comment-list book-comment-list" data-comment-page="right"></ol>
    </div>
</aside>
