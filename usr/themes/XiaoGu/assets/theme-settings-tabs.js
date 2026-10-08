(function () {
    'use strict';

    var form = document.querySelector('form[action*="themes-edit"]');
    if (!form || form.querySelector('.xiaogu-settings-shell')) return;

    var groups = [
        {
            id: 'basic',
            label: '基础设置',
            fields: ['browserTitle', 'icpBeianNumber']
        },
        {
            id: 'profile',
            label: '首页资料',
            fields: ['profileName', 'profileSignature', 'profileAvatarUrl', 'heroImageUrl']
        },
        {
            id: 'gallery',
            label: '相册设置',
            fields: ['galleryAlbums']
        },
        {
            id: 'hope',
            label: '我的盼头',
            fields: ['hopeGames']
        },
        {
            id: 'friends',
            label: '友链管理',
            fields: [
                'friendSiteName', 'friendSiteUrl', 'friendSiteLogoUrl',
                'friendSiteDescription', 'friendContactEmail', 'friendLinks'
            ]
        }
    ];

    var shell = document.createElement('section');
    shell.className = 'xiaogu-settings-shell';
    var tabList = document.createElement('div');
    tabList.className = 'xiaogu-settings-tabs';
    tabList.setAttribute('role', 'tablist');
    tabList.setAttribute('aria-label', '外观设置分类');
    var panes = document.createElement('div');
    panes.className = 'xiaogu-settings-panes';
    shell.append(tabList, panes);

    var firstOption = form.querySelector('.typecho-option');
    form.insertBefore(shell, firstOption || form.firstChild);

    var entries = groups.map(function (group, index) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'xiaogu-settings-tab';
        button.id = 'xiaogu-settings-tab-' + group.id;
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-controls', 'xiaogu-settings-pane-' + group.id);
        button.setAttribute('aria-selected', 'false');
        button.tabIndex = -1;
        button.textContent = group.label;

        var pane = document.createElement('section');
        pane.className = 'xiaogu-settings-pane';
        pane.id = 'xiaogu-settings-pane-' + group.id;
        pane.setAttribute('role', 'tabpanel');
        pane.setAttribute('aria-labelledby', button.id);
        pane.hidden = true;

        group.fields.forEach(function (name) {
            var input = form.querySelector('[name="' + name + '"]');
            var option = input ? input.closest('.typecho-option') : null;
            if (option) pane.appendChild(option);
        });

        tabList.appendChild(button);
        panes.appendChild(pane);
        return {id: group.id, index: index, button: button, pane: pane};
    });

    var reviewPanel = document.getElementById('xiaogu-friend-review');
    var friendsEntry = entries.find(function (entry) { return entry.id === 'friends'; });
    if (reviewPanel && friendsEntry) {
        friendsEntry.pane.insertBefore(reviewPanel, friendsEntry.pane.firstChild);
        reviewPanel.style.display = '';
    }

    function activate(entry, focus) {
        entries.forEach(function (item) {
            var active = item === entry;
            item.button.classList.toggle('is-active', active);
            item.button.setAttribute('aria-selected', active ? 'true' : 'false');
            item.button.tabIndex = active ? 0 : -1;
            item.pane.hidden = !active;
        });
        try {
            window.sessionStorage.setItem('xiaogu-theme-settings-tab', entry.id);
        } catch (error) {
            // Storage may be unavailable in privacy mode; tabs still work normally.
        }
        if (focus) entry.button.focus();
    }

    entries.forEach(function (entry) {
        entry.button.addEventListener('click', function () {
            activate(entry, false);
        });
        entry.button.addEventListener('keydown', function (event) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            var targetIndex = entry.index;
            if (event.key === 'ArrowLeft') targetIndex = (entry.index - 1 + entries.length) % entries.length;
            if (event.key === 'ArrowRight') targetIndex = (entry.index + 1) % entries.length;
            if (event.key === 'Home') targetIndex = 0;
            if (event.key === 'End') targetIndex = entries.length - 1;
            activate(entries[targetIndex], true);
        });
    });

    var errorEntry = entries.find(function (entry) {
        return Boolean(entry.pane.querySelector('.error'));
    });
    var rememberedId = '';
    try {
        rememberedId = window.sessionStorage.getItem('xiaogu-theme-settings-tab') || '';
    } catch (error) {
        rememberedId = '';
    }
    var rememberedEntry = entries.find(function (entry) { return entry.id === rememberedId; });
    activate(errorEntry || rememberedEntry || entries[0], false);
}());
