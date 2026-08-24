<?php

namespace TypechoPlugin\CommentMailer;

use RuntimeException;
use Typecho\Common;
use Typecho\Db;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Utils\Helper;
use Widget\Feedback;
use Widget\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 评论邮件通知
 *
 * @package CommentMailer
 * @author XiaoGu
 * @version 1.0.0
 * @link https://mail.163.com/
 */
class Plugin implements PluginInterface
{
    private const SMTP_HOST = 'smtp.163.com';
    private const SMTP_PORT = 465;
    private const SMTP_TIMEOUT = 12;

    public static function activate()
    {
        TypechoPlugin::factory(Feedback::class)->finishComment = __CLASS__ . '::finishComment';
        Helper::addAction('comment-mailer-webhook', WebhookAction::class);

        return _t('评论邮件通知已启用，请填写网易 163 邮箱和 SMTP 授权码。');
    }

    public static function deactivate()
    {
        Helper::removeAction('comment-mailer-webhook');
    }

    public static function config(Form $form)
    {
        $senderEmail = new Text(
            'senderEmail',
            null,
            null,
            _t('163 发件邮箱'),
            _t('用于登录 smtp.163.com，必须是已开启 SMTP 服务的完整 163 邮箱地址。')
        );
        $senderEmail->addRule('required', _t('请填写 163 发件邮箱。'));
        $senderEmail->addRule('email', _t('请填写正确的 163 邮箱地址。'));
        $form->addInput($senderEmail);

        $authCode = new Password(
            'authCode',
            null,
            null,
            _t('SMTP 授权码'),
            _t('填写网易邮箱生成的客户端授权码，不是邮箱登录密码。授权码仅保存在 Typecho 插件配置中。')
        );
        $authCode->addRule('required', _t('请填写 SMTP 授权码。'));
        $form->addInput($authCode);

        $adminEmail = new Text(
            'adminEmail',
            null,
            null,
            _t('站长收件邮箱'),
            _t('有新评论或新回复时，通知将发送到此邮箱，可以与发件邮箱相同。')
        );
        $adminEmail->addRule('required', _t('请填写站长收件邮箱。'));
        $adminEmail->addRule('email', _t('请填写正确的站长收件邮箱。'));
        $form->addInput($adminEmail);

        $senderName = new Text(
            'senderName',
            null,
            (string) Options::alloc()->title,
            _t('发件人名称'),
            _t('显示在邮件发件人位置，例如“小古有趣”。')
        );
        $senderName->addRule('required', _t('请填写发件人名称。'));
        $form->addInput($senderName);

        $notifyAdmin = new Select(
            'notifyAdmin',
            ['1' => _t('开启'), '0' => _t('关闭')],
            '1',
            _t('新评论通知站长'),
            _t('包括待审核评论；邮件中显示评论者、文章和评论内容。')
        );
        $form->addInput($notifyAdmin);

        $notifyReply = new Select(
            'notifyReply',
            ['1' => _t('开启'), '0' => _t('关闭')],
            '1',
            _t('回复通知原评论者'),
            _t('仅在回复已通过审核且原评论留有有效邮箱时发送。')
        );
        $form->addInput($notifyReply);

        $enableEmailReplies = new Select(
            'enableEmailReplies',
            ['0' => _t('关闭'), '1' => _t('开启')],
            '0',
            _t('邮件回复自动发布'),
            _t('开启后，收件人直接回复通知邮件即可将内容发布到网站评论区。需要先完成下方 Resend 配置。')
        );
        $form->addInput($enableEmailReplies);

        $receivingDomain = new Text(
            'receivingDomain',
            null,
            'reply.gulook.site',
            _t('Resend 收信域名'),
            _t('建议使用 reply.gulook.site，并在 Resend 中为该子域名配置接收邮件所需的 MX 记录。')
        );
        $form->addInput($receivingDomain);

        $resendApiKey = new Password(
            'resendApiKey',
            null,
            null,
            _t('Resend API Key'),
            _t('用于在收到 Webhook 后读取邮件正文。请在 Resend API Keys 页面创建。')
        );
        $form->addInput($resendApiKey);

        $webhookSecret = new Password(
            'webhookSecret',
            null,
            null,
            _t('Resend Webhook Signing Secret'),
            _t('在 Resend Webhook 详情页复制，以 whsec_ 开头。')
        );
        $form->addInput($webhookSecret);

        $webhookUrl = rtrim((string) Options::alloc()->index, '/')
            . '/action/comment-mailer-webhook';
        $webhookEndpoint = new Text(
            'webhookEndpoint',
            null,
            $webhookUrl,
            _t('Webhook 地址'),
            _t('将此地址添加到 Resend Webhooks，并只订阅 email.received。此字段仅用于复制，无需修改。')
        );
        $webhookEndpoint->input->setAttribute('readonly', 'readonly');
        $form->addInput($webhookEndpoint);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function finishComment(Feedback $comment): void
    {
        try {
            $config = self::getConfig();
            $notifications = self::buildNotifications($comment, $config);

            foreach ($notifications as $notification) {
                self::sendMail(
                    $config,
                    $notification['recipient'],
                    $notification['subject'],
                    $notification['html'],
                    $notification['replyTo']
                );
            }
        } catch (\Throwable $exception) {
            error_log('[CommentMailer] ' . $exception->getMessage());
        }
    }

    private static function getConfig(): array
    {
        $settings = Options::alloc()->plugin('CommentMailer');
        $senderEmail = strtolower(trim((string) $settings->senderEmail));
        $adminEmail = strtolower(trim((string) $settings->adminEmail));
        $authCode = trim((string) $settings->authCode);
        $senderName = trim((string) $settings->senderName);
        $receivingDomain = self::normalizeReceivingDomain((string) $settings->receivingDomain);
        $resendApiKey = trim((string) $settings->resendApiKey);
        $webhookSecret = trim((string) $settings->webhookSecret);
        $emailRepliesRequested = (string) $settings->enableEmailReplies === '1';
        $emailRepliesConfigured = $receivingDomain !== ''
            && $resendApiKey !== ''
            && strpos($webhookSecret, 'whsec_') === 0;

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('163 发件邮箱未正确配置。');
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('站长收件邮箱未正确配置。');
        }
        if ($authCode === '') {
            throw new RuntimeException('SMTP 授权码未配置。');
        }
        if ($senderName === '') {
            throw new RuntimeException('发件人名称未配置。');
        }

        return [
            'senderEmail' => $senderEmail,
            'adminEmail' => $adminEmail,
            'authCode' => $authCode,
            'senderName' => self::sanitizeHeader($senderName),
            'notifyAdmin' => (string) $settings->notifyAdmin !== '0',
            'notifyReply' => (string) $settings->notifyReply !== '0',
            'enableEmailReplies' => $emailRepliesRequested && $emailRepliesConfigured,
            'receivingDomain' => $receivingDomain,
            'resendApiKey' => $resendApiKey,
            'webhookSecret' => $webhookSecret,
        ];
    }

    private static function buildNotifications(Feedback $comment, array $config): array
    {
        $author = trim((string) $comment->author);
        $authorEmail = strtolower(trim((string) $comment->mail));
        $commentText = self::plainText((string) $comment->text);
        $status = (string) $comment->status;
        $title = self::contentTitle($comment);
        $siteTitle = $config['senderName'];
        $permalink = (string) $comment->permalink;
        $notifications = [];

        if ($author === '') {
            $author = '匿名访客';
        }

        if ($config['notifyAdmin'] && strcasecmp($authorEmail, $config['adminEmail']) !== 0) {
            $subjectPrefix = $status === 'approved' ? '新评论' : '待审核评论';
            $notifications[$config['adminEmail']] = [
                'recipient' => $config['adminEmail'],
                'subject' => '[' . $siteTitle . '] ' . $subjectPrefix . '：' . $title,
                'html' => self::renderMail(
                    '收到一条新评论',
                    $author . ' 评论了《' . $title . '》',
                    [
                        ['label' => '评论者', 'content' => $author],
                        ['label' => '邮箱', 'content' => $authorEmail !== '' ? $authorEmail : '未填写'],
                        ['label' => '评论内容', 'content' => $commentText],
                    ],
                    $permalink,
                    '查看评论',
                    $config['enableEmailReplies']
                ),
                'replyTo' => self::notificationReplyTo(
                    (int) $comment->coid,
                    $config['adminEmail'],
                    $authorEmail,
                    $config
                ),
            ];
        }

        $parentId = (int) $comment->parent;
        if (!$config['notifyReply'] || $status !== 'approved' || $parentId <= 0) {
            return array_values($notifications);
        }

        $parent = Db::get()->fetchRow(
            Db::get()->select('author', 'mail', 'text', 'status')
                ->from('table.comments')
                ->where('coid = ? AND cid = ? AND type = ?', $parentId, (int) $comment->cid, 'comment')
                ->limit(1)
        );
        if (!$parent || (string) $parent['status'] !== 'approved') {
            return array_values($notifications);
        }

        $parentEmail = strtolower(trim((string) $parent['mail']));
        if (
            !filter_var($parentEmail, FILTER_VALIDATE_EMAIL)
            || strcasecmp($parentEmail, $authorEmail) === 0
        ) {
            return array_values($notifications);
        }

        $parentAuthor = trim((string) $parent['author']);
        $notifications[$parentEmail] = [
            'recipient' => $parentEmail,
            'subject' => '[' . $siteTitle . '] ' . $author . ' 回复了你在《' . $title . '》的评论',
            'html' => self::renderMail(
                '你的评论收到了回复',
                ($parentAuthor !== '' ? $parentAuthor : '你好') . '，' . $author . ' 回复了你：',
                [
                    ['label' => '你的评论', 'content' => self::plainText((string) $parent['text'])],
                    ['label' => '回复内容', 'content' => $commentText],
                ],
                $permalink,
                '查看并回复',
                $config['enableEmailReplies']
            ),
            'replyTo' => self::notificationReplyTo(
                (int) $comment->coid,
                $parentEmail,
                $authorEmail,
                $config
            ),
        ];

        return array_values($notifications);
    }

    private static function contentTitle(Feedback $comment): string
    {
        $content = $comment->parentContent;
        if ((string) $content->fields->displayMode === 'moment') {
            $excerpt = self::plainText((string) $content->content);

            return $excerpt === ''
                ? '朋友圈动态'
                : '朋友圈动态：' . Common::subStr($excerpt, 0, 24, '…');
        }

        $title = trim((string) $content->title);

        return $title !== '' ? $title : '未命名内容';
    }

    private static function notificationReplyTo(
        int $commentId,
        string $recipient,
        string $fallback,
        array $config
    ): ?string {
        if ($config['enableEmailReplies']) {
            $domain = self::normalizeReceivingDomain($config['receivingDomain']);
            $expires = time() + 30 * 24 * 3600;
            $commentPart = base_convert((string) $commentId, 10, 36);
            $expiresPart = base_convert((string) $expires, 10, 36);
            $signature = self::replySignature($commentId, $expires, $recipient);

            return 'reply-' . $commentPart . '-' . $expiresPart . '-' . $signature . '@' . $domain;
        }

        return filter_var($fallback, FILTER_VALIDATE_EMAIL) ? $fallback : null;
    }

    public static function handleInboundWebhook(WebhookAction $action, string $payload, array $headers): array
    {
        $config = self::getConfig();
        if (!$config['enableEmailReplies']) {
            return ['status' => 'ignored', 'reason' => 'email replies disabled'];
        }

        self::verifyWebhook($payload, $headers, $config['webhookSecret']);
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new \InvalidArgumentException('Webhook JSON 格式无效。');
        }
        if (($event['type'] ?? '') !== 'email.received') {
            return ['status' => 'ignored', 'reason' => 'unsupported event'];
        }

        $emailId = isset($event['data']['email_id']) && is_scalar($event['data']['email_id'])
            ? trim((string) $event['data']['email_id'])
            : '';
        if ($emailId === '' || !preg_match('/^[a-zA-Z0-9-]{8,100}$/', $emailId)) {
            throw new \InvalidArgumentException('Webhook 缺少有效的邮件 ID。');
        }

        $dedupName = 'cmr:' . substr(hash('sha256', $emailId), 0, 28);
        if (!self::reserveInboundEmail($dedupName, $emailId)) {
            return ['status' => 'duplicate'];
        }

        try {
            return self::processInboundEmail($action, $emailId, $config, $dedupName);
        } catch (\Throwable $exception) {
            Db::get()->query(
                Db::get()->delete('table.options')->where('name = ? AND user = ?', $dedupName, 0)
            );
            throw $exception;
        }
    }

    private static function processInboundEmail(
        WebhookAction $action,
        string $emailId,
        array $config,
        string $dedupName
    ): array {
        $duplicate = Db::get()->fetchRow(
            Db::get()->select('coid')->from('table.comments')
                ->where('agent = ?', 'CommentMailer/Resend ' . $emailId)
                ->limit(1)
        );
        if ($duplicate) {
            return ['status' => 'duplicate', 'commentId' => (int) $duplicate['coid']];
        }

        $email = self::retrieveReceivedEmail($emailId, $config['resendApiKey']);
        if (self::isAutomatedEmail($email)) {
            return ['status' => 'ignored', 'reason' => 'automated email'];
        }

        $senderEmail = self::extractEmailAddress((string) ($email['from'] ?? ''));
        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'ignored', 'reason' => 'invalid sender'];
        }

        $replyTarget = null;
        $toAddresses = isset($email['to']) && is_array($email['to']) ? $email['to'] : [];
        foreach ($toAddresses as $toAddress) {
            if (!is_scalar($toAddress)) {
                continue;
            }
            $parsed = self::parseReplyAddress((string) $toAddress, $senderEmail, $config);
            if ($parsed !== null) {
                $replyTarget = $parsed;
                break;
            }
        }
        if ($replyTarget === null) {
            return ['status' => 'ignored', 'reason' => 'reply address rejected'];
        }

        $parent = Db::get()->fetchRow(
            Db::get()->select('coid', 'cid', 'status')
                ->from('table.comments')
                ->where('coid = ? AND type = ?', $replyTarget['commentId'], 'comment')
                ->limit(1)
        );
        if (!$parent || (string) $parent['status'] !== 'approved') {
            return ['status' => 'ignored', 'reason' => 'parent comment unavailable'];
        }

        $content = Db::get()->fetchRow(
            Db::get()->select('cid', 'authorId', 'status', 'allowComment')
                ->from('table.contents')
                ->where('cid = ?', (int) $parent['cid'])
                ->limit(1)
        );
        if (
            !$content
            || (string) $content['status'] !== 'publish'
            || (int) $content['allowComment'] !== 1
        ) {
            return ['status' => 'ignored', 'reason' => 'comments closed'];
        }

        $replyText = self::extractReplyText(
            isset($email['text']) && is_scalar($email['text']) ? (string) $email['text'] : '',
            isset($email['html']) && is_scalar($email['html']) ? (string) $email['html'] : ''
        );
        if ($replyText === '') {
            return ['status' => 'ignored', 'reason' => 'empty reply'];
        }

        $sender = self::senderIdentity((string) ($email['from'] ?? ''), $senderEmail);
        $isAuthenticated = self::isAuthenticatedSender($email);
        $commentStatus = $isAuthenticated ? 'approved' : 'waiting';
        $commentId = $action->insertInboundComment([
            'cid' => (int) $parent['cid'],
            'created' => time(),
            'author' => $sender['author'],
            'authorId' => $sender['authorId'],
            'ownerId' => (int) $content['authorId'],
            'mail' => $senderEmail,
            'url' => $sender['url'],
            'ip' => '0.0.0.0',
            'agent' => 'CommentMailer/Resend ' . $emailId,
            'text' => $replyText,
            'type' => 'comment',
            'status' => $commentStatus,
            'parent' => (int) $parent['coid'],
        ]);
        $action->loadInboundComment($commentId);
        Feedback::pluginHandle()->call('finishComment', $action);
        Db::get()->query(
            Db::get()->update('table.options')
                ->rows(['value' => 'comment:' . $commentId])
                ->where('name = ? AND user = ?', $dedupName, 0)
        );

        return [
            'status' => $commentStatus === 'approved' ? 'published' : 'waiting',
            'commentId' => $commentId,
        ];
    }

    private static function reserveInboundEmail(string $name, string $emailId): bool
    {
        try {
            Db::get()->query(
                Db::get()->insert('table.options')->rows([
                    'name' => $name,
                    'user' => 0,
                    'value' => 'processing:' . $emailId,
                ])
            );

            return true;
        } catch (\Throwable $exception) {
            $existing = Db::get()->fetchRow(
                Db::get()->select('name')->from('table.options')
                    ->where('name = ? AND user = ?', $name, 0)
                    ->limit(1)
            );
            if ($existing) {
                return false;
            }

            throw $exception;
        }
    }

    private static function verifyWebhook(string $payload, array $headers, string $secret): void
    {
        if (strpos($secret, 'whsec_') !== 0) {
            throw new RuntimeException('Resend Webhook Signing Secret 未正确配置。');
        }

        $messageId = trim((string) ($headers['svix-id'] ?? ''));
        $timestamp = trim((string) ($headers['svix-timestamp'] ?? ''));
        $signatures = trim((string) ($headers['svix-signature'] ?? ''));
        if ($messageId === '' || !ctype_digit($timestamp) || $signatures === '') {
            throw new \InvalidArgumentException('Webhook 签名头不完整。');
        }
        if (abs(time() - (int) $timestamp) > 300) {
            throw new \InvalidArgumentException('Webhook 请求已过期。');
        }

        $key = base64_decode(substr($secret, 6), true);
        if ($key === false) {
            throw new RuntimeException('Webhook Signing Secret 格式无效。');
        }

        $expected = base64_encode(
            hash_hmac('sha256', $messageId . '.' . $timestamp . '.' . $payload, $key, true)
        );
        foreach (preg_split('/\s+/', $signatures) ?: [] as $signature) {
            $parts = explode(',', $signature, 2);
            if (count($parts) === 2 && $parts[0] === 'v1' && hash_equals($expected, $parts[1])) {
                return;
            }
        }

        throw new \InvalidArgumentException('Webhook 签名验证失败。');
    }

    private static function retrieveReceivedEmail(string $emailId, string $apiKey): array
    {
        if ($apiKey === '' || !function_exists('curl_init')) {
            throw new RuntimeException('Resend API Key 未配置或 PHP cURL 不可用。');
        }

        $curl = curl_init(
            'https://api.resend.com/emails/receiving/' . rawurlencode($emailId)
        );
        if ($curl === false) {
            throw new RuntimeException('无法初始化 Resend API 请求。');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                '读取 Resend 邮件正文失败'
                . ($status > 0 ? '（HTTP ' . $status . '）' : '')
                . ($error !== '' ? '：' . $error : '。')
            );
        }

        $email = json_decode($response, true);
        if (!is_array($email)) {
            throw new RuntimeException('Resend 邮件正文响应格式无效。');
        }

        return $email;
    }

    private static function isAutomatedEmail(array $email): bool
    {
        $headers = self::normalizedEmailHeaders($email);
        $autoSubmitted = strtolower(trim($headers['auto-submitted'] ?? ''));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        foreach (['x-autoreply', 'x-autorespond', 'x-auto-response-suppress'] as $header) {
            if (!empty($headers[$header])) {
                return true;
            }
        }

        return preg_match('/\b(bulk|junk|list)\b/i', $headers['precedence'] ?? '') === 1;
    }

    private static function isAuthenticatedSender(array $email): bool
    {
        $headers = self::normalizedEmailHeaders($email);
        $authentication = $headers['authentication-results'] ?? '';

        return preg_match('/\bdmarc=pass\b/i', $authentication) === 1
            || (
                preg_match('/\bspf=pass\b/i', $authentication) === 1
                && preg_match('/\bdkim=pass\b/i', $authentication) === 1
            );
    }

    private static function normalizedEmailHeaders(array $email): array
    {
        $headers = isset($email['headers']) && is_array($email['headers'])
            ? $email['headers']
            : [];
        $normalized = [];
        foreach ($headers as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $normalized[strtolower((string) $name)] = trim((string) $value);
        }

        return $normalized;
    }

    private static function parseReplyAddress(string $address, string $senderEmail, array $config): ?array
    {
        $address = self::extractEmailAddress($address);
        $domain = self::normalizeReceivingDomain($config['receivingDomain']);
        if (
            $domain === ''
            || !preg_match(
                '/^reply-([0-9a-z]+)-([0-9a-z]+)-([a-f0-9]{24})@'
                . preg_quote($domain, '/')
                . '$/i',
                $address,
                $matches
            )
        ) {
            return null;
        }

        $commentId = (int) base_convert(strtolower($matches[1]), 36, 10);
        $expires = (int) base_convert(strtolower($matches[2]), 36, 10);
        if ($commentId <= 0 || $expires < time()) {
            return null;
        }

        $expected = self::replySignature($commentId, $expires, $senderEmail);
        if (!hash_equals($expected, strtolower($matches[3]))) {
            return null;
        }

        return ['commentId' => $commentId, 'expires' => $expires];
    }

    private static function replySignature(int $commentId, int $expires, string $recipient): string
    {
        return substr(
            hash_hmac(
                'sha256',
                $commentId . '|' . $expires . '|' . strtolower(trim($recipient)),
                hash('sha256', (string) Options::alloc()->secret . '|CommentMailer|reply-token', true)
            ),
            0,
            24
        );
    }

    private static function extractReplyText(string $text, string $html): string
    {
        if (trim($text) === '' && trim($html) !== '') {
            $html = preg_replace('/<(br|\/p|\/div|\/li)>/i', "\n", $html);
            $html = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = strip_tags($html);
        }

        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
        $lines = preg_split('/\n/u', $text) ?: [];
        $replyLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (
                preg_match('/^(>+|--\s*$|_{5,}|-{5,}\s*(Original Message|原始邮件))/iu', $trimmed)
                || preg_match('/^(On .+wrote:|在.+写道[：:]|发件人[：:]|From:)/iu', $trimmed)
            ) {
                break;
            }
            $replyLines[] = rtrim($line);
        }

        $reply = trim(implode("\n", $replyLines));
        $reply = preg_replace("/\n{3,}/u", "\n\n", $reply);

        return Common::subStr((string) $reply, 0, 5000, '…');
    }

    private static function senderIdentity(string $from, string $senderEmail): array
    {
        $user = Db::get()->fetchRow(
            Db::get()->select('uid', 'screenName', 'url')
                ->from('table.users')
                ->where('mail = ?', $senderEmail)
                ->limit(1)
        );
        if ($user) {
            return [
                'author' => trim((string) $user['screenName']) ?: strstr($senderEmail, '@', true),
                'authorId' => (int) $user['uid'],
                'url' => trim((string) $user['url']),
            ];
        }

        $author = trim(preg_replace('/\s*<[^>]+>\s*$/u', '', $from));
        $author = trim($author, " \t\n\r\0\x0B\"'");

        return [
            'author' => $author !== '' ? Common::subStr($author, 0, 150, '') : strstr($senderEmail, '@', true),
            'authorId' => 0,
            'url' => '',
        ];
    }

    private static function extractEmailAddress(string $address): string
    {
        if (preg_match('/<([^<>]+)>/', $address, $matches)) {
            $address = $matches[1];
        }

        return strtolower(trim($address));
    }

    private static function normalizeReceivingDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);

        return trim((string) $domain, " \t\n\r\0\x0B./");
    }

    private static function renderMail(
        string $heading,
        string $summary,
        array $sections,
        string $permalink,
        string $buttonText,
        bool $emailReplyEnabled
    ): string {
        $sectionHtml = '';
        foreach ($sections as $section) {
            $sectionHtml .= '<div style="margin-top:18px">'
                . '<div style="margin-bottom:6px;color:#8a8fa8;font-size:12px">'
                . self::escape((string) $section['label'])
                . '</div>'
                . '<div style="padding:14px 16px;border:1px solid #e7e9f2;border-radius:10px;'
                . 'background:#f8f8fc;color:#34384f;font-size:14px;line-height:1.8;white-space:pre-wrap">'
                . nl2br(self::escape((string) $section['content']))
                . '</div></div>';
        }

        $button = '';
        if (filter_var($permalink, FILTER_VALIDATE_URL)) {
            $button = '<p style="margin:24px 0 0">'
                . '<a href="' . self::escape($permalink) . '" style="display:inline-block;padding:11px 20px;'
                . 'border-radius:999px;background:#7d83ba;color:#fff;text-decoration:none;font-size:14px">'
                . self::escape($buttonText)
                . '</a></p>';
        }

        return '<!doctype html><html><body style="margin:0;padding:28px 12px;background:#f2f3f8;'
            . 'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,PingFang SC,Microsoft YaHei,sans-serif">'
            . '<div style="max-width:620px;margin:0 auto;padding:28px;border:1px solid #e5e7ef;'
            . 'border-radius:16px;background:#fff">'
            . '<h1 style="margin:0 0 10px;color:#30344c;font-size:22px">' . self::escape($heading) . '</h1>'
            . '<p style="margin:0;color:#666d88;font-size:14px;line-height:1.7">' . self::escape($summary) . '</p>'
            . $sectionHtml . $button
            . '<p style="margin:26px 0 0;color:#aaaec0;font-size:12px;line-height:1.6">'
            . ($emailReplyEnabled
                ? '此邮件由网站评论系统自动发送，直接回复邮件即可发布到网站评论区。'
                : '此邮件由网站评论系统自动发送。直接回复邮件只会发送给对方，不会同步到网站评论区。')
            . '</p></div></body></html>';
    }

    private static function sendMail(
        array $config,
        string $recipient,
        string $subject,
        string $html,
        ?string $replyTo
    ): void {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('通知收件邮箱格式不正确。');
        }

        $errno = 0;
        $error = '';
        $socket = stream_socket_client(
            'ssl://' . self::SMTP_HOST . ':' . self::SMTP_PORT,
            $errno,
            $error,
            self::SMTP_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('无法连接网易 SMTP：' . $error . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, self::SMTP_TIMEOUT);

        try {
            self::expect($socket, [220], '连接 SMTP');
            self::command($socket, 'EHLO ' . self::clientHost(), [250], 'SMTP 握手');
            self::command($socket, 'AUTH LOGIN', [334], '开始 SMTP 登录');
            self::command($socket, base64_encode($config['senderEmail']), [334], '提交 SMTP 用户名');
            self::command($socket, base64_encode($config['authCode']), [235], 'SMTP 身份验证');
            self::command(
                $socket,
                'MAIL FROM:<' . $config['senderEmail'] . '>',
                [250],
                '设置发件人'
            );
            self::command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251], '设置收件人');
            self::command($socket, 'DATA', [354], '开始发送邮件');

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . self::encodeHeader($config['senderName']) . ' <' . $config['senderEmail'] . '>',
                'To: <' . $recipient . '>',
                'Subject: ' . self::encodeHeader(self::sanitizeHeader($subject)),
                'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . self::clientHost() . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $headers[] = 'Reply-To: <' . $replyTo . '>';
            }

            $payload = implode("\r\n", $headers)
                . "\r\n\r\n"
                . chunk_split(base64_encode($html), 76, "\r\n");
            $payload = preg_replace('/^\./m', '..', $payload);
            if (fwrite($socket, $payload . "\r\n.\r\n") === false) {
                throw new RuntimeException('写入邮件内容失败。');
            }
            self::expect($socket, [250], '提交邮件');

            fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    private static function command($socket, string $command, array $expectedCodes, string $context): void
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            throw new RuntimeException($context . '时写入 SMTP 连接失败。');
        }

        self::expect($socket, $expectedCodes, $context);
    }

    private static function expect($socket, array $expectedCodes, string $context): void
    {
        $response = self::readResponse($socket);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException($context . '失败：' . trim($response));
        }
    }

    private static function readResponse($socket): string
    {
        $response = '';
        do {
            $line = fgets($socket, 1024);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                throw new RuntimeException(
                    !empty($meta['timed_out']) ? '读取 SMTP 响应超时。' : 'SMTP 连接意外关闭。'
                );
            }
            $response .= $line;
        } while (strlen($line) >= 4 && $line[3] === '-');

        return $response;
    }

    private static function clientHost(): string
    {
        $host = parse_url((string) Options::alloc()->siteUrl, PHP_URL_HOST);

        return $host ?: 'localhost';
    }

    private static function plainText(string $content): string
    {
        $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace("/[ \t]+\n/u", "\n", $content);
        $content = preg_replace("/\n{3,}/u", "\n\n", $content);

        return trim((string) $content);
    }

    private static function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
