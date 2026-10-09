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

    var manager = document.createElement('section');
    manager.className = 'xiaogu-ledger-admin';
    manager.innerHTML = [
        '<section class="xiaogu-ledger-admin-card">',
        '  <div class="xiaogu-ledger-admin-heading"><div><h3>消费类型</h3><p>快捷指令记账时会从这里选择类型。</p></div><strong data-ledger-category-count></strong></div>',
        '  <div class="xiaogu-ledger-add"><input type="text" maxlength="60" placeholder="例如：旅行" data-ledger-category-input><button type="button" class="btn primary" data-ledger-category-add>添加类型</button></div>',
        '  <div class="xiaogu-ledger-rows" data-ledger-category-list></div>',
        '</section>',
        '<section class="xiaogu-ledger-admin-card">',
        '  <div class="xiaogu-ledger-admin-heading"><div><h3>指定月份生活费</h3><p>未添加的月份使用上方默认生活费。</p></div><strong data-ledger-budget-count></strong></div>',
        '  <div class="xiaogu-ledger-add xiaogu-ledger-budget-add"><input type="month" data-ledger-budget-month><input type="number" min="0" step="0.01" placeholder="金额" data-ledger-budget-amount><button type="button" class="btn primary" data-ledger-budget-add>添加月份</button></div>',
        '  <div class="xiaogu-ledger-rows" data-ledger-budget-list></div>',
        '</section>',
        '<p class="xiaogu-ledger-message" role="status" data-ledger-message></p>'
    ].join('');
    budgetsSource.closest('.typecho-option').insertAdjacentElement('afterend', manager);

    var categoryList = manager.querySelector('[data-ledger-category-list]');
    var categoryCount = manager.querySelector('[data-ledger-category-count]');
    var categoryInput = manager.querySelector('[data-ledger-category-input]');
    var budgetList = manager.querySelector('[data-ledger-budget-list]');
    var budgetCount = manager.querySelector('[data-ledger-budget-count]');
    var budgetMonth = manager.querySelector('[data-ledger-budget-month]');
    var budgetAmount = manager.querySelector('[data-ledger-budget-amount]');
    var message = manager.querySelector('[data-ledger-message]');
    budgetMonth.value = new Date().toISOString().slice(0, 7);

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
        budgetCount.textContent = budgets.length + ' 个月份';
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
                setMessage('本月生活费已更新，记得保存设置。', false);
            });
            row.append(month, amount, removeButton(function () {
                budgets.splice(index, 1);
                syncBudgets();
                renderBudgets();
                setMessage('月份设置已删除，将改用默认生活费。', false);
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
        setMessage('月份生活费已保存到表单，记得点击保存设置。', false);
    });

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
}());

