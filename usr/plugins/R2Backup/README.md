# Cloudflare R2 Backup

Typecho 原生备份插件，每天将数据库、关键配置、本地附件上传到私有 Cloudflare R2 Bucket，并可增量镜像整个七牛云 Bucket。

## 功能

- 完整导出当前数据库中所有 Typecho 前缀数据表，包括主题设置与记账表。
- 备份 `config.inc.php`、`.env`、`docker-compose.yml` 和 `usr/uploads`。
- 通过七牛资源列举接口读取整个 Bucket，按对象哈希增量同步；相册、账单照片、队徽等非 Typecho 附件也不会遗漏。
- 每日快照只保留最近 14 天；七牛目录保留最新镜像副本。
- 后台支持“立即备份并测试”：备份在服务器后台执行，页面轮询显示结果，离开页面也不会中断；同时记录最近一次成功或失败状态。
- 提供 CLI 入口和服务器 cron 安装脚本。

## 配置

1. 在 Cloudflare R2 创建私有、Standard 类型的 Bucket，建议命名为 `xiaogu-backup`。
2. 创建仅允许读写该 Bucket 的 R2 API Token。
3. 在 Typecho 后台启用“Cloudflare R2 自动备份”。
4. 填写 Account ID、Bucket、Access Key ID 和 Secret Access Key。
5. 保存设置后点击“立即备份并测试”。
6. 在服务器执行：

   ```sh
   sudo sh scripts/install-r2-backup-cron.sh
   ```

定时任务默认每天北京时间 03:30 执行，可用以下命令查看日志：

```sh
journalctl -t xiaogu-r2-backup
```

手动备份的进程输出记录在服务器的 `/tmp/xiaogu-r2-backup-manual.log`。若后台提示 HTTP 错误，可先检查这份日志和 Web 服务器错误日志。

## R2 目录

```text
xiaogu-backup/
├── snapshots/YYYY-MM-DD/HHMMSS/
│   ├── database/typecho.sql.gz
│   ├── files/...
│   └── manifest.json
└── qiniu/typecho/...
```

不要给 Bucket 设置 14 天生命周期删除规则。快照清理由插件完成，直接按对象生命周期删除可能造成不完整快照。

恢复数据库前先下载目标快照的 `database/typecho.sql.gz` 并检查同目录的 `manifest.json` 校验值。恢复属于破坏性操作，本插件不会自动覆盖线上数据库。
