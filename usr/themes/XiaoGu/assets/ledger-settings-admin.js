(function () {
    'use strict';

    var categoriesSource = document.querySelector('textarea[name="ledgerCategories"]');
    var budgetsSource = document.querySelector('textarea[name="ledgerMonthlyBudgets"]');
    var tokenInput = document.querySelector('input[name="ledgerApiToken"]');
    if (!categoriesSource || !budgetsSource) return;

    categoriesSource.classList.add('xiaogu-ledger-source');
    budgetsSource.classList.add('xiaogu-ledger-source');

    function cleanName(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().slice(0, 60);
    }

    function parseCategories(raw) {
        var result = [];
        String(raw || '').split(/\r?\n/).forEach(function (line) {
            var name = cleanName(line);
            if (name && result.indexOf(name) === -1) result.push(name);
        });
        return result.length ? result : ['餐饮', '交通', '购物', '娱乐', '居住', '医疗', '其他'];
    }

    function parseBudgets(raw) {
        var result = [];
        String(raw || '').split(/\r?\n/).forEach(function (line) {
            var parts = line.split('|');
            var month = String(parts[0] || '').trim();
            var amount = String(parts[1] || '').trim();
            if (/^\d{4}-(0[1-9]|1[0-2])$/.test(month) && amount !== '' && !isNaN(Number(amount))) {
                result.push({month: month, amount: Math.max(0, Number(amount)).toFixed(2)});
            }
        });
        result.sort(function (a, b) { return b.month.localeCompare(a.month); });
        return result.filter(function (item, index) {
            return result.findIndex(function (candidate) { return candidate.month === item.month; }) === index;
        });
    }

    var categories = parseCategories(categoriesSource.value);
    var budgets = parseBudgets(budgetsSource.value);

    function currentPeriodMonth() {
        var date = new Date();
        if (date.getDate() < 10) date.setMonth(date.getMonth() - 1);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    }

    var manager = document.createElement('section');
    manager.className = 'xiaogu-ledger-admin';
    manager.innerHTML = [
        '<section class="xiaogu-ledger-admin-card">',
        '  <div class="xiaogu-ledger-admin-heading"><div><h3>消费类型</h3><p>快捷指令记账时会从这里选择类型。</p></div><strong data-ledger-category-count></strong></div>',
        '  <div class="xiaogu-ledger-add"><input type="text" maxlength="60" placeholder="例如：旅行" data-ledger-category-input><button type="button" class="btn primary" data-ledger-category-add>添加类型</button></div>',
        '  <div class="xiaogu-ledger-rows" data-ledger-category-list></div>',
        '</section>',
        '<section class="xiaogu-ledger-admin-card">',
        '  <div class="xiaogu-ledger-admin-heading"><div><h3>指定账期生活费</h3><p>所选月份代表当月 10 日至下月 10 日前；未添加时使用默认生活费。</p></div><strong data-ledger-budget-count></strong></div>',
        '  <div class="xiaogu-ledger-add xiaogu-ledger-budget-add"><input type="month" data-ledger-budget-month><input type="number" min="0" step="0.01" placeholder="金额" data-ledger-budget-amount><button type="button" class="btn primary" data-ledger-budget-add>添加月份</button></div>',
        '  <div class="xiaogu-ledger-rows" data-ledger-budget-list></div>',
        '</section>',
        '<section class="xiaogu-ledger-admin-card xiaogu-ledger-record-manager">',
        '  <div class="xiaogu-ledger-admin-heading"><div><h3>账单记录</h3><p>按账期查看；删除时会同时清理 Typecho 附件和七牛云照片。</p></div><strong data-ledger-entry-count></strong></div>',
        '  <div class="xiaogu-ledger-record-toolbar"><input type="month" data-ledger-entry-month><button type="button" class="btn" data-ledger-entry-refresh>查看账期</button></div>',
        '  <p class="xiaogu-ledger-record-state" role="status" data-ledger-entry-state></p>',
        '  <div class="xiaogu-ledger-record-list" data-ledger-entry-list></div>',
        '</section>',
        '<p class="xiaogu-ledger-message" role="status" data-ledger-message></p>'
    ].join('');
    budgetsSource.insertAdjacentElement('afterend', manager);

    var categoryList = manager.querySelector('[data-ledger-category-list]');
    var categoryCount = manager.querySelector('[data-ledger-category-count]');
    var categoryInput = manager.querySelector('[data-ledger-category-input]');
    var budgetList = manager.querySelector('[data-ledger-budget-list]');
    var budgetCount = manager.querySelector('[data-ledger-budget-count]');
    var budgetMonth = manager.querySelector('[data-ledger-budget-month]');
    var budgetAmount = manager.querySelector('[data-ledger-budget-amount]');
    var entryMonth = manager.querySelector('[data-ledger-entry-month]');
    var entryRefresh = manager.querySelector('[data-ledger-entry-refresh]');
    var entryCount = manager.querySelector('[data-ledger-entry-count]');
    var entryState = manager.querySelector('[data-ledger-entry-state]');
    var entryList = manager.querySelector('[data-ledger-entry-list]');
    var message = manager.querySelector('[data-ledger-message]');
    var defaultPeriodMonth = currentPeriodMonth();
    budgetMonth.value = defaultPeriodMonth;
    entryMonth.value = defaultPeriodMonth;

    function setMessage(value, error) {
        message.textContent = value || '';
        message.classList.toggle('is-error', Boolean(error));
    }

    function syncCategories() {
        categoriesSource.value = categories.join('\n');
        categoriesSource.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function syncBudgets() {
        budgetsSource.value = budgets.map(function (item) {
            return item.month + '|' + Number(item.amount).toFixed(2);
        }).join('\n');
        budgetsSource.dispatchEvent(new Event('change', {bubbles: true}));
    }

    function removeButton(handler, disabled) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-s is-danger';
        button.textContent = '删除';
        button.disabled = Boolean(disabled);
        button.addEventListener('click', handler);
        return button;
    }

    function ledgerApiUrl() {
        var config = window.XiaoGuLedgerAdminConfig || {};
        return String(config.apiUrl || '').trim();
    }

    function ledgerHeaders(withJson) {
        var headers = {'Authorization': 'Bearer ' + (tokenInput ? tokenInput.value.trim() : '')};
        if (withJson) headers['Content-Type'] = 'application/json';
        return headers;
    }

    function apiError(response, body) {
        if (body && body.message) return body.message;
        return '请求失败（HTTP ' + response.status + '）';
    }

    function recordText(tag, value) {
        var element = document.createElement(tag);
        element.textContent = value;
        return element;
    }

    function renderEntries(entries) {
        entryList.replaceChildren();
        entryCount.textContent = entries.length + ' 笔';
        if (!entries.length) {
            entryState.textContent = '这个账期还没有账单。';
            return;
        }
        entryState.textContent = '';

        entries.forEach(function (entry) {
            var row = document.createElement('article');
            var image = document.createElement('img');
            var copy = document.createElement('div');
            var heading = document.createElement('div');
            var details = document.createElement('p');
            var amount = recordText('strong', '− ¥' + Number(entry.amount || 0).toFixed(2));
            var remove = document.createElement('button');
            row.className = 'xiaogu-ledger-record';
            image.src = entry.thumbnail_url || entry.receipt_url || '';
            image.alt = '';
            image.loading = 'lazy';
            image.addEventListener('error', function () { image.classList.add('is-error'); }, {once: true});
            copy.className = 'xiaogu-ledger-record-copy';
            heading.append(recordText('strong', entry.category || '其他'), recordText('time', entry.spent_at || ''));
            details.textContent = entry.note || '无备注';
            copy.append(heading, details);
            amount.className = 'xiaogu-ledger-record-amount';
            remove.type = 'button';
            remove.className = 'btn btn-s is-danger';
            remove.textContent = '删除';
            remove.addEventListener('click', function () { deleteEntry(entry, remove); });
            row.append(image, copy, amount, remove);
            entryList.appendChild(row);
        });
    }

    function loadEntries() {
        var token = tokenInput ? tokenInput.value.trim() : '';
        var apiUrl = ledgerApiUrl();
        if (!apiUrl) {
            entryState.textContent = '记账接口地址不可用。';
            entryState.classList.add('is-error');
            return;
        }
        if (token.length < 24) {
            entryCount.textContent = '';
            entryList.replaceChildren();
            entryState.textContent = '请先生成密钥并保存设置，再查看账单。';
            entryState.classList.remove('is-error');
            return;
        }
        entryRefresh.disabled = true;
        entryState.classList.remove('is-error');
        entryState.textContent = '正在读取账单…';
        fetch(apiUrl + '?manage=1&month=' + encodeURIComponent(entryMonth.value), {
            method: 'GET',
            headers: ledgerHeaders(false),
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (response) {
            return response.json().catch(function () { return null; }).then(function (body) {
                if (!response.ok || !body || body.code !== 0) throw new Error(apiError(response, body));
                return body;
            });
        }).then(function (body) {
            renderEntries(Array.isArray(body.data.entries) ? body.data.entries : []);
        }).catch(function (error) {
            entryCount.textContent = '';
            entryList.replaceChildren();
            entryState.textContent = error.message || '账单读取失败。';
            entryState.classList.add('is-error');
        }).finally(function () {
            entryRefresh.disabled = false;
        });
    }

    function deleteEntry(entry, button) {
        var prompt = '确定删除这笔账单吗？\n' + (entry.spent_at || '') + ' · '
            + (entry.category || '其他') + ' · ¥' + Number(entry.amount || 0).toFixed(2)
            + '\n照片也会从 Typecho 和七牛云中删除，此操作无法撤销。';
        if (!window.confirm(prompt)) return;

        button.disabled = true;
        button.textContent = '删除中…';
        entryState.classList.remove('is-error');
        entryState.textContent = '正在删除账单和照片…';
        fetch(ledgerApiUrl(), {
            method: 'DELETE',
            headers: ledgerHeaders(true),
            credentials: 'same-origin',
            body: JSON.stringify({id: entry.id})
        }).then(function (response) {
            return response.json().catch(function () { return null; }).then(function (body) {
                if (!response.ok || !body || body.code !== 0) throw new Error(apiError(response, body));
                return body;
            });
        }).then(function (body) {
            entryState.textContent = body.message || '账单已删除。';
            loadEntries();
        }).catch(function (error) {
            entryState.textContent = error.message || '账单删除失败。';
            entryState.classList.add('is-error');
            button.disabled = false;
            button.textContent = '删除';
        });
    }

    function renderCategories() {
        categoryList.replaceChildren();
        categoryCount.textContent = categories.length + ' 个类型';
        categories.forEach(function (category, index) {
            var row = document.createElement('div');
            var input = document.createElement('input');
            row.className = 'xiaogu-ledger-row';
            input.type = 'text';
            input.maxLength = 60;
            input.value = category;
            input.addEventListener('change', function () {
                var next = cleanName(input.value);
                if (!next || categories.some(function (item, itemIndex) { return itemIndex !== index && item === next; })) {
                    input.value = categories[index];
                    setMessage('类型不能为空或重复。', true);
                    return;
                }
                categories[index] = next;
                syncCategories();
                setMessage('消费类型已更新，记得保存设置。', false);
            });
            row.append(input, removeButton(function () {
                categories.splice(index, 1);
                syncCategories();
                renderCategories();
                setMessage('消费类型已删除，记得保存设置。', false);
            }, categories.length === 1));
            categoryList.appendChild(row);
        });
    }

    function renderBudgets() {
        budgetList.replaceChildren();
        budgetCount.textContent = budgets.length + ' 个账期';
        budgets.forEach(function (budget, index) {
            var row = document.createElement('div');
            var month = document.createElement('strong');
            var amount = document.createElement('input');
            row.className = 'xiaogu-ledger-row xiaogu-ledger-budget-row';
            month.textContent = budget.month;
            amount.type = 'number';
            amount.min = '0';
            amount.step = '0.01';
            amount.value = budget.amount;
            amount.addEventListener('change', function () {
                if (amount.value === '' || isNaN(Number(amount.value)) || Number(amount.value) < 0) {
                    amount.value = budget.amount;
                    setMessage('生活费必须是大于等于 0 的数字。', true);
                    return;
                }
                budget.amount = Number(amount.value).toFixed(2);
                syncBudgets();
                setMessage('本账期生活费已更新，记得保存设置。', false);
            });
            row.append(month, amount, removeButton(function () {
                budgets.splice(index, 1);
                syncBudgets();
                renderBudgets();
                setMessage('账期设置已删除，将改用默认生活费。', false);
            }));
            budgetList.appendChild(row);
        });
    }

    manager.querySelector('[data-ledger-category-add]').addEventListener('click', function () {
        var name = cleanName(categoryInput.value);
        if (!name || categories.indexOf(name) !== -1) {
            setMessage('请输入不重复的消费类型。', true);
            return;
        }
        categories.push(name);
        categoryInput.value = '';
        syncCategories();
        renderCategories();
        setMessage('消费类型已添加，记得保存设置。', false);
    });

    manager.querySelector('[data-ledger-budget-add]').addEventListener('click', function () {
        var month = budgetMonth.value;
        var amount = Number(budgetAmount.value);
        if (!/^\d{4}-\d{2}$/.test(month) || budgetAmount.value === '' || isNaN(amount) || amount < 0) {
            setMessage('请选择月份并填写正确金额。', true);
            return;
        }
        var existing = budgets.find(function (item) { return item.month === month; });
        if (existing) existing.amount = amount.toFixed(2);
        else budgets.push({month: month, amount: amount.toFixed(2)});
        budgets.sort(function (a, b) { return b.month.localeCompare(a.month); });
        budgetAmount.value = '';
        syncBudgets();
        renderBudgets();
        setMessage('账期生活费已保存到表单，记得点击保存设置。', false);
    });

    entryRefresh.addEventListener('click', loadEntries);
    entryMonth.addEventListener('change', loadEntries);

    if (tokenInput) {
        var tools = document.createElement('div');
        var generate = document.createElement('button');
        var copy = document.createElement('button');
        var status = document.createElement('span');
        tools.className = 'xiaogu-ledger-token-tools';
        generate.type = copy.type = 'button';
        generate.className = copy.className = 'btn';
        generate.textContent = '生成安全密钥';
        copy.textContent = '复制密钥';
        tools.append(generate, copy, status);
        tokenInput.insertAdjacentElement('afterend', tools);
        generate.addEventListener('click', function () {
            if (!window.crypto || !window.crypto.getRandomValues) {
                status.textContent = '当前浏览器不支持安全随机数。';
                return;
            }
            var bytes = new Uint8Array(24);
            window.crypto.getRandomValues(bytes);
            tokenInput.value = Array.from(bytes).map(function (value) {
                return value.toString(16).padStart(2, '0');
            }).join('');
            tokenInput.dispatchEvent(new Event('change', {bubbles: true}));
            status.textContent = '已生成，请保存设置。';
        });
        copy.addEventListener('click', function () {
            var value = tokenInput.value.trim();
            if (!value) {
                status.textContent = '请先生成密钥。';
                return;
            }
            var promise = navigator.clipboard && window.isSecureContext
                ? navigator.clipboard.writeText(value)
                : Promise.reject(new Error('clipboard unavailable'));
            promise.then(function () { status.textContent = '密钥已复制。'; }).catch(function () {
                tokenInput.focus();
                tokenInput.select();
                status.textContent = '请长按或使用快捷键复制。';
            });
        });
    }

    renderCategories();
    renderBudgets();
    loadEntries();
}());
