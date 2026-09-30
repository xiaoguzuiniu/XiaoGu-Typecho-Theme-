<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $rootDirectory = dirname(__DIR__);
    require_once $rootDirectory . '/config.inc.php';
    require_once $rootDirectory . '/usr/plugins/CommentMailer/Plugin.php';

    $summary = \TypechoPlugin\CommentMailer\Plugin::processScheduleReminders();
    echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    exit($summary['failed'] > 0 ? 1 : 0);
} catch (\Throwable $error) {
    error_log('[schedule-reminders] ' . $error->getMessage());
    fwrite(STDERR, "Schedule reminder check failed.\n");
    exit(1);
}
