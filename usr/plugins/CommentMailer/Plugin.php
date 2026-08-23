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

        return _t('评论邮件通知已启用，请填写网易 163 邮箱和 SMTP 授权码。');
    }

    public static function deactivate()
    {
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
                    '查看评论'
                ),
                'replyTo' => filter_var($authorEmail, FILTER_VALIDATE_EMAIL) ? $authorEmail : null,
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
                '查看并回复'
            ),
            'replyTo' => filter_var($authorEmail, FILTER_VALIDATE_EMAIL) ? $authorEmail : null,
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

    private static function renderMail(
        string $heading,
        string $summary,
        array $sections,
        string $permalink,
        string $buttonText
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
            . '此邮件由网站评论系统自动发送。直接回复邮件只会发送给对方，不会同步到网站评论区。'
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
