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
        if (!$this->request->isPost()) {
            $this->respond(405, ['success' => false, 'message' => '只允许 POST 请求']);
            return;
        }

        try {
            $this->user->pass('administrator');
            $this->security->protect();
            @set_time_limit(0);
            $this->respond(200, BackupService::run('admin'));
        } catch (\Throwable $exception) {
            error_log('[R2Backup] ' . $exception->getMessage());
            $this->respond(500, ['success' => false, 'message' => $exception->getMessage()]);
        }
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
