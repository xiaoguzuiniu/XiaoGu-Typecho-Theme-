(() => {
    const root = document.getElementById('fitness-page');
    const configNode = document.getElementById('fitness-config');
    if (!root || !configNode) return;

    const config = JSON.parse(configNode.textContent);
    const completedDates = new Set(config.completedDates || []);
    const today = new Date();
    let selected = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let visibleMonth = new Date(selected.getFullYear(), selected.getMonth(), 1);
    const daysNode = document.getElementById('fitness-calendar-days');
    const monthTitle = document.getElementById('fitness-month-title');
    const dayTitle = document.getElementById('fitness-day-title');
    const dayStatus = document.getElementById('fitness-day-status');
    const workoutContent = document.getElementById('fitness-workout-content');
    const completeButton = document.getElementById('fitness-complete-button');
    const saveMessage = document.getElementById('fitness-save-message');
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
            button.setAttribute('aria-label', `${year} 年 ${month + 1} 月 ${day} 日${completedDates.has(key) ? '，已完成' : ''}`);
            button.setAttribute('aria-pressed', dateKey(selected) === key ? 'true' : 'false');
            if (key === dateKey(today)) button.classList.add('is-today');
            if (completedDates.has(key)) button.classList.add('is-completed');
            if (dateKey(selected) === key) button.classList.add('is-selected');
            button.addEventListener('click', () => {
                selected = date;
                renderCalendar();
                renderWorkout();
            });
            daysNode.appendChild(button);
        }
    }

    function renderWorkout() {
        const key = dateKey(selected);
        const done = completedDates.has(key);
        dayTitle.textContent = `${selected.getMonth() + 1} 月 ${selected.getDate()} 日 · ${weekdays[selected.getDay()]}`;
        dayStatus.textContent = done ? '已完成' : '未完成';
        dayStatus.classList.toggle('is-completed', done);
        const template = document.getElementById(selected.getDay() === 1 ? 'fitness-monday-template' : 'fitness-empty-template');
        workoutContent.replaceChildren(template.content.cloneNode(true));
        if (completeButton) {
            completeButton.textContent = done ? '取消完成标记' : '标记这一天已完成';
            completeButton.setAttribute('aria-pressed', done ? 'true' : 'false');
            completeButton.dataset.date = key;
        }
        if (saveMessage) saveMessage.textContent = '';
    }

    function changeMonth(delta) {
        visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + delta, 1);
        renderCalendar();
    }

    document.getElementById('fitness-prev-month').addEventListener('click', () => changeMonth(-1));
    document.getElementById('fitness-next-month').addEventListener('click', () => changeMonth(1));
    document.getElementById('fitness-today').addEventListener('click', () => {
        selected = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        visibleMonth = new Date(selected.getFullYear(), selected.getMonth(), 1);
        renderCalendar();
        renderWorkout();
    });

    if (completeButton && config.canEdit) {
        completeButton.addEventListener('click', async () => {
            const key = completeButton.dataset.date;
            const shouldComplete = !completedDates.has(key);
            completeButton.disabled = true;
            saveMessage.textContent = '保存中…';
            try {
                const body = new URLSearchParams({date: key, completed: shouldComplete ? '1' : '0', _: config.token});
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString(),
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || '保存失败');
                if (shouldComplete) completedDates.add(key);
                else completedDates.delete(key);
                renderCalendar();
                renderWorkout();
                saveMessage.textContent = '已保存';
            } catch (error) {
                saveMessage.textContent = error.message || '保存失败，请重试';
            } finally {
                completeButton.disabled = false;
            }
        });
    }

    renderCalendar();
    renderWorkout();
})();
