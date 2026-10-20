<?php

namespace TypechoPlugin\R2Backup;

use Typecho\Plugin as TypechoPlugin;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Text;
use Utils\Helper;
use Widget\Options;
use Widget\Security;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/R2Client.php';
require_once __DIR__ . '/BackupService.php';

/**
 * Cloudflare R2 自动备份
 *
 * @package R2Backup
 * @author XiaoGu
 * @version 1.0.0
 * @link https://developers.cloudflare.com/r2/
 */
final class Plugin implements PluginInterface
{
    public static function activate()
    {
        if (!function_exists('curl_init') || !function_exists('gzopen') || !function_exists('simplexml_load_string')) {
            throw new PluginException(_t('R2 备份插件需要 PHP cURL、zlib 和 SimpleXML 扩展。'));
        }

        Helper::addAction('r2-backup-run', BackupAction::class);
        TypechoPlugin::factory('admin/footer.php')->end = __CLASS__ . '::renderAdminPanel';
        return _t('Cloudflare R2 备份插件已启用，请先填写 R2 配置并执行一次测试备份。');
    }

    public static function deactivate()
    {
        Helper::removeAction('r2-backup-run');
    }

    public static function config(Form $form)
    {
        $accountId = new Text('accountId', null, null, _t('Cloudflare Account ID'), _t('在 Cloudflare 控制台右侧复制 Account ID。'));
        $accountId->addRule('required', _t('请填写 Account ID。'));
        $form->addInput($accountId);

        $bucket = new Text('bucket', null, 'xiaogu-backup', _t('R2 Bucket 名称'), _t('Bucket 必须为私有，并使用 Standard 存储类型。'));
        $bucket->addRule('required', _t('请填写 R2 Bucket 名称。'));
        $form->addInput($bucket);

        $accessKey = new Text('accessKey', null, null, _t('Access Key ID'), _t('建议创建仅允许读写该 Bucket 的 R2 API Token。'));
        $accessKey->addRule('required', _t('请填写 Access Key ID。'));
        $form->addInput($accessKey);

        $secretKey = new Password('secretKey', null, null, _t('Secret Access Key'), _t('密钥仅保存在 Typecho 插件配置中，不要提交到 Git。'));
        $secretKey->addRule('required', _t('请填写 Secret Access Key。'));
        $form->addInput($secretKey);

        $prefix = new Text('prefix', null, 'xiaogu-backup', _t('R2 存储前缀'), _t('同一个 Bucket 中用于隔离本博客备份的目录。'));
        $form->addInput($prefix);

        $syncQiniu = new Select(
            'syncQiniu',
            ['1' => _t('同步七牛云附件'), '0' => _t('只备份数据库和配置')],
            '1',
            _t('七牛云附件镜像'),
            _t('列举整个七牛 Bucket，并根据对象哈希增量复制到 R2；包括相册、账单照片和球队队徽。')
        );
        $form->addInput($syncQiniu);

        $retention = new Text('retentionDisplay', null, '14 天（固定）', _t('快照保留时间'), _t('每天的数据库和配置快照超过 14 天后自动清理。七牛镜像保留最新副本。'));
        $retention->input->setAttribute('readonly', 'readonly');
        $form->addInput($retention);

        $cron = new Text(
            'cronCommand',
            null,
            'cd ' . __TYPECHO_ROOT_DIR__ . ' && php usr/plugins/R2Backup/cron.php',
            _t('服务器定时任务命令'),
            _t('也可以运行 scripts/install-r2-backup-cron.sh 自动安装每天凌晨 03:30 的任务。')
        );
        $cron->input->setAttribute('readonly', 'readonly');
        $form->addInput($cron);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function renderAdminPanel(): void
    {
        if (($_GET['config'] ?? '') !== 'R2Backup') {
            return;
        }
        $endpoint = Security::alloc()->getIndex('/action/r2-backup-run');
        $status = BackupService::status();
        ?>
        <style>
            .r2-backup-panel{margin:22px 0;padding:18px;border:1px solid #d9d9d6;border-radius:6px;background:#fff}
            .r2-backup-panel h3{margin:0 0 7px}.r2-backup-panel p{margin:7px 0;color:#777}
            .r2-backup-panel button{margin-top:8px}.r2-backup-state{display:inline-block;margin-left:10px}
        </style>
        <section class="r2-backup-panel" id="r2-backup-panel">
            <h3>备份状态</h3>
            <p>每天的数据库和配置快照只保留最近 14 天；整个七牛 Bucket 使用增量镜像。</p>
            <p id="r2-backup-last"><?php echo htmlspecialchars(self::statusText($status), ENT_QUOTES, 'UTF-8'); ?></p>
            <button type="button" class="btn primary" id="r2-backup-run">立即备份并测试</button>
            <span class="r2-backup-state" id="r2-backup-state" role="status"></span>
        </section>
        <script>
        (function () {
            var panel = document.getElementById('r2-backup-panel');
            var form = document.querySelector('.typecho-page-main form');
            var button = document.getElementById('r2-backup-run');
            var state = document.getElementById('r2-backup-state');
            if (panel && form) form.parentNode.insertBefore(panel, form.nextSibling);
            if (!button) return;
            button.addEventListener('click', function () {
                button.disabled = true;
                state.textContent = '正在备份，请不要关闭页面…';
                fetch(<?php echo json_encode($endpoint, JSON_UNESCAPED_SLASHES); ?>, {
                    method: 'POST', credentials: 'same-origin'
                }).then(function (response) {
                    return response.json().then(function (data) {
                        if (!response.ok || !data.success) throw new Error(data.message || '备份失败');
                        return data;
                    });
                }).then(function (data) {
                    state.textContent = '备份成功：' + data.snapshot + '，七牛新增 ' + data.qiniuUploaded + ' 个';
                }).catch(function (error) {
                    state.textContent = '备份失败：' + error.message;
                }).finally(function () {
                    button.disabled = false;
                });
            });
        }());
        </script>
        <?php
    }

    private static function statusText(array $status): string
    {
        if (!$status) {
            return '尚未执行过备份。';
        }
        if (!empty($status['success'])) {
            return '最近一次成功：' . ($status['finishedAt'] ?? '') . '；快照 ' . ($status['snapshot'] ?? '')
                . '；七牛新增 ' . ($status['qiniuUploaded'] ?? 0) . ' 个。';
        }
        return '最近一次失败：' . ($status['finishedAt'] ?? '') . '；' . ($status['message'] ?? '未知错误');
    }
}
