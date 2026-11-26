(() => {
    const page = document.querySelector('.music-page');
    const search = document.getElementById('music-search');
    const list = document.getElementById('music-list');
    const status = document.getElementById('music-status');
    const count = document.getElementById('music-count');
    const player = document.getElementById('music-player');
    const audio = document.getElementById('music-audio');
    const now = document.getElementById('music-hero-now');
    const currentTitle = document.getElementById('music-current-title');
    const currentArtist = document.getElementById('music-current-artist');
    if (!page || !search || !list || !audio) return;

    let timer;
    let controller;
    let currentId = null;
    let currentTrack = null;

    function updatePlayingState() {
        const playing = !audio.paused && !audio.ended && currentTrack !== null;
        page.classList.toggle('is-playing', playing);
        page.classList.toggle('is-paused', !playing && currentTrack !== null);
        if (currentTrack) now.textContent = (playing ? '正在播放 · ' : '已暂停 · ') + currentTrack.title;
        list.querySelectorAll('.music-track').forEach(button => {
            const selected = button.dataset.trackId === String(currentId);
            button.classList.toggle('is-playing', selected);
            button.setAttribute('aria-pressed', String(selected));
            if (selected && currentTrack) button.setAttribute('aria-label', (playing ? '暂停 ' : '播放 ') + currentTrack.title);
            const play = button.querySelector('.music-track-play');
            if (play) play.textContent = selected && playing ? 'Ⅱ' : '▶';
        });
    }

    function chooseTrack(track) {
        if (currentId === track.id && audio.src) {
            if (audio.paused) audio.play().catch(() => {});
            else audio.pause();
            return;
        }
        currentId = track.id;
        currentTrack = track;
        currentTitle.textContent = track.title;
        currentArtist.textContent = track.artist || '未知歌手';
        player.hidden = false;
        audio.src = track.audio_url;
        updatePlayingState();
        audio.play().catch(() => {});
    }

    function renderTrack(track, index) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'music-track';
        button.dataset.trackId = String(track.id);
        button.setAttribute('aria-label', '播放 ' + track.title + (track.artist ? '，' + track.artist : ''));
        button.setAttribute('aria-pressed', 'false');

        const number = document.createElement('span');
        number.className = 'music-track-index';
        number.textContent = String(index + 1).padStart(2, '0');
        const info = document.createElement('span');
        info.className = 'music-track-info';
        const cover = document.createElement('span');
        cover.className = 'music-track-cover';
        cover.textContent = '♪';
        cover.setAttribute('aria-hidden', 'true');
        const copy = document.createElement('span');
        copy.className = 'music-track-info-copy';
        const title = document.createElement('strong');
        title.textContent = track.title;
        const sub = document.createElement('small');
        sub.textContent = [track.artist, track.album].filter(Boolean).join(' · ') || '未知歌手';
        copy.append(title, sub);
        info.append(cover, copy);
        const play = document.createElement('span');
        play.className = 'music-track-play';
        play.textContent = '▶';
        play.setAttribute('aria-hidden', 'true');
        button.append(number, info, play);
        button.addEventListener('click', () => chooseTrack(track));
        item.append(button);
        return item;
    }

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
            list.replaceChildren(...tracks.map(renderTrack));
            count.textContent = String(tracks.length).padStart(2, '0');
            status.textContent = query ? '没有找到匹配的歌曲。' : '这里还没有歌曲。';
            status.hidden = tracks.length > 0;
            updatePlayingState();
        } catch (error) {
            if (error.name === 'AbortError') return;
            status.textContent = error.message || '歌单加载失败';
        }
    }

    audio.addEventListener('play', updatePlayingState);
    audio.addEventListener('pause', updatePlayingState);
    audio.addEventListener('ended', updatePlayingState);
    audio.addEventListener('error', () => { now.textContent = '音频暂时无法播放'; });
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 250); });
    load();
})();
