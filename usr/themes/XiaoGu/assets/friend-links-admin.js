(function () {
    'use strict';

    var source = document.querySelector('textarea[name="friendLinks"]');
    if (!source) return;

    source.classList.add('xiaogu-friends-source');
    var sourceOption = source.closest('.typecho-option');
    if (sourceOption) sourceOption.classList.add('xiaogu-friends-option');

    var links = parseLinks(source.value);
    var editingIndex = -1;
    var panel = document.createElement('section');
    panel.className = 'xiaogu-friends-admin';
    panel.innerHTML = [
        '<div class="xiaogu-friends-heading">',
        '  <div><h3>可视化友链管理</h3><p>分别填写站点信息，无需手工拼接格式。</p></div>',
        '  <strong class="xiaogu-friends-count"></strong>',
        '</div>',
        '<div class="xiaogu-friends-form">',
        '  <div class="xiaogu-friends-field"><label>站点名称 *</label><input type="text" data-friend-name maxlength="150" placeholder="例：小古有趣"></div>',
        '  <div class="xiaogu-friends-field"><label>网站地址 *</label><input type="url" data-friend-url placeholder="https://example.com"></div>',
        '  <div class="xiaogu-friends-field is-wide"><label>头像地址</label><div class="xiaogu-friends-avatar-input"><span class="xiaogu-friends-avatar-preview" data-friend-avatar-preview>图</span><input type="url" data-friend-avatar placeholder="https://example.com/avatar.png"></div></div>',
        '  <div class="xiaogu-friends-field is-wide"><label>站点描述</label><input type="text" data-friend-description maxlength="300" placeholder="简单介绍这个站点"></div>',
        '</div>',
        '<div class="xiaogu-friends-actions"><span class="xiaogu-friends-message" role="status"></span><button type="button" class="btn" data-friend-cancel hidden>取消编辑</button><button type="button" class="btn primary" data-friend-save>添加友链</button></div>',
        '<div class="xiaogu-friends-list"></div>'
    ].join('');
    source.insertAdjacentElement('afterend', panel);

    var nameInput = panel.querySelector('[data-friend-name]');
    var urlInput = panel.querySelector('[data-friend-url]');
    var avatarInput = panel.querySelector('[data-friend-avatar]');
    var descriptionInput = panel.querySelector('[data-friend-description]');
    var avatarPreview = panel.querySelector('[data-friend-avatar-preview]');
    var saveButton = panel.querySelector('[data-friend-save]');
    var cancelButton = panel.querySelector('[data-friend-cancel]');
    var message = panel.querySelector('.xiaogu-friends-message');
    var list = panel.querySelector('.xiaogu-friends-list');
    var count = panel.querySelector('.xiaogu-friends-count');

    function parseLinks(raw) {
        return raw.split(/\r?\n/).map(function (line) {
            var parts = line.split('|').map(function (part) { return part.trim(); });
            if (!parts[0] || !parts[1]) return null;
            return {name: parts[0], url: parts[1], avatar: parts[2] || '', description: parts[3] || ''};
        }).filter(Boolean);
    }

    function containsSeparator(values) {
        return values.some(function (value) { return value.indexOf('|') !== -1; });
    }

    function validHttpUrl(value, required) {
        if (!value) return !required;
        try {
            var parsed = new URL(value);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (error) {
            return false;
        }
    }

    function syncSource() {
        source.value = links.map(function (link) {
            return [link.name, link.url, link.avatar, link.description].join('|');
        }).join('\n');
    }

    function makeAvatar(link) {
        if (link.avatar) {
            var image = document.createElement('img');
            image.className = 'xiaogu-friend-card-avatar';
            image.src = link.avatar;
            image.alt = '';
            image.addEventListener('error', function () {
                var fallback = document.createElement('span');
                fallback.className = 'xiaogu-friend-card-avatar';
                fallback.textContent = link.name.slice(0, 1);
                image.replaceWith(fallback);
            });
            return image;
        }
        var fallback = document.createElement('span');
        fallback.className = 'xiaogu-friend-card-avatar';
        fallback.textContent = link.name.slice(0, 1);
        return fallback;
    }

    function resetForm() {
        editingIndex = -1;
        nameInput.value = '';
        urlInput.value = '';
        avatarInput.value = '';
        descriptionInput.value = '';
        saveButton.textContent = '添加友链';
        cancelButton.hidden = true;
        message.textContent = '';
        updateAvatarPreview();
    }

    function editLink(index) {
        var link = links[index];
        if (!link) return;
        editingIndex = index;
        nameInput.value = link.name;
        urlInput.value = link.url;
        avatarInput.value = link.avatar;
        descriptionInput.value = link.description;
        saveButton.textContent = '保存修改';
        cancelButton.hidden = false;
        message.textContent = '';
        updateAvatarPreview();
        nameInput.focus();
        panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function render() {
        list.replaceChildren();
        count.textContent = links.length + ' 个站点';
        if (!links.length) {
            var empty = document.createElement('p');
            empty.className = 'xiaogu-friends-empty';
            empty.textContent = '还没有友链，请在上方添加。';
            list.appendChild(empty);
            return;
        }

        links.forEach(function (link, index) {
            var card = document.createElement('article');
            card.className = 'xiaogu-friend-card';
            var main = document.createElement('div');
            main.className = 'xiaogu-friend-card-main';
            var copy = document.createElement('div');
            copy.className = 'xiaogu-friend-card-copy';
            var title = document.createElement('strong');
            title.textContent = link.name;
            var address = document.createElement('a');
            address.href = link.url;
            address.target = '_blank';
            address.rel = 'noopener noreferrer';
            address.textContent = link.url;
            copy.append(title, address);
            if (link.description) {
                var description = document.createElement('small');
                description.textContent = link.description;
                copy.appendChild(description);
            }
            main.append(makeAvatar(link), copy);

            var buttons = document.createElement('div');
            buttons.className = 'xiaogu-friend-card-buttons';
            var edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'btn btn-s';
            edit.textContent = '编辑';
            edit.addEventListener('click', function () { editLink(index); });
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-s';
            remove.textContent = '删除';
            remove.addEventListener('click', function () {
                if (!window.confirm('确认删除友链“' + link.name + '”吗？')) return;
                links.splice(index, 1);
                syncSource();
                resetForm();
                render();
            });
            buttons.append(edit, remove);
            card.append(main, buttons);
            list.appendChild(card);
        });
    }

    function updateAvatarPreview() {
        var url = avatarInput.value.trim();
        avatarPreview.replaceChildren();
        if (!url) {
            avatarPreview.textContent = (nameInput.value.trim() || '图').slice(0, 1);
            return;
        }
        var image = document.createElement('img');
        image.src = url;
        image.alt = '';
        image.style.width = '100%';
        image.style.height = '100%';
        image.style.borderRadius = 'inherit';
        image.style.objectFit = 'cover';
        image.addEventListener('error', function () {
            avatarPreview.textContent = (nameInput.value.trim() || '图').slice(0, 1);
        });
        avatarPreview.appendChild(image);
    }

    avatarInput.addEventListener('input', updateAvatarPreview);
    nameInput.addEventListener('input', updateAvatarPreview);
    cancelButton.addEventListener('click', resetForm);
    saveButton.addEventListener('click', function () {
        var link = {
            name: nameInput.value.trim(),
            url: urlInput.value.trim(),
            avatar: avatarInput.value.trim(),
            description: descriptionInput.value.trim()
        };
        message.style.color = '#c62828';
        if (!link.name) {
            message.textContent = '请填写站点名称。';
            nameInput.focus();
            return;
        }
        if (!validHttpUrl(link.url, true)) {
            message.textContent = '请填写正确的 HTTP/HTTPS 网站地址。';
            urlInput.focus();
            return;
        }
        if (!validHttpUrl(link.avatar, false)) {
            message.textContent = '请填写正确的 HTTP/HTTPS 头像地址。';
            avatarInput.focus();
            return;
        }
        if (containsSeparator([link.name, link.url, link.avatar, link.description])) {
            message.textContent = '站点信息中不能包含 | 符号。';
            return;
        }
        var duplicate = links.some(function (item, index) {
            return index !== editingIndex && item.url.replace(/\/$/, '').toLowerCase() === link.url.replace(/\/$/, '').toLowerCase();
        });
        if (duplicate) {
            message.textContent = '该网站地址已在友链列表中。';
            return;
        }

        if (editingIndex >= 0) links[editingIndex] = link;
        else links.push(link);
        syncSource();
        render();
        resetForm();
        message.style.color = '#2e7d32';
        message.textContent = '已更新，请点击页面底部“保存设置”。';
    });

    source.addEventListener('change', function () {
        links = parseLinks(source.value);
        resetForm();
        render();
    });

    syncSource();
    updateAvatarPreview();
    render();
}());
