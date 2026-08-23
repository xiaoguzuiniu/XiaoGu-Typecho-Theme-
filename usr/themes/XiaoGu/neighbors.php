<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$profileName = trim((string) $this->options->profileName);
$profileSignature = trim((string) $this->options->profileSignature);
$profileAvatarUrl = trim((string) $this->options->profileAvatarUrl);
$friendSiteName = trim((string) $this->options->friendSiteName);
$friendSiteDescription = trim((string) $this->options->friendSiteDescription);
$friendSiteUrl = trim((string) $this->options->friendSiteUrl);
$friendSiteLogoUrl = trim((string) $this->options->friendSiteLogoUrl);
$siteName = $friendSiteName !== ''
    ? $friendSiteName
    : ($profileName !== '' ? $profileName : (string) $this->options->title);
$siteDescription = $friendSiteDescription !== ''
    ? $friendSiteDescription
    : ($profileSignature !== '' ? $profileSignature : (string) $this->options->description);
$siteUrl = rtrim(
    $friendSiteUrl !== '' ? $friendSiteUrl : (string) $this->options->siteUrl,
    '/'
) . '/';
$siteLogoUrl = $friendSiteLogoUrl !== '' ? $friendSiteLogoUrl : $profileAvatarUrl;
if ($siteLogoUrl === '') {
    ob_start();
    $this->options->themeUrl('assets/favicon.svg');
    $siteLogoUrl = (string) ob_get_clean();
}
$contactEmail = trim((string) $this->options->friendContactEmail);
$friendLinks = getFriendLinks((string) $this->options->friendLinks);
$captchaA = random_int(2, 9);
$captchaB = random_int(1, 9);
$captchaPayload = $captchaA . ':' . $captchaB . ':' . (int) $this->cid;
$captchaToken = hash_hmac('sha256', $captchaPayload, (string) $this->options->secret);
$commentSecurityToken = $this->security->getToken($this->request->getRequestUrl());
$applicationUrl = $siteUrl . '?xiaogu_action=friend_apply&cid=' . (int) $this->cid;
$copyLines = [
    '网站名称：' . $siteName,
    'LOGO地址：' . $siteLogoUrl,
    '网站地址：' . $siteUrl,
    '网站描述：' . $siteDescription,
];
if ($contactEmail !== '') {
    $copyLines[] = '联系邮箱：' . $contactEmail;
}
?>

<div class="neighbors-page">
    <header class="neighbors-hero">
        <h1>好朋友的传送带</h1>
        <span>沿着传送带，去拜访每一位有趣的朋友。</span>
    </header>

    <section class="friend-self-card" aria-labelledby="friend-self-title">
        <div class="friend-self-heading">
            <span class="friend-avatar friend-self-avatar">
                <span aria-hidden="true"><?php echo htmlspecialchars(mb_substr($siteName, 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                <img src="<?php echo htmlspecialchars($siteLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="">
            </span>
            <div>
                <strong id="friend-self-title"><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></strong>
                <small>这是我的站点</small>
            </div>
            <button type="button" class="friend-copy-button">复制信息</button>
        </div>
        <dl class="friend-self-details">
            <div><dt>网站名称</dt><dd><?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></dd></div>
            <div><dt>LOGO 地址</dt><dd><a href="<?php echo htmlspecialchars($siteLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($siteLogoUrl, ENT_QUOTES, 'UTF-8'); ?></a></dd></div>
            <div><dt>网站地址</dt><dd><a href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?></a></dd></div>
            <div><dt>网站描述</dt><dd><?php echo htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8'); ?></dd></div>
            <?php if ($contactEmail !== ''): ?>
                <div><dt>联系邮箱</dt><dd><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></dd></div>
            <?php endif; ?>
        </dl>
    </section>

    <section class="friend-list-section" aria-labelledby="friend-list-title">
        <div class="friend-section-heading">
            <h2 id="friend-list-title"><span aria-hidden="true">✦</span> 朋友们</h2>
            <small>共 <?php echo count($friendLinks); ?> 个</small>
        </div>

        <?php if ($friendLinks): ?>
            <div class="friend-card-grid">
                <?php foreach ($friendLinks as $friend): ?>
                    <a class="friend-card" href="<?php echo htmlspecialchars($friend['url'], ENT_QUOTES, 'UTF-8'); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <span class="friend-avatar">
                            <span aria-hidden="true"><?php echo htmlspecialchars(mb_substr($friend['name'], 0, 1), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if ($friend['avatar'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($friend['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </span>
                        <span class="friend-card-copy">
                            <strong><?php echo htmlspecialchars($friend['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo htmlspecialchars($friend['description'] !== '' ? $friend['description'] : '去看看这个有趣的小站', ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="friend-empty">暂时还没有添加友链，可在主题设置的“友链列表”中添加。</div>
        <?php endif; ?>
    </section>

    <section class="friend-apply-section" aria-labelledby="friend-apply-title">
        <div class="friend-section-heading">
            <h2 id="friend-apply-title"><span aria-hidden="true">✦</span> 申请友链</h2>
        </div>

        <div class="friend-apply-notice">
            <h3>友链申请须知</h3>
            <ol>
                <li>请先将本站添加到您的友链列表中。</li>
                <li>网站可以稳定访问，并已启用 HTTPS。</li>
                <li>内容保持正常更新，拥有清晰的站点名称、头像和介绍。</li>
                <li>提交后申请会进入站点后台，站长审核通过后自动加入友链。</li>
            </ol>
        </div>

        <form class="friend-apply-form" method="post"
                  action="<?php echo htmlspecialchars($applicationUrl, ENT_QUOTES, 'UTF-8'); ?>"
                  data-referrer="<?php echo htmlspecialchars($this->request->getRequestUrl(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="friend-form-field">
                    <label for="friend-site-name">网站名称 <b>*</b></label>
                    <input id="friend-site-name" type="text" name="site_name" maxlength="50" required>
                </div>
                <div class="friend-form-field">
                    <label for="friend-site-url">网站地址 <b>*</b></label>
                    <input id="friend-site-url" type="url" name="site_url" placeholder="https://" required>
                </div>
                <div class="friend-form-field">
                    <label for="friend-avatar-url">网站头像地址</label>
                    <input id="friend-avatar-url" type="url" name="avatar_url" placeholder="https://">
                </div>
                <div class="friend-form-field">
                    <label for="friend-description">网站描述 <b>*</b></label>
                    <input id="friend-description" type="text" name="description" maxlength="300" required>
                </div>
                <div class="friend-form-field">
                    <label for="friend-rss-url">RSS 地址</label>
                    <input id="friend-rss-url" type="url" name="rss_url" placeholder="https://">
                </div>
                <div class="friend-form-field">
                    <label for="friend-mail">联系邮箱<?php if ($this->options->commentsRequireMail): ?> <b>*</b><?php endif; ?></label>
                    <input id="friend-mail" type="email" name="mail"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                </div>
                <div class="friend-form-field friend-form-wide">
                    <label for="friend-note">备注</label>
                    <input id="friend-note" type="text" name="note" maxlength="300">
                </div>
                <div class="friend-captcha">
                    <span><?php echo $captchaA; ?> + <?php echo $captchaB; ?> = ?</span>
                    <input type="number" name="captcha_answer" aria-label="验证码结果" required>
                </div>
                <input type="hidden" name="captcha_a" value="<?php echo $captchaA; ?>">
                <input type="hidden" name="captcha_b" value="<?php echo $captchaB; ?>">
                <input type="hidden" name="captcha_token" value="<?php echo htmlspecialchars($captchaToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="_" value="<?php echo htmlspecialchars($commentSecurityToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="friend-form-submit">
                    <span class="friend-apply-status" role="status"></span>
                    <button type="submit">提交申请</button>
                </div>
        </form>
    </section>
</div>

<script>
(function () {
    const root = document.querySelector('.neighbors-page');
    if (!root) return;

    root.querySelectorAll('.friend-avatar img').forEach(function (image) {
        function hideBrokenImage() {
            image.hidden = true;
        }
        image.addEventListener('error', hideBrokenImage);
        if (image.complete && !image.naturalWidth) hideBrokenImage();
    });

    const copyButton = root.querySelector('.friend-copy-button');
    const copyText = <?php echo json_encode(implode("\n", $copyLines), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (copyButton) {
        copyButton.addEventListener('click', async function () {
            try {
                await navigator.clipboard.writeText(copyText);
            } catch (error) {
                const textarea = document.createElement('textarea');
                textarea.value = copyText;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                textarea.remove();
            }
            copyButton.textContent = '已复制';
            window.setTimeout(function () {
                copyButton.textContent = '复制信息';
            }, 1600);
        });
    }

    const form = root.querySelector('.friend-apply-form');
    if (!form || !('fetch' in window)) return;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('.friend-apply-status');
        button.disabled = true;
        status.textContent = '正在提交…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                referrer: form.dataset.referrer
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || '提交失败');
            }
            form.reset();
            status.textContent = data.message;
        } catch (error) {
            status.textContent = error.message || '提交失败，请稍后重试';
        } finally {
            button.disabled = false;
        }
    });
}());
</script>
