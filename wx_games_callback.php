<?php
/**
 * wx_games 插件生命周期回调
 * 统一管理所有游戏的数据库表创建/更新/删除
 */
!defined('EMLOG_ROOT') && exit('access denied!');

/**
 * 插件激活回调 - 创建全部游戏数据表 + 初始化配置
 */
function callback_init() {
    $db = Database::getInstance();
    $charset = 'utf8mb4';

    // ============================================================
    // 1. 斗地主 (ddz) 数据表
    // ============================================================

    // 1-1. 用户积分表
    $table = DB_PREFIX . 'wx_ddz_scores';
    $db->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID，0表示AI或游客',
        `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '用户昵称',
        `avatar` varchar(255) DEFAULT '' COMMENT '头像URL',
        `score` int(11) NOT NULL DEFAULT 0 COMMENT '当前积分',
        `total_games` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '总场次',
        `wins` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '胜场',
        `losses` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '负场',
        `draws` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '平局',
        `best_score` int(11) NOT NULL DEFAULT 0 COMMENT '历史最高分',
        `is_ai` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否AI玩家',
        `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
        `created_at` int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uid_is_ai` (`uid`, `is_ai`),
        KEY `score` (`score`),
        KEY `best_score` (`best_score`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COMMENT='斗地主用户积分表';");

    // 1-2. 游戏记录表
    $table = DB_PREFIX . 'wx_ddz_games';
    $db->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID',
        `nickname` varchar(100) NOT NULL DEFAULT '',
        `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化',
        `result` enum('win','lose','draw','pending') NOT NULL DEFAULT 'pending' COMMENT '结果',
        `is_landlord` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否地主',
        `multiplier` int(11) unsigned NOT NULL DEFAULT 1 COMMENT '倍数',
        `bomb_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '炸弹数',
        `is_spring` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否春天',
        `game_token` varchar(64) DEFAULT '' COMMENT '游戏唯一标识',
        `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=进行中, 0=已结束',
        `created_at` int(10) unsigned NOT NULL DEFAULT 0,
        `finished_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '结束时间',
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `status` (`status`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COMMENT='斗地主游戏记录表';");

    // 1-3. 积分变动日志表
    $table = DB_PREFIX . 'wx_ddz_logs';
    $db->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '目标用户ID',
        `nickname` varchar(100) NOT NULL DEFAULT '',
        `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化值',
        `score_before` int(11) NOT NULL DEFAULT 0 COMMENT '变动前积分',
        `score_after` int(11) NOT NULL DEFAULT 0 COMMENT '变动后积分',
        `reason` varchar(255) DEFAULT '' COMMENT '变动原因',
        `operator` varchar(100) DEFAULT '' COMMENT '操作者',
        `created_at` int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COMMENT='斗地主积分变动日志';");

    // 1-4. 商城商品表
    $table = DB_PREFIX . 'wx_ddz_shop_items';
    $db->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称',
        `description` varchar(500) DEFAULT '' COMMENT '商品描述',
        `icon` varchar(255) DEFAULT '' COMMENT '图标URL',
        `item_type` varchar(50) NOT NULL DEFAULT '' COMMENT '道具类型标识',
        `effect_data` text COMMENT '效果参数JSON',
        `price_emlog` int(11) NOT NULL DEFAULT 0 COMMENT '站点积分价',
        `price_ddz` int(11) NOT NULL DEFAULT 0 COMMENT '斗地主积分价',
        `is_limited` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否限量',
        `stock` int(11) NOT NULL DEFAULT -1 COMMENT '库存(-1=不限)',
        `max_per_user` int(11) NOT NULL DEFAULT 0 COMMENT '每人限购(0=不限)',
        `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
        `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=上架 0=下架',
        `created_at` int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `status_sort` (`status`, `sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COMMENT='斗地主商城商品表';");

    // 1-5. 用户背包表
    $table = DB_PREFIX . 'wx_ddz_user_items';
    $db->query("CREATE TABLE IF NOT EXISTS `$table` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID',
        `item_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商品ID',
        `quantity` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '数量',
        `used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '已使用次数',
        `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '当前生效(0=否, 1=是)',
        `charges` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '总局数(积分加成卡专用，0=不限)',
        `purchased_at` int(10) unsigned NOT NULL DEFAULT 0,
        `expires_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '过期时间(0=永不过期)',
        PRIMARY KEY (`id`),
        UNIQUE KEY `uid_item` (`uid`, `item_id`),
        KEY `uid` (`uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COMMENT='斗地主用户背包表';");

    // ============================================================
    // 2. 麻将 (mj) 数据表
    // ============================================================

    // 2-1. 玩家积分表
    $table = DB_PREFIX . 'wx_mojang_scores';
    $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID，0表示游客',
        `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
        `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像URL',
        `score` int(11) NOT NULL DEFAULT 0 COMMENT '麻将积分',
        `total_games` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '总局数',
        `wins` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '赢局数',
        `losses` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '输局数',
        `draws` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '流局数',
        `self_draw_wins` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '自摸次数',
        `discard_wins` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '点炮胡次数',
        `big_fan_wins` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '大番型(≥6番)胡牌次数',
        `best_score` int(11) NOT NULL DEFAULT 0 COMMENT '最高单局得分',
        `max_fan` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '最高单局番数',
        `is_ai` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否为AI玩家',
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uid` (`uid`),
        KEY `score` (`score`),
        KEY `is_ai` (`is_ai`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=utf8mb4_unicode_ci COMMENT='麻将玩家积分表';");

    // 2-2. 游戏记录表
    $table = DB_PREFIX . 'wx_mojang_games';
    $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL COMMENT '玩家UID',
        `winner` varchar(20) NOT NULL DEFAULT '' COMMENT '赢家: player/ai1/ai2/ai3/draw(流局)',
        `win_type` varchar(20) NOT NULL DEFAULT '' COMMENT '赢牌方式: self_draw/discard/draw',
        `fan_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '番数',
        `fan_type` varchar(255) NOT NULL DEFAULT '' COMMENT '番种列表(逗号分隔)',
        `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化(正=赢,负=输)',
        `result` varchar(10) NOT NULL DEFAULT 'pending' COMMENT '结果: win/lose/draw/pending',
        `hand_tiles` text DEFAULT NULL COMMENT '玩家起手牌(JSON)',
        `final_hand` text DEFAULT NULL COMMENT '胡牌时手牌(JSON)',
        `win_tile` varchar(10) DEFAULT NULL COMMENT '胡的那张牌',
        `game_token` varchar(32) NOT NULL DEFAULT '' COMMENT '游戏token(防重入)',
        `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '0=已完成,1=进行中',
        `finished_at` datetime DEFAULT NULL COMMENT '完成时间',
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `game_token` (`game_token`),
        KEY `status` (`status`),
        KEY `created` (`created`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=utf8mb4_unicode_ci COMMENT='麻将游戏记录表';");

    // 2-3. 积分变动日志表
    $table = DB_PREFIX . 'wx_mojang_logs';
    $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL COMMENT '玩家UID',
        `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化',
        `score_before` int(11) NOT NULL DEFAULT 0 COMMENT '变动前积分',
        `score_after` int(11) NOT NULL DEFAULT 0 COMMENT '变动后积分',
        `reason` varchar(100) NOT NULL DEFAULT '' COMMENT '变动原因(游戏/签到/管理/购买/惩罚)',
        `operator` varchar(50) NOT NULL DEFAULT '' COMMENT '操作者',
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `created` (`created`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=utf8mb4_unicode_ci COMMENT='麻将积分变动日志';");

    // 2-4. 商城商品表
    $table = DB_PREFIX . 'wx_mojang_shop_items';
    $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称',
        `description` text DEFAULT NULL COMMENT '商品描述',
        `icon` varchar(100) NOT NULL DEFAULT '' COMMENT '图标类名或URL',
        `item_type` varchar(30) NOT NULL DEFAULT '' COMMENT '道具类型',
        `effect_data` text DEFAULT NULL COMMENT '效果数据(JSON)',
        `price_emlog` int(11) NOT NULL DEFAULT 0 COMMENT 'Emlog站点积分价格',
        `price_majiang` int(11) NOT NULL DEFAULT 0 COMMENT '麻将积分价格',
        `stock` int(11) NOT NULL DEFAULT -1 COMMENT '库存(-1不限)',
        `max_per_user` int(11) NOT NULL DEFAULT -1 COMMENT '每人限购(-1不限)',
        `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
        `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否上架',
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `item_type` (`item_type`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=utf8mb4_unicode_ci COMMENT='麻将商城商品表';");

    // 2-5. 用户背包表
    $table = DB_PREFIX . 'wx_mojang_user_items';
    $db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL COMMENT '玩家UID',
        `item_id` int(11) unsigned NOT NULL COMMENT '商品ID',
        `quantity` int(11) NOT NULL DEFAULT 1 COMMENT '数量',
        `used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已使用(一次性道具)',
        `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否激活中',
        `charges` int(11) NOT NULL DEFAULT -1 COMMENT '剩余次数(-1不限)',
        `expires_at` datetime DEFAULT NULL COMMENT '过期时间',
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `item_id` (`item_id`),
        KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=utf8mb4_unicode_ci COMMENT='麻将用户背包表';");

    // ============================================================
    // 3. 兼容旧表字段检查（使用 SHOW COLUMNS LIKE）
    // ============================================================
    $table = DB_PREFIX . 'wx_ddz_games';
    $check = $db->once_fetch_array("SHOW COLUMNS FROM `$table` LIKE 'game_token'");
    if (empty($check)) {
        $db->query("ALTER TABLE `$table` ADD `game_token` varchar(64) DEFAULT '' COMMENT '游戏唯一标识'");
    }
    $check = $db->once_fetch_array("SHOW COLUMNS FROM `$table` LIKE 'status'");
    if (empty($check)) {
        $db->query("ALTER TABLE `$table` ADD `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=进行中, 0=已结束'");
        $db->query("ALTER TABLE `$table` ADD `finished_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '结束时间'");
        $db->query("UPDATE `$table` SET `status` = 0, `result` = 'draw' WHERE `result` = 'draw' AND `status` = 1");
    }

    $table = DB_PREFIX . 'wx_ddz_user_items';
    $check = $db->once_fetch_array("SHOW COLUMNS FROM `$table` LIKE 'is_active'");
    if (empty($check)) {
        $db->query("ALTER TABLE `$table` ADD `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '当前生效(0=否, 1=是)'");
    }
    $check = $db->once_fetch_array("SHOW COLUMNS FROM `$table` LIKE 'expires_at'");
    if (empty($check)) {
        $db->query("ALTER TABLE `$table` ADD `expires_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '过期时间(0=永不过期)'");
    }
    $check = $db->once_fetch_array("SHOW COLUMNS FROM `$table` LIKE 'charges'");
    if (empty($check)) {
        $db->query("ALTER TABLE `$table` ADD `charges` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '总局数(积分加成卡专用)'");
    }

    // ============================================================
    // 4. 初始化 Storage 配置
    // ============================================================
    // 游戏开关状态
    $storage_games = Storage::getInstance('wx_games');
    $game_status = $storage_games->getValue('game_status');
    if (empty($game_status)) {
        $storage_games->setValue('game_status', ['ddz' => '1', 'mj' => '1'], 'array');
    }

    // 斗地主配置
    $storage_ddz = Storage::getInstance('wx_ddz');
    $config_ddz = $storage_ddz->getValue('config');
    if (empty($config_ddz)) {
        $storage_ddz->setValue('config', [
            'title'              => 'H5 斗地主',
            'guest_play'         => '1',
            'ai_names'           => 'AI玩家1,AI玩家2',
            'max_entries'        => 100,
            'penalty_multiplier' => 1.0,
            'notice'             => '欢迎来到H5斗地主！游戏过程中请遵守规则，公平竞技。',
            'recent_updates'     => '',
            'recharge_link'      => '',
        ], 'array');
    }

    // 麻将配置
    $storage_mj = Storage::getInstance('wx_mojang');
    $config_mj = $storage_mj->getValue('config');
    if (empty($config_mj)) {
        $storage_mj->setValue('config', [
            'title'              => 'H5 国标麻将',
            'guest_play'         => '1',
            'max_entries'        => 50,
            'penalty_multiplier' => 2,
            'base_score'         => 100,
            'min_fan_to_win'     => 8,
            'notice'             => '欢迎来到国标麻将！8番起胡，祝您旗开得胜！',
            'recent_updates'     => '',
        ], 'array');
        $storage_mj->setValue('ai_players', [
            'player1' => ['name' => '麻将高手', 'avatar' => '', 'quotes' => ['good' => ['这牌不错', '可以打'], 'bad' => ['手气不好', '等等看'], 'win' => ['胡了！', '承让承让'], 'lose' => ['运气好而已', '下一把']]],
            'player2' => ['name' => '牌场老手', 'avatar' => '', 'quotes' => ['good' => ['有戏', '这局稳了'], 'bad' => ['牌不太顺', '再等等'], 'win' => ['自摸！', '不好意思'], 'lose' => ['你厉害', '佩服']]],
            'player3' => ['name' => '新手小白', 'avatar' => '', 'quotes' => ['good' => ['运气不错', '试试看'], 'bad' => ['看不懂了', '好难啊'], 'win' => ['哇赢了！', '运气好'], 'lose' => ['再来一局', '我不服']]],
            'player4' => ['name' => '冷面雀神', 'avatar' => '', 'quotes' => ['good' => ['还行', '稳一手'], 'bad' => ['麻烦', '啧'], 'win' => ['嗯', '运气'], 'lose' => ['可以', '你赢了']]],
            'player5' => ['name' => '微笑天使', 'avatar' => '', 'quotes' => ['good' => ['开心~', '加油加油'], 'bad' => ['呜呜', '好难呀'], 'win' => ['耶！', '我胡啦！'], 'lose' => ['好厉害呀', '下次一定！']]],
            'player6' => ['name' => '战术大师', 'avatar' => '', 'quotes' => ['good' => ['按计划进行', '预料之中'], 'bad' => ['需要调整', '再观察'], 'win' => ['完美', '意料之中'], 'lose' => ['计算失误', '下次注意']]],
        ], 'array');
    }
}

/**
 * 插件更新回调
 */
function callback_up() {
    callback_init();
}

/**
 * 插件删除回调 - 清理所有游戏数据
 */
function callback_rm() {
    $db = Database::getInstance();

    // 斗地主表
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_ddz_scores`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_ddz_games`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_ddz_logs`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_ddz_shop_items`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_ddz_user_items`");

    // 麻将表
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_mojang_scores`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_mojang_games`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_mojang_logs`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_mojang_shop_items`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_mojang_user_items`");

    // 清理 Storage 配置
    $storage_ddz = Storage::getInstance('wx_ddz');
    $storage_ddz->deleteAllName('YES');

    $storage_mj = Storage::getInstance('wx_mojang');
    $storage_mj->deleteAllName('YES');
}
