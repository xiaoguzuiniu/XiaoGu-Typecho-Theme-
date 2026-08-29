# 备忘录发布朋友圈动态

## 接口

```text
POST /api/moments
Authorization: Bearer YOUR_TOKEN
Content-Type: multipart/form-data
```

表单字段：

| 字段 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `content` | 文本 | 是 | 备忘录正文 |
| `images[]` | 文件 | 否 | JPEG 等图片，最多9张、单张不超过12MB |

朋友圈动态不设置文章标题，备忘录内容会从第一行开始完整保留。主题中的动态列表仍会根据正文自动生成简短摘要。

成功响应：

```json
{
  "code": 0,
  "message": "success",
  "data": {
    "cid": 123,
    "url": "https://example.com/archives/123/",
    "images": 3
  }
}
```

## iPhone 快捷指令

1. 使用“查找备忘录”，再用“从列表中选取”选择要发布的备忘录。
2. 使用“获取备忘录的详细信息”，详情选择“正文”。
3. 使用“选择照片”，启用“选择多张”，每次最多手动选择9张。
4. 使用“转换图像”，转换为 JPEG；这能避免 HEIC 上传兼容问题。
5. 添加 URL：`http://服务器IP/api/moments`。
6. 添加“获取 URL 内容”：
   - 方法选择 `POST`；
   - 请求头 `Authorization` 设置为 `Bearer YOUR_TOKEN`；
   - 请求正文选择“表单”；
   - `content` 选择第2步取得的备忘录正文；
   - `images[]` 选择第4步转换后的图像。
7. 从返回结果中读取 `data` → `url`，使用“显示结果”展示文章链接。
