<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 3);
require $root . '/config.inc.php';

require_once __DIR__ . '/R2Client.php';
require_once __DIR__ . '/BackupService.php';

try {
    $result = \TypechoPlugin\R2Backup\BackupService::run('cron');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
        'finishedAt' => date(DATE_ATOM),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
