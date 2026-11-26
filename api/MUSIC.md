# 音乐页面与接口

部署后自动创建 `/music` 页面。歌单、搜索和播放对访客开放；上传和导入需要在 Typecho 后台「外观设置」配置至少 24 位的「音乐接口密钥」，通过 `Authorization: Bearer <密钥>` 传入。不要把密钥写进公开网页。

音频由已配置的七牛云插件直接上传到七牛云，数据库仅保存歌曲信息和七牛对象路径。请先启用并配置 `QiniuStorage` 插件，并确保访问域名支持 HTTPS。服务端 PHP 上传限制为 50 MB；若已有 Web 容器，需重建容器使新的上传限制生效。

所有 JSON 响应采用 `{ "code": 0, "message": "success", "data": ... }` 格式；失败时 `code` 为 HTTP 状态码。

## 查询歌单

`GET /api/music/tracks` 返回 `data.tracks`。可加 `?q=晴天` 按歌曲名、歌手或专辑搜索，最多返回 200 首。每首歌曲包含 `id`、`title`、`artist`、`album`、`source`、`song_id`、`audio_url`。

## 上传音频

`POST /api/music/tracks`，`multipart/form-data`：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| `title` | 是 | 歌名 |
| `artist` | 否 | 歌手 |
| `album` | 否 | 专辑 |
| `audio` | 是 | 有权保存的 MP3、M4A、AAC、FLAC、OGG 或 WAV，最大 50 MB |

```bash
curl -X POST 'https://你的域名/api/music/tracks' \
  -H 'Authorization: Bearer 你的密钥' \
  -F 'title=歌曲名' -F 'artist=歌手' -F 'audio=@/path/to/song.mp3'
```

## 按网易云 song_id 导入

`POST /api/music/imports`，同样使用 `multipart/form-data`，比上传接口多一个必填 `song_id`（网易云数字歌曲 ID）。`title` 和 `audio` 仍必填。接口把 `song_id` 与你提供的授权音频绑定、去重并加入歌单；不从网易云抓取受限制的音频，也不代替音源授权。

```bash
curl -X POST 'https://你的域名/api/music/imports' \
  -H 'Authorization: Bearer 你的密钥' \
  -F 'song_id=123456' -F 'title=歌曲名' -F 'artist=歌手' \
  -F 'audio=@/path/to/authorized-song.mp3'
```

## 获取播放地址

`GET /api/music/tracks/{id}/audio` 返回 302，跳转到七牛云音频地址。博客播放器和 Mac 客户端可直接使用该地址；音频能否在浏览器播放取决于七牛云域名允许访问且返回正确的音频类型。
