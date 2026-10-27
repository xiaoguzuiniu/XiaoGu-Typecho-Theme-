(() => {
    const root = document.getElementById('fitness-page');
    const configNode = document.getElementById('fitness-config');
    if (!root || !configNode) return;

    const config = JSON.parse(configNode.textContent);
    const completedDates = new Set(config.completedDates || []);
    const today = new Date();
    const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let visibleMonth = new Date(todayDate.getFullYear(), todayDate.getMonth(), 1);
    let activeDateKey = null;
    let requestController = null;
    const daysNode = document.getElementById('fitness-calendar-days');
    const monthTitle = document.getElementById('fitness-month-title');
    const dayTitle = document.getElementById('fitness-day-title');
    const workoutContent = document.getElementById('fitness-workout-content');
    const dialog = document.getElementById('fitness-checkin-dialog');
    const detailTitle = document.getElementById('fitness-checkin-title');
    const detailContent = document.getElementById('fitness-checkin-content');
    const weekdays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];

    function dateKey(date) {
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }

    function renderCalendar() {
        const year = visibleMonth.getFullYear();
        const month = visibleMonth.getMonth();
        monthTitle.textContent = `${year} 年 ${month + 1} 月`;
        daysNode.replaceChildren();
        const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
        const numberOfDays = new Date(year, month + 1, 0).getDate();
        for (let i = 0; i < firstWeekday; i++) {
            const blank = document.createElement('span');
            blank.className = 'fitness-calendar-blank';
            blank.setAttribute('aria-hidden', 'true');
            daysNode.appendChild(blank);
        }
        for (let day = 1; day <= numberOfDays; day++) {
            const date = new Date(year, month, day);
            const key = dateKey(date);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fitness-calendar-day';
            button.textContent = String(day);
            button.setAttribute('aria-label', `${year} 年 ${month + 1} 月 ${day} 日${completedDates.has(key) ? '，已打卡' : ''}，查看打卡详情`);
            if (key === dateKey(todayDate)) button.classList.add('is-today');
            if (completedDates.has(key)) button.classList.add('is-completed');
            if (activeDateKey === key) button.classList.add('is-selected');
            button.addEventListener('click', () => openCheckin(date));
            daysNode.appendChild(button);
        }
    }

    function renderTodayWorkout() {
        dayTitle.textContent = `${todayDate.getMonth() + 1} 月 ${todayDate.getDate()} 日 · ${weekdays[todayDate.getDay()]}`;
        const template = document.getElementById(todayDate.getDay() === 1 ? 'fitness-monday-template' : 'fitness-empty-template');
        workoutContent.replaceChildren(template.content.cloneNode(true));
    }

    function showMessage(message) {
        const paragraph = document.createElement('p');
        paragraph.className = 'fitness-checkin-message';
        paragraph.textContent = message;
        detailContent.replaceChildren(paragraph);
    }

    function renderEntries(entries) {
        detailContent.replaceChildren();
        if (!entries.length) {
            showMessage(completedDates.has(activeDateKey)
                ? '这一天有旧的完成标记，暂无照片和文字记录。'
                : '这一天还没有打卡记录。');
            return;
        }
        entries.forEach((entry) => {
            const card = document.createElement('article');
            card.className = 'fitness-checkin-entry';
            const time = document.createElement('time');
            time.textContent = entry.created_at || '';
            card.appendChild(time);
            if (entry.image_url) {
                const image = document.createElement('img');
                image.src = entry.thumbnail_url || entry.image_url;
                image.alt = '这一天的健身打卡照片';
                image.loading = 'lazy';
                image.decoding = 'async';
                card.appendChild(image);
            }
            if (entry.note) {
                const note = document.createElement('p');
                note.textContent = entry.note;
                card.appendChild(note);
            } else if (!entry.image_url) {
                const empty = document.createElement('p');
                empty.textContent = '已完成训练';
                card.appendChild(empty);
            }
            detailContent.appendChild(card);
        });
    }

    async function openCheckin(date) {
        if (requestController) requestController.abort();
        requestController = new AbortController();
        activeDateKey = dateKey(date);
        renderCalendar();
        detailTitle.textContent = `${date.getFullYear()} 年 ${date.getMonth() + 1} 月 ${date.getDate()} 日`;
        showMessage('正在读取打卡记录…');
        dialog.showModal();
        try {
            const url = new URL(config.endpoint, window.location.href);
            url.searchParams.set('date', activeDateKey);
            const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', signal: requestController.signal});
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || '读取失败');
            renderEntries(result.data.entries || []);
        } catch (error) {
            if (error.name !== 'AbortError') showMessage(error.message || '读取失败，请稍后重试。');
        }
    }

    function closeCheckin() {
        if (requestController) requestController.abort();
        dialog.close();
    }

    dialog.addEventListener('close', () => {
        if (requestController) requestController.abort();
        activeDateKey = null;
        renderCalendar();
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeCheckin();
    });
    document.getElementById('fitness-checkin-close').addEventListener('click', closeCheckin);
    document.getElementById('fitness-prev-month').addEventListener('click', () => {
        visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
        renderCalendar();
    });
    document.getElementById('fitness-next-month').addEventListener('click', () => {
        visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
        renderCalendar();
    });
    document.getElementById('fitness-today').addEventListener('click', () => {
        visibleMonth = new Date(todayDate.getFullYear(), todayDate.getMonth(), 1);
        renderCalendar();
    });

    renderCalendar();
    renderTodayWorkout();
})();
