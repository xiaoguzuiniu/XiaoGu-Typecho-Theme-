<?php

namespace TypechoPlugin\R2Backup;

use Widget\ActionInterface;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class BackupAction extends Options implements ActionInterface
{
    public function action()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->user->pass('administrator', true)) {
            $this->respond(403, ['success' => false, 'message' => '请先以管理员身份登录']);
            return;
        }

        try {
            if (!$this->request->isPost()) {
                $this->respond(200, ['success' => true, 'status' => BackupService::status()]);
                return;
            }

            $token = (string) $this->request->get('_');
            $expected = $this->security->getToken($this->request->getReferer());
            if (!hash_equals($expected, $token)) {
                $this->respond(403, ['success' => false, 'message' => '页面验证已过期，请刷新设置页后重试']);
                return;
            }

            $php = PHP_BINDIR . '/php';
            if (!function_exists('exec') || !is_file($php) || !is_executable($php)) {
                throw new \RuntimeException('服务器无法启动 PHP 后台任务，请通过服务器 CLI 执行备份');
            }

            BackupService::queueManual();
            $command = 'nohup ' . escapeshellarg($php) . ' '
                . escapeshellarg(__DIR__ . '/cron.php') . ' manual > '
                . escapeshellarg(sys_get_temp_dir() . '/xiaogu-r2-backup-manual.log')
                . ' 2>&1 < /dev/null &';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            if ($exitCode !== 0) {
                throw new \RuntimeException('无法启动备份进程（退出码 ' . $exitCode . '）');
            }
            $this->respond(202, ['success' => true, 'running' => true]);
        } catch (\Throwable $exception) {
            error_log('[R2Backup] ' . $exception->getMessage());
            if (!empty($command)) {
                BackupService::failManualStart($exception->getMessage());
            }
            $this->respond(500, ['success' => false, 'message' => $exception->getMessage()]);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
