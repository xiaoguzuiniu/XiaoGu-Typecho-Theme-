(() => {
    const page = document.getElementById('overtime-page');
    const dataNode = document.getElementById('overtime-page-data');
    if (!page || !dataNode) return;
    const entries = JSON.parse(dataNode.textContent);
    const buttons = Array.from(page.querySelectorAll('.overtime-calendar-day'));
    const title = document.getElementById('overtime-detail-date');
    const content = document.getElementById('overtime-detail-content');

    const formatHours = (value) => `${Number(value).toLocaleString('zh-CN', {maximumFractionDigits: 2})} h`;
    const formatPay = (value) => `¥${Number(value).toLocaleString('zh-CN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    function showDate(date) {
        buttons.forEach((button) => {
            const selected = button.dataset.date === date;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        const [year, month, day] = date.split('-');
        title.textContent = `${year} 年 ${Number(month)} 月 ${Number(day)} 日`;
        content.replaceChildren();
        const entry = entries[date];
        if (!entry) {
            const empty = document.createElement('p');
            empty.className = 'overtime-detail-empty';
            empty.textContent = '这一天还没有加班记录。';
            content.appendChild(empty);
            return;
        }

        const metrics = document.createElement('div');
        metrics.className = 'overtime-detail-metrics';
        [['日常加班', formatHours(entry.weekday_hours)], ['周末加班', formatHours(entry.weekend_hours)], ['加班费', formatPay(entry.pay)]].forEach(([label, value]) => {
            const item = document.createElement('div');
            const caption = document.createElement('span');
            const amount = document.createElement('strong');
            caption.textContent = label;
            amount.textContent = value;
            item.append(caption, amount);
            metrics.appendChild(item);
        });
        content.appendChild(metrics);
        if (entry.note) {
            const note = document.createElement('p');
            note.className = 'overtime-detail-note';
            note.textContent = entry.note;
            content.appendChild(note);
        }
    }

    buttons.forEach((button) => button.addEventListener('click', () => showDate(button.dataset.date)));
    showDate(page.dataset.initialDate);
})();
