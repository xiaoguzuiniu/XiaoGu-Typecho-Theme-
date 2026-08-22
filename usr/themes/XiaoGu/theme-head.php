<meta name="color-scheme" content="light dark">
<script>
    (function () {
        const root = document.documentElement;
        const storageKey = 'xiaogu-color-theme';
        let preference = 'auto';

        try {
            const saved = window.localStorage.getItem(storageKey);
            if (saved === 'light' || saved === 'dark' || saved === 'auto') {
                preference = saved;
            }
        } catch (error) {
            preference = 'auto';
        }

        const systemDark = window.matchMedia
            && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = preference === 'dark'
            || (preference === 'auto' && systemDark)
            ? 'dark'
            : 'light';

        root.dataset.theme = theme;
        root.dataset.themePreference = preference;
        root.style.colorScheme = theme;
    }());
</script>
