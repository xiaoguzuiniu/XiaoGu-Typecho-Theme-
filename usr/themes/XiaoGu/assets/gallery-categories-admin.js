(function () {
    'use strict';

    var source = document.querySelector('textarea[name="galleryAlbums"]');
    if (!source) return;

    var categories = parse(source.value);
    if (!categories.length) categories = ['生活片刻'];

    source.classList.add('xiaogu-gallery-categories-source');
    var option = source.closest('.typecho-option');
    if (option) option.classList.add('xiaogu-gallery-categories-option');

    var manager = document.createElement('section');
    manager.className = 'xiaogu-gallery-categories-admin';
    manager.innerHTML = [
        '<div class="xiaogu-gallery-categories-heading">',
        '  <div><h3>相册分类管理</h3><p>先维护分类，编辑相册时即可直接选择。</p></div>',
        '  <strong data-gallery-category-count></strong>',
        '</div>',
        '<div class="xiaogu-gallery-category-add">',
        '  <input type="text" maxlength="60" placeholder="输入新分类名称" data-gallery-category-new>',
        '  <button type="button" class="btn primary" data-gallery-category-add>添加分类</button>',
        '</div>',
        '<div class="xiaogu-gallery-category-message" role="status" data-gallery-category-message></div>',
        '<div class="xiaogu-gallery-category-list" data-gallery-category-list></div>',
        '<p class="xiaogu-gallery-category-note">删除分类不会修改已发布照片；旧分类仍会在相册编辑页保留。</p>'
    ].join('');
    source.insertAdjacentElement('afterend', manager);

    var list = manager.querySelector('[data-gallery-category-list]');
    var count = manager.querySelector('[data-gallery-category-count]');
    var input = manager.querySelector('[data-gallery-category-new]');
    var message = manager.querySelector('[data-gallery-category-message]');

    function clean(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().slice(0, 60);
    }

    function parse(raw) {
        var result = [];
        String(raw || '').split(/\r?\n/).forEach(function (line) {
            var name = clean(line);
            if (name && result.indexOf(name) === -1) result.push(name);
        });
        return result;
    }

    function sync() {
        source.value = categories.join('\n');
        source.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function setMessage(text, error) {
        message.textContent = text || '';
        message.classList.toggle('is-error', Boolean(error));
    }

    function button(text, title, disabled, handler, danger) {
        var element = document.createElement('button');
        element.type = 'button';
        element.className = 'btn btn-s' + (danger ? ' is-danger' : '');
        element.textContent = text;
        element.title = title;
        element.disabled = disabled;
        element.addEventListener('click', handler);
        return element;
    }

    function render() {
        list.replaceChildren();
        count.textContent = categories.length + ' 个分类';
        categories.forEach(function (category, index) {
            var row = document.createElement('div');
            var name = document.createElement('input');
            var actions = document.createElement('div');
            row.className = 'xiaogu-gallery-category-row';
            name.type = 'text';
            name.maxLength = 60;
            name.value = category;
            name.setAttribute('aria-label', '分类名称');
            name.addEventListener('change', function () {
                var next = clean(name.value);
                if (!next) {
                    name.value = categories[index];
                    setMessage('分类名称不能为空。', true);
                    return;
                }
                if (categories.some(function (item, itemIndex) {
                    return itemIndex !== index && item === next;
                })) {
                    name.value = categories[index];
                    setMessage('分类名称不能重复。', true);
                    return;
                }
                categories[index] = next;
                setMessage('分类名称已更新，记得保存设置。', false);
                sync();
                render();
            });
            actions.className = 'xiaogu-gallery-category-actions';
            actions.append(
                button('↑', '向前移动', index === 0, function () { move(index, -1); }),
                button('↓', '向后移动', index === categories.length - 1, function () { move(index, 1); }),
                button('删除', '删除分类', categories.length === 1, function () {
                    categories.splice(index, 1);
                    setMessage('分类已删除，记得保存设置。', false);
                    sync();
                    render();
                }, true)
            );
            row.append(name, actions);
            list.appendChild(row);
        });
    }

    function move(index, direction) {
        var target = index + direction;
        if (target < 0 || target >= categories.length) return;
        var category = categories.splice(index, 1)[0];
        categories.splice(target, 0, category);
        setMessage('分类顺序已更新，记得保存设置。', false);
        sync();
        render();
    }

    function addCategory() {
        var category = clean(input.value);
        if (!category) {
            setMessage('请输入分类名称。', true);
            input.focus();
            return;
        }
        if (categories.indexOf(category) !== -1) {
            setMessage('这个分类已经存在。', true);
            input.focus();
            return;
        }
        categories.push(category);
        input.value = '';
        setMessage('分类已添加，记得保存设置。', false);
        sync();
        render();
        input.focus();
    }

    manager.querySelector('[data-gallery-category-add]').addEventListener('click', addCategory);
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            addCategory();
        }
    });

    sync();
    render();
}());
