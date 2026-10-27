<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configPath = dirname(__DIR__) . '/config.inc.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Typecho 配置文件不存在，无法创建健身页面。\n");
    exit(1);
}

require_once $configPath;

$db = \Typecho\Db::get();
$existing = $db->fetchRow(
    $db->select('cid', 'type', 'status')->from('table.contents')
        ->where('slug = ?', 'fitness')->limit(1)
);

if ($existing) {
    if ($existing['type'] !== 'page') {
        fwrite(STDERR, "缩略名 fitness 已被其他内容占用，请先在后台处理。\n");
        exit(1);
    }
    echo '健身页面已存在（CID ' . (int) $existing['cid'] . '，状态 ' . $existing['status'] . "），未修改。\n";
    exit(0);
}

$author = $db->fetchRow(
    $db->select('uid')->from('table.users')->order('uid', \Typecho\Db::SORT_ASC)->limit(1)
);
if (!$author) {
    fwrite(STDERR, "未找到站点用户，无法创建健身页面。\n");
    exit(1);
}

$now = time();
$cid = $db->query($db->insert('table.contents')->rows([
    'title' => '健身',
    'slug' => 'fitness',
    'created' => $now - 1,
    'modified' => $now,
    'text' => '',
    'authorId' => (int) $author['uid'],
    'type' => 'page',
    'status' => 'publish',
    'allowComment' => 0,
    'allowPing' => 0,
    'allowFeed' => 0,
    'parent' => 0,
]));

echo '已创建空白健身页面（CID ' . (int) $cid . "）。\n";
