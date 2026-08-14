<aside class="book-panel book-panel-left" id="book-panel-left" aria-label="网站目录" aria-hidden="true" inert>
    <div class="book-page-inner">
        <span class="book-page-number">01</span>
        <p class="book-eyebrow">CONTENTS</p>
        <h2>网站目录</h2>
        <p class="book-intro">从这里快速进入文章分类和独立页面。</p>

        <section class="book-section">
            <h3>文章分类</h3>
            <ul>
                <?php \Widget\Metas\Category\Rows::alloc()->to($bookCategories); ?>
                <?php while ($bookCategories->next()): ?>
                    <li><a href="<?php $bookCategories->permalink(); ?>"><?php $bookCategories->name(); ?></a></li>
                <?php endwhile; ?>
            </ul>
        </section>

        <section class="book-section">
            <h3>独立页面</h3>
            <ul>
                <?php \Widget\Contents\Page\Rows::alloc()->to($bookPages); ?>
                <?php while ($bookPages->next()): ?>
                    <li><a href="<?php $bookPages->permalink(); ?>"><?php $bookPages->title(); ?></a></li>
                <?php endwhile; ?>
            </ul>
        </section>
    </div>
</aside>

<aside class="book-panel book-panel-right" id="book-panel-right" aria-label="文章书签" aria-hidden="true" inert>
    <div class="book-page-inner">
        <span class="book-page-number">02</span>
        <p class="book-eyebrow">BOOKMARKS</p>
        <h2>文章书签</h2>
        <p class="book-intro">按标签浏览内容，或者继续阅读最近发布的文章。</p>

        <section class="book-section">
            <h3>内容标签</h3>
            <div class="book-tags">
                <?php \Widget\Metas\Tag\Cloud::alloc('sort=count&desc=1')->to($bookTags); ?>
                <?php while ($bookTags->next()): ?>
                    <a href="<?php $bookTags->permalink(); ?>"># <?php $bookTags->name(); ?></a>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="book-section">
            <h3>最近文章</h3>
            <ol class="book-recent">
                <?php \Widget\Contents\Post\Recent::alloc('pageSize=5')->to($bookRecent); ?>
                <?php while ($bookRecent->next()): ?>
                    <li><a href="<?php $bookRecent->permalink(); ?>"><?php $bookRecent->title(); ?></a></li>
                <?php endwhile; ?>
            </ol>
        </section>
    </div>
</aside>
