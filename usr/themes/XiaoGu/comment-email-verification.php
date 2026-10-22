<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function xiaoGuStartCommentMailSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (headers_sent()) throw new \RuntimeException('无法启动邮箱验证，请刷新页面');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => parse_url((string) \Widget\Options::alloc()->siteUrl, PHP_URL_SCHEME) === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) throw new \RuntimeException('无法启动邮箱验证会话');
}

function xiaoGuIsCommentMailVerified(string $mail): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[session_name()])) return false;
    xiaoGuStartCommentMailSession();
    $verified = $_SESSION['xiaogu_comment_mail_verified'] ?? null;
    return is_array($verified)
        && (int) ($verified['expires'] ?? 0) > time()
        && hash_equals((string) ($verified['mail'] ?? ''), strtolower(trim($mail)));
}

function xiaoGuCommentMailRate(string $identity, bool $record = false): void
{
    $name = 'xgmv_' . substr(hash('sha256', $identity), 0, 27);
    $db = \Typecho\Db::get();
    $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ? AND user = ?', $name, 0)->limit(1));
    $rate = $row ? json_decode((string) $row['value'], true) : null;
    $rate = is_array($rate) ? $rate : [];
    $day = gmdate('Y-m-d');
    $count = ($rate['day'] ?? '') === $day ? (int) ($rate['count'] ?? 0) : 0;
    if (!$record) {
        if (time() - (int) ($rate['last'] ?? 0) < 60 || $count >= 5) {
            throw new \RuntimeException('验证码请求过于频繁，请稍后再试');
        }
        return;
    }
    $value = json_encode(['last' => time(), 'day' => $day, 'count' => $count + 1]);
    if ($row) {
        $db->query($db->update('table.options')->rows(['value' => $value])->where('name = ? AND user = ?', $name, 0));
    } else {
        $db->query($db->insert('table.options')->rows(['name' => $name, 'user' => 0, 'value' => $value]));
    }
}

function xiaoGuHandleCommentMailAction(string $action): void
{
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new \RuntimeException('请求方式错误');
        $request = \Typecho\Request::getInstance();
        $referer = $request->getReferer();
        $siteHost = parse_url((string) \Widget\Options::alloc()->siteUrl, PHP_URL_HOST);
        if (!$referer || parse_url($referer, PHP_URL_HOST) !== $siteHost) {
            throw new \RuntimeException('请求来源无效');
        }
        $token = isset($_POST['_']) && is_scalar($_POST['_']) ? (string) $_POST['_'] : '';
        if ($token === '' || !hash_equals(\Widget\Security::alloc()->getToken($referer), $token)) {
            throw new \RuntimeException('安全验证失败，请刷新页面');
        }
        $mail = isset($_POST['mail']) && is_scalar($_POST['mail']) ? strtolower(trim((string) $_POST['mail'])) : '';
        if (strlen($mail) > 150 || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('请填写正确的邮箱');
        }
        xiaoGuStartCommentMailSession();
        if ($action === 'mail_code') {
            $ip = $request->getIp();
            xiaoGuCommentMailRate('ip:' . $ip);
            xiaoGuCommentMailRate('mail:' . $mail);
            $plugins = \Widget\Options::alloc()->plugins;
            if (!isset($plugins['activated']['CommentMailer'])
                || !class_exists(\TypechoPlugin\CommentMailer\Plugin::class)) {
                throw new \RuntimeException('评论邮件插件未启用，暂时无法验证邮箱');
            }
            $code = (string) random_int(100000, 999999);
            \TypechoPlugin\CommentMailer\Plugin::sendVerificationCode($mail, $code);
            xiaoGuCommentMailRate('ip:' . $ip, true);
            xiaoGuCommentMailRate('mail:' . $mail, true);
            $_SESSION['xiaogu_comment_mail_challenge'] = [
                'mail' => $mail,
                'hash' => hash_hmac('sha256', $mail . ':' . $code, (string) \Widget\Options::alloc()->secret),
                'expires' => time() + 600,
                'tries' => 0,
            ];
            unset($_SESSION['xiaogu_comment_mail_verified']);
            echo json_encode(['success' => true, 'message' => '验证码已发送，10 分钟内有效'], JSON_UNESCAPED_UNICODE);
        } else {
            $code = isset($_POST['code']) && is_scalar($_POST['code']) ? trim((string) $_POST['code']) : '';
            $challenge = $_SESSION['xiaogu_comment_mail_challenge'] ?? null;
            if (!is_array($challenge) || (int) ($challenge['expires'] ?? 0) < time()
                || (int) ($challenge['tries'] ?? 0) >= 5 || !preg_match('/^\d{6}$/D', $code)
                || !hash_equals((string) ($challenge['mail'] ?? ''), $mail)) {
                throw new \RuntimeException('验证码无效或已过期，请重新获取');
            }
            $_SESSION['xiaogu_comment_mail_challenge']['tries']++;
            $expected = hash_hmac('sha256', $mail . ':' . $code, (string) \Widget\Options::alloc()->secret);
            if (!hash_equals((string) $challenge['hash'], $expected)) {
                throw new \RuntimeException('验证码不正确');
            }
            session_regenerate_id(true);
            $_SESSION['xiaogu_comment_mail_verified'] = ['mail' => $mail, 'expires' => time() + 1800];
            unset($_SESSION['xiaogu_comment_mail_challenge']);
            \Typecho\Cookie::set('__typecho_remember_mail', $mail, 30 * 24 * 3600);
            echo json_encode(['success' => true, 'message' => '邮箱已验证，30 分钟内可以发布评论或留言'], JSON_UNESCAPED_UNICODE);
        }
    } catch (\Throwable $error) {
        \Typecho\Response::getInstance()->setStatus(400);
        echo json_encode(['success' => false, 'message' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
