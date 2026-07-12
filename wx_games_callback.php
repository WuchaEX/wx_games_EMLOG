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

    // ============================================================
    // 统一数据表 (所有游戏共用，带 game 列区分)
    // ============================================================

    // 1. 统一用户积分表
    $db->query(<<<SQL
CREATE TABLE IF NOT EXISTS `emlog_wx_games_scores` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game` varchar(20) NOT NULL DEFAULT '' COMMENT '游戏标识: ddz/mj/niuniu',
  `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `avatar` varchar(255) DEFAULT '' COMMENT '头像URL',
  `score` int(11) NOT NULL DEFAULT 0 COMMENT '当前积分',
  `total_games` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '总场次',
  `wins` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '胜场',
  `losses` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '负场',
  `draws` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '平局',
  `best_score` int(11) NOT NULL DEFAULT 0 COMMENT '历史最高分',
  `is_ai` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否AI玩家',
  `extra_data` text DEFAULT NULL COMMENT '游戏特有数据JSON',
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_uid_ai` (`game`, `uid`, `is_ai`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统一游戏用户积分表';
SQL
);

    // 2. 统一积分变动日志表
    $db->query(<<<SQL
CREATE TABLE IF NOT EXISTS `emlog_wx_games_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game` varchar(20) NOT NULL DEFAULT '' COMMENT '游戏标识',
  `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '目标用户ID',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化值',
  `score_before` int(11) NOT NULL DEFAULT 0 COMMENT '变动前积分',
  `score_after` int(11) NOT NULL DEFAULT 0 COMMENT '变动后积分',
  `reason` varchar(255) DEFAULT '' COMMENT '变动原因',
  `operator` varchar(100) DEFAULT '' COMMENT '操作者',
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `game_uid` (`game`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统一游戏积分变动日志';
SQL
);

    // 3. 统一商城商品表
    $db->query(<<<SQL
CREATE TABLE IF NOT EXISTS `emlog_wx_games_shop_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game` varchar(20) NOT NULL DEFAULT '' COMMENT '游戏标识',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '商品名称',
  `description` varchar(500) DEFAULT '' COMMENT '商品描述',
  `icon` varchar(255) DEFAULT '' COMMENT '图标URL',
  `item_type` varchar(50) NOT NULL DEFAULT '' COMMENT '道具类型标识',
  `effect_data` text COMMENT '效果参数JSON',
  `price_emlog` int(11) NOT NULL DEFAULT 0 COMMENT '站点积分价',
  `price_game` int(11) NOT NULL DEFAULT 0 COMMENT '游戏积分价',
  `is_limited` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否限量',
  `stock` int(11) NOT NULL DEFAULT -1 COMMENT '库存(-1=不限)',
  `max_per_user` int(11) NOT NULL DEFAULT 0 COMMENT '每人限购(0=不限)',
  `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=上架 0=下架',
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  `is_global` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=全局道具，购买后全游戏互通',
  PRIMARY KEY (`id`),
  KEY `game_status` (`game`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统一游戏商城商品表';
SQL
);

    // 4. 统一用户背包表
    $db->query(<<<SQL
CREATE TABLE IF NOT EXISTS `emlog_wx_games_user_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `game` varchar(20) NOT NULL DEFAULT '' COMMENT '游戏标识',
  `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID',
  `item_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商品ID',
  `quantity` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '数量',
  `used` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '已使用次数',
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT '当前生效(0=否, 1=是)',
  `charges` int(11) NOT NULL DEFAULT 0 COMMENT '剩余次数',
  `purchased_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '购买时间',
  `expires_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '过期时间(0=永不过期)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `game_uid_item` (`game`, `uid`, `item_id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统一游戏用户背包表';
SQL
);

    // ============================================================
    // 游戏特有数据表 (结构差异大，保持独立)
    // ============================================================

    // 斗地主游戏记录表
    $db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wx_ddz_games` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='斗地主游戏记录表';");

    // 麻将游戏记录表
    $db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wx_mojang_games` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL COMMENT '玩家UID',
        `winner` varchar(20) NOT NULL DEFAULT '' COMMENT '赢家',
        `win_type` varchar(20) NOT NULL DEFAULT '' COMMENT '赢牌方式',
        `fan_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '番数',
        `fan_type` varchar(255) NOT NULL DEFAULT '' COMMENT '番种列表',
        `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化',
        `result` varchar(10) NOT NULL DEFAULT 'pending' COMMENT '结果',
        `hand_tiles` text DEFAULT NULL COMMENT '起手牌JSON',
        `final_hand` text DEFAULT NULL COMMENT '胡牌手牌JSON',
        `win_tile` varchar(10) DEFAULT NULL COMMENT '胡的那张牌',
        `game_token` varchar(32) NOT NULL DEFAULT '' COMMENT '游戏token',
        `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '0=已完成,1=进行中',
        `finished_at` datetime DEFAULT NULL COMMENT '完成时间',
        `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `game_token` (`game_token`),
        KEY `status` (`status`),
        KEY `created` (`created`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='麻将游戏记录表';");

    // 斗牛游戏记录表
    $db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "wx_niuniu_games` (
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT 'emlog用户ID',
        `nickname` varchar(100) NOT NULL DEFAULT '',
        `score_change` int(11) NOT NULL DEFAULT 0 COMMENT '积分变化',
        `result` enum('win','lose','draw','pending') NOT NULL DEFAULT 'pending' COMMENT '结果',
        `niu_type` varchar(20) DEFAULT '' COMMENT '用户的牛型',
        `ai1_type` varchar(20) DEFAULT '' COMMENT 'AI1的牛型',
        `ai2_type` varchar(20) DEFAULT '' COMMENT 'AI2的牛型',
        `game_token` varchar(64) DEFAULT '' COMMENT '游戏唯一标识',
        `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=进行中, 0=已结束',
        `created_at` int(10) unsigned NOT NULL DEFAULT 0,
        `finished_at` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '结束时间',
        PRIMARY KEY (`id`),
        KEY `uid` (`uid`),
        KEY `status` (`status`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='斗牛游戏记录表';");

    // ============================================================
    // 初始化 Storage 配置
    // ============================================================
    $storage_ddz = Storage::getInstance('wx_ddz');
    $config_ddz = $storage_ddz->getValue('config');
    if (empty($config_ddz)) {
        $storage_ddz->setValue('config', [
            'title' => 'H5 斗地主', 'guest_play' => '1',
            'ai_names' => '全宝蓝,李居丽,朴素妍,咸恩静,朴孝敏,朴智妍',
            'max_entries' => 100, 'penalty_multiplier' => 1.0,
            'notice' => '欢迎来到H5斗地主！游戏过程中请遵守规则，公平竞技。',
            'recent_updates' => '', 'recharge_link' => '',
        ], 'array');
        $storage_ddz->setValue('ai_players', [
            0 => ['name' => '全宝蓝', 'avatar' => 'boram.jpg', 'quotes' => [
                'bomb' => ['看我炸翻他们！', '嘿嘿，炸的就是你！'],
                'rocket' => ['火箭起飞咯~', '王炸！我最大！'],
                'win' => ['大姐赢了！', '个子小赢牌可不小！'],
                'lose' => ['哼！下次赢回来！', '呜呜输了一丢丢…'],
            ]],
        ], 'array');
    }

    $storage_mj = Storage::getInstance('wx_mojang');
    $config_mj = $storage_mj->getValue('config');
    if (empty($config_mj)) {
        $storage_mj->setValue('config', [
            'title' => 'H5 国标麻将', 'guest_play' => '1',
            'base_score' => 100, 'min_fan_to_win' => 1,
            'max_entries' => 100, 'penalty_multiplier' => 1.0,
            'notice' => '欢迎来到H5国标麻将！',
            'recent_updates' => '', 'recharge_link' => '',
        ], 'array');
        $storage_mj->setValue('ai_players', [
            0 => ['name' => 'AI小美', 'avatar' => 'ai1.jpg', 'quotes' => ['win' => ['胡了！'], 'lose' => ['点炮了']]],
            1 => ['name' => 'AI小帅', 'avatar' => 'ai2.jpg', 'quotes' => ['win' => ['自摸！'], 'lose' => ['输了']]],
        ], 'array');
    }

    $storage_nn = Storage::getInstance('wx_niuniu');
    $config_nn = $storage_nn->getValue('config');
    if (empty($config_nn)) {
        $storage_nn->setValue('config', [
            'title' => 'H5 斗牛', 'guest_play' => '1',
            'base_bet' => 100, 'max_entries' => 50,
            'penalty_multiplier' => 1.0,
            'notice' => '欢迎来到H5斗牛！',
            'recent_updates' => '', 'recharge_link' => '',
        ], 'array');
        $storage_nn->setValue('ai_players', [
            0 => ['name' => 'AI小美', 'avatar' => 'ai1.jpg', 'quotes' => ['win' => ['赢了'], 'lose' => ['输了']]],
            1 => ['name' => 'AI小帅', 'avatar' => 'ai2.jpg', 'quotes' => ['win' => ['哈哈'], 'lose' => ['下次赢']]],
        ], 'array');
    }
}

/**
 * 插件更新回调
 */
function callback_up() {
    $db = Database::getInstance();
    // 检测并新增 is_global 字段 (全局道具)
    $shop_cols = $db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "wx_games_shop_items`");
    $has_global = false;
    while ($col = $db->fetch_array($shop_cols)) {
        if ($col['Field'] === 'is_global') { $has_global = true; break; }
    }
    if (!$has_global) {
        $db->query("ALTER TABLE `" . DB_PREFIX . "wx_games_shop_items` ADD COLUMN `is_global` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=全局道具，购买后全游戏互通' AFTER `created_at`");
    }
    callback_init();
}

/**
 * 插件删除回调 - 清理所有游戏数据
 */
function callback_rm() {
    $db = Database::getInstance();
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_games_scores`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_ddz_games`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_games_logs`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_games_shop_items`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_games_user_items`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_mojang_games`");
    $db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "wx_niuniu_games`");

    Storage::getInstance('wx_ddz')->deleteAllName('YES');
    Storage::getInstance('wx_mojang')->deleteAllName('YES');
    Storage::getInstance('wx_niuniu')->deleteAllName('YES');
}
