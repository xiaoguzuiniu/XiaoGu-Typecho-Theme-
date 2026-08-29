<?php

declare(strict_types=1);

function healthApiRespond(int $status, int $code, string $message, ?array $data = null): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $response = [
        'code' => $code,
        'message' => $message,
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function healthApiAuthorizationHeader(): string
{
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
            return trim($_SERVER[$key]);
        }
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                return trim((string) $value);
            }
        }
    }

    return '';
}

function healthApiValidDate(string $value): bool
{
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = \DateTimeImmutable::getLastErrors();
    return $parsed !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsed->format('Y-m-d') === $value;
}

function healthApiValidTime(string $value): bool
{
    $parsed = \DateTimeImmutable::createFromFormat('!H:i:s', $value);
    $errors = \DateTimeImmutable::getLastErrors();
    return $parsed !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsed->format('H:i:s') === $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    healthApiRespond(405, 405, 'Method Not Allowed');
}

$configuredToken = trim((string) getenv('HEALTH_API_TOKEN'));
if ($configuredToken !== '') {
    $authorization = healthApiAuthorizationHeader();
    $prefix = 'Bearer ';
    if (strncasecmp($authorization, $prefix, strlen($prefix)) !== 0
        || !hash_equals($configuredToken, trim(substr($authorization, strlen($prefix))))) {
        header('WWW-Authenticate: Bearer');
        healthApiRespond(401, 401, 'Unauthorized');
    }
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody === false ? '' : $rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
    healthApiRespond(400, 400, 'Invalid JSON');
}

$required = ['date', 'update_time', 'steps', 'active_energy'];
foreach ($required as $parameter) {
    if (!array_key_exists($parameter, $payload)) {
        healthApiRespond(400, 400, 'Missing parameter: ' . $parameter);
    }
}

if (!is_string($payload['date']) || !healthApiValidDate($payload['date'])) {
    healthApiRespond(400, 400, 'Invalid parameter: date');
}
if (!is_string($payload['update_time']) || !healthApiValidTime($payload['update_time'])) {
    healthApiRespond(400, 400, 'Invalid parameter: update_time');
}

$stepsValue = $payload['steps'];
if ((!is_int($stepsValue) && !is_float($stepsValue))
    || !is_finite((float) $stepsValue)
    || (float) $stepsValue < 0
    || floor((float) $stepsValue) !== (float) $stepsValue
    || (float) $stepsValue > 4294967295) {
    healthApiRespond(400, 400, 'Invalid parameter: steps');
}

$energyValue = $payload['active_energy'];
if ((!is_int($energyValue) && !is_float($energyValue))
    || !is_finite((float) $energyValue)
    || (float) $energyValue < 0
    || (float) $energyValue > 99999999.99) {
    healthApiRespond(400, 400, 'Invalid parameter: active_energy');
}

try {
    $rootDirectory = dirname(__DIR__);
    require_once $rootDirectory . '/config.inc.php';
    require_once __DIR__ . '/health.php';

    $record = xiaoguHealthUpsert(
        \Typecho\Db::get(),
        $payload['date'],
        $payload['update_time'],
        (int) $stepsValue,
        round((float) $energyValue, 2)
    );

    healthApiRespond(200, 0, 'success', [
        'date' => (string) $record['date'],
        'update_time' => (string) $record['update_time'],
        'steps' => (int) $record['steps'],
        'active_energy' => (float) $record['active_energy'],
    ]);
} catch (\Throwable $error) {
    error_log('[health-api] ' . $error->getMessage());
    healthApiRespond(500, 500, 'Internal Server Error');
}
