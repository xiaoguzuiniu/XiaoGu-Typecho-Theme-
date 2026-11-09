# 我的加班接口

加班日记按天记录，侧栏和日历自动汇总当前自然月的日常加班、周末加班及加班费。时长单位为 `h`，金额单位为人民币元。加班费由调用方传入，服务端不计算工资规则。

## 配置密钥

在 Typecho 后台「外观设置」填写「加班日记接口密钥」，至少 24 位随机字符并保存。所有接口请求都需携带 `Authorization: Bearer 你的密钥`。不要将密钥放入公开网页或仓库。

## 写入每日记录

`POST /api/overtime`，JSON 请求体：

```json
{
  "date": "2026-11-09",
  "weekday_hours": 2.5,
  "weekend_hours": 0,
  "pay": 85.5,
  "note": "完成项目上线（可选）"
}
```

`date`、`weekday_hours`、`weekend_hours`、`pay` 必填，`note` 可选；时长与金额最多两位小数。相同日期再次写入会覆盖该天的记录，不会重复累计。月度总数由每日记录汇总。

没有加班时，可以正常提交两个时长和 `pay` 都为 `0`。接口仍保存这一天的记录；之后 GET 的 `entries` 会包含该日期，工时和金额均为 `0`，加班日记的日历也会将其视为已记录的日期。如果时长为 0，但 `pay` 大于 0，接口会返回 400。

```bash
curl -X POST https://你的域名/api/overtime \
  -H 'Authorization: Bearer 你的密钥' \
  -H 'Content-Type: application/json' \
  -d '{"date":"2026-11-09","weekday_hours":2.5,"weekend_hours":0,"pay":85.5}'
```

## 读取数据

`GET /api/overtime` 读取本月；`GET /api/overtime?month=2026-11` 读取指定月份。未写入的月份返回三个汇总值 0 和空的 `entries`。

```bash
curl https://你的域名/api/overtime \
  -H 'Authorization: Bearer 你的密钥'
```

成功响应中的 `data` 包含 `month`、`weekday_hours`、`weekend_hours`、`monthly_pay`、`recorded_days` 和 `entries`（每日明细）。旧的 `/api/steps` 健康同步接口已移除；原 `health_daily` 表不再使用，但不会自动删除已有数据。
