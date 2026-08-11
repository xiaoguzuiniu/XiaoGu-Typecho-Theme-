<header class="site-header">
    <a class="brand" href="<?php $this->options->siteUrl(); ?>" aria-label="返回首页">
        <span class="brand-mark">X</span>
        <span class="brand-copy">
            <strong><?php $this->options->title(); ?></strong>
            <small>MY PERSONAL BLOG</small>
        </span>
    </a>

    <nav class="top-nav" aria-label="主导航">
        <a class="top-nav-home<?php if ($this->is('index')): ?> is-current<?php endif; ?>"
           href="<?php $this->options->siteUrl(); ?>">⌂ 首页</a>

        <?php \Widget\Metas\Category\Rows::alloc()->to($categories); ?>
        <?php while ($categories->next()): ?>
            <a class="<?php if ($this->is('category', $categories->slug)): ?>is-current<?php endif; ?>"
               href="<?php $categories->permalink(); ?>"># <?php $categories->name(); ?></a>
        <?php endwhile; ?>

        <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
        <?php while ($pages->next()): ?>
            <a class="<?php if ($this->is('page', $pages->slug)): ?>is-current<?php endif; ?>"
               href="<?php $pages->permalink(); ?>">◇ <?php $pages->title(); ?></a>
        <?php endwhile; ?>
    </nav>

    <div class="header-actions">
        <form class="search-form" method="get" action="<?php $this->options->siteUrl(); ?>" role="search">
            <label class="sr-only" for="site-search">搜索文章</label>
            <input id="site-search" type="search" name="s" placeholder="搜索">
            <button type="submit" aria-label="搜索">⌕</button>
        </form>
        <a class="admin-link" href="<?php $this->options->adminUrl(); ?>" aria-label="进入后台">♙</a>
    </div>
</header>
