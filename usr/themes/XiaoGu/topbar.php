<?php
$navigationPages = [];
\Widget\Contents\Page\Rows::alloc()->to($pages);
while ($pages->next()) {
    $navigationPages[(string) $pages->slug] = [
        'title' => (string) $pages->title,
        'url' => (string) $pages->permalink,
    ];
}

$categoryIsOpen = $this->is('category');
?>

<svg class="nav-icon-sprite" aria-hidden="true">
    <symbol id="nav-icon-home" viewBox="0 0 1024 1024">
        <path d="M362.667 895.915V639.85c0-36.267 33.109-63.851 72.533-63.851h153.6c39.253 0 72.533 27.648 72.533 63.85v256.065h59.904c61.27 0 110.763-47.958 110.763-106.731V414.165L557.163 139.328a63.808 63.808 0 0 0-90.326 0L192 414.165v375.019c0 58.88 49.387 106.73 110.763 106.73h59.904z m42.666 0h213.334V639.85c0-10.71-12.587-21.184-29.867-21.184H435.2c-17.408 0-29.867 10.389-29.867 21.184v256.064z m469.334-439.083v332.352c0 82.645-68.886 149.397-153.43 149.397H302.763c-84.63 0-153.43-66.645-153.43-149.397V456.832l-27.584 27.584a21.333 21.333 0 1 1-30.165-30.165l345.088-345.088a106.475 106.475 0 0 1 150.656 0L932.416 454.25a21.333 21.333 0 0 1-30.165 30.165l-27.584-27.584z"/>
    </symbol>
    <symbol id="nav-icon-gallery" viewBox="0 0 1024 1024">
        <path d="M668.1 227.3H207.8C93.2 227.3 0 320.6 0 435.1v296.4c0 114.6 93.2 207.8 207.8 207.8H668c114.6 0 207.8-93.2 207.8-207.8V435.1c0.1-114.5-93.1-207.8-207.7-207.8z m139.8 504.2c0 77.1-62.7 139.8-139.8 139.8H207.8c-57.3 0-106.7-34.7-128.3-84.2l217.8-217.8 134.2 134.2c13.3 13.3 34.8 13.3 48.1 0l88.2-88.2 96.6 96.6c13.3 13.3 34.8 13.3 48.1 0 13.3-13.3 13.3-34.8 0-48.1L592 543.2c-13.3-13.3-34.8-13.3-48.1 0l-88.2 88.2-134.3-134.2c-13.3-13.3-34.8-13.3-48.1 0L68 702.6V435.1c0-77.1 62.7-139.8 139.8-139.8H668c77.1 0 139.8 62.7 139.8 139.8v296.4z"/>
        <path d="M627.440143 485.154298a53.1 53.1 0 1 0 75.093429-75.096051 53.1 53.1 0 1 0-75.093429 75.096051Z"/>
        <path d="M675.1 84.6h-288c-18.8 0-34 15.2-34 34s15.2 34 34 34h288c154.9 0 280.9 126 280.9 280.9v149.8c0 18.8 15.2 34 34 34s34-15.2 34-34V433.6c0-192.4-156.5-349-348.9-349z"/>
    </symbol>
    <symbol id="nav-icon-category" viewBox="0 0 1024 1024">
        <path d="M393.309091 69.818182H162.909091C111.709091 69.818182 69.818182 111.709091 69.818182 162.909091v230.4c0 51.2 41.890909 93.090909 93.090909 93.090909h230.4c51.2 0 93.090909-41.890909 93.090909-93.090909V162.909091c0-51.2-41.890909-93.090909-93.090909-93.090909z m46.545454 321.163636c0 25.6-20.945455 46.545455-46.545454 46.545455H162.909091c-25.6 0-46.545455-20.945455-46.545455-46.545455V162.909091c0-25.6 20.945455-46.545455 46.545455-46.545455h230.4c25.6 0 46.545455 20.945455 46.545454 46.545455v228.072727zM861.090909 69.818182h-230.4c-51.2 0-93.090909 41.890909-93.090909 93.090909v230.4c0 51.2 41.890909 93.090909 93.090909 93.090909H861.090909c51.2 0 93.090909-41.890909 93.090909-93.090909V162.909091c0-51.2-41.890909-93.090909-93.090909-93.090909z m46.545455 321.163636c0 25.6-20.945455 46.545455-46.545455 46.545455h-230.4c-25.6 0-46.545455-20.945455-46.545454-46.545455V162.909091c0-25.6 20.945455-46.545455 46.545454-46.545455H861.090909c25.6 0 46.545455 20.945455 46.545455v228.072727zM393.309091 539.927273H162.909091c-51.2 0-93.090909 41.890909-93.090909 93.090909V861.090909c0 51.2 41.890909 93.090909 93.090909 93.090909h230.4c51.2 0 93.090909-41.890909 93.090909-93.090909v-230.4c0-48.872727-41.890909-90.763636-93.090909-90.763636z m46.545454 321.163636c0 25.6-20.945455 46.545455-46.545454 46.545455H162.909091c-25.6 0-46.545455-20.945455-46.545455-46.545455v-230.4c0-25.6 20.945455-46.545455 46.545455-46.545454h230.4c25.6 0 46.545455 20.945455 46.545454 46.545454V861.090909zM861.090909 539.927273h-230.4c-51.2 0-93.090909 41.890909-93.090909 93.090909V861.090909c0 51.2 41.890909 93.090909 93.090909 93.090909H861.090909c51.2 0 93.090909-41.890909 93.090909-93.090909v-230.4c0-48.872727-41.890909-90.763636-93.090909-90.763636zM907.636364 861.090909c0 25.6-20.945455 46.545455-46.545455 46.545455h-230.4c-25.6 0-46.545455-20.945455-46.545454-46.545455v-230.4c0-25.6 20.945455-46.545455 46.545454-46.545454H861.090909c25.6 0 46.545455 20.945455 46.545455 46.545454V861.090909z"/>
    </symbol>
    <symbol id="nav-icon-message" viewBox="0 0 1024 1024">
        <path d="M677.38624 895.367168c6.36928 8.501248 4.2496 19.80416-3.5328 26.161152-8.491008 6.356992-19.802112 1.765376-26.157056-6.025216L566.390784 804.864 243.283968 804.864c-37.462016 0-73.619456-13.09696-98.36544-37.107712C120.877056 743.008256 102.4 711.548928 102.4 674.791424L102.4 274.630656c0-37.484544 18.477056-74.969088 42.51648-99.715072C169.664512 150.874112 205.821952 131.072 243.283968 131.072l519.655424 0c36.755456 0 71.245824 19.802112 95.993856 43.843584 24.037376 24.745984 40.1408 62.232576 40.1408 99.715072l0 400.160768c0 36.755456-16.101376 70.7072-40.1408 95.455232-24.748032 24.016896-58.957824 39.579648-95.711232 39.579648-10.622976 0-19.236864-8.476672-19.236864-19.0976 0-9.887744 8.419328-18.364416 19.027968-18.364416 26.863616 0 45.568-11.32544 63.240192-29.00992 17.672192-17.657856 23.668736-41.699328 23.668736-68.564992L849.922048 274.630656c0-26.865664-6.017024-49.321984-23.699456-67.004416C808.56064 189.943808 789.803008 180.224 762.937344 180.224L243.283968 180.224c-26.865664 0-47.98464 9.719808-65.665024 27.40224C159.956992 225.31072 151.552 247.764992 151.552 274.630656l0 400.160768c0 26.863616 8.404992 54.85568 26.064896 72.538112C195.299328 765.014016 216.418304 780.288 243.283968 780.288l332.302336 0c5.65248 0 11.302912-1.828864 14.845952 3.090432L677.38624 895.367168z"/>
        <path d="M318.230528 444.30336c25.452544 0 45.959168 21.215232 45.959168 46.667776s-20.508672 45.961216-45.959168 45.961216c-25.454592 0-45.950976-20.51072-45.950976-45.961216S292.775936 444.30336 318.230528 444.30336z"/>
        <path d="M507.701248 444.30336c25.454592 0 45.963264 21.215232 45.963264 46.667776s-20.51072 45.961216-45.963264 45.961216c-25.452544 0-45.94688-20.51072-45.94688-45.961216S482.250752 444.30336 507.701248 444.30336z"/>
        <path d="M697.186304 536.932352c25.45664 0 45.950976-20.51072 45.950976-45.961216s-20.494336-46.667776-45.950976-46.667776c-25.452544 0-46.665728 21.215232-46.665728 46.667776s21.213184 45.961216 46.665728 45.961216z"/>
    </symbol>
    <symbol id="nav-icon-neighbor" viewBox="0 0 1024 1024">
        <path d="M469.854111 687.156252c-15.275915 0-27.66099-12.384052-27.66099-27.66099s12.384052-27.66099 27.66099-27.66099h84.23652c10.474562 0 20.676925-2.073218 30.27963-6.219655 9.165754-3.764744 17.458627-9.329483 24.605398-16.476253 7.14677-7.202029 12.71151-15.493879 16.694217-24.769126 4.036943-9.493212 6.110161-19.695574 6.110161-30.224372V217.144552c0-20.840654-8.074909-40.318264-22.750143-54.939263-7.092535-7.14677-15.385409-12.71151-24.659633-16.639982-9.547447-4.036943-19.74981-6.110161-30.27963-6.110162H217.089293c-20.840654 0-40.318264 8.129144-54.885027 22.805402-14.676257 14.512529-22.805402 34.043351-22.805402 54.885028v337.001337c0 10.366092 2.073218 20.513196 6.110161 30.224372 3.982708 9.383718 9.601682 17.622356 16.585747 24.659633 14.512529 14.45727 34.480302 22.805402 54.829769 22.805402h42.281989c15.275915 0 27.66099 12.384052 27.66099 27.66099s-12.384052 27.66099-27.66099 27.66099H217.08827c-35.135218 0-69.396533-14.185071-94.111424-38.954197-24.769127-24.659633-38.954197-58.976206-38.898939-94.111425V217.144552c0-18.004049 3.491521-35.462676 10.474562-51.829436 6.710842-15.930831 16.258289-30.170137 28.478612-42.281988 24.605398-24.769127 58.812477-38.954197 93.89346-38.954198h337.165066c17.84032 0 35.298947 3.491521 51.883671 10.474563 15.876596 6.710842 30.115901 16.312524 42.281989 28.478611s21.768793 26.405393 28.478611 42.336224c6.928806 16.366759 10.420327 33.825386 10.420327 51.775201v337.001337c0 17.949814-3.491521 35.353183-10.420327 51.829435-6.710842 15.876596-16.312524 30.115901-28.53387 42.281989-12.275581 12.275581-26.514887 21.823028-42.336224 28.424377-16.366759 6.983041-33.607422 10.474562-51.393507 10.474562h-84.616166z"/>
        <path d="M469.854111 939.92107c-35.571147 0-69.01484-13.803377-94.111425-38.954198-24.769127-24.769127-38.954197-59.031465-38.898938-94.057189V469.908346c0-17.84032 3.491521-35.298947 10.474562-51.883671 6.655584-15.767102 16.258289-30.006408 28.53387-42.227753 24.659633-24.714891 58.867736-38.898939 93.839225-38.898939h84.400249c15.275915 0 27.715225 12.384052 27.715225 27.66099s-12.43931 27.66099-27.715225 27.66099H469.855134c-20.513196 0-40.481993 8.292873-54.829769 22.805402-14.676257 14.566764-22.805402 34.098609-22.805402 54.829769v337.109808c0 10.529821 2.073218 20.732184 6.110161 30.170136 3.928472 9.274224 9.493212 17.567098 16.585747 24.659633 14.3488 14.45727 34.316573 22.805402 54.775534 22.805402h337.273537c10.584056 0 20.732184-2.073218 30.224372-6.16542 9.219989-3.928472 17.512862-9.493212 24.605397-16.530488 7.202029-7.310499 12.766768-15.603373 16.694218-24.769127 4.036943-9.601682 6.110161-19.695574 6.110161-30.061666V469.908346c0-20.513196-8.292873-40.536228-22.695908-54.885028-7.202029-7.14677-15.549138-12.766768-24.823362-16.694217-9.438976-4.036943-19.640316-6.110161-30.115902-6.110161h-42.172495c-15.275915 0-27.66099-12.384052-27.660989-27.66099s12.384052-27.66099 27.660989-27.66099h42.172495c17.894555 0 35.353183 3.491521 51.775201 10.474562a132.539642 132.539642 0 0 1 42.281989 28.478612c12.221346 12.166087 21.768793 26.405393 28.478611 42.227753 6.928806 16.422018 10.420327 33.880645 10.420327 51.829436v337.055572c0 17.894555-3.491521 35.298947-10.420327 51.829436-6.655584 15.712867-16.258289 29.952172-28.53387 42.227753-12.166087 12.221346-26.405393 21.823028-42.281989 28.478612-16.258289 6.928806-33.553187 10.420327-51.393507 10.420327H469.854111z"/>
    </symbol>
    <symbol id="nav-icon-about" viewBox="0 0 1024 1024">
        <path d="M559.36 536.746667a256 256 0 0 0 116.48-213.333334 261.973333 261.973333 0 0 0-523.52 0 256 256 0 0 0 116.48 213.333334A387.84 387.84 0 0 0 21.333333 896v27.306667a36.693333 36.693333 0 0 0 72.96 0v-3.413334V896a320.426667 320.426667 0 0 1 640 0 234.24 234.24 0 0 1 0 24.746667 36.266667 36.266667 0 0 0 72.533334 0v-27.306667a387.84 387.84 0 0 0-247.466667-356.693333z m-145.066667-29.013334a185.6 185.6 0 1 1 187.733334-185.6 186.88 186.88 0 0 1-187.733334 185.6z m588.373334 323.84a37.12 37.12 0 0 1-37.12 35.413334 36.693333 36.693333 0 0 1-36.693334-36.693334 256 256 0 0 0-227.413333-261.12 42.666667 42.666667 0 0 1 0-82.773333 139.52 139.52 0 0 0 20.053333-270.506667L712.533333 213.333333a36.693333 36.693333 0 0 1 5.973334-73.386666H725.333333a213.333333 213.333333 0 0 1 71.253334 389.546666 323.84 323.84 0 0 1 206.933333 298.666667z"/>
    </symbol>
</svg>

<header class="site-header">
    <nav class="top-nav" aria-label="主导航" data-damped-scroll>
        <a class="top-nav-home<?php if ($this->is('index')): ?> is-current<?php endif; ?>"
           href="<?php $this->options->siteUrl(); ?>"><svg class="nav-icon" aria-hidden="true"><use href="#nav-icon-home"></use></svg><span>首页</span></a>

        <?php if (isset($navigationPages['gallery'])): ?>
            <a class="<?php if ($this->is('page', 'gallery')): ?>is-current<?php endif; ?>"
               href="<?php echo htmlspecialchars($navigationPages['gallery']['url'], ENT_QUOTES, 'UTF-8'); ?>"><svg class="nav-icon" aria-hidden="true"><use href="#nav-icon-gallery"></use></svg><span>相册</span></a>
        <?php endif; ?>

        <div class="nav-category-group<?php if ($categoryIsOpen): ?> is-open<?php endif; ?>" id="nav-category-group">
            <button class="nav-category-toggle<?php if ($categoryIsOpen): ?> is-current<?php endif; ?>"
                    id="nav-category-toggle" type="button" aria-expanded="<?php echo $categoryIsOpen ? 'true' : 'false'; ?>"
                    aria-controls="nav-category-list">
                <span class="nav-category-label"><span class="nav-category-grid-icon" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span>分类</span></span>
                <span class="nav-category-arrow" aria-hidden="true"></span>
            </button>
            <div class="nav-category-list" id="nav-category-list">
                <?php \Widget\Metas\Category\Rows::alloc()->to($categories); ?>
                <?php while ($categories->next()): ?>
                    <a class="<?php if ($this->is('category', $categories->slug)): ?>is-current<?php endif; ?>"
                       href="<?php $categories->permalink(); ?>">· <?php $categories->name(); ?></a>
                <?php endwhile; ?>
            </div>
        </div>

        <?php if (isset($navigationPages['guestbook'])): ?>
            <a class="<?php if ($this->is('page', 'guestbook')): ?>is-current<?php endif; ?>"
               href="<?php echo htmlspecialchars($navigationPages['guestbook']['url'], ENT_QUOTES, 'UTF-8'); ?>"><svg class="nav-icon" aria-hidden="true"><use href="#nav-icon-message"></use></svg><span>留言</span></a>
        <?php endif; ?>

        <?php if (isset($navigationPages['neighbors'])): ?>
            <a class="<?php if ($this->is('page', 'neighbors')): ?>is-current<?php endif; ?>"
               href="<?php echo htmlspecialchars($navigationPages['neighbors']['url'], ENT_QUOTES, 'UTF-8'); ?>"><svg class="nav-icon" aria-hidden="true"><use href="#nav-icon-neighbor"></use></svg><span>邻居</span></a>
        <?php endif; ?>

        <?php if (isset($navigationPages['start-page'])): ?>
            <a class="<?php if ($this->is('page', 'start-page')): ?>is-current<?php endif; ?>"
               href="<?php echo htmlspecialchars($navigationPages['start-page']['url'], ENT_QUOTES, 'UTF-8'); ?>"><svg class="nav-icon" aria-hidden="true"><use href="#nav-icon-about"></use></svg><span>关于</span></a>
        <?php endif; ?>
    </nav>

    <div class="header-actions">
        <form class="search-form" method="get" action="<?php $this->options->siteUrl(); ?>" role="search">
            <label class="sr-only" for="site-search">搜索文章</label>
            <input id="site-search" type="search" name="s" placeholder="搜索">
            <button type="button" aria-label="展开搜索" aria-controls="site-search" aria-expanded="false">⌕</button>
        </form>
        <button class="theme-toggle" type="button" aria-label="切换为深色模式" title="切换为深色模式">
            <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7Z"/>
            </svg>
            <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"></path>
            </svg>
        </button>
        <a class="admin-link" href="https://github.com/xiaoguzuiniu" target="_blank"
           rel="noopener noreferrer" aria-label="访问 GitHub 主页">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M12 .7a11.5 11.5 0 0 0-3.64 22.41c.58.11.79-.25.79-.56v-2.23c-3.22.7-3.9-1.37-3.9-1.37-.53-1.34-1.29-1.7-1.29-1.7-1.05-.72.08-.71.08-.71 1.16.08 1.77 1.2 1.77 1.2 1.04 1.77 2.71 1.26 3.37.96.1-.75.4-1.26.74-1.55-2.57-.29-5.27-1.29-5.27-5.68 0-1.26.45-2.28 1.19-3.09-.12-.29-.52-1.47.11-3.05 0 0 .97-.31 3.16 1.18a10.96 10.96 0 0 1 5.76 0c2.19-1.49 3.16-1.18 3.16-1.18.63 1.58.23 2.76.11 3.05.74.81 1.19 1.83 1.19 3.09 0 4.4-2.7 5.38-5.28 5.67.42.36.79 1.07.79 2.16v3.2c0 .31.21.68.79.56A11.5 11.5 0 0 0 12 .7Z"/>
            </svg>
        </a>
    </div>
</header>

<script>
    (function () {
        const group = document.getElementById('nav-category-group');
        const toggle = document.getElementById('nav-category-toggle');
        const storageKey = 'xiaogu-category-nav-open';
        if (!group || !toggle) return;

        function setOpen(open, persist) {
            group.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            if (persist) {
                try {
                    window.localStorage.setItem(storageKey, open ? '1' : '0');
                } catch (error) {
                    // 本地存储不可用时仍允许当前页面展开分类。
                }
            }
        }

        let savedOpen = group.classList.contains('is-open');
        if (!savedOpen) {
            try {
                savedOpen = window.localStorage.getItem(storageKey) === '1';
            } catch (error) {
                savedOpen = false;
            }
        }

        setOpen(savedOpen, false);
        toggle.addEventListener('click', function () {
            setOpen(!group.classList.contains('is-open'), true);
        });
    }());
</script>
<script>
    (function () {
        const root = document.documentElement;
        const toggle = document.querySelector('.theme-toggle');
        const colorScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const storageKey = 'xiaogu-color-theme';
        if (!toggle) return;

        function updateToggle() {
            const dark = root.dataset.theme === 'dark';
            const label = dark ? '切换为浅色模式' : '切换为深色模式';
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);
            toggle.setAttribute('aria-pressed', String(dark));
        }

        function applyTheme(preference, persist) {
            const dark = preference === 'dark'
                || (preference === 'auto' && colorScheme.matches);
            const theme = dark ? 'dark' : 'light';

            root.dataset.theme = theme;
            root.dataset.themePreference = preference;
            root.style.colorScheme = theme;

            if (persist) {
                try {
                    window.localStorage.setItem(storageKey, preference);
                } catch (error) {
                    // 本地存储不可用时仍允许当前页面切换主题。
                }
            }

            updateToggle();
        }

        toggle.addEventListener('click', function () {
            root.classList.add('is-theme-switching');
            applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
            window.setTimeout(function () {
                root.classList.remove('is-theme-switching');
            }, 220);
        });

        const handleSystemTheme = function () {
            if ((root.dataset.themePreference || 'auto') === 'auto') {
                applyTheme('auto', false);
            }
        };

        if (typeof colorScheme.addEventListener === 'function') {
            colorScheme.addEventListener('change', handleSystemTheme);
        } else {
            colorScheme.addListener(handleSystemTheme);
        }

        updateToggle();
    }());
</script>
<script>
    (function () {
        const form = document.querySelector('.search-form');
        const input = form ? form.querySelector('input[type="search"]') : null;
        const button = form ? form.querySelector('button') : null;
        if (!form || !input || !button) return;

        function setOpen(open) {
            form.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? '搜索文章' : '展开搜索');
        }

        button.addEventListener('click', function () {
            if (!form.classList.contains('is-open')) {
                setOpen(true);
                input.focus();
                return;
            }

            if (input.value.trim() !== '') {
                form.requestSubmit();
                return;
            }

            setOpen(false);
            button.blur();
        });

        input.addEventListener('focus', function () {
            setOpen(true);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            setOpen(false);
            input.blur();
        });

        form.addEventListener('submit', function (event) {
            if (input.value.trim() !== '') return;
            event.preventDefault();
            setOpen(true);
            input.focus();
        });
    }());
</script>
