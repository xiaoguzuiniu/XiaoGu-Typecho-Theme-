(() => {
    const search = document.getElementById('music-search');
    const list = document.getElementById('music-list');
    const status = document.getElementById('music-status');
    const count = document.getElementById('music-count');
    const player = document.getElementById('music-player');
    const audio = document.getElementById('music-audio');
    const form = document.getElementById('music-add-form');
    if (!search || !list || !audio) return;
    let timer;
    let controller;
    async function load() {
        if (controller) controller.abort();
        controller = new AbortController();
        status.hidden = false;
        status.textContent = '正在加载歌单…';
        try {
            const query = search.value.trim();
            const response = await fetch('/api/music/tracks' + (query ? '?q=' + encodeURIComponent(query) : ''), {signal: controller.signal});
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || '歌单加载失败');
            const tracks = body.data.tracks || [];
            list.replaceChildren();
            tracks.forEach((track, index) => {
                const item = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'music-track';
                const number = document.createElement('span'); number.className = 'music-track-index'; number.textContent = String(index + 1).padStart(2, '0');
                const info = document.createElement('span'); info.className = 'music-track-info';
                const title = document.createElement('strong'); title.textContent = track.title;
                const sub = document.createElement('small'); sub.textContent = [track.artist, track.album].filter(Boolean).join(' · ') || '未知歌手';
                info.append(title, sub);
                const play = document.createElement('span'); play.className = 'music-track-play'; play.textContent = '▶'; play.setAttribute('aria-hidden', 'true');
                button.append(number, info, play);
                button.addEventListener('click', () => {
                    list.querySelectorAll('.is-playing').forEach(node => node.classList.remove('is-playing'));
                    button.classList.add('is-playing');
                    document.getElementById('music-current-title').textContent = track.title;
                    document.getElementById('music-current-artist').textContent = track.artist || '未知歌手';
                    player.hidden = false;
                    audio.src = track.audio_url;
                    audio.play().catch(() => {});
                });
                item.append(button); list.append(item);
            });
            count.textContent = tracks.length + ' 首';
            status.textContent = query ? '没有找到匹配的歌曲。' : '歌单还是空的，上传一首音乐开始吧。';
            status.hidden = tracks.length > 0;
        } catch (error) {
            if (error.name === 'AbortError') return;
            status.textContent = error.message || '歌单加载失败';
        }
    }
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
    if (form) form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = document.getElementById('music-upload-status');
        const button = form.querySelector('button[type="submit"]');
        const token = form.elements.token.value.trim();
        const songId = form.elements.song_id.value.trim();
        const payload = new FormData(form);
        payload.delete('token');
        if (!songId) payload.delete('song_id');
        button.disabled = true;
        message.textContent = '正在上传…';
        try {
            const response = await fetch('/api/music/' + (songId ? 'imports' : 'tracks'), {
                method: 'POST', headers: {'Authorization': 'Bearer ' + token}, body: payload,
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message || '上传失败');
            message.textContent = '已加入歌单';
            form.elements.audio.value = '';
            form.elements.song_id.value = '';
            search.value = '';
            load();
        } catch (error) {
            message.textContent = error.message || '上传失败';
        } finally {
            button.disabled = false;
        }
    });
    load();
})();
