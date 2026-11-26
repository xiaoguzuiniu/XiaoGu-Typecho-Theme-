<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<section class="music-page" aria-label="音乐歌单">
    <header class="music-hero">
        <div class="music-hero-copy">
            <span class="music-eyebrow"><span class="music-eyebrow-line"></span> XIAOGU RADIO <span class="music-eyebrow-number">/ 01</span></span>
            <h1>让音乐，<br><em>慢慢发生。</em></h1>
            <p>把喜欢的旋律，留在这里。</p>
            <div class="music-hero-now" aria-live="polite"><span class="music-live-dot"></span><span id="music-hero-now">选一首歌，开始聆听</span></div>
        </div>
        <div class="music-hero-art" aria-hidden="true">
            <div class="music-disc"><div class="music-disc-label"><span>小古的<br>音乐角落</span><i></i></div></div>
            <span class="music-disc-caption">SIDE A · PLAY SOMETHING GOOD</span>
        </div>
    </header>

    <div class="music-section-head">
        <div><span class="music-section-kicker">YOUR COLLECTION</span><h2>播放列表 <span id="music-count">00</span></h2></div>
        <label class="music-search-box" for="music-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.4"></circle><path d="m16 16 4.5 4.5"></path></svg>
            <input id="music-search" type="search" placeholder="搜索歌曲 / 歌手 / 专辑" autocomplete="off" aria-label="搜索歌曲、歌手或专辑">
        </label>
    </div>

    <div class="music-list-heading" aria-hidden="true"><span>曲目</span><span>标题 / 歌手</span><span>播放</span></div>
    <p class="music-status" id="music-status" role="status">正在加载歌单…</p>
    <ol class="music-list" id="music-list" aria-label="歌曲列表"></ol>
    <div class="music-player" id="music-player" hidden>
        <div class="music-player-mark" aria-hidden="true"><span>♪</span></div>
        <div class="music-player-copy"><small>正在播放</small><strong id="music-current-title"></strong><span id="music-current-artist"></span></div>
        <audio id="music-audio" controls preload="none"></audio>
    </div>
</section>
