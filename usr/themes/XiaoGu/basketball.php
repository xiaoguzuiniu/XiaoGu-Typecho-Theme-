<?php

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Create and seed the team catalogue used by the visual schedule editor.
 */
function xiaoguBasketballEnsureTeams(\Typecho\Db $db): void
{
    static $ready = false;
    if ($ready) return;

    $prefix = $db->getPrefix();
    if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
        throw new \RuntimeException('Invalid database prefix');
    }

    $table = '`' . $prefix . 'basketball_teams`';
    $db->query("CREATE TABLE IF NOT EXISTS {$table} (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `league` VARCHAR(12) NOT NULL,
        `team_key` VARCHAR(32) NOT NULL,
        `name` VARCHAR(64) NOT NULL,
        `logo_url` VARCHAR(500) NOT NULL,
        `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_league_team` (`league`, `team_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", \Typecho\Db::WRITE, '');

    $nba = [
        ['ATL', '老鹰', 'atl'], ['BOS', '凯尔特人', 'bos'],
        ['BKN', '篮网', 'bkn'], ['CHA', '黄蜂', 'cha'],
        ['CHI', '公牛', 'chi'], ['CLE', '骑士', 'cle'],
        ['DAL', '独行侠', 'dal'], ['DEN', '掘金', 'den'],
        ['DET', '活塞', 'det'], ['GSW', '勇士', 'gs'],
        ['HOU', '火箭', 'hou'], ['IND', '步行者', 'ind'],
        ['LAC', '快船', 'lac'], ['LAL', '湖人', 'lal'],
        ['MEM', '灰熊', 'mem'], ['MIA', '热火', 'mia'],
        ['MIL', '雄鹿', 'mil'], ['MIN', '森林狼', 'min'],
        ['NOP', '鹈鹕', 'no'], ['NYK', '尼克斯', 'ny'],
        ['OKC', '雷霆', 'okc'], ['ORL', '魔术', 'orl'],
        ['PHI', '76人', 'phi'], ['PHX', '太阳', 'phx'],
        ['POR', '开拓者', 'por'], ['SAC', '国王', 'sac'],
        ['SAS', '马刺', 'sa'], ['TOR', '猛龙', 'tor'],
        ['UTA', '爵士', 'utah'], ['WAS', '奇才', 'wsh'],
    ];
    $cba = [
        ['BEIKONG', '北控', 29136], ['BEIJING', '北京', 29115],
        ['FUJIAN', '福建', 29134], ['GUANGDONG', '广东', 29124],
        ['JILIN', '吉林', 29137], ['LIAONING', '辽宁', 29129],
        ['TONGXI', '同曦', 29133], ['QINGDAO', '青岛', 29135],
        ['SHANDONG', '山东', 29130], ['SHANXI', '山西', 29132],
        ['SHANGHAI', '上海', 29125], ['SHENZHEN', '深圳', 29131],
        ['GUANGZHOU', '广州', 29139], ['SICHUAN', '四川', 29127],
        ['JIANGSU', '江苏', 29118], ['TIANJIN', '天津', 29138],
        ['XINJIANG', '新疆', 29117], ['ZHEJIANG', '浙江', 29140],
        ['GUANGSHA', '广厦', 29128], ['NINGBO', '宁波', 100074683],
    ];

    $adapter = $db->getAdapter();
    $rows = [];
    foreach ($nba as $index => $team) {
        $rows[] = ['NBA', $team[0], $team[1],
            'https://a.espncdn.com/i/teamlogos/nba/500/' . $team[2] . '.png', $index + 1];
    }
    foreach ($cba as $index => $team) {
        $rows[] = ['CBA', $team[0], $team[1],
            'https://img.gulook.site/xiaogu/team-logos/cba/' . $team[2] . '.png', $index + 1];
    }

    foreach ($rows as $row) {
        $values = array_map([$adapter, 'quoteValue'], array_slice($row, 0, 4));
        $sortOrder = (int) $row[4];
        $db->query("INSERT INTO {$table} (`league`, `team_key`, `name`, `logo_url`, `sort_order`)
            VALUES ({$values[0]}, {$values[1]}, {$values[2]}, {$values[3]}, {$sortOrder})
            ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `logo_url` = VALUES(`logo_url`),
                `sort_order` = VALUES(`sort_order`)", \Typecho\Db::WRITE, '');
    }

    $ready = true;
}

function xiaoguBasketballTeams(\Typecho\Db $db): array
{
    xiaoguBasketballEnsureTeams($db);
    return $db->fetchAll(
        $db->select('id', 'league', 'team_key', 'name', 'logo_url')
            ->from('table.basketball_teams')
            ->order('league', \Typecho\Db::SORT_DESC)
            ->order('sort_order', \Typecho\Db::SORT_ASC)
    );
}
