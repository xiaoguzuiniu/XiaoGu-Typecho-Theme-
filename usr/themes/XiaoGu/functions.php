<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!defined('__TYPECHO_GRAVATAR_PREFIX__')) {
    define('__TYPECHO_GRAVATAR_PREFIX__', 'https://cravatar.cn/avatar/');
}

if (defined('__TYPECHO_ADMIN__')) {
    \Typecho\Plugin::factory('admin/footer.php')->begin = 'renderXiaoGuThemeImagePicker';
}

/**
 * 主题初始化：拦截前端 AJAX 请求（浏览量、点赞、动态评论、友链申请）。
 *
 * @param \Widget\Archive $archive
 */
function themeInit($archive)
{
    static $visitorCommentHookRegistered = false;
    if (!$visitorCommentHookRegistered) {
        \Typecho\Plugin::factory(\Widget\Feedback::class)->comment = 'applyXiaoGuVisitorCommentIdentity';
        $visitorCommentHookRegistered = true;
    }

    $action = isset($_GET['xiaogu_action']) ? $_GET['xiaogu_action'] : '';
    $cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;

    if ($cid <= 0 || !in_array($action, ['view', 'like', 'moment_comment', 'friend_apply', 'friend_review'], true)) {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
        $db = \Typecho\Db::get();

        if ($action === 'friend_review') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new \Exception('审批请求方式错误');
            }
            if (!\Widget\User::alloc()->pass('administrator', true)) {
                throw new \Exception('没有友链审批权限');
            }

            $submittedToken = isset($_GET['_']) && is_scalar($_GET['_']) ? (string) $_GET['_'] : '';
            $expectedToken = \Widget\Security::alloc()->getToken(
                \Typecho\Request::getInstance()->getReferer()
            );
            if ($submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
                throw new \Exception('审批安全验证失败，请刷新后台页面后重试');
            }

            $reviewAction = isset($_POST['review_action']) && is_scalar($_POST['review_action'])
                ? (string) $_POST['review_action']
                : '';
            $coid = isset($_POST['coid']) ? (int) $_POST['coid'] : 0;
            if (!in_array($reviewAction, ['approve', 'reject'], true) || $coid <= 0) {
                throw new \Exception('友链审批参数错误');
            }

            $comment = $db->fetchRow(
                $db->select('coid', 'cid', 'author', 'mail', 'url', 'text', 'status')
                    ->from('table.comments')
                    ->where('coid = ? AND cid = ? AND type = ?', $coid, $cid, 'comment')
                    ->limit(1)
            );
            $application = $comment ? parseXiaoGuFriendApplication($comment) : false;
            if (!$comment || $application === false || !in_array($comment['status'], ['waiting', 'approved'], true)) {
                throw new \Exception('找不到可审批的友链申请');
            }

            if ($reviewAction === 'reject') {
                $statusChanged = $db->query(
                    $db->update('table.comments')
                        ->rows(['status' => 'spam'])
                        ->where('coid = ? AND status = ?', $coid, $comment['status'])
                );
                if ($statusChanged && $comment['status'] === 'approved') {
                    $db->query(
                        $db->update('table.contents')
                            ->expression('commentsNum', 'commentsNum - 1')
                            ->where('cid = ? AND commentsNum > 0', $cid)
                    );
                }

                echo json_encode([
                    'success' => true,
                    'message' => '已拒绝该友链申请',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $friendLine = formatXiaoGuFriendLink($application);
            $options = \Widget\Options::alloc();
            $themeOptionName = 'theme:' . (string) $options->theme;
            $themeOption = $db->fetchRow(
                $db->select('value')->from('table.options')
                    ->where('name = ? AND user = ?', $themeOptionName, 0)
                    ->limit(1)
            );
            $themeSettings = $themeOption ? json_decode((string) $themeOption['value'], true) : [];
            if (!is_array($themeSettings)) {
                if ($themeOption && trim((string) $themeOption['value']) !== '') {
                    throw new \Exception('现有主题设置格式异常，已停止写入以避免覆盖');
                }
                $themeSettings = [];
            }

            $friendLinks = isset($themeSettings['friendLinks'])
                ? trim((string) $themeSettings['friendLinks'])
                : '';
            if (!isXiaoGuFriendUrlConfigured($friendLinks, $application['site_url'])) {
                $themeSettings['friendLinks'] = $friendLinks === ''
                    ? $friendLine
                    : $friendLinks . "\n" . $friendLine;
                $encodedSettings = json_encode(
                    $themeSettings,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
                if ($encodedSettings === false) {
                    throw new \Exception('友链设置保存失败');
                }

                if ($themeOption) {
                    $db->query(
                        $db->update('table.options')
                            ->rows(['value' => $encodedSettings])
                            ->where('name = ? AND user = ?', $themeOptionName, 0)
                    );
                } else {
                    $db->query(
                        $db->insert('table.options')->rows([
                            'name' => $themeOptionName,
                            'user' => 0,
                            'value' => $encodedSettings
                        ])
                    );
                }
            }

            if ($comment['status'] !== 'approved') {
                $statusChanged = $db->query(
                    $db->update('table.comments')
                        ->rows(['status' => 'approved'])
                        ->where('coid = ? AND status = ?', $coid, $comment['status'])
                );
                if ($statusChanged) {
                    $db->query(
                        $db->update('table.contents')
                            ->expression('commentsNum', 'commentsNum + 1')
                            ->where('cid = ?', $cid)
                    );
                }
            }

            echo json_encode([
                'success' => true,
                'message' => '已通过并加入友链列表',
                'friendLine' => $friendLine,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'friend_apply') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new \Exception('申请请求方式错误');
            }

            $page = \Widget\Archive::allocWithAlias(
                'friend-apply-target-' . $cid,
                'type=single',
                ['cid' => $cid],
                false
            );
            if (!$page || !$page->have() || !$page->is('page') || (string) $page->slug !== 'neighbors') {
                throw new \Exception('找不到友链申请页面');
            }

            $fields = [];
            foreach (['site_name', 'site_url', 'avatar_url', 'description', 'rss_url', 'mail', 'note'] as $field) {
                $fields[$field] = isset($_POST[$field]) && is_scalar($_POST[$field])
                    ? trim((string) $_POST[$field])
                    : '';
            }

            if ($fields['site_name'] === '' || strlen($fields['site_name']) > 150) {
                throw new \Exception('请填写正确的网站名称');
            }
            if ($fields['description'] === '' || strlen($fields['description']) > 900) {
                throw new \Exception('请填写 300 字以内的网站描述');
            }
            $fields['site_url'] = validateFriendUrl($fields['site_url'], '网站地址', true);
            $fields['avatar_url'] = validateFriendUrl($fields['avatar_url'], '网站头像地址', false);
            $fields['rss_url'] = validateFriendUrl($fields['rss_url'], 'RSS 地址', false);
            if ($fields['mail'] !== '' && !filter_var($fields['mail'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('联系邮箱格式不正确');
            }

            $captchaA = isset($_POST['captcha_a']) ? (int) $_POST['captcha_a'] : 0;
            $captchaB = isset($_POST['captcha_b']) ? (int) $_POST['captcha_b'] : 0;
            $captchaAnswer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : PHP_INT_MIN;
            $captchaToken = isset($_POST['captcha_token']) && is_scalar($_POST['captcha_token'])
                ? (string) $_POST['captcha_token']
                : '';
            $captchaPayload = $captchaA . ':' . $captchaB . ':' . $cid;
            $captchaExpected = hash_hmac('sha256', $captchaPayload, (string) \Widget\Options::alloc()->secret);
            if (
                $captchaA < 1
                || $captchaB < 1
                || !hash_equals($captchaExpected, $captchaToken)
                || $captchaAnswer !== $captchaA + $captchaB
            ) {
                throw new \Exception('验证码计算错误');
            }

            $application = [
                '友链申请',
                '网站名称：' . $fields['site_name'],
                '网站地址：' . $fields['site_url'],
                '网站描述：' . $fields['description'],
            ];
            if ($fields['avatar_url'] !== '') {
                $application[] = '头像地址：' . $fields['avatar_url'];
            }
            if ($fields['rss_url'] !== '') {
                $application[] = 'RSS 地址：' . $fields['rss_url'];
            }
            if ($fields['note'] !== '') {
                $application[] = '备注：' . $fields['note'];
            }

            $input = [
                'type' => 'comment',
                'permalink' => $page->path,
                'author' => $fields['site_name'],
                'mail' => $fields['mail'],
                'url' => $fields['site_url'],
                'text' => implode("\n", $application),
                '_' => isset($_POST['_']) && is_scalar($_POST['_']) ? (string) $_POST['_'] : '',
            ];
            $feedback = submitXiaoGuFeedback($page, $input, false);
            $status = (string) $feedback->status;

            echo json_encode([
                'success' => true,
                'status' => $status,
                'message' => '友链申请已提交，站长会尽快查看',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'moment_comment') {
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new \Exception('评论请求方式错误');
            }

            $post = \Widget\Archive::allocWithAlias(
                'moment-comment-target-' . $cid,
                'type=single',
                ['cid' => $cid],
                false
            );
            if (!$post || !$post->have() || !$post->is('post')) {
                throw new \Exception('找不到要评论的动态');
            }

            $input = [
                'type' => 'comment',
                'permalink' => $post->path,
            ];
            foreach (['author', 'mail', 'url', 'text', 'parent', '_'] as $field) {
                if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
                    $input[$field] = (string) $_POST[$field];
                }
            }

            $feedback = submitXiaoGuFeedback($post, $input);

            $status = (string) $feedback->status;
            echo json_encode([
                'success' => true,
                'status' => $status,
                'message' => $status === 'approved' ? '评论成功' : '评论已提交，正在等待审核',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'view') {
            $cookieName = 'xiaogu_view_' . $cid;
            if (empty($_COOKIE[$cookieName])) {
                $row = $db->fetchRow($db->select('int_value')->from('table.fields')
                    ->where('cid = ? AND name = ?', $cid, 'views'));
                $count = $row ? intval($row['int_value']) : 0;
                $count++;
                saveIntField($db, $cid, 'views', $count);
                setcookie($cookieName, '1', 0, '/');
            }

            $row = $db->fetchRow($db->select('int_value')->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, 'views'));
            $count = $row ? intval($row['int_value']) : 0;

            echo json_encode(['success' => true, 'count' => $count]);
            exit;
        }

        if ($action === 'like') {
            $cookieName = 'xiaogu_like_' . $cid;
            $liked = !empty($_COOKIE[$cookieName]);

            $row = $db->fetchRow($db->select('int_value')->from('table.fields')
                ->where('cid = ? AND name = ?', $cid, 'likes'));
            $count = $row ? intval($row['int_value']) : 0;

            if ($liked) {
                $count = max(0, $count - 1);
                saveIntField($db, $cid, 'likes', $count);
                setcookie($cookieName, '', time() - 3600, '/');
                $liked = false;
            } else {
                $count++;
                saveIntField($db, $cid, 'likes', $count);
                setcookie($cookieName, '1', 0, '/');
                $liked = true;
            }

            echo json_encode(['success' => true, 'liked' => $liked, 'count' => $count]);
            exit;
        }
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage() ?: '请求处理失败',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function applyXiaoGuVisitorCommentIdentity(array $comment, \Widget\Archive $content): array
{
    $request = \Typecho\Request::getInstance();
    $author = trim((string) $request->get('author'));
    $mail = trim((string) $request->get('mail'));
    $url = trim((string) $request->get('url'));

    if ($author === '' && $mail === '') {
        return $comment;
    }

    if ($author === '' || mb_strlen($author, 'UTF-8') > 150) {
        throw new \Typecho\Exception('请填写正确的昵称');
    }
    $mailRequired = (bool) \Widget\Options::alloc()->commentsRequireMail;
    if (
        ($mailRequired && $mail === '')
        || ($mail !== '' && (strlen($mail) > 150 || !filter_var($mail, FILTER_VALIDATE_EMAIL)))
    ) {
        throw new \Typecho\Exception('请填写正确的邮箱');
    }
    if ($url !== '') {
        if (strlen($url) > 255 || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Typecho\Exception('请填写正确的网址');
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \Typecho\Exception('网址必须以 http:// 或 https:// 开头');
        }
    }

    $comment['author'] = $author;
    $comment['mail'] = $mail;
    $comment['url'] = $url;
    unset($comment['authorId']);

    $expire = 30 * 24 * 3600;
    \Typecho\Cookie::set('__typecho_remember_author', $author, $expire);
    \Typecho\Cookie::set('__typecho_remember_mail', $mail, $expire);
    \Typecho\Cookie::set('__typecho_remember_url', $url, $expire);

    return $comment;
}

function validateFriendUrl(string $url, string $label, bool $required): string
{
    if ($url === '') {
        if ($required) {
            throw new \Exception('请填写' . $label);
        }
        return '';
    }

    $validated = filter_var($url, FILTER_VALIDATE_URL);
    $scheme = $validated ? strtolower((string) parse_url($validated, PHP_URL_SCHEME)) : '';
    if (!$validated || !in_array($scheme, ['http', 'https'], true)) {
        throw new \Exception($label . '格式不正确');
    }

    return $validated;
}

function parseXiaoGuFriendApplication(array $comment)
{
    $text = str_replace(["\r\n", "\r"], "\n", trim((string) ($comment['text'] ?? '')));
    $lines = preg_split('/\n+/u', $text) ?: [];
    if (empty($lines) || trim((string) $lines[0]) !== '友链申请') {
        return false;
    }

    $labels = [
        '网站名称' => 'site_name',
        '网站地址' => 'site_url',
        '网站描述' => 'description',
        '头像地址' => 'avatar_url',
        'RSS 地址' => 'rss_url',
        '备注' => 'note'
    ];
    $application = [
        'site_name' => trim((string) ($comment['author'] ?? '')),
        'site_url' => trim((string) ($comment['url'] ?? '')),
        'description' => '',
        'avatar_url' => '',
        'rss_url' => '',
        'mail' => trim((string) ($comment['mail'] ?? '')),
        'note' => ''
    ];

    foreach (array_slice($lines, 1) as $line) {
        foreach ($labels as $label => $key) {
            $prefix = $label . '：';
            if (strpos($line, $prefix) === 0) {
                $application[$key] = trim(substr($line, strlen($prefix)));
                break;
            }
        }
    }

    if (
        $application['site_name'] === ''
        || $application['description'] === ''
        || !filter_var($application['site_url'], FILTER_VALIDATE_URL)
    ) {
        return false;
    }

    return $application;
}

function formatXiaoGuFriendLink(array $application): string
{
    $clean = static function ($value): string {
        return trim(str_replace(
            ["|", "\r", "\n"],
            ["｜", ' ', ' '],
            (string) $value
        ));
    };

    return implode('|', [
        $clean($application['site_name'] ?? ''),
        $clean($application['site_url'] ?? ''),
        $clean($application['avatar_url'] ?? ''),
        $clean($application['description'] ?? '')
    ]);
}

function isXiaoGuFriendUrlConfigured(string $friendLinks, string $siteUrl): bool
{
    $target = rtrim(strtolower(trim($siteUrl)), '/');
    foreach (preg_split('/\r\n|\r|\n/', $friendLinks) ?: [] as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (isset($parts[1]) && rtrim(strtolower($parts[1]), '/') === $target) {
            return true;
        }
    }

    return false;
}

function submitXiaoGuFeedback(\Widget\Archive $content, array $input, bool $requireOpen = true): \Widget\Feedback
{
    $db = \Typecho\Db::get();
    $options = \Widget\Options::alloc();
    $security = \Widget\Security::alloc();
    $user = \Widget\User::alloc();

    if ($requireOpen && !$content->allow('comment')) {
        throw new \Exception('当前内容已关闭提交');
    }

    if (
        !$user->pass('editor', true)
        && (int) $content->authorId !== (int) $user->uid
        && $options->commentsPostIntervalEnable
    ) {
        $latestComment = $db->fetchRow(
            $db->select('created')
                ->from('table.comments')
                ->where('cid = ? AND ip = ?', (int) $content->cid, \Typecho\Request::getInstance()->getIp())
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        $elapsed = $latestComment ? (int) $options->time - (int) $latestComment['created'] : 0;
        if ($elapsed > 0 && $elapsed < (int) $options->commentsPostInterval) {
            throw new \Exception('提交过于频繁，请稍后再试');
        }
    }

    $antiSpamEnabled = (bool) $options->commentsAntiSpam;
    if ($antiSpamEnabled) {
        $submittedToken = isset($input['_']) ? (string) $input['_'] : '';
        $expectedToken = $security->getToken(\Typecho\Request::getInstance()->getReferer());
        if ($submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
            throw new \Exception('安全验证失败，请刷新页面后重试');
        }
        $options->commentsAntiSpam = false;
    }

    $httpRequest = \Typecho\Request::getInstance();
    $httpResponse = \Typecho\Response::getInstance();
    $httpRequest->beginSandbox(new \Typecho\Config($input));
    $httpResponse->beginSandbox();
    $feedback = null;

    try {
        $feedback = new \Widget\Feedback(
            new \Typecho\Widget\Request($httpRequest, new \Typecho\Config($input)),
            new \Typecho\Widget\Response($httpRequest, $httpResponse),
            ['checkReferer' => false]
        );
        $feedback->execute();
        (function (\Widget\Archive $target) {
            $this->content = $target;
            $this->comment();
        })->call($feedback, $content);
    } catch (\Typecho\Widget\Terminal $e) {
        // Feedback redirects after a successful insert; the sandbox turns it into a terminal signal.
    } finally {
        $httpResponse->endSandbox();
        $httpRequest->endSandbox();
        if ($antiSpamEnabled) {
            $options->commentsAntiSpam = true;
        }
    }

    if (!$feedback || !$feedback->have()) {
        throw new \Exception('提交验证失败，请检查填写内容');
    }

    return $feedback;
}

function themeConfig($form)
{
    $browserTitle = new \Typecho\Widget\Helper\Form\Element\Text(
        'browserTitle',
        null,
        null,
        _t('浏览器标签标题'),
        _t('显示在浏览器标签页和悬停卡片中；留空时使用站点标题。')
    );
    $form->addInput($browserTitle);

    $profileName = new \Typecho\Widget\Helper\Form\Element\Text(
        'profileName',
        null,
        null,
        _t('首页显示名称'),
        _t('留空时使用站点标题。')
    );
    $form->addInput($profileName);

    $profileSignature = new \Typecho\Widget\Helper\Form\Element\Text(
        'profileSignature',
        null,
        null,
        _t('首页个性签名'),
        _t('留空时使用站点描述。')
    );
    $form->addInput($profileSignature);

    $profileAvatarUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'profileAvatarUrl',
        null,
        null,
        _t('首页头像地址'),
        _t('可以从附件库选择或直接上传；留空时显示主题默认头像。')
    );
    $form->addInput($profileAvatarUrl->addRule('url', _t('请填写正确的头像 URL 地址')));

    $heroImageUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'heroImageUrl',
        null,
        null,
        _t('首页头图地址'),
        _t('可以从附件库选择或直接上传；留空时使用主题自带的雪山图片。')
    );
    $form->addInput($heroImageUrl->addRule('url', _t('请填写正确的头图 URL 地址')));

    $friendSiteName = new \Typecho\Widget\Helper\Form\Element\Text(
        'friendSiteName',
        null,
        null,
        _t('本站友链名称'),
        _t('显示在邻居页面的“这是我的站点”信息卡中；留空时使用首页显示名称。')
    );
    $form->addInput($friendSiteName);

    $friendSiteUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'friendSiteUrl',
        null,
        null,
        _t('本站友链网址'),
        _t('留空时使用 Typecho 设置中的站点地址。')
    );
    $form->addInput($friendSiteUrl->addRule('url', _t('请填写正确的本站友链网址')));

    $friendSiteLogoUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'friendSiteLogoUrl',
        null,
        null,
        _t('本站友链 LOGO'),
        _t('可以从附件库选择或直接上传；留空时使用首页头像。')
    );
    $form->addInput($friendSiteLogoUrl->addRule('url', _t('请填写正确的 LOGO URL 地址')));

    $friendSiteDescription = new \Typecho\Widget\Helper\Form\Element\Text(
        'friendSiteDescription',
        null,
        null,
        _t('本站友链描述'),
        _t('留空时使用首页个性签名或站点描述。')
    );
    $form->addInput($friendSiteDescription);

    $friendContactEmail = new \Typecho\Widget\Helper\Form\Element\Text(
        'friendContactEmail',
        null,
        null,
        _t('友链联系邮箱'),
        _t('显示在邻居页面的本站信息卡中；留空时不显示。')
    );
    $form->addInput($friendContactEmail->addRule('email', _t('请填写正确的联系邮箱')));

    $friendLinks = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'friendLinks',
        null,
        null,
        _t('友链列表'),
        _t('每行一个站点，格式：站点名称|网站地址|头像地址|站点描述。头像和描述可以留空。')
    );
    $form->addInput($friendLinks);
}

function renderXiaoGuThemeImagePicker()
{
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $adminPage = basename((string) $requestPath);
    $isThemeOptions = $adminPage === 'options-theme.php';
    $isPostEditor = $adminPage === 'write-post.php';
    if (!$isThemeOptions && !$isPostEditor) {
        return;
    }

    $db = \Typecho\Db::get();
    $options = \Widget\Options::alloc();
    $security = \Widget\Security::alloc();
    $neighborsPage = $isThemeOptions
        ? $db->fetchRow(
            $db->select('cid')->from('table.contents')
                ->where('slug = ? AND type = ?', 'neighbors', 'page')
                ->limit(1)
        )
        : false;
    $applications = [];
    $reviewUrl = '';

    if ($neighborsPage) {
        $reviewUrl = $security->getIndex(
            '/?xiaogu_action=friend_review&cid=' . (int) $neighborsPage['cid']
        );
        $comments = $db->fetchAll(
            $db->select('coid', 'author', 'mail', 'url', 'text', 'status', 'created')
                ->from('table.comments')
                ->where('cid = ? AND type = ?', (int) $neighborsPage['cid'], 'comment')
                ->order('created', \Typecho\Db::SORT_DESC)
                ->limit(100)
        );
        $configuredFriends = trim((string) $options->friendLinks);

        foreach ($comments as $comment) {
            if (!in_array($comment['status'], ['waiting', 'approved'], true)) {
                continue;
            }

            $application = parseXiaoGuFriendApplication($comment);
            if (
                $application === false
                || isXiaoGuFriendUrlConfigured($configuredFriends, $application['site_url'])
            ) {
                continue;
            }

            $application['coid'] = (int) $comment['coid'];
            $application['created'] = (int) $comment['created'];
            $applications[] = $application;
        }
    }

    $attachments = \Widget\Contents\Attachment\Admin::allocWithAlias(
        'xiaogu-theme-image-picker',
        ['pageSize' => 500]
    );
    $images = [];

    while ($attachments->next()) {
        if (!$attachments->attachment->isImage) {
            continue;
        }

        $images[] = [
            'cid' => (int) $attachments->cid,
            'name' => (string) $attachments->title,
            'url' => (string) $attachments->attachment->url
        ];
    }

    $uploadUrl = $security->getIndex('/action/upload');
    $pickerFields = $isPostEditor
        ? [
            [
                'name' => 'fields[postCover]',
                'emptyLabel' => '留空时自动使用正文第一张图片；正文无图时使用默认头图',
                'clearLabel' => '自动选择',
                'attachToPost' => true,
            ],
        ]
        : [
            ['name' => 'profileAvatarUrl', 'emptyLabel' => '使用主题默认头像'],
            ['name' => 'heroImageUrl', 'emptyLabel' => '使用主题默认雪山图'],
            ['name' => 'friendSiteLogoUrl', 'emptyLabel' => '使用首页头像或主题默认图标'],
        ];
    ?>
    <?php if ($isThemeOptions): ?>
    <section class="xiaogu-friend-review" id="xiaogu-friend-review" style="display:none">
        <div class="xiaogu-friend-review-heading">
            <div>
                <h3>友链申请审批</h3>
                <p>通过后会自动整理格式并加入下方“友链列表”。</p>
            </div>
            <strong data-friend-review-count><?php echo count($applications); ?> 个待处理</strong>
        </div>
        <div class="xiaogu-friend-review-list">
            <?php foreach ($applications as $application): ?>
                <article class="xiaogu-friend-application" data-friend-application="<?php echo (int) $application['coid']; ?>">
                    <div class="xiaogu-friend-application-main">
                        <?php if ($application['avatar_url'] !== ''): ?>
                            <img src="<?php echo htmlspecialchars($application['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="" loading="lazy">
                        <?php endif; ?>
                        <div>
                            <strong><?php echo htmlspecialchars($application['site_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <a href="<?php echo htmlspecialchars($application['site_url'], ENT_QUOTES, 'UTF-8'); ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($application['site_url'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <p><?php echo htmlspecialchars($application['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if ($application['mail'] !== ''): ?>
                                <small><?php echo htmlspecialchars($application['mail'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="xiaogu-friend-application-actions">
                        <button type="button" class="btn btn-s primary"
                                data-friend-review-action="approve">通过并加入友链</button>
                        <button type="button" class="btn btn-s"
                                data-friend-review-action="reject">拒绝</button>
                        <span data-friend-review-status></span>
                    </div>
                </article>
            <?php endforeach; ?>
            <p class="xiaogu-friend-review-empty"<?php if (!empty($applications)): ?> hidden<?php endif; ?>>
                暂无待处理的友链申请。
            </p>
        </div>
    </section>
    <?php endif; ?>
    <style>
        .xiaogu-friend-review {
            margin: 0 0 24px;
            padding: 18px;
            border: 1px solid #e4e7f1;
            border-radius: 8px;
            background: #fafbff;
        }

        .xiaogu-friend-review-heading {
            display: flex;
            margin-bottom: 14px;
            justify-content: space-between;
            gap: 18px;
            align-items: flex-start;
        }

        .xiaogu-friend-review-heading h3,
        .xiaogu-friend-review-heading p {
            margin: 0;
        }

        .xiaogu-friend-review-heading p,
        .xiaogu-friend-review-heading strong {
            color: #8a91a8;
            font-size: 12px;
        }

        .xiaogu-friend-review-list {
            display: grid;
            gap: 10px;
        }

        .xiaogu-friend-application {
            display: flex;
            padding: 12px;
            border: 1px solid #e7e9f2;
            border-radius: 7px;
            background: #fff;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .xiaogu-friend-application-main {
            display: flex;
            min-width: 0;
            gap: 10px;
            align-items: center;
        }

        .xiaogu-friend-application-main img {
            width: 42px;
            height: 42px;
            border: 1px solid #e6e6e6;
            border-radius: 9px;
            object-fit: cover;
            flex: 0 0 auto;
        }

        .xiaogu-friend-application-main strong,
        .xiaogu-friend-application-main a,
        .xiaogu-friend-application-main p,
        .xiaogu-friend-application-main small {
            display: block;
            margin: 0;
        }

        .xiaogu-friend-application-main a {
            overflow: hidden;
            color: #467bba;
            font-size: 12px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .xiaogu-friend-application-main p,
        .xiaogu-friend-application-main small {
            color: #777;
            font-size: 12px;
        }

        .xiaogu-friend-application-actions {
            display: flex;
            flex: 0 0 auto;
            gap: 6px;
            align-items: center;
        }

        .xiaogu-friend-application-actions span {
            color: #777;
            font-size: 12px;
        }

        .xiaogu-friend-review-empty {
            margin: 0;
            padding: 18px;
            color: #999;
            text-align: center;
        }

        .xiaogu-image-picker {
            display: grid;
            margin-top: 10px;
            padding: 12px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #fafafa;
            grid-template-columns: minmax(0, 1fr) auto auto;
            gap: 8px;
            align-items: center;
        }

        .xiaogu-image-picker select {
            width: 100%;
            min-width: 0;
        }

        .xiaogu-image-picker-preview {
            display: flex;
            min-height: 48px;
            margin-top: 2px;
            grid-column: 1 / -1;
            gap: 10px;
            align-items: center;
        }

        .xiaogu-image-picker-preview img {
            width: 72px;
            height: 48px;
            border: 1px solid #e2e2e2;
            border-radius: 5px;
            background: #fff;
            object-fit: cover;
        }

        .xiaogu-image-picker-preview img[hidden] {
            display: none;
        }

        .xiaogu-image-picker-status {
            color: #999;
            font-size: 12px;
            line-height: 1.5;
        }

        .xiaogu-image-picker-status.is-error {
            color: #c62828;
        }

        @media (max-width: 480px) {
            .xiaogu-friend-application {
                align-items: stretch;
                flex-direction: column;
            }

            .xiaogu-friend-application-actions {
                flex-wrap: wrap;
            }

            .xiaogu-image-picker {
                grid-template-columns: 1fr 1fr;
            }

            .xiaogu-image-picker select {
                grid-column: 1 / -1;
            }
        }
    </style>
    <script>
        (function () {
            var uploadUrl = <?php echo json_encode(
                $uploadUrl,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ); ?>;
            var reviewUrl = <?php echo json_encode(
                $reviewUrl,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ); ?>;
            var images = <?php echo json_encode(
                $images,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ); ?>;
            var fields = <?php echo json_encode(
                $pickerFields,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ); ?>;
            var pickers = [];

            function setStatus(picker, message, isError) {
                picker.status.textContent = message;
                picker.status.classList.toggle('is-error', Boolean(isError));
            }

            function updatePreview(picker) {
                var url = picker.input.value.trim();
                picker.preview.hidden = !url;
                picker.preview.removeAttribute('src');

                if (url) {
                    picker.preview.src = url;
                    setStatus(picker, '当前图片预览', false);
                } else {
                    setStatus(picker, picker.config.emptyLabel, false);
                }
            }

            function appendImageOption(picker, image) {
                if (!image.url || Array.from(picker.select.options).some(function (option) {
                    return option.value === image.url;
                })) {
                    return;
                }

                var option = document.createElement('option');
                option.value = image.url;
                option.textContent = image.name || ('图片 #' + image.cid);
                picker.select.appendChild(option);
            }

            function selectImage(picker, url) {
                picker.input.value = url;
                picker.select.value = url;
                picker.input.dispatchEvent(new Event('input', {bubbles: true}));
                picker.input.dispatchEvent(new Event('change', {bubbles: true}));
                updatePreview(picker);
            }

            function uploadImage(picker, file) {
                if (!file || !file.type.startsWith('image/')) {
                    setStatus(picker, '请选择图片文件', true);
                    return;
                }

                var data = new FormData();
                data.append('file', file);
                picker.uploadButton.disabled = true;
                setStatus(picker, '正在上传…', false);

                var targetUrl = new URL(uploadUrl, window.location.href);
                if (picker.config.attachToPost) {
                    var cidInput = document.querySelector('input[name="cid"]');
                    if (cidInput && cidInput.value) {
                        targetUrl.searchParams.set('cid', cidInput.value);
                    }
                }

                fetch(targetUrl.toString(), {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin'
                }).then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                }).then(function (result) {
                    var attachment = Array.isArray(result) ? result[1] : null;
                    if (!attachment || !attachment.isImage || !attachment.url) {
                        throw new Error('上传结果无效');
                    }

                    var image = {
                        cid: attachment.cid,
                        name: attachment.title,
                        url: attachment.url
                    };
                    images.unshift(image);
                    pickers.forEach(function (item) {
                        appendImageOption(item, image);
                    });
                    if (picker.config.attachToPost) {
                        var form = document.forms.write_post;
                        if (form && !form.querySelector(
                            'input[name="attachment[]"][value="' + attachment.cid + '"]'
                        )) {
                            var attachmentInput = document.createElement('input');
                            attachmentInput.type = 'hidden';
                            attachmentInput.name = 'attachment[]';
                            attachmentInput.value = attachment.cid;
                            form.appendChild(attachmentInput);
                        }
                    }
                    selectImage(picker, image.url);
                    setStatus(picker, '上传成功，保存后生效', false);
                }).catch(function (error) {
                    setStatus(picker, '上传失败：' + error.message, true);
                }).finally(function () {
                    picker.fileInput.value = '';
                    picker.uploadButton.disabled = false;
                });
            }

            fields.forEach(function (config) {
                var input = document.querySelector('input[name="' + config.name + '"]');
                if (!input) return;

                var panel = document.createElement('div');
                panel.className = 'xiaogu-image-picker';

                var select = document.createElement('select');
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = '从附件库选择图片…';
                select.appendChild(placeholder);

                var uploadButton = document.createElement('button');
                uploadButton.type = 'button';
                uploadButton.className = 'btn btn-s';
                uploadButton.textContent = '上传图片';

                var clearButton = document.createElement('button');
                clearButton.type = 'button';
                clearButton.className = 'btn btn-s';
                clearButton.textContent = config.clearLabel || '使用默认图';

                var fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.accept = 'image/*';
                fileInput.hidden = true;

                var previewWrap = document.createElement('div');
                previewWrap.className = 'xiaogu-image-picker-preview';
                var preview = document.createElement('img');
                preview.alt = '';
                var status = document.createElement('span');
                status.className = 'xiaogu-image-picker-status';
                previewWrap.appendChild(preview);
                previewWrap.appendChild(status);

                panel.appendChild(select);
                panel.appendChild(uploadButton);
                panel.appendChild(clearButton);
                panel.appendChild(fileInput);
                panel.appendChild(previewWrap);
                input.insertAdjacentElement('afterend', panel);

                var picker = {
                    config: config,
                    input: input,
                    select: select,
                    uploadButton: uploadButton,
                    fileInput: fileInput,
                    preview: preview,
                    status: status
                };
                images.forEach(function (image) {
                    appendImageOption(picker, image);
                });
                pickers.push(picker);

                if (input.value && !Array.from(select.options).some(function (option) {
                    return option.value === input.value;
                })) {
                    appendImageOption(picker, {
                        cid: 0,
                        name: '当前填写的图片地址',
                        url: input.value
                    });
                }
                select.value = input.value;
                updatePreview(picker);

                select.addEventListener('change', function () {
                    if (select.value) selectImage(picker, select.value);
                });
                uploadButton.addEventListener('click', function () {
                    fileInput.click();
                });
                fileInput.addEventListener('change', function () {
                    uploadImage(picker, fileInput.files[0]);
                });
                clearButton.addEventListener('click', function () {
                    selectImage(picker, '');
                });
                input.addEventListener('input', function () {
                    select.value = Array.from(select.options).some(function (option) {
                        return option.value === input.value;
                    }) ? input.value : '';
                    updatePreview(picker);
                });
                preview.addEventListener('error', function () {
                    preview.hidden = true;
                    setStatus(picker, '图片地址无法加载', true);
                });
            });

            var reviewPanel = document.getElementById('xiaogu-friend-review');
            var settingsForm = document.querySelector('form[action*="themes-edit"]');
            if (reviewPanel && settingsForm) {
                settingsForm.parentNode.insertBefore(reviewPanel, settingsForm);
                reviewPanel.style.display = '';

                reviewPanel.addEventListener('click', function (event) {
                    var button = event.target.closest('[data-friend-review-action]');
                    if (!button || !reviewUrl) return;

                    var card = button.closest('[data-friend-application]');
                    var action = button.getAttribute('data-friend-review-action');
                    var coid = card ? card.getAttribute('data-friend-application') : '';
                    var status = card ? card.querySelector('[data-friend-review-status]') : null;
                    if (!card || !coid || !status) return;
                    if (action === 'reject' && !window.confirm('确认拒绝这条友链申请吗？')) return;

                    var buttons = card.querySelectorAll('button');
                    buttons.forEach(function (item) {
                        item.disabled = true;
                    });
                    status.textContent = action === 'approve' ? '正在通过…' : '正在拒绝…';

                    var data = new FormData();
                    data.append('coid', coid);
                    data.append('review_action', action);

                    fetch(reviewUrl, {
                        method: 'POST',
                        body: data,
                        credentials: 'same-origin'
                    }).then(function (response) {
                        return response.json().then(function (result) {
                            if (!response.ok || !result.success) {
                                throw new Error(result.message || ('HTTP ' + response.status));
                            }
                            return result;
                        });
                    }).then(function (result) {
                        if (action === 'approve' && result.friendLine) {
                            var friendLinks = document.querySelector('textarea[name="friendLinks"]');
                            if (friendLinks) {
                                var current = friendLinks.value.trim();
                                var lines = current ? current.split(/\r?\n/) : [];
                                if (lines.indexOf(result.friendLine) === -1) {
                                    friendLinks.value = current
                                        ? current + '\n' + result.friendLine
                                        : result.friendLine;
                                    friendLinks.dispatchEvent(new Event('change', {bubbles: true}));
                                }
                            }
                        }

                        card.remove();
                        var remaining = reviewPanel.querySelectorAll('[data-friend-application]').length;
                        reviewPanel.querySelector('[data-friend-review-count]').textContent =
                            remaining + ' 个待处理';
                        reviewPanel.querySelector('.xiaogu-friend-review-empty').hidden = remaining !== 0;
                    }).catch(function (error) {
                        status.textContent = error.message;
                        buttons.forEach(function (item) {
                            item.disabled = false;
                        });
                    });
                });
            }
        }());
    </script>
    <?php
}

function themeFields($layout)
{
    $displayMode = new \Typecho\Widget\Helper\Form\Element\Select(
        'displayMode',
        [
            'article' => _t('普通文章'),
            'moment' => _t('朋友圈动态'),
        ],
        'article',
        _t('文章展示方式'),
        '<span style="display:block;padding-top:10px;line-height:1.6;">'
            . _t('普通文章可进入详情页；朋友圈动态直接在首页展示正文和图片。')
            . '</span>'
    );

    $postCover = new \Typecho\Widget\Helper\Form\Element\Text(
        'postCover',
        null,
        null,
        _t('文章封面图'),
        '<span style="display:block;padding-top:10px;line-height:1.6;">'
            . _t('可以从附件库选择、直接上传或填写图片 URL；留空时自动提取正文第一张图片，正文无图时使用默认头图。')
            . '</span>'
    );

    $layout->addItem($displayMode);
    $layout->addItem($postCover->addRule('url', _t('请填写正确的封面图 URL 地址')));
}

function getFriendLinks(string $raw): array
{
    $links = [];
    $lines = preg_split('/\R/u', $raw) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $parts = array_pad(explode('|', $line, 4), 4, '');
        $name = trim($parts[0]);
        $url = trim($parts[1]);
        $avatar = trim($parts[2]);
        $description = trim($parts[3]);

        if ($name === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            continue;
        }
        if ($avatar !== '' && !filter_var($avatar, FILTER_VALIDATE_URL)) {
            $avatar = '';
        }

        $links[] = [
            'name' => $name,
            'url' => $url,
            'avatar' => $avatar,
            'description' => $description,
        ];
    }

    return $links;
}

/**
 * 获取文章封面图 URL。
 *
 * 优先级：自定义字段 postCover > 正文第一张图片 > 空字符串。
 *
 * @param \Widget\Base\Contents $widget
 * @return string
 */
function getPostCover($widget)
{
    $cover = (string) $widget->fields->postCover;
    if ($cover !== '') {
        return $cover;
    }

    $text = (string) $widget->text;

    // 优先匹配 HTML <img>
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $text, $matches)) {
        return $matches[1];
    }

    // 再匹配 Markdown 图片语法 ![alt](url)
    if (preg_match('/!\[[^\]]*\]\(([^\)]+)\)/', $text, $matches)) {
        $url = trim($matches[1]);
        // 支持 ![alt](url "title") 这种带标题的写法
        if (($spacePos = strpos($url, ' ')) !== false) {
            $url = substr($url, 0, $spacePos);
        }
        return $url;
    }

    return '';
}

/**
 * 获取文章浏览量。
 *
 * @param \Widget\Base\Contents $widget
 * @return int
 */
function getPostViews($widget)
{
    return max(0, (int) $widget->fields->views);
}

/**
 * 获取文章点赞数。
 *
 * @param \Widget\Base\Contents $widget
 * @return int
 */
function getPostLikes($widget)
{
    return max(0, (int) $widget->fields->likes);
}

/**
 * 获取侧边栏创作活动日历。
 *
 * @param int $weeks
 * @return array
 */
function getSiteActivityCalendar(int $weeks = 17): array
{
    $weeks = max(4, min(26, $weeks));
    $today = new \DateTimeImmutable('today');
    $calendarStart = $today->modify('monday this week')->modify('-' . ($weeks - 1) . ' weeks');
    $calendarEnd = $calendarStart->modify('+' . ($weeks * 7 - 1) . ' days');
    $rangeStart = $calendarStart->getTimestamp();
    $rangeEnd = $today->setTime(23, 59, 59)->getTimestamp();

    $db = \Typecho\Db::get();
    $posts = $db->fetchAll(
        $db->select('cid', 'title', 'text', 'created', 'modified')
            ->from('table.contents')
            ->where('type = ? AND status = ?', 'post', 'publish')
            ->where('(created >= ? OR modified >= ?)', $rangeStart, $rangeStart)
            ->order('modified', \Typecho\Db::SORT_ASC)
    );

    $activitiesByDate = [];
    foreach ($posts as $post) {
        $created = (int) $post['created'];
        $modified = (int) $post['modified'];
        $createdDate = date('Y-m-d', $created);
        $modifiedDate = date('Y-m-d', $modified);
        $title = trim((string) $post['title']);
        $displayModeField = $db->fetchRow(
            $db->select('str_value')
                ->from('table.fields')
                ->where('cid = ? AND name = ?', (int) $post['cid'], 'displayMode')
        );
        $displayMode = $displayModeField ? (string) $displayModeField['str_value'] : 'article';

        if ($displayMode === 'moment') {
            $content = (string) $post['text'];
            if (strpos($content, '<!--markdown-->') === 0) {
                $content = \Utils\Markdown::convert(substr($content, 15));
            }
            $content = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = trim((string) preg_replace('/\s+/u', ' ', $content));
            $title = $content !== '' ? \Typecho\Common::subStr($content, 0, 24, '…') : '朋友圈动态';
        } else {
            $title = $title !== '' ? $title : '未命名文章';
        }

        if ($created >= $rangeStart && $created <= $rangeEnd) {
            $activitiesByDate[$createdDate][] = [
                'type' => 'new',
                'title' => $title,
            ];
        }

        if ($modified >= $rangeStart && $modified <= $rangeEnd && $modifiedDate !== $createdDate) {
            $activitiesByDate[$modifiedDate][] = [
                'type' => 'edit',
                'title' => $title,
            ];
        }
    }

    $days = [];
    for ($offset = 0; $offset < $weeks * 7; $offset++) {
        $date = $calendarStart->modify('+' . $offset . ' days');
        $dateKey = $date->format('Y-m-d');
        $activities = $activitiesByDate[$dateKey] ?? [];
        $count = count($activities);

        $days[] = [
            'date' => $dateKey,
            'future' => $date > $today,
            'count' => $count,
            'level' => min(5, $count),
            'activities' => $activities,
        ];
    }

    $months = [];
    for ($week = 0; $week < $weeks; $week++) {
        $weekStart = $calendarStart->modify('+' . $week . ' weeks');
        for ($day = 0; $day < 7; $day++) {
            $date = $weekStart->modify('+' . $day . ' days');
            if ($date->format('j') === '1') {
                $months[] = [
                    'column' => $week + 1,
                    'label' => $date->format('m'),
                ];
                break;
            }
        }
    }

    return [
        'weeks' => $weeks,
        'days' => $days,
        'months' => $months,
    ];
}

/**
 * 判断当前访客是否已对指定文章点赞。
 *
 * @param int $cid
 * @return bool
 */
function isPostLiked(int $cid)
{
    return !empty($_COOKIE['xiaogu_like_' . $cid]);
}

/**
 * 将整型自定义字段写入或更新到 fields 表。
 *
 * @param \Typecho\Db $db
 * @param int    $cid
 * @param string $name
 * @param int    $value
 */
function saveIntField($db, int $cid, string $name, int $value)
{
    $exists = $db->fetchRow($db->select('cid')->from('table.fields')
        ->where('cid = ? AND name = ?', $cid, $name));

    if ($exists) {
        $db->query($db->update('table.fields')
            ->rows([
                'type'        => 'int',
                'str_value'   => null,
                'float_value' => null,
                'int_value'   => $value,
            ])
            ->where('cid = ? AND name = ?', $cid, $name));
    } else {
        $db->query($db->insert('table.fields')
            ->rows([
                'cid'         => $cid,
                'name'        => $name,
                'type'        => 'int',
                'str_value'   => null,
                'int_value'   => $value,
                'float_value' => 0,
            ]));
    }
}

/**
 * 记录文章浏览量（同一浏览器会话内每篇文章只计一次）。
 *
 * @param \Widget\Base\Contents $widget
 */
function recordPostView($widget)
{
    $cid = (int) $widget->cid;
    if ($cid <= 0) {
        return;
    }

    $cookieName = 'xiaogu_view_' . $cid;
    if (!empty($_COOKIE[$cookieName])) {
        return;
    }

    try {
        $db = \Typecho\Db::get();
        $current = $db->fetchRow($db->select('int_value')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, 'views'));
        $count = $current ? intval($current['int_value']) : 0;
        $count++;
        saveIntField($db, $cid, 'views', $count);
        setcookie($cookieName, '1', 0, '/');
    } catch (\Exception $e) {
        // 记录失败时不影响页面渲染
    }
}
