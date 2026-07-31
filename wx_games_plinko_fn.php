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

    // 只 UPDATE 实际传入的字段，防止覆蓋未传入字段为 0
    $sets = ['`updated_at` = ' . $ts];
    if (isset($data['balance']))      $sets[] = '`balance` = ' . floatval($data['balance']);
    if (isset($data['total_bet']))    $sets[] = '`total_bet` = ' . floatval($data['total_bet']);
    if (isset($data['total_payout'])) $sets[] = '`total_payout` = ' . floatval($data['total_payout']);
    if (isset($data['play_count']))   $sets[] = '`play_count` = ' . intval($data['play_count']);

    // INSERT 必须提供所有列（默认值兜底）
    $balance    = isset($data['balance'])      ? floatval($data['balance'])      : 200;
    $total_bet  = isset($data['total_bet'])    ? floatval($data['total_bet'])    : 0;
    $total_payout = isset($data['total_payout']) ? floatval($data['total_payout']) : 0;
    $play_count = isset($data['play_count'])   ? intval($data['play_count'])   : 0;

    $db->query("INSERT INTO `$table` (`uid`,`balance`,`total_bet`,`total_payout`,`play_count`,`updated_at`)
        VALUES (" . intval($uid) . ",$balance,$total_bet,$total_payout,$play_count,$ts)
        ON DUPLICATE KEY UPDATE " . implode(', ', $sets));
}

// ============================================================
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
        case 'save':           wx_plinko_api_save();            break;
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
// API: save - 保存游戏存档
// ============================================================
function wx_plinko_api_save() {
    $user = wx_plinko_check_user();
    if (!$user) {
        wx_games_error('未登录');
        return;
    }
    $uid = intval($user['uid']);

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) {
        wx_games_error('无效的请求数据');
        return;
    }

    $balance      = isset($data['balance'])      ? floatval($data['balance'])      : 200;
    $total_bet    = isset($data['total_bet'])    ? intval($data['total_bet'])    : 0;
    $total_payout = isset($data['total_payout']) ? intval($data['total_payout']) : 0;
    $play_count   = isset($data['play_count'])   ? intval($data['play_count'])   : 0;

    $save_data = [
        'balance'      => $balance,
        'total_bet'    => $total_bet,
        'total_payout' => $total_payout,
        'play_count'   => $play_count,
        'updated_at'   => time(),
    ];

    wx_plinko_save_account($uid, $save_data);

    wx_games_ok(['saved_at' => $save_data['updated_at']]);
    return;
}

// ============================================================
// API: load - 加载游戏存档
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
        $new_bal = ($acct ? floatval($acct['balance']) : 200) + $coins;
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
            addCreditRecord($uid, 'reduce', $price_emlog, 'plinko_buy_' . $item['name'] . '_' . time());
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
            addCreditRecord($uid, 'reduce', $price_emlog, 'plinko_buy_' . $item['name'] . '_' . time());
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
    wx_games_ok([
        'score'       => $row ? floatval($row['balance']) : 200,
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
    $ranking = [];
    $rows = $db->query("SELECT `uid`, `balance` FROM `$table` ORDER BY `balance` DESC LIMIT 50");
    while ($r = $db->fetch_array($rows)) {
        $ranking[] = ['uid' => (int)$r['uid'], 'balance' => floatval($r['balance'])];
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

    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_plinko_games';
    $db->query("INSERT INTO `$table` (`uid`, `bet`, `multiplier`, `payout`, `profit`, `risk`, `rows`, `bin`, `created_at`)
        VALUES ($uid, $bet, $multiplier, $payout, $profit, $risk, $rowCount, $binIndex, " . time() . ")");

    // log_ball 不再写 balance（避免覆盖 admin 改分 / 币包加成）
// balance 同步交给 save() 每 3 秒一次 + log_ball 只负责记球

    wx_games_ok(['logged' => true]);
    return;
}
