<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 主题初始化：拦截前端 AJAX 请求（浏览量记录、点赞切换、动态评论）。
 *
 * @param \Widget\Archive $archive
 */
function themeInit($archive)
{
    $action = isset($_GET['xiaogu_action']) ? $_GET['xiaogu_action'] : '';
    $cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;

    if ($cid <= 0 || !in_array($action, ['view', 'like', 'moment_comment'], true)) {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
        $db = \Typecho\Db::get();

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

            $options = \Widget\Options::alloc();
            $security = \Widget\Security::alloc();
            $user = \Widget\User::alloc();
            if (!$post->allow('comment')) {
                throw new \Exception('此动态已关闭评论');
            }

            if (
                !$user->pass('editor', true)
                && (int) $post->authorId !== (int) $user->uid
                && $options->commentsPostIntervalEnable
            ) {
                $latestComment = $db->fetchRow(
                    $db->select('created')
                        ->from('table.comments')
                        ->where('cid = ? AND ip = ?', $cid, \Typecho\Request::getInstance()->getIp())
                        ->order('created', \Typecho\Db::SORT_DESC)
                        ->limit(1)
                );
                $elapsed = $latestComment ? (int) $options->time - (int) $latestComment['created'] : 0;
                if ($elapsed > 0 && $elapsed < (int) $options->commentsPostInterval) {
                    throw new \Exception('您的评论过于频繁，请稍后再试');
                }
            }

            $antiSpamEnabled = (bool) $options->commentsAntiSpam;
            if ($antiSpamEnabled) {
                $submittedToken = isset($input['_']) ? (string) $input['_'] : '';
                $expectedToken = $security->getToken(\Typecho\Request::getInstance()->getReferer());
                if ($submittedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
                    throw new \Exception('评论安全验证失败，请刷新页面后重试');
                }
                $options->commentsAntiSpam = false;
            }

            $httpRequest = \Typecho\Request::getInstance();
            $httpResponse = \Typecho\Response::getInstance();
            $httpRequest->beginSandbox(new \Typecho\Config($input));
            $httpResponse->beginSandbox();

            try {
                $feedback = new \Widget\Feedback(
                    new \Typecho\Widget\Request($httpRequest, new \Typecho\Config($input)),
                    new \Typecho\Widget\Response($httpRequest, $httpResponse),
                    ['checkReferer' => false]
                );
                $feedback->execute();
                (function (\Widget\Archive $content) {
                    $this->content = $content;
                    $this->comment();
                })->call($feedback, $post);
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
                throw new \Exception('评论验证失败，请刷新页面后重试');
            }

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
        _t('填写完整图片 URL；留空时显示主题默认的 X 头像。')
    );
    $form->addInput($profileAvatarUrl->addRule('url', _t('请填写正确的头像 URL 地址')));

    $heroImageUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'heroImageUrl',
        null,
        null,
        _t('首页头图地址'),
        _t('填写完整图片 URL；留空时使用主题自带的雪山图片。')
    );
    $form->addInput($heroImageUrl->addRule('url', _t('请填写正确的头图 URL 地址')));
}

function themeFields($layout)
{
    $displayMode = new \Typecho\Widget\Helper\Form\Element\Select(
        'displayMode',
        [
            'article' => _t('普通文章（点击进入详情）'),
            'moment' => _t('朋友圈动态（列表直接展示）'),
        ],
        'article',
        _t('内容展示类型'),
        _t('朋友圈动态会在文章列表中直接显示完整正文和图片，不提供详情页入口。')
    );

    $postCover = new \Typecho\Widget\Helper\Form\Element\Text(
        'postCover',
        null,
        null,
        _t('文章封面图'),
        _t('填写完整图片 URL；留空时自动提取文章内第一张图片，都没有则显示默认占位图。')
    );

    $layout->addItem($displayMode);
    $layout->addItem($postCover->addRule('url', _t('请填写正确的封面图 URL 地址')));
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
        $db->select('cid', 'title', 'created', 'modified')
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
        $title = $title !== '' ? $title : '未命名文章';

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
