# Cloudflare R2 Backup

Typecho 原生备份插件，每天将数据库、关键配置、本地附件上传到私有 Cloudflare R2 Bucket，并可增量镜像整个七牛云 Bucket。

## 功能

- 完整导出当前数据库中所有 Typecho 前缀数据表，包括主题设置与记账表。
- 备份 `config.inc.php`、`.env`、`docker-compose.yml` 和 `usr/uploads`。
- 通过七牛资源列举接口读取整个 Bucket，按对象哈希增量同步；相册、账单照片、队徽等非 Typecho 附件也不会遗漏。
- 每日快照只保留最近 14 天；七牛目录保留最新镜像副本。
- 后台支持“立即备份并测试”：备份在服务器后台执行，页面轮询显示结果，离开页面也不会中断；同时记录最近一次成功或失败状态。
- 后台支持选择快照并恢复：先校验数据库和本地上传文件，再生成恢复前保护快照，二次确认后由后台任务覆盖数据库与同名本地上传文件。
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

## 恢复

在插件设置页的“从 R2 恢复”中选择快照，核对站点、时间和文件数量，点击恢复并输入页面给出的确认文字。任务在后台执行；完成后可能需要重新登录。恢复前会自动生成 `pre-restore` 保护快照，供需要时再次回滚。

插件内恢复用于**当前已正常运行的网站**：覆盖 Typecho 数据库及备份中的同名 `usr/uploads` 文件；不会删除当前目录中多出的文件，也不会自动覆盖 `config.inc.php`、`.env`、`docker-compose.yml` 或七牛云对象。七牛云镜像保留在 R2，可在七牛云数据丢失时单独处理。服务器完全损坏或迁移新服务器时，仍需先部署 Typecho、配置数据库与 R2 插件，再从后台恢复。恢复日志位于服务器 `/tmp/xiaogu-r2-restore.log`。
