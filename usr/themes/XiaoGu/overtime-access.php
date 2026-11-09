<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function xiaoguOvertimePageAccess(string $password, bool $secureCookie): array
{
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow', true);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('xiaogu_overtime');
        $started = session_start([
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            'cookie_secure' => $secureCookie,
            'cookie_samesite' => 'Lax',
        ]);
        if (!$started) {
            return ['allowed' => false, 'message' => '暂时无法验证访问密码，请稍后重试。'];
        }
    }

    if ($password === '') {
        unset($_SESSION['xiaogu_overtime_access']);
        return ['allowed' => false, 'message' => '请先在后台「外观设置」中配置加班页面密码。'];
    }

    $fingerprint = hash('sha256', 'xiaogu-overtime-page|' . $password);
    $saved = $_SESSION['xiaogu_overtime_access'] ?? null;
    if (is_array($saved) && isset($saved['fingerprint'], $saved['expires'])
        && is_string($saved['fingerprint'])
        && hash_equals($fingerprint, $saved['fingerprint'])
        && (int) $saved['expires'] > time()) {
        return ['allowed' => true, 'message' => ''];
    }
    unset($_SESSION['xiaogu_overtime_access']);

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !array_key_exists('overtime_password', $_POST)) {
        return ['allowed' => false, 'message' => ''];
    }

    $attempts = $_SESSION['xiaogu_overtime_attempts'] ?? ['count' => 0, 'until' => 0];
    if (!is_array($attempts)) $attempts = ['count' => 0, 'until' => 0];
    if ((int) ($attempts['until'] ?? 0) > time()) {
        return ['allowed' => false, 'message' => '尝试次数过多，请 5 分钟后再试。'];
    }

    $submitted = $_POST['overtime_password'];
    if (is_string($submitted) && strlen($submitted) <= 1024 && hash_equals($password, $submitted)) {
        if (!session_regenerate_id(true)) {
            return ['allowed' => false, 'message' => '暂时无法验证访问密码，请稍后重试。'];
        }
        $_SESSION['xiaogu_overtime_access'] = [
            'fingerprint' => $fingerprint,
            'expires' => time() + 12 * 3600,
        ];
        unset($_SESSION['xiaogu_overtime_attempts']);
        return ['allowed' => true, 'message' => '', 'justAuthenticated' => true];
    }

    $count = (int) ($attempts['count'] ?? 0) + 1;
    $_SESSION['xiaogu_overtime_attempts'] = [
        'count' => $count,
        'until' => $count >= 5 ? time() + 300 : 0,
    ];
    return ['allowed' => false, 'message' => $count >= 5 ? '尝试次数过多，请 5 分钟后再试。' : '密码不正确，请重试。'];
}
