<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<section class="music-page" aria-label="音乐歌单">
    <header class="music-hero">
        <div><span class="music-eyebrow">MY PLAYLIST</span><h1>音乐</h1><p>收藏想反复听的声音。</p></div>
        <span class="music-hero-icon" aria-hidden="true">♫</span>
    </header>
    <div class="music-toolbar">
        <label class="sr-only" for="music-search">搜索歌曲</label>
        <input id="music-search" type="search" placeholder="搜索歌曲、歌手或专辑" autocomplete="off">
        <span id="music-count">0 首</span>
    </div>
    <details class="music-add">
        <summary>添加歌曲</summary>
        <form id="music-add-form">
            <label>接口密钥<input type="password" name="token" required autocomplete="off" placeholder="后台外观设置中的音乐接口密钥"></label>
            <label>歌名<input name="title" required maxlength="200"></label>
            <label>歌手<input name="artist" maxlength="200"></label>
            <label>专辑<input name="album" maxlength="200"></label>
            <label>网易云 song_id（导入时填写）<input name="song_id" inputmode="numeric" pattern="[1-9][0-9]*" placeholder="可留空，作为普通上传"></label>
            <label>授权音频文件<input type="file" name="audio" accept="audio/*,.mp3,.m4a,.aac,.flac,.ogg,.wav" required></label>
            <button type="submit">上传并加入歌单</button>
            <p id="music-upload-status" role="status"></p>
        </form>
    </details>
    <p class="music-status" id="music-status" role="status">正在加载歌单…</p>
    <ol class="music-list" id="music-list" aria-label="歌曲列表"></ol>
    <div class="music-player" id="music-player" hidden>
        <div class="music-player-art" aria-hidden="true">♫</div>
        <div class="music-player-copy"><strong id="music-current-title"></strong><span id="music-current-artist"></span></div>
        <audio id="music-audio" controls preload="none"></audio>
    </div>
</section>
