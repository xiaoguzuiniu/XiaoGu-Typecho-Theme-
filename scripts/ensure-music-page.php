<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = dirname(__DIR__) . '/config.inc.php';
if (!is_file($config)) { fwrite(STDERR, "Typecho 配置文件不存在。\n"); exit(1); }
require_once $config;
$db = \Typecho\Db::get();
$existing = $db->fetchRow($db->select('cid', 'type')->from('table.contents')->where('slug = ?', 'music')->limit(1));
if ($existing) {
    if ($existing['type'] !== 'page') { fwrite(STDERR, "music 缩略名已被占用。\n"); exit(1); }
    echo "音乐页面已存在。\n";
    exit;
}
$author = $db->fetchRow($db->select('uid')->from('table.users')->order('uid', \Typecho\Db::SORT_ASC)->limit(1));
if (!$author) { fwrite(STDERR, "未找到站点用户。\n"); exit(1); }
$now = time();
$db->query($db->insert('table.contents')->rows([
    'title' => '音乐', 'slug' => 'music', 'created' => $now, 'modified' => $now,
    'text' => '', 'authorId' => (int) $author['uid'], 'type' => 'page',
    'status' => 'publish', 'allowComment' => 0, 'allowPing' => 0,
    'allowFeed' => 0, 'parent' => 0,
]));
echo "已创建音乐页面。\n";
