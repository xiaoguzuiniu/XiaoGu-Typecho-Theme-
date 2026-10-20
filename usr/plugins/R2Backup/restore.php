<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 3) . '/config.inc.php';
require_once __DIR__ . '/R2Client.php';
require_once __DIR__ . '/BackupService.php';
require_once __DIR__ . '/RestoreService.php';

try {
    $result = \TypechoPlugin\R2Backup\RestoreService::run((string) ($argv[1] ?? ''));
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (\Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
