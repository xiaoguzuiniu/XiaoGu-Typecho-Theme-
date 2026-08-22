<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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

    if ($cid <= 0 || !in_array($action, ['view', 'like', 'moment_comment', 'friend_apply'], true)) {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
        $db = \Typecho\Db::get();

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
    if ($mail === '' || strlen($mail) > 150 || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
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
    if (basename((string) $requestPath) !== 'options-theme.php') {
        return;
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

    $uploadUrl = \Widget\Security::alloc()->getIndex('/action/upload');
    ?>
    <style>
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
            var images = <?php echo json_encode(
                $images,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ); ?>;
            var fields = [
                {name: 'profileAvatarUrl', emptyLabel: '使用主题默认头像'},
                {name: 'heroImageUrl', emptyLabel: '使用主题默认雪山图'}
            ];
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

                fetch(uploadUrl, {
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
                    selectImage(picker, image.url);
                    setStatus(picker, '上传成功，保存设置后生效', false);
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
                clearButton.textContent = '使用默认图';

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
            . _t('填写完整图片 URL；留空时自动提取文章内第一张图片，都没有则显示默认占位图。')
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
