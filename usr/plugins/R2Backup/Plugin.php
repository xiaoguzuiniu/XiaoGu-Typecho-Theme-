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
require_once __DIR__ . '/RestoreService.php';

/**
 * Cloudflare R2 自动备份
 *
 * @package R2Backup
 * @author XiaoGu
 * @version 1.1.0
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
        Helper::addAction('r2-backup-restore', RestoreAction::class);
        TypechoPlugin::factory('admin/footer.php')->end = __CLASS__ . '::renderAdminPanel';
        return _t('Cloudflare R2 备份插件已启用，请先填写 R2 配置并执行一次测试备份。');
    }

    public static function deactivate()
    {
        Helper::removeAction('r2-backup-run');
        Helper::removeAction('r2-backup-restore');
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
        // 插件更新不会再次调用 activate()。在设置页补登记新 action，避免旧安装升级后返回 404。
        $actions = Options::alloc()->actionTable;
        if (!is_array($actions) || ($actions['r2-backup-restore'] ?? null) !== RestoreAction::class) {
            Helper::addAction('r2-backup-restore', RestoreAction::class);
        }
        $endpoint = Security::alloc()->getIndex('/action/r2-backup-run');
        $restoreEndpoint = Security::alloc()->getIndex('/action/r2-backup-restore');
        $status = BackupService::status();
        ?>
        <style>
            .r2-backup-panel{margin:22px 0;padding:18px;border:1px solid #d9d9d6;border-radius:6px;background:#fff}
            .r2-backup-panel h3{margin:0 0 7px}.r2-backup-panel p{margin:7px 0;color:#777}
            .r2-backup-panel button{margin-top:8px}.r2-backup-state{display:inline-block;margin-left:10px}
            .r2-backup-panel select{min-width:230px;max-width:100%;padding:5px}.r2-backup-warning{color:#a33!important}
        </style>
        <section class="r2-backup-panel" id="r2-backup-panel">
            <h3>备份状态</h3>
            <p>每天的数据库和配置快照只保留最近 14 天；整个七牛 Bucket 使用增量镜像。</p>
            <p id="r2-backup-last"><?php echo htmlspecialchars(self::statusText($status), ENT_QUOTES, 'UTF-8'); ?></p>
            <button type="button" class="btn primary" id="r2-backup-run">立即备份并测试</button>
            <span class="r2-backup-state" id="r2-backup-state" role="status"></span>
        </section>
        <section class="r2-backup-panel" id="r2-restore-panel">
            <h3>从 R2 恢复</h3>
            <p class="r2-backup-warning">会覆盖当前数据库和同名本地上传文件。系统将先自动生成一份“恢复前”保护快照；服务器配置和七牛云照片不会被覆盖。</p>
            <p>选择快照：<select id="r2-restore-snapshot"><option value="">正在读取快照…</option></select></p>
            <p id="r2-restore-summary">将显示所选快照的备份清单。</p>
            <button type="button" class="btn" id="r2-restore-run" disabled>恢复所选快照</button>
            <span class="r2-backup-state" id="r2-restore-state" role="status"></span>
        </section>
        <script>
        (function () {
            var panel = document.getElementById('r2-backup-panel');
            var form = document.querySelector('.typecho-page-main form');
            var button = document.getElementById('r2-backup-run');
            var state = document.getElementById('r2-backup-state');
            var last = document.getElementById('r2-backup-last');
            var endpoint = <?php echo json_encode($endpoint, JSON_UNESCAPED_SLASHES); ?>;
            var restoreEndpoint = <?php echo json_encode($restoreEndpoint, JSON_UNESCAPED_SLASHES); ?>;
            var restorePanel = document.getElementById('r2-restore-panel');
            var restoreSelect = document.getElementById('r2-restore-snapshot');
            var restoreButton = document.getElementById('r2-restore-run');
            var restoreState = document.getElementById('r2-restore-state');
            var restoreSummary = document.getElementById('r2-restore-summary');
            var restoreRunning = false;
            if (panel && form) form.parentNode.insertBefore(panel, form.nextSibling);
            if (restorePanel && panel) panel.parentNode.insertBefore(restorePanel, panel.nextSibling);
            if (!button) return;

            function readResponse(response) {
                return response.text().then(function (body) {
                    var data;
                    try {
                        data = JSON.parse(body);
                    } catch (error) {
                        var page = new DOMParser().parseFromString(body, 'text/html');
                        var detail = (page.querySelector('h1') || page.querySelector('title'));
                        throw new Error('服务器返回 HTTP ' + response.status
                            + (detail ? '：' + detail.textContent.trim() : '，请查看服务器错误日志'));
                    }
                    if (!response.ok || !data.success) throw new Error(data.message || '备份失败');
                    return data;
                });
            }

            function pollStatus() {
                fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
                    .then(readResponse)
                    .then(function (data) {
                        var result = data.status || {};
                        if (result.running) {
                            if (Date.now() / 1000 - Number(result.startedAt || 0) > 3600) {
                                button.disabled = false;
                                state.textContent = '备份超过 1 小时仍未完成，请检查服务器备份日志后重试。';
                                return;
                            }
                            state.textContent = '正在后台备份，可离开页面后再回来查看结果…';
                            setTimeout(pollStatus, 3000);
                            return;
                        }
                        button.disabled = false;
                        if (result.success) {
                            state.textContent = '备份成功：' + result.snapshot
                                + '，七牛新增 ' + result.qiniuUploaded + ' 个';
                            last.textContent = '最近一次成功：' + result.finishedAt
                                + '；快照 ' + result.snapshot + '。';
                        } else {
                            state.textContent = '备份失败：' + (result.message || '未知错误');
                        }
                    })
                    .catch(function (error) {
                        button.disabled = false;
                        state.textContent = '无法读取备份状态：' + error.message;
                    });
            }

            button.addEventListener('click', function () {
                button.disabled = true;
                state.textContent = '正在启动后台备份…';
                fetch(endpoint, {
                    method: 'POST', credentials: 'same-origin'
                }).then(readResponse).then(function () {
                    state.textContent = '备份已启动，正在等待结果…';
                    setTimeout(pollStatus, 1500);
                }).catch(function (error) {
                    state.textContent = '备份失败：' + error.message;
                    button.disabled = false;
                });
            });
            function pollRestore() {
                fetch(restoreEndpoint + '&status=1', {credentials: 'same-origin', cache: 'no-store'}).then(readResponse).then(function (data) {
                    var result = data.status || {};
                    if (result.running) {
                        restoreRunning = true;
                        if (Date.now() / 1000 - Number(result.startedAt || 0) > 7200) {
                            restoreState.textContent = '恢复超过 2 小时，请查看服务器日志。';
                            return;
                        }
                        restoreState.textContent = result.stage || '正在后台恢复…';
                        setTimeout(pollRestore, 5000);
                        return;
                    }
                    restoreRunning = false;
                    restoreButton.disabled = false;
                    restoreState.textContent = result.success
                        ? '恢复成功；恢复前保护快照：' + result.protectionSnapshot + '。请刷新页面或重新登录。'
                        : '恢复失败：' + (result.message || '请查看服务器恢复日志');
                }).catch(function (error) {
                    restoreState.textContent = '无法读取恢复状态：' + error.message + '。如登录状态已变更，请重新登录。';
                });
            }
            function showRestoreSummary() {
                if (!restoreSelect.value) return;
                restoreButton.disabled = true;
                restoreSummary.textContent = '正在检查快照清单…';
                fetch(restoreEndpoint + '&snapshot=' + encodeURIComponent(restoreSelect.value), {credentials: 'same-origin', cache: 'no-store'})
                    .then(readResponse).then(function (data) {
                        var info = data.summary;
                        restoreSummary.textContent = '备份时间：' + info.createdAt + '；站点：' + info.site
                            + '；数据库表：' + info.tables + '；本地上传文件：' + info.localUploads + '。';
                        restoreButton.disabled = restoreRunning;
                    }).catch(function (error) {
                        restoreSummary.textContent = '无法核对清单：' + error.message;
                        restoreButton.disabled = true;
                    });
            }
            fetch(restoreEndpoint, {credentials: 'same-origin', cache: 'no-store'}).then(readResponse).then(function (data) {
                var snapshots = data.snapshots || [];
                restoreSelect.replaceChildren();
                snapshots.forEach(function (item) {
                    var option = document.createElement('option');
                    option.value = item.snapshot;
                    option.textContent = item.snapshot.replace('/', ' ');
                    restoreSelect.appendChild(option);
                });
                if (!snapshots.length) {
                    restoreSelect.add(new Option('暂无可恢复快照', ''));
                }
                restoreRunning = !!(data.status || {}).running;
                restoreButton.disabled = !snapshots.length || restoreRunning;
                if (snapshots.length) showRestoreSummary();
                if ((data.status || {}).running) pollRestore();
            }).catch(function (error) {
                restoreSelect.replaceChildren(new Option('读取失败', ''));
                restoreState.textContent = error.message;
            });
            restoreSelect.addEventListener('change', showRestoreSummary);
            restoreButton.addEventListener('click', function () {
                var snapshot = restoreSelect.value;
                if (!snapshot) return;
                var phrase = '恢复 ' + snapshot;
                var confirmation = window.prompt('将覆盖当前数据库和同名本地上传文件。请完整输入“' + phrase + '”以确认：');
                if (confirmation !== phrase) return;
                restoreButton.disabled = true;
                restoreRunning = true;
                restoreState.textContent = '正在启动恢复…';
                var body = new URLSearchParams({snapshot: snapshot, confirmation: confirmation});
                fetch(restoreEndpoint, {method: 'POST', credentials: 'same-origin', body: body}).then(readResponse).then(function () {
                    restoreState.textContent = '恢复已开始；请不要重复点击。';
                    setTimeout(pollRestore, 3000);
                }).catch(function (error) {
                    restoreButton.disabled = false;
                    restoreRunning = false;
                    restoreState.textContent = '无法启动恢复：' + error.message;
                });
            });
            <?php if (!empty($status['running']) && time() - (int) ($status['startedAt'] ?? 0) < 3600): ?>
            button.disabled = true;
            pollStatus();
            <?php endif; ?>
        }());
        </script>
        <?php
    }

    private static function statusText(array $status): string
    {
        if (!$status) {
            return '尚未执行过备份。';
        }
        if (!empty($status['running'])) {
            return time() - (int) ($status['startedAt'] ?? 0) < 3600
                ? '备份正在后台执行，可稍后刷新查看结果。'
                : '备份超过 1 小时仍未完成，请检查服务器备份日志。';
        }
        if (!empty($status['success'])) {
            return '最近一次成功：' . ($status['finishedAt'] ?? '') . '；快照 ' . ($status['snapshot'] ?? '')
                . '；七牛新增 ' . ($status['qiniuUploaded'] ?? 0) . ' 个。';
        }
        return '最近一次失败：' . ($status['finishedAt'] ?? '') . '；' . ($status['message'] ?? '未知错误');
    }
}
