<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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
