# 今日健康同步接口

## 地址

- `POST /api/steps`
- 兼容地址：`POST /api/steps.php`

请求体：

```json
{
  "date": "2026-08-24",
  "update_time": "15:40:26",
  "steps": 6528,
  "active_energy": 286.5
}
```

测试：

```bash
curl -X POST https://你的域名/api/steps \
  -H 'Content-Type: application/json' \
  -d '{"date":"2026-08-24","update_time":"15:40:26","steps":6528,"active_energy":286.5}'
```

服务端以 `date` 为唯一键。同一天的新同步覆盖旧数据，不进行数值累加；如果较早的
`update_time` 延迟到达，则保留数据库中较新的记录。

## 可选鉴权

在 `.env` 设置非空 Token：

```dotenv
HEALTH_API_TOKEN=请替换为足够长的随机字符串
```

重建 Web 容器后，请求需要增加：

```text
Authorization: Bearer 你的Token
```

