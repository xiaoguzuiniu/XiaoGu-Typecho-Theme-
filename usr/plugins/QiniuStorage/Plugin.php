<?php

namespace TypechoPlugin\QiniuStorage;

use Typecho\Common;
use Typecho\Config;
use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Widget\Options;
use Widget\Upload;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 七牛云 Kodo 附件存储
 *
 * @package QiniuStorage
 * @author XiaoGu
 * @version 1.0.0
 * @link https://developer.qiniu.com/kodo
 */
class Plugin implements PluginInterface
{
    private const PATH_PREFIX = 'qiniu://';

    private const REGIONS = [
        'z0' => [
            'upload' => 'https://up.qiniup.com',
            'resource' => 'https://rs-z0.qiniuapi.com'
        ],
        'z1' => [
            'upload' => 'https://up-z1.qiniup.com',
            'resource' => 'https://rs-z1.qiniuapi.com'
        ],
        'z2' => [
            'upload' => 'https://up-z2.qiniup.com',
            'resource' => 'https://rs-z2.qiniuapi.com'
        ],
        'cn-east-2' => [
            'upload' => 'https://up-cn-east-2.qiniup.com',
            'resource' => 'https://rs-cn-east-2.qiniuapi.com'
        ],
        'na0' => [
            'upload' => 'https://up-na0.qiniup.com',
            'resource' => 'https://rs-na0.qiniuapi.com'
        ],
        'as0' => [
            'upload' => 'https://up-as0.qiniup.com',
            'resource' => 'https://rs-as0.qiniuapi.com'
        ]
    ];

    public static function activate()
    {
        if (!function_exists('curl_init') || !class_exists('CURLFile')) {
            throw new PluginException(_t('七牛云存储插件需要 PHP cURL 扩展。'));
        }

        $factory = TypechoPlugin::factory(Upload::class);
        $factory->uploadHandle = __CLASS__ . '::uploadHandle';
        $factory->modifyHandle = __CLASS__ . '::modifyHandle';
        $factory->deleteHandle = __CLASS__ . '::deleteHandle';
        $factory->attachmentHandle = __CLASS__ . '::attachmentHandle';
        $factory->attachmentDataHandle = __CLASS__ . '::attachmentDataHandle';

        return _t('七牛云存储已接管新上传的附件，已有本地附件仍可正常访问。');
    }

    public static function deactivate()
    {
    }

    public static function config(Form $form)
    {
        $bucket = new Text(
            'bucket',
            null,
            null,
            _t('存储空间名称'),
            _t('填写七牛云 Kodo 中已创建的公开空间名称。')
        );
        $bucket->addRule('required', _t('请填写存储空间名称。'));
        $form->addInput($bucket);

        $accessKey = new Text(
            'accessKey',
            null,
            null,
            _t('AccessKey'),
            _t('在七牛云“密钥管理”中获取，仅保存在 Typecho 插件配置中。')
        );
        $accessKey->addRule('required', _t('请填写 AccessKey。'));
        $form->addInput($accessKey);

        $secretKey = new Password(
            'secretKey',
            null,
            null,
            _t('SecretKey'),
            _t('请勿将 SecretKey 写入主题或公开仓库。')
        );
        $secretKey->addRule('required', _t('请填写 SecretKey。'));
        $form->addInput($secretKey);

        $domain = new Text(
            'domain',
            null,
            null,
            _t('访问域名'),
            _t('填写完整域名。七牛测试域名自动使用 HTTP，自定义 CDN 域名默认使用 HTTPS。')
        );
        $domain->addRule('required', _t('请填写附件访问域名。'));
        $form->addInput($domain);

        $region = new Select(
            'region',
            [
                'z0' => _t('华东-浙江'),
                'z1' => _t('华北-河北'),
                'z2' => _t('华南-广东'),
                'cn-east-2' => _t('华东-浙江 2'),
                'na0' => _t('北美-洛杉矶'),
                'as0' => _t('亚太-新加坡')
            ],
            'z0',
            _t('存储区域'),
            _t('必须与七牛云空间所在区域一致，否则上传或删除会失败。')
        );
        $form->addInput($region);

        $pathPrefix = new Text(
            'pathPrefix',
            null,
            'typecho/{year}/{month}',
            _t('对象路径'),
            _t('支持 {year}、{month}、{day}，文件名由插件自动生成。')
        );
        $form->addInput($pathPrefix);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function uploadHandle(array $file)
    {
        $fileInfo = self::validateFile($file);
        if ($fileInfo === false) {
            return false;
        }

        $config = self::getConfig();
        if ($config === false) {
            return false;
        }

        $source = self::prepareSource($file);
        if ($source === false) {
            return false;
        }

        $key = self::createObjectKey($config['pathPrefix'], $fileInfo['extension']);

        try {
            if (!self::uploadFile($source['path'], $fileInfo['name'], $key, $config, true)) {
                return false;
            }

            $size = isset($file['size']) ? (int) $file['size'] : filesize($source['path']);
            $mime = Common::mimeContentType($source['path']);

            return [
                'name' => $fileInfo['name'],
                'path' => self::PATH_PREFIX . $key,
                'size' => $size,
                'type' => $fileInfo['extension'],
                'mime' => $mime ?: 'application/octet-stream'
            ];
        } finally {
            self::cleanSource($source);
        }
    }

    public static function modifyHandle(array $content, array $file)
    {
        $fileInfo = self::validateFile($file);
        if ($fileInfo === false || empty($content['attachment'])) {
            return false;
        }

        $attachment = $content['attachment'];
        if ((string) $attachment->type !== $fileInfo['extension']) {
            self::logError('替换附件', '新文件扩展名与原附件不一致');
            return false;
        }

        $config = self::getConfig();
        if ($config === false) {
            return false;
        }

        $source = self::prepareSource($file);
        if ($source === false) {
            return false;
        }

        $isRemote = self::isRemotePath((string) $attachment->path);
        $key = $isRemote
            ? self::objectKey((string) $attachment->path)
            : self::createObjectKey($config['pathPrefix'], $fileInfo['extension']);

        try {
            if (!self::uploadFile($source['path'], $fileInfo['name'], $key, $config, !$isRemote)) {
                return false;
            }

            if (!$isRemote) {
                self::removeLocalFile((string) $attachment->path);
            }

            $size = isset($file['size']) ? (int) $file['size'] : filesize($source['path']);
            $mime = Common::mimeContentType($source['path']);

            return [
                'name' => (string) $attachment->name,
                'path' => self::PATH_PREFIX . $key,
                'size' => $size,
                'type' => (string) $attachment->type,
                'mime' => $mime ?: (string) $attachment->mime
            ];
        } finally {
            self::cleanSource($source);
        }
    }

    public static function deleteHandle(array $content): bool
    {
        if (empty($content['attachment'])) {
            self::logError('删除附件', '附件信息不完整');
            return false;
        }

        $path = (string) $content['attachment']->path;
        if (!self::isRemotePath($path)) {
            return self::removeLocalFile($path);
        }

        $config = self::getConfig();
        if ($config === false) {
            return false;
        }

        $entry = self::urlSafeBase64($config['bucket'] . ':' . self::objectKey($path));
        $url = $config['resourceHost'] . '/delete/' . $entry;
        $headers = self::managementHeaders($url, $config);

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'Typecho-QiniuStorage/1.0'
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            self::logError('删除附件', $error ?: '七牛云请求失败');
            return false;
        }

        if ($status !== 200 && $status !== 612) {
            self::logError('删除附件', self::responseError($body, $status));
            return false;
        }

        return true;
    }

    public static function attachmentHandle(Config $attachment): string
    {
        $path = (string) $attachment->path;
        if (!self::isRemotePath($path)) {
            return Common::url(
                $path,
                defined('__TYPECHO_UPLOAD_URL__') ? __TYPECHO_UPLOAD_URL__ : Options::alloc()->siteUrl
            );
        }

        $config = self::getConfig();
        if ($config === false) {
            return '';
        }

        return $config['domain'] . '/' . self::encodeObjectKey(self::objectKey($path));
    }

    public static function attachmentDataHandle(array $content): string
    {
        if (empty($content['attachment'])) {
            self::logError('读取附件', '附件信息不完整');
            return '';
        }

        $attachment = $content['attachment'];
        $path = (string) $attachment->path;
        if (!self::isRemotePath($path)) {
            $localPath = Common::url(
                $path,
                defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__
            );
            $data = @file_get_contents($localPath);
            if ($data === false) {
                self::logError('读取附件', '无法读取本地附件');
                return '';
            }

            return $data;
        }

        $url = self::attachmentHandle($attachment);
        if ($url === '') {
            return '';
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'Typecho-QiniuStorage/1.0'
        ]);

        $data = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($data === false || $status < 200 || $status >= 300) {
            self::logError('读取附件', $error ?: 'HTTP ' . $status);
            return '';
        }

        return $data;
    }

    private static function validateFile(array $file)
    {
        if (empty($file['name'])) {
            self::logError('上传附件', '文件名为空');
            return false;
        }

        $name = str_replace(['\\', '"', '<', '>'], ['/', '', '', ''], (string) $file['name']);
        $name = basename($name);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === '' || !Upload::checkFileType($extension)) {
            self::logError('上传附件', '不允许上传该文件类型');
            return false;
        }

        return [
            'name' => $name,
            'extension' => $extension
        ];
    }

    private static function prepareSource(array $file)
    {
        if (!empty($file['tmp_name']) && is_file($file['tmp_name'])) {
            return [
                'path' => (string) $file['tmp_name'],
                'temporary' => false
            ];
        }

        $data = null;
        if (array_key_exists('bytes', $file)) {
            $data = $file['bytes'];
        } elseif (array_key_exists('bits', $file)) {
            $data = $file['bits'];
        }

        if (!is_string($data)) {
            self::logError('上传附件', '没有可读取的文件数据');
            return false;
        }

        $path = tempnam(sys_get_temp_dir(), 'qiniu_');
        if ($path === false) {
            self::logError('上传附件', '无法创建临时文件');
            return false;
        }

        $written = file_put_contents($path, $data);
        if ($written === false || $written !== strlen($data)) {
            @unlink($path);
            self::logError('上传附件', '无法写入临时文件');
            return false;
        }

        return [
            'path' => $path,
            'temporary' => true
        ];
    }

    private static function cleanSource(array $source)
    {
        if (!empty($source['temporary']) && is_file($source['path'])) {
            @unlink($source['path']);
        }
    }

    private static function uploadFile(
        string $path,
        string $originalName,
        string $key,
        array $config,
        bool $insertOnly
    ): bool {
        $policy = [
            'scope' => $config['bucket'] . ':' . $key,
            'deadline' => time() + 3600,
            'insertOnly' => $insertOnly ? 1 : 0,
            'detectMime' => 1,
            'returnBody' => '{"key":$(key),"hash":$(etag)}'
        ];
        $encodedPolicy = self::urlSafeBase64((string) json_encode($policy));
        $signature = self::urlSafeBase64(
            hash_hmac('sha1', $encodedPolicy, $config['secretKey'], true)
        );
        $token = $config['accessKey'] . ':' . $signature . ':' . $encodedPolicy;
        $mime = Common::mimeContentType($path) ?: 'application/octet-stream';

        $curl = curl_init($config['uploadHost']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'token' => $token,
                'key' => $key,
                'file' => new \CURLFile($path, $mime, $originalName)
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Expect:'],
            CURLOPT_USERAGENT => 'Typecho-QiniuStorage/1.0'
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            self::logError('上传附件', $error ?: '七牛云请求失败');
            return false;
        }

        $response = json_decode($body, true);
        if ($status !== 200 || !is_array($response) || ($response['key'] ?? null) !== $key) {
            self::logError('上传附件', self::responseError($body, $status));
            return false;
        }

        return true;
    }

    private static function getConfig()
    {
        $options = Options::alloc()->plugin('QiniuStorage');
        $bucket = trim((string) $options->bucket);
        $accessKey = trim((string) $options->accessKey);
        $secretKey = trim((string) $options->secretKey);
        $domain = self::normalizeDomain((string) $options->domain);
        $region = (string) $options->region;

        if ($bucket === '' || $accessKey === '' || $secretKey === '' || $domain === '') {
            self::logError('读取配置', 'Bucket、AccessKey、SecretKey 或访问域名未填写');
            return false;
        }

        if (!isset(self::REGIONS[$region])) {
            self::logError('读取配置', '存储区域无效');
            return false;
        }

        return [
            'bucket' => $bucket,
            'accessKey' => $accessKey,
            'secretKey' => $secretKey,
            'domain' => $domain,
            'pathPrefix' => trim((string) $options->pathPrefix),
            'uploadHost' => self::REGIONS[$region]['upload'],
            'resourceHost' => self::REGIONS[$region]['resource']
        ];
    }

    private static function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $domain)) {
            $scheme = preg_match('#(?:^|\.)clouddn\.com(?:/|$)#i', $domain) ? 'http://' : 'https://';
            $domain = $scheme . $domain;
        }

        return rtrim($domain, '/');
    }

    private static function createObjectKey(string $template, string $extension): string
    {
        $prefix = strtr($template, [
            '{year}' => date('Y'),
            '{month}' => date('m'),
            '{day}' => date('d')
        ]);
        $prefix = trim(str_replace('\\', '/', $prefix), '/');
        $segments = [];

        foreach (explode('/', $prefix) as $segment) {
            $segment = trim($segment);
            if ($segment !== '' && $segment !== '.' && $segment !== '..') {
                $segments[] = $segment;
            }
        }

        if (empty($segments)) {
            $segments[] = 'typecho';
        }

        $random = substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 24);
        $segments[] = date('His') . '-' . $random . '.' . $extension;

        return implode('/', $segments);
    }

    private static function managementHeaders(string $url, array $config): array
    {
        $parts = parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $host = isset($parts['host']) ? $parts['host'] : '';
        if (!empty($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        $date = gmdate('Ymd\THis\Z');
        $contentType = 'application/x-www-form-urlencoded';
        $signingData = 'POST ' . $path
            . "\nHost: " . $host
            . "\nContent-Type: " . $contentType
            . "\nX-Qiniu-Date: " . $date
            . "\n\n";
        $signature = self::urlSafeBase64(
            hash_hmac('sha1', $signingData, $config['secretKey'], true)
        );

        return [
            'Authorization: Qiniu ' . $config['accessKey'] . ':' . $signature,
            'Content-Type: ' . $contentType,
            'X-Qiniu-Date: ' . $date
        ];
    }

    private static function urlSafeBase64(string $data): string
    {
        return str_replace(['+', '/'], ['-', '_'], base64_encode($data));
    }

    private static function isRemotePath(string $path): bool
    {
        return strpos($path, self::PATH_PREFIX) === 0;
    }

    private static function objectKey(string $path): string
    {
        return substr($path, strlen(self::PATH_PREFIX));
    }

    private static function encodeObjectKey(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    private static function removeLocalFile(string $path): bool
    {
        $localPath = Common::url(
            $path,
            defined('__TYPECHO_UPLOAD_ROOT_DIR__') ? __TYPECHO_UPLOAD_ROOT_DIR__ : __TYPECHO_ROOT_DIR__
        );

        if (!is_file($localPath)) {
            return true;
        }

        if (!@unlink($localPath)) {
            self::logError('删除附件', '无法删除本地附件');
            return false;
        }

        return true;
    }

    private static function responseError(string $body, int $status): string
    {
        $response = json_decode($body, true);
        $message = is_array($response) && !empty($response['error'])
            ? (string) $response['error']
            : 'HTTP ' . $status;

        return substr(str_replace(["\r", "\n"], ' ', $message), 0, 300);
    }

    private static function logError(string $operation, string $message)
    {
        error_log('[QiniuStorage] ' . $operation . '失败：' . $message);
    }
}
