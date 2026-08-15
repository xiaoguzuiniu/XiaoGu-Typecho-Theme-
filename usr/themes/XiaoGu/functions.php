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
