<?php

namespace TypechoPlugin\CommentMailer;

use InvalidArgumentException;
use Typecho\Db\Exception as DbException;
use Widget\ActionInterface;
use Widget\Feedback;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class WebhookAction extends Feedback implements ActionInterface
{
    public function action()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->request->isPost()) {
            $this->respond(405, ['error' => 'Method Not Allowed']);
            return;
        }

        $payload = file_get_contents('php://input');
        if (!is_string($payload) || $payload === '') {
            $this->respond(400, ['error' => 'Empty payload']);
            return;
        }

        try {
            $result = Plugin::handleInboundWebhook($this, $payload, [
                'svix-id' => (string) $this->request->getHeader('svix-id'),
                'svix-timestamp' => (string) $this->request->getHeader('svix-timestamp'),
                'svix-signature' => (string) $this->request->getHeader('svix-signature'),
            ]);
            $this->respond(200, $result);
        } catch (InvalidArgumentException $exception) {
            $this->respond(400, ['error' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            error_log('[CommentMailer] Inbound webhook failed: ' . $exception->getMessage());
            $this->respond(500, ['error' => 'Inbound email processing failed']);
        }
    }

    public function insertInboundComment(array $comment): int
    {
        return $this->insert($comment);
    }

    public function loadInboundComment(int $commentId): void
    {
        $row = $this->db->fetchRow(
            $this->select()->where('table.comments.coid = ?', $commentId)->limit(1)
        );
        if (!$row) {
            throw new DbException('无法读取刚创建的邮件回复评论。');
        }

        $this->push($row);
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
