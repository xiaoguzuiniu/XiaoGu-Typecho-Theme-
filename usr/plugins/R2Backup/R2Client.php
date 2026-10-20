<?php

namespace TypechoPlugin\R2Backup;

use RuntimeException;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

final class R2Client
{
    private string $host;
    private string $bucket;
    private string $accessKey;
    private string $secretKey;

    public function __construct(string $accountId, string $bucket, string $accessKey, string $secretKey)
    {
        foreach ([$accountId, $bucket, $accessKey, $secretKey] as $value) {
            if (trim($value) === '') {
                throw new RuntimeException('R2 配置不完整');
            }
        }

        $this->host = trim($accountId) . '.r2.cloudflarestorage.com';
        $this->bucket = trim($bucket);
        $this->accessKey = trim($accessKey);
        $this->secretKey = trim($secretKey);
    }

    public function putFile(string $key, string $path, string $contentType = 'application/octet-stream', array $metadata = []): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('待上传备份文件不存在或不可读：' . $path);
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('无法打开待上传备份文件：' . $path);
        }

        $headers = ['content-type' => $contentType];
        foreach ($metadata as $name => $value) {
            $safeName = strtolower((string) preg_replace('/[^a-zA-Z0-9-]/', '-', (string) $name));
            if ($safeName !== '') {
                $headers['x-amz-meta-' . $safeName] = (string) $value;
            }
        }

        try {
            $response = $this->request('PUT', $key, [], $headers, $path, $handle);
        } finally {
            fclose($handle);
        }

        if ($response['status'] !== 200) {
            throw new RuntimeException('上传 R2 失败：' . $this->errorMessage($response));
        }
    }

    public function head(string $key): ?array
    {
        $response = $this->request('HEAD', $key);
        if ($response['status'] === 404) {
            return null;
        }
        if ($response['status'] !== 200) {
            throw new RuntimeException('读取 R2 对象信息失败：' . $this->errorMessage($response));
        }

        return $response['headers'];
    }

    public function getFile(string $key, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('无法创建临时恢复文件');
        }
        try {
            $response = $this->request('GET', $key, [], [], null, null, $handle);
        } finally {
            fclose($handle);
        }
        if ($response['status'] !== 200) {
            @unlink($path);
            throw new RuntimeException('下载 R2 备份失败：HTTP ' . $response['status']);
        }
    }

    public function delete(string $key): void
    {
        $response = $this->request('DELETE', $key);
        if (!in_array($response['status'], [200, 204, 404], true)) {
            throw new RuntimeException('删除 R2 过期备份失败：' . $this->errorMessage($response));
        }
    }

    public function list(string $prefix): array
    {
        $objects = [];
        $continuationToken = '';

        do {
            $query = ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => '1000'];
            if ($continuationToken !== '') {
                $query['continuation-token'] = $continuationToken;
            }

            $response = $this->request('GET', '', $query);
            if ($response['status'] !== 200) {
                throw new RuntimeException('列出 R2 备份失败：' . $this->errorMessage($response));
            }

            $xml = @simplexml_load_string($response['body']);
            if ($xml === false) {
                throw new RuntimeException('无法解析 R2 对象列表');
            }

            foreach ($xml->Contents as $item) {
                $objects[] = [
                    'key' => (string) $item->Key,
                    'size' => (int) $item->Size,
                    'lastModified' => (string) $item->LastModified,
                ];
            }

            $continuationToken = (string) $xml->NextContinuationToken;
            $isTruncated = strtolower((string) $xml->IsTruncated) === 'true';
        } while ($isTruncated && $continuationToken !== '');

        return $objects;
    }

    private function request(
        string $method,
        string $key,
        array $query = [],
        array $extraHeaders = [],
        ?string $payloadPath = null,
        $payloadHandle = null,
        $downloadHandle = null
    ): array {
        $method = strtoupper($method);
        $canonicalUri = '/' . rawurlencode($this->bucket);
        if ($key !== '') {
            $canonicalUri .= '/' . implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
        }

        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $payloadHash = $payloadPath !== null ? hash_file('sha256', $payloadPath) : hash('sha256', '');
        if (!is_string($payloadHash)) {
            throw new RuntimeException('无法计算备份文件校验值');
        }

        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $headers = [
            'host' => $this->host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ];
        foreach ($extraHeaders as $name => $value) {
            $headers[strtolower(trim((string) $name))] = trim(preg_replace('/\s+/', ' ', (string) $value));
        }
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = $date . '/auto/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $regionKey = hash_hmac('sha256', 'auto', $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $responseHeaders = [];
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $url = 'https://' . $this->host . $canonicalUri;
        if ($canonicalQuery !== '') {
            $url .= '?' . $canonicalQuery;
        }

        $curl = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_USERAGENT => 'Typecho-R2Backup/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $responseHeaders[$name] = trim(substr($line, $position + 1));
                }
                return $length;
            },
        ];

        if ($method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }
        if ($payloadHandle !== null && $payloadPath !== null) {
            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $payloadHandle;
            $options[CURLOPT_INFILESIZE] = filesize($payloadPath);
        }
        if ($downloadHandle !== null) {
            $options[CURLOPT_WRITEFUNCTION] = static function ($curl, string $data) use ($downloadHandle): int {
                return fwrite($downloadHandle, $data);
            };
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new RuntimeException('R2 网络请求失败：' . ($error !== '' ? $error : '未知错误'));
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => (string) $body];
    }

    private function errorMessage(array $response): string
    {
        $xml = @simplexml_load_string((string) $response['body']);
        if ($xml !== false && isset($xml->Message)) {
            return 'HTTP ' . $response['status'] . ' ' . trim((string) $xml->Message);
        }
        return 'HTTP ' . $response['status'];
    }
}
