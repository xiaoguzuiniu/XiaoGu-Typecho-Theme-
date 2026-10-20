<?php

namespace TypechoPlugin\R2Backup;

use Widget\ActionInterface;
use Widget\Base\Options;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

final class RestoreAction extends Options implements ActionInterface
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
                $requested = (string) $this->request->get('snapshot');
                if ((string) $this->request->get('status') === '1') {
                    $this->respond(200, ['success' => true, 'status' => RestoreService::status()]);
                } elseif ($requested !== '') {
                    $this->respond(200, ['success' => true, 'summary' => RestoreService::summary($requested), 'status' => RestoreService::status()]);
                } else {
                    $this->respond(200, ['success' => true, 'snapshots' => RestoreService::snapshots(), 'status' => RestoreService::status()]);
                }
                return;
            }
            $token = (string) $this->request->get('_');
            if (!hash_equals($this->security->getToken($this->request->getReferer()), $token)) {
                $this->respond(403, ['success' => false, 'message' => '页面验证已过期，请刷新后重试']);
                return;
            }
            $snapshot = (string) $this->request->get('snapshot');
            RestoreService::validateSnapshot($snapshot);
            if ((string) $this->request->get('confirmation') !== '恢复 ' . $snapshot) {
                $this->respond(400, ['success' => false, 'message' => '请正确输入确认文字']);
                return;
            }
            $available = array_column(RestoreService::snapshots(), 'snapshot');
            if (!in_array($snapshot, $available, true)) {
                throw new \RuntimeException('快照不存在或已过期');
            }
            $status = RestoreService::status();
            if (!empty($status['running']) && time() - (int) ($status['startedAt'] ?? 0) < 7200) {
                throw new \RuntimeException('已有恢复任务正在执行');
            }
            $backup = BackupService::status();
            if (!empty($backup['running']) && time() - (int) ($backup['startedAt'] ?? 0) < 3600) {
                throw new \RuntimeException('备份任务正在执行，请稍后恢复');
            }
            $php = PHP_BINDIR . '/php';
            if (!function_exists('exec') || !is_file($php) || !is_executable($php)) {
                throw new \RuntimeException('服务器无法启动 PHP 后台恢复任务');
            }
            RestoreService::setStatus(['running' => true, 'stage' => '正在准备恢复', 'snapshot' => $snapshot, 'startedAt' => time()]);
            $command = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/restore.php') . ' '
                . escapeshellarg($snapshot) . ' > ' . escapeshellarg(sys_get_temp_dir() . '/xiaogu-r2-restore.log')
                . ' 2>&1 < /dev/null &';
            exec($command, $output, $exitCode);
            if ($exitCode !== 0) {
                RestoreService::setStatus(['success' => false, 'message' => '无法启动恢复进程', 'finishedAt' => date(DATE_ATOM)]);
                throw new \RuntimeException('无法启动恢复进程');
            }
            $this->respond(202, ['success' => true, 'running' => true]);
        } catch (\Throwable $error) {
            error_log('[R2Backup restore] ' . $error->getMessage());
            $this->respond(500, ['success' => false, 'message' => $error->getMessage()]);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
