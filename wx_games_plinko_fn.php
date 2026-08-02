<?php
/**
 * wx_games Plinko 弹珠台函数模块
 * AJAX 路由、存档读写、商城购买、外观背包
 */
!defined('EMLOG_ROOT') && exit('access denied!');

// Plinko 常量
define('PLINKO_EXCHANGE_RATE', 1); // 1站点积分 = 1 plinko币

// ========== 配置读取（从 emlog_storage，与 ddz/mj/niuniu 一致） ==========
function wx_plinko_get_config() {
    static $config = null;
    if ($config === null) {
        $defaults = [
            'title'          => 'H5弹珠台',
            'init_balance'   => 200,
            'notice'         => '欢迎来到H5弹珠台！选择风险等级，投球赢取奖励！',
            'recent_updates' => "v1.0.0 - H5弹珠台正式上线\nv1.0.0 - 真实物理引擎\nv1.0.0 - 余额同步、多球连发、深色主题",
            'recharge_link'  => '',
        ];
        try {
            $storage = Storage::getInstance('wx_plinko');
            $saved = $storage->getValue('config');
            if (is_array($saved)) {
                $config = array_merge($defaults, $saved);
            } else {
                $config = $defaults;
            }
        } catch (Throwable $e) {
            $config = $defaults;
        }
    }
    return $config;
}

// ========== 账户读写（自有表 wx_plinko_accounts，因 score 需 DECIMAL 而非 INT） ==========
function wx_plinko_get_account($uid) {
    $db = Database::getInstance();
    return $db->once_fetch_array("SELECT * FROM `" . DB_PREFIX . "wx_plinko_accounts` WHERE `uid` = " . intval($uid) . " LIMIT 1");
}

function wx_plinko_save_account($uid, $data) {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_plinko_accounts';
    $ts = isset($data['updated_at']) ? intval($data['updated_at']) : time();

    // 只 UPDATE 实际传入的字段
    $sets = ['`updated_at` = ' . $ts];
    if (isset($data['balance']))      $sets[] = '`balance` = ' . floatval($data['balance']);
    if (isset($data['total_bet']))    $sets[] = '`total_bet` = ' . floatval($data['total_bet']);
    if (isset($data['total_payout'])) $sets[] = '`total_payout` = ' . floatval($data['total_payout']);
    if (isset($data['play_count']))   $sets[] = '`play_count` = ' . intval($data['play_count']);
    if (isset($data['member_exp']))   $sets[] = '`member_exp` = ' . intval($data['member_exp']);
    if (isset($data['members']))      $sets[] = "`members` = '" . addslashes(json_encode($data['members'], JSON_UNESCAPED_UNICODE)) . "'";

    // INSERT 值 — 必须算好，否则 PHP 未定义变量导致 VALUES() 空缺
    $cfg = wx_plinko_get_config();
    $initBalance = isset($cfg['init_balance']) ? intval($cfg['init_balance']) : 200;
    $balance    = isset($data['balance'])      ? floatval($data['balance'])      : $initBalance;
    $total_bet  = isset($data['total_bet'])    ? floatval($data['total_bet'])    : 0;
    $total_payout = isset($data['total_payout']) ? floatval($data['total_payout']) : 0;
    $play_count = isset($data['play_count'])   ? intval($data['play_count'])   : 0;
    $member_exp = isset($data['member_exp']) ? intval($data['member_exp']) : 0;
    $members    = isset($data['members']) ? "'" . addslashes(json_encode($data['members'], JSON_UNESCAPED_UNICODE)) . "'" : "NULL";

    $db->query("INSERT INTO `$table` (`uid`,`balance`,`total_bet`,`total_payout`,`play_count`,`member_exp`,`members`,`updated_at`)
        VALUES (" . intval($uid) . ",$balance,$total_bet,$total_payout,$play_count,$member_exp,$members,$ts)
        ON DUPLICATE KEY UPDATE " . implode(', ', $sets));
}

// ============================================================
// ========== 成员系统辅助函数 ==========
define('PLINKO_MEMBER_KEYS', ['boram', 'qri', 'soyeon', 'eunjung', 'hyomin', 'jiyeon']);

function wx_plinko_get_default_members() {
    $m = [];
    foreach (PLINKO_MEMBER_KEYS as $k) {
        $m[$k] = ['unlocked' => false, 'level' => 1]; // 默认 Lv1（匹配 config.levels[0]）
    }
    return $m;
}

function wx_plinko_get_members($uid) {
    $row = wx_plinko_get_account($uid);
    if (!$row || empty($row['members'])) return wx_plinko_get_default_members();
    $data = json_decode($row['members'], true);
    if (!is_array($data)) return wx_plinko_get_default_members();
    // 修复旧用户：level=0 统一升为 level=1
    foreach (PLINKO_MEMBER_KEYS as $k) {
        if (!isset($data[$k])) $data[$k] = ['unlocked' => false, 'level' => 1];
        if ($data[$k]['level'] <= 0) $data[$k]['level'] = 1;
    }
    return $data;
}

function wx_plinko_get_member_exp($uid) {
    $row = wx_plinko_get_account($uid);
    return $row ? intval($row['member_exp']) : 0;
}

function wx_plinko_add_exp($uid, $amount = 1) {
    $row = wx_plinko_get_account($uid);
    $cur = $row ? intval($row['member_exp']) : 0;
    $add = max(1, (int)round($amount));
    wx_plinko_save_account($uid, ['member_exp' => $cur + $add]);
    return $cur + $add;
}

function wx_plinko_member_level_up($uid, $memberKey, $expCost, $newLevel) {
    if (!in_array($memberKey, PLINKO_MEMBER_KEYS)) return false;
    $row = wx_plinko_get_account($uid);
    if (!$row) return false;
    $curExp = intval($row['member_exp']);
    if ($curExp < $expCost) return false;
    $members = wx_plinko_get_members($uid);
    $members[$memberKey]['level'] = $newLevel;
    wx_plinko_save_account($uid, ['member_exp' => $curExp - $expCost, 'members' => $members]);
    return true;
}

function wx_plinko_member_unlock($uid, $memberKey) {
    if (!in_array($memberKey, PLINKO_MEMBER_KEYS)) return false;
    $members = wx_plinko_get_members($uid);
    $members[$memberKey]['unlocked'] = true;
    $members[$memberKey]['level'] = 1; // 解锁后默认 Lv1（匹配 config.levels[0].level=1）
    wx_plinko_save_account($uid, ['members' => $members]);
    return true;
}

// ========== 成员配置（emlog_storage.wx_plinko.member_config） ==========
function wx_plinko_get_member_config() {
    $defaults = [
        'boram' => [
            'name' => '全宝蓝', 'avatar' => 'boram.jpg', 'skill_desc' => '低倍槽退还下注额',
                        'levels' => [
                ['level' => 1, 'exp_cost' => 0,   'params' => ['spacing' => 0]],
                ['level' => 2, 'exp_cost' => 100, 'params' => ['spacing' => 0.05]],
                ['level' => 3, 'exp_cost' => 300, 'params' => ['spacing' => 0.10]],
                ['level' => 4, 'exp_cost' => 600, 'params' => ['spacing' => 0.15]],
                ['level' => 5, 'exp_cost' => 1000,'params' => ['spacing' => 0.20]],
            ]
        ],
        'qri' => [
            'name' => '李居丽', 'avatar' => 'qri.jpg', 'skill_desc' => '钉反弹系数增加',
                        'levels' => [
                ['level' => 1, 'exp_cost' => 0,   'params' => ['restitution' => 0]],
                ['level' => 2, 'exp_cost' => 100, 'params' => ['restitution' => 0.02]],
                ['level' => 3, 'exp_cost' => 300, 'params' => ['restitution' => 0.04]],
                ['level' => 4, 'exp_cost' => 600, 'params' => ['restitution' => 0.06]],
                ['level' => 5, 'exp_cost' => 1000,'params' => ['restitution' => 0.08]],
            ]
        ],
        'soyeon' => [
            'name' => '朴素妍', 'avatar' => 'soyeon.jpg', 'skill_desc' => '落槽后随机倍率加成',
                        'levels' => [
                ['level' => 1, 'exp_cost' => 0,   'params' => ['prob' => 0, 'min' => 0.8, 'max' => 1.5]],
                ['level' => 2, 'exp_cost' => 100, 'params' => ['prob' => 0.05,'min' => 0.8, 'max' => 1.5]],
                ['level' => 3, 'exp_cost' => 300, 'params' => ['prob' => 0.10,'min' => 0.8, 'max' => 1.6]],
                ['level' => 4, 'exp_cost' => 600, 'params' => ['prob' => 0.15,'min' => 0.8, 'max' => 1.7]],
                ['level' => 5, 'exp_cost' => 1000,'params' => ['prob' => 0.20,'min' => 0.8, 'max' => 1.8]],
            ]
        ],
        'eunjung' => [
            'name' => '咸恩静', 'avatar' => 'eunjung.jpg', 'skill_desc' => '概率额外弹珠',
                        'levels' => [
                ['level' => 1, 'exp_cost' => 0,   'params' => ['prob' => 0]],
                ['level' => 2, 'exp_cost' => 100, 'params' => ['prob' => 0.04]],
                ['level' => 3, 'exp_cost' => 300, 'params' => ['prob' => 0.08]],
                ['level' => 4, 'exp_cost' => 600, 'params' => ['prob' => 0.12]],
                ['level' => 5, 'exp_cost' => 1000,'params' => ['prob' => 0.16]],
            ]
        ],
        'hyomin' => [
            'name' => '朴孝敏', 'avatar' => 'hyomin.jpg', 'skill_desc' => '初速度↓+投球间隔↓',
                        'levels' => [
                ['level' => 1, 'exp_cost' => 0,   'params' => ['speed' => 0, 'interval' => 0]],
                ['level' => 2, 'exp_cost' => 100, 'params' => ['speed' => 0.03,'interval' => 50]],
                ['level' => 3, 'exp_cost' => 300, 'params' => ['speed' => 0.06,'interval' => 100]],
                ['level' => 4, 'exp_cost' => 600, 'params' => ['speed' => 0.09,'interval' => 150]],
                ['level' => 5, 'exp_cost' => 1000,'params' => ['speed' => 0.12,'interval' => 200]],
            ]
        ],
        'jiyeon' => [
            'name' => '朴智妍', 'avatar' => 'jiyeon.jpg', 'skill_desc' => '起始位置偏移调整',
                        'levels' => [
                ['level' => 1, 'exp_cost' => 0,   'params' => ['offset' => 0]],
                ['level' => 2, 'exp_cost' => 100, 'params' => ['offset' => 3]],
                ['level' => 3, 'exp_cost' => 300, 'params' => ['offset' => 6]],
                ['level' => 4, 'exp_cost' => 600, 'params' => ['offset' => 9]],
                ['level' => 5, 'exp_cost' => 1000,'params' => ['offset' => 12]],
            ]
        ],
        // EXP 获取设置（全局，非单个成员）
        'exp_mode' => 'ball',    // 'ball' = 每球1EXP, 'payout' = 落槽得分=EXP
        'exp_multiplier' => 1.0, // 全局经验倍率
    ];
    try {
        $storage = Storage::getInstance('wx_plinko');
        $saved = $storage->getValue('member_config');
        if (is_array($saved)) {
            foreach (PLINKO_MEMBER_KEYS as $k) {
                if (isset($saved[$k]) && is_array($saved[$k])) {
                    // 保留原始 defaults 的 levels（merge 前的兜底）
                    $defaultLevels = isset($defaults[$k]['levels']) ? $defaults[$k]['levels'] : [];
                    $defaults[$k] = array_merge($defaults[$k], $saved[$k]);
                    // 兜底：每个 level 的 params 不能为空（用原始 defaults，不是已 merge 的）
                    if (isset($saved[$k]['levels']) && is_array($saved[$k]['levels'])) {
                        $mergedLevels = [];
                        foreach ($saved[$k]['levels'] as $li => $lv) {
                            $defaultParams = isset($defaultLevels[$li]['params']) ? $defaultLevels[$li]['params'] : [];
                            $lvParams = isset($lv['params']) && is_array($lv['params']) && !empty($lv['params']) ? $lv['params'] : $defaultParams;
                            $mergedLevels[] = [
                                'level' => (int)$lv['level'],
                                'exp_cost' => (int)$lv['exp_cost'],
                                'params' => $lvParams,
                            ];
                        }
                        $defaults[$k]['levels'] = $mergedLevels;
                    }
                }
            }
            // 合并 EXP 全局设置（非成员字段）
            if (isset($saved['exp_mode'])) $defaults['exp_mode'] = $saved['exp_mode'];
            if (isset($saved['exp_multiplier'])) $defaults['exp_multiplier'] = floatval($saved['exp_multiplier']);
        }
    } catch (Throwable $e) {}
    return $defaults;
}

// 获取指定成员指定等级的效果 params
function wx_plinko_get_member_level_params($memberKey, $level) {
    if ($level <= 1) return [];
    $cfg = wx_plinko_get_member_config();
    if (!isset($cfg[$memberKey]['levels'])) return [];
    foreach ($cfg[$memberKey]['levels'] as $lv) {
        if ((int)$lv['level'] === (int)$level) {
            return isset($lv['params']) ? $lv['params'] : [];
        }
    }
    return [];
}

// AJAX 路由分发（由 wx_games.php 调用）
// ============================================================
function wx_plinko_route_ajax($action) {
    header('Content-Type: application/json; charset=utf-8');

    // Referer 校验
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (!empty($ref)) {
        $ref_host = parse_url($ref, PHP_URL_HOST);
        $srv_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($ref_host && $srv_host && $ref_host !== $srv_host) {
            wx_games_error('非法请求');
            return;
        }
    }

    switch ($action) {
        case 'load':           wx_plinko_api_load();            break;
        case 'get_inventory':  wx_plinko_api_get_inventory();  break;
        case 'get_shop':       wx_plinko_api_get_shop_items();  break;
        case 'get_shop_items': wx_plinko_api_get_shop_items();  break;
        case 'purchase_item':  wx_plinko_api_purchase_item();  break;
        case 'use_item':       wx_plinko_api_use_item();       break;
        case 'get_my_rank':    wx_plinko_api_get_my_rank();    break;
        case 'get_my_emlog_credits': wx_plinko_api_get_my_emlog_credits(); break;
        case 'get_active_effects': wx_plinko_api_get_active_effects(); break;
        case 'get_ranking':     wx_plinko_api_get_ranking();     break;
        case 'get_score_log':   wx_plinko_api_get_score_log();   break;
        case 'log_ball':       wx_plinko_api_log_ball();       break;
        case 'get_members':    wx_plinko_api_get_members();    break;
        case 'level_up':       wx_plinko_api_level_up();       break;
        case 'get_analysis': wx_plinko_api_get_analysis(); break;
        default:
            wx_games_error('未知操作');
            return;
    }
}

// ============================================================
// 信号处理（由 wx_games.php 调用）
// ============================================================
function wx_plinko_handle_signal($signal) {
    // Plinko 暂无信号处理逻辑，预留
}

// ============================================================
// 用户检查
// ============================================================
function wx_plinko_check_user() {
    return wx_games_check_user();
}

// ============================================================
// ============================================================
function wx_plinko_api_load() {
    $user = wx_plinko_check_user();
    if (!$user) {
        wx_games_error('未登录');
        return;
    }
    $uid = intval($user['uid']);

    $row = wx_plinko_get_account($uid);

    if (!$row) {
        wx_games_ok(['found' => false, 'data' => null, 'saved_at' => 0]);
        return;
    }

    wx_games_ok([
        'found'    => true,
        'data'     => [
            'balance'      => floatval($row['balance']),
            'total_bet'    => floatval($row['total_bet']),
            'total_payout' => floatval($row['total_payout']),
            'play_count'   => intval($row['play_count']),
            'updated_at'   => intval($row['updated_at']),
        ],
        'saved_at' => intval($row['updated_at']),
    ]);
    return;
}

// API: get_inventory - 获取外观道具背包
// ============================================================
function wx_plinko_api_get_inventory() {
    $user = wx_plinko_check_user();
    if (!$user) {
        wx_games_error('未登录');
        return;
    }
    $uid = intval($user['uid']);

    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';

    $result = $db->query("
        SELECT MIN(i.`id`) AS inv_id, i.`item_id`, SUM(CAST(i.`quantity` AS SIGNED) - CAST(i.`used` AS SIGNED)) AS qty,
               MAX(i.`is_active`) AS is_active, MAX(i.`game`) AS from_game,
               MAX(s.`name`) AS name, MAX(s.`icon`) AS icon,
               MAX(s.`item_type`) AS item_type, MAX(s.`effect_data`) AS effect_data,
               MAX(s.`is_global`) AS is_global
        FROM `" . $table_inv . "` i
        JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`quantity` > i.`used`
          AND (i.`game` = 'plinko' OR s.`is_global` = 1)
        GROUP BY i.`item_id`
        ORDER BY MAX(i.`is_active`) DESC, MAX(i.`purchased_at`) DESC"
    );

    $items = [];
    while ($row = $db->fetch_array($result)) {
        $items[] = [
            'inv_id'      => (int)$row['inv_id'],
            'item_id'     => (int)$row['item_id'],
            'quantity'    => (int)$row['qty'],
            'name'        => $row['name'],
            'icon'        => $row['icon'],
            'item_type'   => $row['item_type'],
            'from_game'   => $row['from_game'],
            'is_global'   => (int)$row['is_global'],
            'effect_data' => stripslashes($row['effect_data']),
            'is_active'   => (int)$row['is_active'],
        ];
    }

    wx_games_ok(['items' => $items]);
    return;
}

// ============================================================
// API: get_shop - 获取 plinko 可购买商品列表
// ============================================================
function wx_plinko_api_get_shop_items() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);

    $db = Database::getInstance();
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $result = $db->query("SELECT `id`, `name`, `icon`, `description`, `price_emlog`, `price_game`, `effect_data`, `item_type`, `is_global`
        FROM `$table_items`
        WHERE (`game` = 'plinko' OR `is_global` = 1) AND `status` = 1
        ORDER BY `sort_order` ASC, `id` ASC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $item_id = (int)$row['id'];
        // 检查是否已拥有（仅对皮肤/主题/外观类商品判重）
        $owned = false;
        if (in_array($row['item_type'], ['plinko_skin', 'plinko_theme', 'title_colored', 'title_effect', 'title_badge', 'card_back', 'emoticon'])) {
            $own = $db->once_fetch_array("SELECT SUM(`quantity` - `used`) AS cnt FROM `$table_inv` WHERE `uid` = $uid AND `item_id` = $item_id LIMIT 1");
            $owned = ($own && (int)$own['cnt'] > 0);
        }
        $eff = stripslashes($row['effect_data']);
        $items[] = [
            'id'          => $item_id,
            'name'        => $row['name'],
            'icon'        => $row['icon'],
            'item_type'   => $row['item_type'],
            'description' => $row['description'] ?: '',
            'price_emlog' => (int)$row['price_emlog'],
            'price_ddz'   => (int)$row['price_game'],  // ddz 前端用 price_ddz 作为游戏币key
            'effect_desc' => plinko_format_effect_desc($row['item_type'], json_decode($eff, true)),
            'is_global'   => !empty($row['is_global']),
            'owned'       => $owned,
        ];
    }
    // member_unlock 价格完全由 shop_items 表的 price_emlog/price_game 控制（后台商城管理设置）
    wx_games_ok(['items' => $items]);
    return;
}

function plinko_format_effect_desc($type, $eff) {
    if (!is_array($eff)) return '';
    if ($type === 'plinko_coin_pack') return '👑 +' . (isset($eff['coins']) ? intval($eff['coins']) : 0) . ' H5弹珠';
    if ($type === 'plinko_skin')    return '🎨 ' . (isset($eff['skin_name']) ? $eff['skin_name'] : '自定义弹珠皮肤');
    if ($type === 'plinko_theme')   return '🌈 ' . (isset($eff['theme_name']) ? $eff['theme_name'] : '自定义钉阵主题');
    if ($type === 'title_colored')  return '🎖️ 昵称变色（永久）';
    if ($type === 'title_effect')   return '✨ 昵称特效（永久）';
    if ($type === 'title_badge')   return '🏅 称号勋章（永久）';
    if ($type === 'card_back')     return '🃏 牌背皮肤';
    if ($type === 'emoticon')      return '😀 专属表情';
    if ($type === 'score_buff')    return '🔥 积分加成' . (isset($eff['multiplier']) ? ' ×' . $eff['multiplier'] : '');
    return '🎁 ' . $type;
}

// ============================================================
// API: use_item - 使用背包中的 plinko 道具（与 ddz/mj/niuniu 对齐）
// ============================================================
function wx_plinko_api_use_item() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);

    $inv_id = isset($_POST['inv_id']) ? intval($_POST['inv_id']) : 0;
    if ($inv_id <= 0) { wx_games_error('参数错误'); return; }

    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $row = $db->once_fetch_array("
        SELECT i.*, s.`item_type`, s.`effect_data`, s.`is_global`, s.`name`
        FROM `$table_inv` i JOIN `$table_items` s ON i.`item_id` = s.`id`
        WHERE i.`id` = $inv_id AND i.`uid` = $uid AND i.`quantity` > i.`used` LIMIT 1");
    if (!$row) { wx_games_error('道具不存在或已用完'); return; }

    $item_type = $row['item_type'];
    $global_types   = ['title_colored', 'title_effect'];
    $cosmetic_types = ['title_colored', 'title_effect', 'card_back', 'emoticon', 'title_badge', 'plinko_skin', 'plinko_theme'];

    if ($item_type === 'member_unlock') {
        $eff = json_decode(stripslashes($row['effect_data']), true);
        $memberKey = is_array($eff) && isset($eff['member']) ? $eff['member'] : '';
        if (!in_array($memberKey, PLINKO_MEMBER_KEYS)) { wx_games_error('无效成员'); return; }
        // 限购 1 次：已解锁就拒绝
        $existingMembers = wx_plinko_get_members($uid);
        if (!empty($existingMembers[$memberKey]['unlocked'])) { wx_games_error('该成员已解锁，不可重复购买'); return; }
        $members = wx_plinko_get_members($uid);
        if (!empty($members[$memberKey]['unlocked'])) { wx_games_error('该成员已解锁'); return; }
        wx_plinko_member_unlock($uid, $memberKey);
        $db->query("UPDATE `$table_inv` SET `quantity` = GREATEST(`quantity` - 1, 0) WHERE `id` = $inv_id");
        wx_games_ok(['msg' => '已解锁新成员！', 'item_type' => 'member_unlock', 'member' => $memberKey]);
        return;
    }

    if (in_array($item_type, $cosmetic_types, true)) {
        $db->query("UPDATE `$table_inv` i JOIN `$table_items` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = '" . addslashes($item_type) . "'");
        if (in_array($item_type, $global_types, true)) {
            // 全局称号/特效：激活所有游戏的同 item_id 记录
            $db->query("UPDATE `$table_inv` SET `is_active` = 1 WHERE `uid` = $uid AND `item_id` = " . intval($row['item_id']));
        } else {
            // 非全局（含 plinko_skin/theme）：仅当前游戏
            $db->query("UPDATE `$table_inv` SET `is_active` = 1 WHERE `id` = " . intval($row['id']));
        }
        wx_games_ok(['msg' => '已激活：' . $row['name'], 'item_type' => $item_type]);
        return;
    }
    if ($item_type === 'score_buff') {
        $eff = json_decode(stripslashes($row['effect_data']), true);
        if (!is_array($eff)) $eff = [];
        $multiplier = isset($eff['multiplier']) ? floatval($eff['multiplier']) : 1;
        $games      = isset($eff['games'])      ? intval($eff['games'])      : 3;
        if ($multiplier <= 0) $multiplier = 1;
        if ($games <= 0) $games = 3;
        $db->query("UPDATE `$table_inv` i JOIN `$table_items` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = 'score_buff'");
        $db->query("UPDATE `$table_inv` SET `is_active` = 1, `charges` = $games, `used` = 0 WHERE `id` = " . intval($row['id']));
        wx_games_ok(['msg' => $multiplier . '倍加成已激活，剩余' . $games . '局', 'multiplier' => $multiplier, 'games' => $games]);
        return;
    }
    if ($item_type === 'plinko_coin_pack') {
        $eff = json_decode(stripslashes($row['effect_data']), true);
        $coins = is_array($eff) ? (isset($eff['coins']) ? intval($eff['coins']) : 0) : 0;
        if ($coins <= 0) { wx_games_error('道具配置错误'); return; }
        $acct = wx_plinko_get_account($uid);
        $cfg = wx_plinko_get_config();
        $initBal = isset($cfg['init_balance']) ? intval($cfg['init_balance']) : 200;
        $new_bal = ($acct ? floatval($acct['balance']) : $initBal) + $coins;
        wx_plinko_save_account($uid, ['balance' => $new_bal, 'updated_at' => time()]);
        $db->query("UPDATE `$table_inv` SET `quantity` = GREATEST(`quantity` - 1, 0) WHERE `id` = $inv_id");
        wx_games_ok(['msg' => '已兑换 +' . $coins . ' 弹珠', 'item_type' => 'plinko_coin_pack', 'new_balance' => $new_bal]);
        return;
    }
    // 其他非外观/币包：消耗一次
    $db->query("UPDATE `$table_inv` SET `used` = `used` + 1 WHERE `id` = " . intval($row['id']));
    wx_games_ok(['msg' => '使用成功']);
    return;
}

// ============================================================
// API: purchase_item - 购买商城商品（适配 ddz 双货币模式）
// ============================================================
function wx_plinko_api_purchase_item() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $pay_type = isset($_POST['pay_type']) ? addslashes(trim($_POST['pay_type'])) : '';
    if ($item_id <= 0 || !in_array($pay_type, ['emlog', 'plinko', 'both'], true)) {
        wx_games_error('参数错误'); return;
    }

    $db = Database::getInstance();
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $item = $db->once_fetch_array("SELECT * FROM `$table_items` WHERE `id` = $item_id AND `status` = 1 AND (`game` = 'plinko' OR `is_global` = 1) LIMIT 1");
    if (!$item) { wx_games_error('商品不存在或已下架'); return; }

    $price_emlog  = (int)$item['price_emlog'];
    $price_plinko = (int)$item['price_game'];
    $item_type    = $item['item_type'];
    $is_global    = !empty($item['is_global']);

    if ($pay_type === 'both') {
        if ($price_emlog <= 0 || $price_plinko <= 0) { wx_games_error('该商品不支持双货币支付'); return; }
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser($uid);
        $credits = isset($emlog_user['credits']) ? intval($emlog_user['credits']) : 0;
        if ($credits < $price_emlog) { wx_games_error('站点积分不足，需要' . $price_emlog . '积分'); return; }
        $acct = wx_plinko_get_account($uid);
        $balance = $acct ? floatval($acct['balance']) : 0;
        if ($balance < $price_plinko) { wx_games_error('弹珠币不足，需要' . $price_plinko . '币'); return; }
        $userModel->reduceCredits($uid, $price_emlog);
        if (function_exists('addCreditRecord')) {
            $game_title = (wx_plinko_get_config()['title'] ?? 'H5弹珠台');
            addCreditRecord($uid, 'reduce', $price_emlog, $game_title . '_buy_' . $item['name'] . '_' . time());
        }
        wx_plinko_save_account($uid, ['balance' => $balance - $price_plinko, 'updated_at' => time()]);
    } elseif ($pay_type === 'emlog') {
        if ($price_emlog <= 0) { wx_games_error('该商品不支持站点积分购买'); return; }
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser($uid);
        $credits = isset($emlog_user['credits']) ? intval($emlog_user['credits']) : 0;
        if ($credits < $price_emlog) { wx_games_error('站点积分不足，需要' . $price_emlog . '积分'); return; }
        $userModel->reduceCredits($uid, $price_emlog);
        if (function_exists('addCreditRecord')) {
            $game_title = (wx_plinko_get_config()['title'] ?? 'H5弹珠台');
            addCreditRecord($uid, 'reduce', $price_emlog, $game_title . '_buy_' . $item['name'] . '_' . time());
        }
    } else { // plinko
        if ($price_plinko <= 0) { wx_games_error('该商品不支持弹珠币购买'); return; }
        $acct = wx_plinko_get_account($uid);
        $balance = $acct ? floatval($acct['balance']) : 0;
        if ($balance < $price_plinko) { wx_games_error('弹珠币不足，需要' . $price_plinko . '币'); return; }
        wx_plinko_save_account($uid, ['balance' => $balance - $price_plinko, 'updated_at' => time()]);
    }

    // 写入背包
    $now = time();
    $table_inv = DB_PREFIX . 'wx_games_user_items';

    // member_unlock 限购 1 次：购买时立即解锁，避免重复购买
    if ($item['item_type'] === 'member_unlock') {
        $eff = json_decode(stripslashes($item['effect_data']), true);
        $memberKey = is_array($eff) && isset($eff['member']) ? $eff['member'] : '';
        if (in_array($memberKey, PLINKO_MEMBER_KEYS)) {
            $m = wx_plinko_get_members($uid);
            if (!empty($m[$memberKey]['unlocked'])) {
                wx_games_error('该成员已解锁，不可重复购买');
                return;
            }
            wx_plinko_member_unlock($uid, $memberKey);
            wx_games_ok(['msg' => '解锁成功！AI ' . ($memberKey) . ' 已加入可选伙伴', 'item_type' => $item_type, 'member' => $memberKey]);
            return;
        }
    }

    if ($is_global) {
        $all_games = ['ddz', 'mj', 'niuniu', 'plinko'];
        foreach ($all_games as $g) {
            $db->query("INSERT INTO `$table_inv` (`game`, `uid`, `item_id`, `quantity`, `purchased_at`, `expires_at`)
                VALUES ('$g', $uid, $item_id, 1, $now, 0)
                ON DUPLICATE KEY UPDATE `quantity` = `quantity` + 1, `purchased_at` = $now");
        }
    } else {
        $existing = $db->once_fetch_array("SELECT `id`, `quantity` FROM `$table_inv` WHERE `game` = 'plinko' AND `uid` = $uid AND `item_id` = $item_id LIMIT 1");
        if ($existing) {
            $db->query("UPDATE `$table_inv` SET `quantity` = `quantity` + 1, `purchased_at` = $now WHERE `game` = 'plinko' AND `id` = " . intval($existing['id']));
        } else {
            $db->query("INSERT INTO `$table_inv` (`game`, `uid`, `item_id`, `quantity`, `used`, `purchased_at`, `expires_at`)
                VALUES ('plinko', $uid, $item_id, 1, 0, $now, 0)");
        }
    }

    wx_games_ok(['msg' => '购买成功！道具已发放到背包', 'item_type' => $item_type]);
    return;
}

// ============================================================
// API: get_my_rank - 获取当前用户弹珠余额
// ============================================================
function wx_plinko_api_get_my_rank() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);
    $row = wx_plinko_get_account($uid);
    $cfg = wx_plinko_get_config();
    $initBal = isset($cfg['init_balance']) ? intval($cfg['init_balance']) : 200;
    wx_games_ok([
        'score'       => $row ? floatval($row['balance']) : $initBal,
        'total_bet'   => $row ? floatval($row['total_bet']) : 0,
        'total_payout'=> $row ? floatval($row['total_payout']) : 0,
        'play_count'  => $row ? intval($row['play_count']) : 0,
    ]);
    return;
}

// ============================================================
// API: get_my_emlog_credits - 获取站点积分
// ============================================================
function wx_plinko_api_get_my_emlog_credits() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    try {
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser(intval($user['uid']));
        $credits = ($emlog_user && isset($emlog_user['credits'])) ? intval($emlog_user['credits']) : 0;
        wx_games_ok(['credits' => $credits]);
    } catch (Throwable $e) {
        wx_games_ok(['credits' => 0]);
    }
    return;
}

// ============================================================
// API: get_ranking - 弹珠排行榜（按余额降序）
// ============================================================
function wx_plinko_api_get_ranking() {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_plinko_accounts';
    $type = isset($_GET['type']) && $_GET['type'] === 'exp' ? 'exp' : 'balance';
    $order = $type === 'exp' ? '`member_exp`' : '`balance`';
    $ranking = [];
    $rows = $db->query("SELECT `uid`, `balance`, `member_exp` FROM `$table` WHERE " . ($type === 'exp' ? '`member_exp` > 0' : '1') . " ORDER BY $order DESC LIMIT 50");
    while ($r = $db->fetch_array($rows)) {
        $ranking[] = ['uid' => (int)$r['uid'], 'balance' => floatval($r['balance']), 'exp' => (int)$r['member_exp']];
    }
    if (!empty($ranking)) {
        $uids = array_column($ranking, 'uid');
        $user_rows = $db->query("SELECT `uid`, `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` IN (" . implode(',', $uids) . ")");
        $users = [];
        while ($u = $db->fetch_array($user_rows)) { $users[(int)$u['uid']] = $u; }
        foreach ($ranking as &$r) {
            $r['nickname'] = isset($users[$r['uid']]) ? $users[$r['uid']]['nickname'] : '未知';
            $r['avatar'] = isset($users[$r['uid']]) ? (wx_games_resolve_avatar($users[$r['uid']]) ?: '') : '';
        }
    }
    wx_games_ok(['ranking' => $ranking]);
    return;
}

// ============================================================
// API: get_score_log - 弹珠流水（wx_plinko_games 表）
// ============================================================
function wx_plinko_api_get_score_log() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_plinko_games';
    $result = $db->query("SELECT `bet`, `multiplier`, `payout`, `profit`, `risk`, `rows`, `bin`, `created_at`
        FROM `$table` WHERE `uid` = $uid ORDER BY `id` DESC LIMIT 50");
    $logs = [];
    while ($row = $db->fetch_array($result)) {
        $riskNames = ['低', '中', '高'];
        $logs[] = [
            'bet'        => (int)$row['bet'],
            'multiplier' => round($row['multiplier'], 1),
            'payout'     => (int)$row['payout'],
            'profit'     => (int)$row['profit'],
            'risk'       => $riskNames[$row['risk']] ?? '中',
            'rows'       => (int)$row['rows'],
            'time'       => date('m-d H:i', (int)$row['created_at']),
        ];
    }
    wx_games_ok(['logs' => $logs]);
    return;
}
function wx_plinko_api_get_active_effects() {
    $user = wx_plinko_check_user();
    if (!$user) { echo json_encode(['code' => 0, 'data' => []], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($user['uid']);
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $result = $db->query("
        SELECT i.`id` AS inv_id, i.`item_id`, s.`item_type`, s.`effect_data`, s.`name`
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`is_active` = 1");
    $effects = [];
    while ($row = $db->fetch_array($result)) {
        $effects[] = ['inv_id' => (int)$row['inv_id'], 'item_id' => (int)$row['item_id'],
            'item_type' => $row['item_type'], 'effect_data' => stripslashes($row['effect_data']), 'name' => $row['name']];
    }
    echo json_encode(['code' => 0, 'data' => $effects], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 逐球日志 API（每颗球落槽后实时写入）
// ============================================================
function wx_plinko_api_log_ball() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('Not logged in'); return; }
    $uid = intval($user['uid']);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { wx_games_error('Invalid data'); return; }

    $bet         = isset($data['betAmount'])  ? intval($data['betAmount'])  : 0;
    $multiplier  = isset($data['multiplier']) ? floatval($data['multiplier']) : 1;
    $payout      = isset($data['payout'])     ? intval($data['payout'])     : 0;
    $profit      = isset($data['profit'])     ? intval($data['profit'])     : 0;
    $risk        = isset($data['risk'])       ? intval($data['risk'])       : 1;
    $rowCount    = isset($data['rowCount'])   ? intval($data['rowCount'])   : 16;
    $binIndex    = isset($data['binIndex'])   ? intval($data['binIndex'])   : -1;

    // Boram 效果：低倍槽退还「损失金额」的 spacing%（服务端权威）
    if ($multiplier < 1 && $profit < 0) {
        $members = wx_plinko_get_members($uid);
        $boram = isset($members['boram']) ? $members['boram'] : null;
        if ($boram && !empty($boram['unlocked']) && $boram['level'] > 0) {
            $cfg = wx_plinko_get_member_config();
            $spacing = 0;
            foreach ($cfg['boram']['levels'] as $lv) {
                if ((int)$lv['level'] === (int)$boram['level'] && isset($lv['params']['spacing'])) {
                    $spacing = (float)$lv['params']['spacing'];
                    break;
                }
            }
            if ($spacing > 0) {
                $loss = $bet - $payout; // 实际损失
                $refund = round($loss * $spacing * 100) / 100;
                $payout += $refund;
                $profit += $refund;
            }
        }
    }
    // Soyeon 效果：随机倍率加成（服务端权威）
    $members2 = wx_plinko_get_members($uid);
    $soyeon = isset($members2['soyeon']) ? $members2['soyeon'] : null;
    if ($soyeon && !empty($soyeon['unlocked']) && $soyeon['level'] > 0) {
        $cfg2 = wx_plinko_get_member_config();
        $prob = 0; $minR = 0.8; $maxR = 1.5;
        foreach ($cfg2['soyeon']['levels'] as $lv) {
            if ((int)$lv['level'] === (int)$soyeon['level']) {
                $p = $lv['params'] ?? [];
                $prob = isset($p['prob']) ? (float)$p['prob'] : 0;
                $minR = isset($p['min']) ? (float)$p['min'] : 0.8;
                $maxR = isset($p['max']) ? (float)$p['max'] : 1.5;
                break;
            }
        }
        if ($prob > 0 && (mt_rand(1, 10000) / 10000) < $prob) {
            $randMult = $minR + (mt_rand(0, 9999) / 9999) * ($maxR - $minR);
            $bonus = round($payout * ($randMult - 1) * 100) / 100;
            $payout += $bonus;
            $profit += $bonus;
        }
    }

    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_plinko_games';
    $db->query("INSERT INTO `$table` (`uid`, `bet`, `multiplier`, `payout`, `profit`, `risk`, `rows`, `bin`, `created_at`)
        VALUES ($uid, $bet, $multiplier, $payout, $profit, $risk, $rowCount, $binIndex, " . time() . ")");

    // 服务端计算余额（DB 为唯一真相源，不信任客户端 localStorage）
    $initBalance = isset($cfg['init_balance']) ? intval($cfg['init_balance']) : 200;
    $acct = wx_plinko_get_account($uid);
    $newBal = ($acct ? floatval($acct['balance']) : $initBalance) + $profit;
    if ($newBal < 0) $newBal = 0;
    wx_plinko_save_account($uid, ['balance' => $newBal, 'updated_at' => time()]);

    // 计算经验值（原始 Storage 直接读，绕过 static cache）
    $storage = Storage::getInstance('wx_plinko');
    $cfg = $storage->getValue('config');
    $expMode = is_array($cfg) ? (isset($cfg['exp_mode']) ? $cfg['exp_mode'] : 'ball') : 'ball';
    $expMult = is_array($cfg) ? (isset($cfg['exp_multiplier']) ? floatval($cfg['exp_multiplier']) : 1.0) : 1.0;
    if ($expMode === 'payout') {
        // payout 模式：按下注额（bet）计算，非落槽结算额（payout）
        $expAmount = max(1, $bet) * $expMult;
    } else {
        $expAmount = 1 * $expMult;
    }
    $newExp = wx_plinko_add_exp($uid, $expAmount);

    wx_games_ok(['logged' => true, 'balance' => $newBal, 'exp' => $newExp]);
    return;
}

// ============================================================
// API：get_members - 获取成员数据
// ============================================================
function wx_plinko_api_get_members() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);
    $members = wx_plinko_get_members($uid);
    $exp = wx_plinko_get_member_exp($uid);
    $cfg = wx_plinko_get_member_config();
    $plinkoCfg = wx_plinko_get_config();
    wx_games_ok([
        'members' => $members,
        'exp' => $exp,
        'config' => $cfg,
        'exp_mode' => isset($plinkoCfg['exp_mode']) ? $plinkoCfg['exp_mode'] : 'ball',
        'exp_multiplier' => isset($plinkoCfg['exp_multiplier']) ? floatval($plinkoCfg['exp_multiplier']) : 1.0,
    ]);
    return;
}

// ============================================================
// API：level_up - 成员升级
// ============================================================
function wx_plinko_api_level_up() {
    $user = wx_plinko_check_user();
    if (!$user) { wx_games_error('未登录'); return; }
    $uid = intval($user['uid']);
    $memberKey = isset($_POST['member']) ? preg_replace('/[^a-z]/', '', $_POST['member']) : '';
    if (!in_array($memberKey, PLINKO_MEMBER_KEYS)) { wx_games_error('无效成员'); return; }
    $members = wx_plinko_get_members($uid);
    $curLevel = isset($members[$memberKey]['level']) ? intval($members[$memberKey]['level']) : 0;
    $cfg = wx_plinko_get_member_config();
    $levels = isset($cfg[$memberKey]['levels']) ? $cfg[$memberKey]['levels'] : [];
    $nextLevel = $curLevel + 1;
    $cost = 0;
    foreach ($levels as $lv) {
        if ((int)$lv['level'] === $nextLevel) { $cost = (int)$lv['exp_cost']; break; }
    }
    if ($cost === 0) { wx_games_error('已是最高级'); return; }
    $curExp = wx_plinko_get_member_exp($uid);
    if ($curExp < $cost) { wx_games_error('经验不足，需要 ' . $cost . ' EXP，当前 ' . $curExp); return; }
    if (wx_plinko_member_level_up($uid, $memberKey, $cost, $nextLevel)) {
        wx_games_ok(['level' => $nextLevel, 'exp' => $curExp - $cost]);
    } else {
        wx_games_error('升级失败');
    }
    return;
}

function wx_plinko_api_get_analysis() {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_plinko_games';
    
    // Get all ball records grouped by risk + rows
    $combos = [];
    $rows = $db->query("SELECT `risk`, `rows`, COUNT(*) AS plays, SUM(`bet`) AS total_bet, SUM(`profit`) AS total_profit FROM `$table` GROUP BY `risk`, `rows` ORDER BY `risk`, `rows`");
    while ($r = $db->fetch_array($rows)) {
        $combos[] = [
            'risk' => $r['risk'],
            'rows' => (int)$r['rows'],
            'plays' => (int)$r['plays'],
            'total_bet' => floatval($r['total_bet']),
            'total_profit' => floatval($r['total_profit']),
            'ev' => wx_plinko_calc_ev($r['risk'], (int)$r['rows']),
        ];
    }
    wx_games_ok(['combos' => $combos]);
}

function wx_plinko_calc_ev($risk, $rows) {
    $config = [
        8  => ['低' => [2.6,1.8,1.1,0.5,0.5,1.1,1.8,2.6,0],    '中' => [5.7,3,1.5,0.6,0.6,1.5,3,5.7,0],          '高' => [22,9,3,0.3,0.3,3,9,22,0]],
        9  => ['低' => [2.5,1.7,1.1,0.7,0.7,1.1,1.7,2.5,0],    '中' => [5.5,2.8,1.4,0.8,0.8,1.4,2.8,5.5,0],       '高' => [21,8.5,2.5,0.4,0.4,2.5,8.5,21,0]],
        10 => ['低' => [2.5,1.7,1.1,0.5,0.5,0.5,1.1,1.7,2.5,0], '中' => [5.5,2.8,1.4,0.6,0.6,0.6,1.4,2.8,5.5,0],    '高' => [21,8.5,2.5,0.3,0.3,0.3,2.5,8.5,21,0]],
        11 => ['低' => [2.6,1.8,1.1,0.5,0.5,0.5,1.1,1.8,2.6,0], '中' => [5.7,3,1.5,0.6,0.6,0.6,1.5,3,5.7,0],       '高' => [22,9,3,0.3,0.3,0.3,3,9,22,0]],
        12 => ['低' => [3.0,2.0,1.2,0.6,0.3,0.3,0.6,1.2,2.0,3.0,0], '中' => [13,5,2,0.7,0.4,0.4,0.7,2,5,13,0],       '高' => [50,15,5,1,0.2,0.2,1,5,15,50,0]],
        13 => ['低' => [3.5,2.2,1.3,0.7,0.4,0.4,0.7,1.3,2.2,3.5,0], '中' => [18,6,3,1,0.5,0.5,1,3,6,18,0],       '高' => [60,19,7,2,0.2,0.2,2,7,19,60,0]],
        14 => ['低' => [4.0,2.5,1.5,0.8,0.3,0.3,0.3,0.8,1.5,2.5,4.0,0], '中' => [25,8,4,1.2,0.5,0.5,0.5,1.2,4,8,25,0], '高' => [72,23,9,3,0.3,0.3,0.3,3,9,23,72,0]],
        15 => ['低' => [5.0,3.0,1.8,1,0.5,0.2,0.2,0.5,1,1.8,3.0,5.0,0], '中' => [36,11,5,2,0.8,0.3,0.3,0.8,2,5,11,36,0], '高' => [90,30,12,5,1,0.2,0.2,1,5,12,30,90,0]],
        16 => ['低' => [6.0,3.5,2.0,1.2,0.5,0.2,0.2,0.5,1.2,2.0,3.5,6.0,0], '中' => [55,15,7,3,1,0.4,0.4,1,3,7,15,55,0], '高' => [110,41,16,7,2,0.3,0.3,2,7,16,41,110,0]],
    ];
    if (!isset($config[$rows]) || !isset($config[$rows][$risk])) return -50;
    $multipliers = $config[$rows][$risk];
    $n = count($multipliers);
    if ($n === 0) return -50;
    $sum = array_sum($multipliers);
    $avg = $sum / $n;
    return round(($avg - 1) * 100, 2);
}
