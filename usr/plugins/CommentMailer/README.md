# CommentMailer

Typecho 1.3 评论邮件通知插件，使用网易 163 邮箱 SMTP 服务。

## 功能

- 新评论通知站长，包括待审核评论。
- 已通过审核的回复通知原评论者。
- 邮件中显示文章标题、原评论、回复内容和直达评论链接。
- 同一邮箱只发送一封通知，自己回复自己时不发送。
- 可通过 Resend 接收入站邮件，将邮件回复自动发布为对应评论的子回复。
- 邮件发送失败不会阻止评论提交，错误写入 PHP 日志。

## 配置

1. 登录网易 163 邮箱。
2. 进入“设置”中的 POP3/SMTP/IMAP 服务。
3. 开启 SMTP 服务并生成客户端授权码。
4. 在 Typecho 后台进入“控制台 → 插件”。
5. 启用“评论邮件通知”，填写：
   - 163 发件邮箱
   - SMTP 授权码
   - 站长收件邮箱
   - 发件人名称

插件固定使用 `smtp.163.com:465` 的 SSL 连接。SMTP 授权码不是邮箱登录密码。

## 邮件回复自动发布

1. 在 Resend 添加并验证 `reply.gulook.site`。
2. 按 Resend 提示为 `reply.gulook.site` 添加收信 MX 记录，不要修改根域名现有 MX。
3. 创建 Resend API Key。
4. 在 Resend Webhooks 添加插件配置页显示的 Webhook 地址，只订阅 `email.received`。
5. 将 Webhook 详情中的 `whsec_` Signing Secret 填入插件。
6. 开启“邮件回复自动发布”并保存。
7. 修改过插件后需停用再启用一次，以注册 Webhook 地址。

通知邮件的 Reply-To 会使用一个 30 天有效、与收件邮箱绑定的签名地址。Webhook 请求还会校验
Resend/Svix 签名和五分钟时间窗；重复的 Resend 邮件不会重复创建评论。
