<?php
defined('EMLOG_ROOT') || exit('access denied!');

require_once __DIR__ . '/wx_games_ddz_fn.php';

$db = Database::getInstance();

// ========== 保存基本设置 ==========
if (Input::postStrVar('ddz_action') === 'save_setting') {
    $storage = Storage::getInstance('wx_ddz');
    // 读取现有配置，只覆盖提交的字段（防止单个表单覆盖其他设置）
    $config = wx_ddz_get_config();
    if (isset($_POST['title'])) {
        $config['title'] = addslashes(trim(Input::postStrVar('title', $config['title'])));
    }
    if (isset($_POST['guest_play'])) {
        $config['guest_play'] = $_POST['guest_play'] === '1' ? '1' : '0';
    }
    if (isset($_POST['max_entries'])) {
        $config['max_entries'] = max(10, min(500, Input::postIntVar('max_entries', $config['max_entries'])));
    }
    if (isset($_POST['penalty_multiplier'])) {
        $config['penalty_multiplier'] = max(0.1, min(10, floatval(str_replace(',', '.', Input::postStrVar('penalty_multiplier', '1.0')))));
    }
    if (isset($_POST['base_bet'])) {
        $config['base_bet'] = Input::postIntVar('base_bet', 100);
    }
    if (isset($_POST['recharge_link'])) {
        $config['recharge_link'] = addslashes(trim(Input::postStrVar('recharge_link', '')));
    }
    if (isset($_POST['notice'])) {
        $config['notice'] = addslashes(trim(Input::postStrVar('notice', $config['notice'])));
    }
    if (isset($_POST['recent_updates'])) {
        $config['recent_updates'] = addslashes(trim(Input::postStrVar('recent_updates', $config['recent_updates'])));
    }
    $storage->setValue('config', $config, 'array');
    // 如果有数据清理请求
    if (isset($_POST['do_reset'])) {
        $actions = [];
        if (isset($_POST['reset_scores'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'ddz' AND `is_ai` = 0");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs`");
            $actions[] = '积分';
        }
        if (isset($_POST['reset_games'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_ddz_games`");
            $actions[] = '战绩';
        }
        if (isset($_POST['reset_items'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'ddz'");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `game` = 'ddz'");
            $actions[] = '道具';
        }
        if (!empty($actions)) {
            emMsg('设置已保存，已清理：' . implode('、', $actions), './plugin.php?plugin=wx_games&game=ddz');
        }
    }
    emMsg('设置已保存', './plugin.php?plugin=wx_games&game=ddz');
}

// ========== 保存AI设置 ==========
if (Input::postStrVar('ddz_action') === 'save_ai_setting') {
    $storage = Storage::getInstance('wx_ddz');
    $ai_count = max(2, min(10, Input::postIntVar('ai_count', 2)));
    $ai_players = [];
    $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
    $quote_types = ['bomb', 'rocket', 'plane', 'straight', 'bigCard', 'bid', 'pass', 'win', 'lose'];
    for ($i = 0; $i < $ai_count; $i++) {
        $name = isset($_POST['ai_name'][$i]) ? addslashes(trim($_POST['ai_name'][$i])) : 'AI玩家' . ($i + 1);
        $avatar = isset($_POST['ai_avatar'][$i]) ? addslashes(trim($_POST['ai_avatar'][$i])) : $avatar_files[$i % count($avatar_files)];
        if (empty($name)) $name = 'AI玩家' . ($i + 1);
        $quotes = [];
        foreach ($quote_types as $qt) {
            $qt_key = 'ai_quotes_' . $qt . '_' . $i;
            $raw = isset($_POST[$qt_key]) ? trim($_POST[$qt_key]) : '';
            if (!empty($raw)) {
                // 每行一条台词
                $lines = explode("\n", $raw);
                $quotes[$qt] = [];
                foreach ($lines as $line) {
                    $line = addslashes(trim($line));
                    if (!empty($line)) $quotes[$qt][] = $line;
                }
            } else {
                $quotes[$qt] = [];
            }
        }
        $ai_players[] = ['name' => $name, 'avatar' => $avatar, 'quotes' => $quotes];
    }
    $storage->setValue('ai_players', $ai_players, 'array');
    emMsg('AI设置已保存', './plugin.php?plugin=wx_games&game=ddz');
}

// ========== 保存公告与更新内容 ==========
if (Input::postStrVar('ddz_action') === 'save_content') {
    $storage = Storage::getInstance('wx_ddz');
    $config = wx_ddz_get_config();
    $config['notice'] = addslashes(trim(Input::postStrVar('notice', $config['notice'])));
    $config['recent_updates'] = addslashes(trim(Input::postStrVar('recent_updates', $config['recent_updates'])));
    $storage->setValue('config', $config, 'array');
    emMsg('内容已保存', './plugin.php?plugin=wx_games&game=ddz');
}

// ========== 积分管理操作 ==========
if (Input::postStrVar('ddz_action') === 'change_score') {
    $admin_uid = Input::postIntVar('uid', 0);
    if ($admin_uid <= 0) {
        emMsg('用户ID无效', './plugin.php?plugin=wx_games&game=ddz');
    }
    $score_change = Input::postIntVar('score_change', 0);
    $reason = addslashes(trim(Input::postStrVar('reason', '管理员手动调整')));
    if ($score_change !== 0) {
        $operator_nick = '';
        if (function_exists('LoginAuth') && LoginAuth::isLogin()) {
            $u = LoginAuth::getUserData();
            $operator_nick = isset($u['nickname']) ? $u['nickname'] : 'admin';
        }
        wx_ddz_admin_change_score($admin_uid, $score_change, $reason, $operator_nick);
        emMsg('积分修改成功', './plugin.php?plugin=wx_games&game=ddz');
    } else {
        emMsg('积分变化不能为0', './plugin.php?plugin=wx_games&game=ddz');
    }
}

if (Input::postStrVar('ddz_action') === 'delete_user') {
    $admin_uid = Input::postIntVar('uid', 0);
    if ($admin_uid > 0) {
        $table_scores = DB_PREFIX . 'wx_games_scores';
        $table_games = DB_PREFIX . 'wx_ddz_games';
        $table_logs = DB_PREFIX . 'wx_games_logs';
        $db->query("DELETE FROM `$table_scores` WHERE `uid` = $admin_uid AND `is_ai` = 0");
        $db->query("DELETE FROM `$table_games` WHERE `uid` = $admin_uid");
        $db->query("DELETE FROM `$table_logs` WHERE `uid` = $admin_uid");
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 日志分页 AJAX ==========
if (Input::getStrVar('ddz_action') === 'get_logs_page') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $log_page = max(1, Input::getIntVar('log_page', 1));
    $log_search = addslashes(trim(Input::getStrVar('search', '')));
    $exclude_ai = Input::getStrVar('exclude_ai') === '1';
    $logPageSize = 10;
    $log_offset = ($log_page - 1) * $logPageSize;
    $table_logs = DB_PREFIX . 'wx_games_logs';
    $log_where = "WHERE l.`game` = 'ddz'";
    if ($exclude_ai) {
        $log_where .= " AND l.`uid` NOT IN (SELECT `uid` FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'ddz' AND `is_ai` = 1)";
    }
    if ($log_search) {
        $log_where .= " AND (l.`nickname` LIKE '%$log_search%' OR l.`uid` = '" . intval($log_search) . "')";
    }
    $db = Database::getInstance();
    $total = (int)$db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table_logs` l $log_where")['cnt'];
    $totalPages = max(1, ceil($total / $logPageSize));
    $rows = $db->query("SELECT l.*, IFNULL(u.nickname, '未知') AS nickname FROM `$table_logs` l LEFT JOIN `" . DB_PREFIX . "user` u ON l.uid = u.uid $log_where ORDER BY l.created_at DESC LIMIT $log_offset, $logPageSize");
    $data = [];
    while ($r = $db->fetch_array($rows)) {
        $data[] = $r;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'data' => $data, 'total' => $total, 'totalPages' => $totalPages, 'currentPage' => $log_page], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 用户列表分页 AJAX ==========
if (Input::getStrVar('ddz_action') === 'get_users_page') {
    $page = max(1, Input::getIntVar('page', 1));
    $search = addslashes(trim(Input::getStrVar('search', '')));
    $pageSize = 10;
    $offset = ($page - 1) * $pageSize;
    $db = Database::getInstance();
    $table_scores = DB_PREFIX . 'wx_games_scores';
    $where = "WHERE `game` = 'ddz' AND `is_ai` = 0";
    if ($search) {
        $where = "WHERE (`nickname` LIKE '%$search%' OR `uid` = '$search') AND `game` = 'ddz' AND `is_ai` = 0";
    }
    $total = (int)$db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table_scores` $where")['cnt'];
    $totalPages = max(1, ceil($total / $pageSize));
    $rows = $db->query("SELECT * FROM `$table_scores` $where ORDER BY `score` DESC LIMIT $offset, $pageSize");
    $data = [];
    while ($row = $db->fetch_array($rows)) {
        $uid = (int)$row['uid'];
        $user_row = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
        $data[] = [
            'id' => (int)$row['id'],
            'uid' => $uid,
            'nickname' => $user_row ? $user_row['nickname'] : $row['nickname'],
            'avatar' => $user_row ? $user_row['photo'] : '',
            'score' => (int)$row['score'],
            'total_games' => (int)$row['total_games'],
            'wins' => (int)$row['wins'],
            'losses' => (int)$row['losses'],
            'draws' => (int)$row['draws'],
            'best_score' => (int)$row['best_score'],
        ];
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'data' => $data, 'totalPages' => $totalPages, 'currentPage' => $page], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 数据清理（复选框分项）==========
if (Input::postStrVar('ddz_action') === 'reset_data') {
    $actions = [];
    if (isset($_POST['reset_scores'])) $actions[] = '积分';
    if (isset($_POST['reset_games']))  $actions[] = '战绩';
    if (isset($_POST['reset_items']))  $actions[] = '道具';
    if (!empty($actions)) {
        if (isset($_POST['reset_scores'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'ddz' AND `is_ai` = 0");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs`");
        }
        if (isset($_POST['reset_games'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_ddz_games`");
        }
        if (isset($_POST['reset_items'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'ddz'");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `game` = 'ddz'");
        }
        emMsg('已清理：' . implode('、', $actions), './plugin.php?plugin=wx_games&game=ddz');
    } else {
        emMsg('请至少勾选一项', './plugin.php?plugin=wx_games&game=ddz');
    }
}

// ========== 商城商品管理 ==========
$table_shop = DB_PREFIX . 'wx_games_shop_items';

if (Input::postStrVar('ddz_action') === 'add_shop_item') {
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) emMsg('商品名称不能为空', './plugin.php?plugin=wx_games&game=ddz');
    $item_type = addslashes(trim(Input::postStrVar('item_type', '')));
    // stripslashes 修复表单提交的存量反斜杠数据，再由 $db->escape_string 统一安全转义
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_ddz = Input::postIntVar('price_ddz', 0);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $sort_order = Input::postIntVar('sort_order', 0);
    $now = time();
    $db->query("INSERT INTO `$table_shop` (`game`, `name`, `description`, `icon`, `item_type`, `effect_data`, `price_emlog`, `price_game`, `stock`, `max_per_user`, `sort_order`, `status`, `created_at`)
        VALUES ('ddz', '$name', '$description', '$icon', '$item_type', '$effect_data', $price_emlog, $price_ddz, $stock, $max_per_user, $sort_order, 1, $now)");
    emMsg('商品已添加', './plugin.php?plugin=wx_games&game=ddz');
}

if (Input::postStrVar('ddz_action') === 'edit_shop_item') {
    $edit_id = Input::postIntVar('item_id', 0);
    if ($edit_id <= 0) emMsg('参数错误', './plugin.php?plugin=wx_games&game=ddz');
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) emMsg('商品名称不能为空', './plugin.php?plugin=wx_games&game=ddz');
    $item_type = addslashes(trim(Input::postStrVar('item_type', '')));
    // stripslashes 修复表单提交的存量反斜杠数据，再由 $db->escape_string 统一安全转义
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_ddz = Input::postIntVar('price_ddz', 0);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $sort_order = Input::postIntVar('sort_order', 0);
    $status = Input::postIntVar('status', 1);
    $db->query("UPDATE `$table_shop` SET
        `name` = '$name', `description` = '$description', `icon` = '$icon',
        `item_type` = '$item_type', `effect_data` = '$effect_data',
        `price_emlog` = $price_emlog, `price_game` = $price_ddz,
        `stock` = $stock, `max_per_user` = $max_per_user,
        `sort_order` = $sort_order, `status` = $status
        WHERE `id` = $edit_id");
    emMsg('商品已更新', './plugin.php?plugin=wx_games&game=ddz');
}

if (Input::getStrVar('ddz_action') === 'delete_shop_item') {
    $del_id = Input::getIntVar('item_id', 0);
    if ($del_id > 0) {
        $db->query("DELETE FROM `$table_shop` WHERE `id` = $del_id");
    }
    emMsg('商品已删除', './plugin.php?plugin=wx_games&game=ddz');
}

// ========== 读取设置 ==========
$config = wx_ddz_get_config();
$penalty_multiplier = isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0;
$base_bet = isset($config['base_bet']) ? intval($config['base_bet']) : 100;

// 读取AI玩家设置
$storage = Storage::getInstance('wx_ddz');
$ai_players = [];
try {
    $saved_ai = $storage->getValue('ai_players');
    if (is_array($saved_ai) && !empty($saved_ai)) $ai_players = $saved_ai;
} catch (\Throwable $e) {}
if (empty($ai_players)) {
    $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
    $names = isset($config['ai_names']) ? explode(',', $config['ai_names']) : ['AI玩家1', 'AI玩家2'];
    foreach ($names as $i => $name) {
        $name = trim($name);
        if (empty($name)) $name = 'AI玩家' . ($i + 1);
        $ai_players[] = ['name' => $name, 'avatar' => $avatar_files[$i % count($avatar_files)]];
    }
}
$ai_count = count($ai_players);
$plugin_assets_url = WX_DDZ_URL . 'assets/';

// 读取商城商品数据（支持按类型筛选）
$shop_items = [];
$filter_type = addslashes(trim(Input::getStrVar('filter_type', '')));
try {
    $shop_where = $filter_type ? "WHERE `game` = 'ddz' AND `item_type` = '$filter_type'" : "WHERE `game` = 'ddz'";
    $shop_result = $db->query("SELECT * FROM `$table_shop` $shop_where ORDER BY `sort_order` ASC, `id` ASC");
    while ($row = $db->fetch_array($shop_result)) {
        // stripslashes 修复存量被 addslashes 污染的 effect_data
        if (isset($row['effect_data'])) {
            $row['effect_data'] = stripslashes($row['effect_data']);
        }
        $shop_items[] = $row;
    }
} catch (\Throwable $e) {}
$item_types = [
    'title_colored' => '昵称变色',
    'title_effect'  => '昵称特效',
    'card_back'     => '牌背皮肤',
    'emoticon'      => '专属表情',
    'bomb_effect'   => '炸弹特效',
    'score_buff'    => '积分加成卡',
    'title_badge'   => '称号徽章',
];
// 道具类型默认图标与参考说明
$item_type_icons = [
    'title_colored' => ['icon' => '🎨', 'hint' => '在游戏中昵称显示为彩色，如：{"color":"#ff4500"}'],
    'title_effect'  => ['icon' => '✨', 'hint' => '昵称带光晕特效，如：{"effect":"glow","color":"gold"}'],
    'card_back'     => ['icon' => '🃏', 'hint' => '更换AI牌背图案，如：{"skin":"diamond","url":"..."}'],
    'emoticon'      => ['icon' => '😎', 'hint' => '游戏中发送专属弹幕，如：{"code":"victory","text":"稳了！"}'],
    'bomb_effect'   => ['icon' => '💥', 'hint' => '出炸弹时全屏动画，如：{"effect":"fire","color":"red"}'],
    'score_buff'    => ['icon' => '⚡', 'hint' => '下N局积分加成，如：{"multiplier":1.5,"games":5}'],
    'title_badge'   => ['icon' => '👑', 'hint' => '名称旁显示称号，如：{"badge":"地主之王"}'],
];

// 获取背包和购买统计数据（带分页）
$table_inv = DB_PREFIX . 'wx_games_user_items';
$pageSize = 20;

// 消耗统计分页
$stat_page = max(1, Input::getIntVar('stat_page', 1));
$stat_offset = ($stat_page - 1) * $pageSize;
$inventory_stats = [];
$stat_total = 0;
try {
    $stat_count = $db->once_fetch_array("
        SELECT COUNT(DISTINCT i.`item_id`) AS cnt
        FROM `$table_inv` i
        JOIN `$table_shop` s ON i.`item_id` = s.`id`
        WHERE i.`game` = 'ddz'
    ");
    $stat_total = (int)($stat_count['cnt'] ?? 0);
    $inv_result = $db->query("
        SELECT i.`item_id`, s.`name`, s.`icon`,
               SUM(i.`quantity`) AS total_bought,
               SUM(i.`used`) AS total_used,
               COUNT(DISTINCT i.`uid`) AS buyer_count
        FROM `$table_inv` i
        JOIN `$table_shop` s ON i.`item_id` = s.`id`
        WHERE i.`game` = 'ddz'
        GROUP BY i.`item_id`
        ORDER BY total_bought DESC
        LIMIT $pageSize OFFSET $stat_offset
    ");
    while ($row = $db->fetch_array($inv_result)) {
        $inventory_stats[] = $row;
    }
} catch (\Throwable $e) {}
$stat_total_pages = max(1, ceil($stat_total / $pageSize));

// 购买记录分页（逐条不合并）
$buy_page = max(1, Input::getIntVar('buy_page', 1));
$buy_offset = ($buy_page - 1) * $pageSize;
$purchase_history = [];
$buy_total = 0;
try {
    $buy_count = $db->once_fetch_array("
        SELECT COUNT(*) AS cnt FROM `$table_inv` i
        JOIN `$table_shop` s ON i.`item_id` = s.`id`
        WHERE i.`game` = 'ddz'
    ");
    $buy_total = (int)($buy_count['cnt'] ?? 0);
    $purchase_result = $db->query("
        SELECT i.*, s.`name` AS item_name, s.`icon` AS item_icon
        FROM `$table_inv` i
        JOIN `$table_shop` s ON i.`item_id` = s.`id`
        WHERE i.`game` = 'ddz'
        ORDER BY i.`purchased_at` DESC
        LIMIT $pageSize OFFSET $buy_offset
    ");
    while ($row = $db->fetch_array($purchase_result)) {
        $purchase_history[] = $row;
    }
} catch (\Throwable $e) {}
$buy_total_pages = max(1, ceil($buy_total / $pageSize));

// 分页导航辅助函数
function render_pagination($current, $total, $param_name) {
    if ($total <= 1) return '';
    $query_params = [];
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query_params);
    $html = '<div class="pagination-admin">';
    for ($i = 1; $i <= $total; $i++) {
        $query_params[$param_name] = $i;
        $url = './plugin.php?' . http_build_query($query_params);
        $active = $i == $current ? 'active' : '';
        $html .= '<a href="' . $url . '" class="' . $active . '">' . $i . '</a>';
    }
    $html .= '</div>';
    return $html;
}

// ========== 积分管理数据 ==========
$table_scores = DB_PREFIX . 'wx_games_scores';
$table_logs = DB_PREFIX . 'wx_games_logs';

// 搜索 & 分页
$search = addslashes(trim(Input::getStrVar('search', '')));
$where = "WHERE `game` = 'ddz' AND `is_ai` = 0";
if ($search) {
    $where = "WHERE (`nickname` LIKE '%$search%' OR `uid` = '$search') AND `game` = 'ddz' AND `is_ai` = 0";
}
$page = max(1, Input::getIntVar('page', 1));
$pageSize = 10;
$offset = ($page - 1) * $pageSize;

// 用户列表
$result = $db->query("SELECT * FROM `$table_scores` $where ORDER BY `score` DESC LIMIT $offset, $pageSize");
$users = [];
while ($row = $db->fetch_array($result)) {
    // 真人：昵称和头像实时解析，不依赖 scores 表缓存
    $nickname = $row['nickname'];
    $avatar   = $row['avatar'];
    if ((int)$row['is_ai'] === 0) {
        $user_row = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = " . intval($row['uid']) . " LIMIT 1");
        if ($user_row) {
            $nickname = $user_row['nickname'];
        }
        $avatar = wx_ddz_resolve_avatar((int)$row['uid'], $user_row ? $user_row['photo'] : null);
    }
    $users[] = [
        'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => $nickname,
        'avatar' => $avatar, 'score' => (int)$row['score'],
        'total_games' => (int)$row['total_games'], 'wins' => (int)$row['wins'],
        'losses' => (int)$row['losses'], 'draws' => (int)$row['draws'],
        'best_score' => (int)$row['best_score'], 'updated_at' => (int)$row['updated_at'],
    ];
}
$count_row = $db->once_fetch_array("SELECT COUNT(*) as total FROM `$table_scores` $where");
$total_users_count = (int)$count_row['total'];
$totalPages = ceil($total_users_count / $pageSize);

// 日志（分页，每页10条）
$logPage = max(1, Input::getIntVar('log_page', 1));
$logPageSize = 10;
$logOffset = ($logPage - 1) * $logPageSize;
$total_log_count = 0;
$logTotalPages = 1;
$logs = [];
try {
    $logCountRow = $db->once_fetch_array("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'ddz'");
    $total_log_count = (int)($logCountRow ? $logCountRow['total'] : 0);
    $logTotalPages = max(1, ceil($total_log_count / $logPageSize));
    $logs_result = $db->query("SELECT * FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'ddz' ORDER BY `created_at` DESC LIMIT $logOffset, $logPageSize");
    while ($row = $db->fetch_array($logs_result)) {
        $logs[] = [
            'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => $row['nickname'],
            'score_change' => (int)$row['score_change'], 'score_before' => (int)$row['score_before'],
            'score_after' => (int)$row['score_after'], 'reason' => $row['reason'],
            'operator' => $row['operator'], 'created_at' => (int)$row['created_at'],
        ];
    }
} catch (\Throwable $e) {}

// ========== 设置页面渲染 ==========
function wx_ddz_admin_render() {
    global $config, $penalty_multiplier, $base_bet, $ai_players, $ai_count, $plugin_assets_url, $db;
    global $users, $logs, $search, $page, $totalPages, $total_users_count, $table_scores, $shop_items, $item_types, $item_type_icons, $inventory_stats, $purchase_history, $stat_page, $stat_total_pages, $buy_page, $buy_total_pages, $pageSize, $filter_type;
    global $logPage, $logTotalPages, $total_log_count;
    // 调试：确认表名和条数
    // $debug_r = $db->once_fetch_array("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'ddz'");
    // 如果 $total_log_count 不对，用以下查询校正：
    if ($total_log_count === 0) {
        try {
            $logCountRow2 = $db->once_fetch_array("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'ddz'");
            if ($logCountRow2 && (int)$logCountRow2['total'] > 0) {
                $total_log_count = (int)$logCountRow2['total'];
                $logTotalPages = max(1, ceil($total_log_count / 10));
                $logs_result2 = $db->query("SELECT * FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'ddz' ORDER BY `created_at` DESC LIMIT " . (($logPage - 1) * 10) . ", 10");
                $logs = [];
                while ($row = $db->fetch_array($logs_result2)) {
                    $logs[] = [
                        'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => $row['nickname'],
                        'score_change' => (int)$row['score_change'], 'score_before' => (int)$row['score_before'],
                        'score_after' => (int)$row['score_after'], 'reason' => $row['reason'],
                        'operator' => $row['operator'], 'created_at' => (int)$row['created_at'],
                    ];
                }
            }
        } catch (\Throwable $e) {}
    }
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">🃏 H5 斗地主 - 插件设置</h1>
    </div>

    <ul class="nav nav-tabs mb-4" id="settingTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">基本设置</a></li>
        <li class="nav-item"><a class="nav-link" id="ai-tab" data-toggle="tab" href="#ai" role="tab">AI玩家设置</a></li>
        <li class="nav-item"><a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab">积分管理</a></li>
    </ul>

    <div class="tab-content" id="settingTabsContent">
        <!-- ========== 基本设置 ========== -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <form method="post" action="./plugin.php?plugin=wx_games&game=ddz">
                <input type="hidden" name="ddz_action" value="save_setting">
            <div class="row">
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">基本设置</div>
                        <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>游戏标题</label>
                                            <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($config['title']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>游客模式</label>
                                            <select class="form-control" name="guest_play">
                                                <option value="1" <?php echo $config['guest_play'] == '1' ? 'selected' : ''; ?>>开启</option>
                                                <option value="0" <?php echo $config['guest_play'] == '0' ? 'selected' : ''; ?>>关闭</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>底分</label>
                                            <input class="form-control" name="base_bet" type="number" value="<?php echo $base_bet; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>排行榜最大条目数</label>
                                            <input type="number" class="form-control" name="max_entries" value="<?php echo (int)$config['max_entries']; ?>" min="10" max="500">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>积分充值链接</label>
                                    <input type="url" class="form-control" name="recharge_link" value="<?php echo htmlspecialchars(isset($config['recharge_link']) ? $config['recharge_link'] : ''); ?>" placeholder="https://...">
                                </div>
                                <hr>
                                <div class="form-group" style="margin-bottom:8px">
                                    <label style="font-weight:600;color:#e17055">防逃跑惩罚倍率</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="penalty_multiplier" value="<?php echo number_format($penalty_multiplier, 1, '.', ''); ?>" min="0.1" max="10" step="0.1" style="max-width:180px">
                                        <span class="input-group-text" style="border-radius:0 8px 8px 0;background:#f8f9fe;border:1px solid #e0e2ea;border-left:none;padding:10px 14px;">x</span>
                                        <span style="margin-left:12px;align-self:center;font-size:13px;color:#888">惩罚 = 底分 × 游戏倍率 × 此倍率</span>
                                    </div>
                                </div>
                                <div style="font-size:13px;color:#888;margin-bottom:8px">
                                    <strong>当前：</strong>逃跑扣 <strong style="color:#e17055"><?php echo $base_bet * $penalty_multiplier; ?>×游戏倍率</strong> 分
                                </div>
                            <hr>
                            <!-- 数据管理 -->
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                                    <span style="font-size:14px;font-weight:600">🗃️ 数据管理</span>
                                    <span style="color:#aaa;font-size:13px">玩家记录数：
                                        <?php
                                        try {
                                            $ddz_cr = $db->query("SELECT COUNT(*) as total FROM `$table_scores` WHERE `game` = 'ddz' AND `is_ai` = 0");
                                            $ddz_crow = $db->fetch_array($ddz_cr);
                                            echo '<strong>' . (int)$ddz_crow['total'] . '</strong>';
                                        } catch (\Throwable $e) { echo '0'; }
                                        ?>
                                    </span>
                                </div>
                                <div>
                                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
                                        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;font-weight:400">
                                            <input type="checkbox" name="reset_scores" value="1"> 🏆 清空积分
                                        </label>
                                        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;font-weight:400">
                                            <input type="checkbox" name="reset_games" value="1"> 📊 清空战绩
                                        </label>
                                        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;font-weight:400">
                                            <input type="checkbox" name="reset_items" value="1"> 🎒 清空道具
                                        </label>
                                        <button type="submit" name="do_reset" value="1" class="wx-btn wx-btn-danger" style="padding:4px 16px;font-size:12px" onclick="return confirm('⚠️ 确定要清理所选数据吗？此操作不可恢复！')">执行清理</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">📢 公告与更新</div>
                        <div class="card-body">
                                <div class="form-group">
                                    <label>游戏公告</label>
                                    <textarea class="form-control" name="notice" rows="4" style="width:100%;resize:vertical;"><?php echo htmlspecialchars($config['notice']); ?></textarea>
                                    <small class="form-text text-muted">显示在游戏首页欢迎界面</small>
                                </div>
                                <div class="form-group" style="margin-bottom:8px">
                                    <label>最近更新（每行一条）</label>
                                    <textarea class="form-control" name="recent_updates" rows="6" style="width:100%;resize:vertical;"><?php echo htmlspecialchars($config['recent_updates']); ?></textarea>
                                    <small class="form-text text-muted">格式：版本号 - 内容</small>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
            <div style="text-align:center;margin-top:16px">
                <button type="submit" class="wx-btn" style="padding:10px 48px;font-size:15px">💾 保存全部设置</button>
            </div>
            </form>
        </div>

        <!-- ========== AI玩家设置 ========== -->
        <div class="tab-pane fade" id="ai" role="tabpanel">
            <div class="wx-card card-dark">
                <div class="card-header">AI玩家设置</div>
                <div class="card-body">
                    <form method="post" action="./plugin.php?plugin=wx_games&game=ddz" id="aiForm">
                        <input type="hidden" name="ddz_action" value="save_ai_setting">
                        <div class="d-flex align-items-center" style="gap:16px;margin-bottom:20px;flex-wrap:wrap;">
                            <div>
                                <label style="font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:4px;">AI玩家数量</label>
                                <select class="form-control" name="ai_count" id="aiCount" style="width:auto;min-width:120px;">
                                    <?php for ($n = 2; $n <= 10; $n++) : ?>
                                    <option value="<?php echo $n; ?>" <?php echo $ai_count == $n ? 'selected' : ''; ?>><?php echo $n; ?> 个</option>
                                    <?php endfor; ?>
                                </select>
                                <small class="form-text text-muted">每局随机选2个</small>
                            </div>
                            <button type="submit" class="wx-btn" style="margin-top:18px;">💾 保存所有AI设置</button>
                        </div>

                        <div class="ai-player-grid" id="aiPlayersContainer">
                            <?php
                            $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
                            $theme_colors = ['#e74c3c','#d63031','#e17055','#2ecc71','#e67e22','#fdcb6e'];
                            $quote_types = ['bomb' => '💣 炸弹', 'rocket' => '🚀 火箭', 'plane' => '✈️ 飞机', 'straight' => '🔢 顺子', 'bigCard' => '🃏 大牌', 'bid' => '👑 地主', 'pass' => '🤫 过牌', 'win' => '🎉 胜利', 'lose' => '😢 失败'];
                            foreach ($ai_players as $i => $ai) :
                                $current_avatar = isset($ai['avatar']) ? $ai['avatar'] : $avatar_files[$i % count($avatar_files)];
                                $color = $theme_colors[$i % count($theme_colors)];
                                $ai_quotes = isset($ai['quotes']) && is_array($ai['quotes']) ? $ai['quotes'] : [];
                                $filled = 0;
                                foreach ($quote_types as $qtk => $qtl) {
                                    if (isset($ai_quotes[$qtk]) && is_array($ai_quotes[$qtk]) && count($ai_quotes[$qtk]) > 0) $filled++;
                                }
                            ?>
                            <div class="ai-player-row" data-index="<?php echo $i; ?>" style="background:#fff;border-radius:14px;border:1px solid #eef0f5;box-shadow:0 2px 12px rgba(0,0,0,0.04);overflow:hidden;transition:all 0.25s;position:relative;">
                                <!-- 顶部色条 -->
                                <div style="height:4px;background:linear-gradient(90deg,<?php echo $color; ?>,<?php echo $color; ?>88);"></div>
                                <div style="padding:16px 18px;">
                                    <!-- 头部：头像 + 名称 + 进度 -->
                                    <div class="d-flex align-items-center" style="gap:14px;margin-bottom:14px;">
                                        <div style="position:relative;">
                                            <img src="<?php echo $plugin_assets_url . $current_avatar; ?>" style="width:60px;height:60px;object-fit:cover;border-radius:50%;border:3px solid <?php echo $color; ?>;box-shadow:0 4px 12px <?php echo $color; ?>33;" id="aiPreview<?php echo $i; ?>">
                                            <span style="position:absolute;top:-4px;right:-4px;background:<?php echo $color; ?>;color:#fff;font-size:10px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;border:2px solid #fff;"><?php echo $i+1; ?></span>
                                        </div>
                                        <div style="flex:1;min-width:0;">
                                            <input type="text" class="form-control" name="ai_name[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($ai['name']); ?>" placeholder="输入玩家名称" style="font-size:15px;font-weight:600;border:none;padding:4px 0;background:transparent;border-bottom:2px solid #eef0f5;border-radius:0;width:100%;">
                                            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap;">
                                                <span style="font-size:11px;color:#999;">头像：</span>
                                                <select class="form-control" name="ai_avatar[<?php echo $i; ?>]" style="width:auto;min-width:120px;font-size:12px;padding:2px 8px;height:28px;" onchange="document.getElementById('aiPreview<?php echo $i; ?>').src='<?php echo $plugin_assets_url; ?>'+this.value">
                                                    <?php foreach ($avatar_files as $file) : ?>
                                                    <option value="<?php echo $file; ?>" <?php echo $current_avatar === $file ? 'selected' : ''; ?>><?php echo $file; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div style="text-align:center;flex-shrink:0;">
                                            <div style="font-size:22px;font-weight:700;color:<?php echo $color; ?>;"><?php echo $filled; ?></div>
                                            <div style="font-size:10px;color:#999;">/ 9 类台词</div>
                                        </div>
                                    </div>

                                    <!-- 进度条 -->
                                    <div style="height:4px;background:#eef0f5;border-radius:2px;margin-bottom:14px;overflow:hidden;">
                                        <div style="height:100%;width:<?php echo $filled*11.1; ?>%;background:linear-gradient(90deg,<?php echo $color; ?>,<?php echo $color; ?>88);border-radius:2px;transition:width 0.3s;"></div>
                                    </div>

                                    <!-- 台词类型标签 -->
                                    <div class="quote-tags" style="display:flex;flex-wrap:wrap;gap:5px;">
                                        <?php foreach ($quote_types as $qt_key => $qt_label):
                                            $has_content = isset($ai_quotes[$qt_key]) && is_array($ai_quotes[$qt_key]) && count($ai_quotes[$qt_key]) > 0;
                                            $qt_value = isset($ai_quotes[$qt_key]) && is_array($ai_quotes[$qt_key]) ? implode("\n", $ai_quotes[$qt_key]) : '';
                                        ?>
                                        <a href="javascript:void(0)" class="quote-tag" data-target="qp<?php echo $i; ?>_<?php echo $qt_key; ?>" style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11px;text-decoration:none;transition:all 0.2s;background:<?php echo $has_content ? $color : '#eef0f5'; ?>;color:<?php echo $has_content ? '#fff' : '#999'; ?>;border:1px solid <?php echo $has_content ? $color : '#e0e2ea'; ?>;cursor:pointer;">
                                            <?php echo $qt_label; ?>
                                            <?php if ($has_content): ?><span style="font-size:9px;opacity:0.7;"><?php echo count($ai_quotes[$qt_key]); ?></span><?php endif; ?>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- 台词编辑面板 -->
                                    <div class="quote-panels" style="margin-top:12px;display:none;" id="quotePanels_<?php echo $i; ?>">
                                        <div style="font-size:11px;color:#999;margin-bottom:8px;">点击上方标签切换编辑，每行一句台词，留空则不触发</div>
                                        <?php foreach ($quote_types as $qt_key => $qt_label):
                                            $qt_value = isset($ai_quotes[$qt_key]) && is_array($ai_quotes[$qt_key]) ? implode("\n", $ai_quotes[$qt_key]) : '';
                                        ?>
                                        <div class="quote-panel" id="qp<?php echo $i; ?>_<?php echo $qt_key; ?>" style="display:none;margin-bottom:10px;">
                                            <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;"><?php echo $qt_label; ?></label>
                                            <textarea class="form-control" name="ai_quotes_<?php echo $qt_key; ?>_<?php echo $i; ?>" rows="3" style="font-size:12px;resize:vertical;font-family:monospace;border-left:3px solid <?php echo $color; ?>;" placeholder="每行输入一句台词"><?php echo htmlspecialchars($qt_value); ?></textarea>
                                        </div>
                                        <?php endforeach; ?>
                                        <div style="text-align:right;margin-top:4px;">
                                            <a href="javascript:void(0)" class="quote-close-all" style="font-size:12px;color:#999;text-decoration:none;">收起编辑</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top:16px;">
                            <button type="submit" class="wx-btn">💾 保存所有AI设置</button>
                        </div>
                    </form>
                </div>
            </div>
            <style>
            .ai-player-row:hover { border-color: #d0d4e0 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.08) !important; }
            .quote-tag:hover { opacity: 0.85; transform: scale(1.05); }
            .quote-tag.active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px currentColor; }
            .ai-player-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 16px; }
            @media (max-width: 768px) { .ai-player-grid { grid-template-columns: 1fr; } }
            </style>
        </div>

        <!-- ========== 积分管理 ========== -->
        <div class="tab-pane fade" id="admin" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <!-- 积分查询与修改 -->
                    <div class="wx-card card-dark mb-4">
                        <div class="card-header">积分查询与修改</div>
                        <div class="card-body">
                            <form method="post" class="mb-3" style="max-width:400px">
                                <input type="hidden" name="ddz_action" value="change_score">
                                <div class="form-group">
                                    <label>用户ID</label>
                                    <input class="form-control" name="uid" type="number" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label>积分变动（正=增加，负=扣除）</label>
                                    <input class="form-control" name="score_change" type="number" required>
                                </div>
                                <div class="form-group">
                                    <label>原因</label>
                                    <input class="form-control" name="reason" value="管理员手动调整">
                                </div>
                                <button type="submit" class="wx-btn">提交修改</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <!-- 积分变动日志 -->
                    <div class="wx-card card-dark mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>积分变动日志（共 <?php echo $total_log_count; ?> 条）</span>
                            <label style="font-weight:normal;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:4px;">
                                <input type="checkbox" id="ddzExcludeAiLog" onchange="loadLogsPage(1)" checked>
                                排除AI玩家积分
                            </label>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <div style="overflow-x:auto;">
                                <table class="table-admin">
                                    <thead>
                                        <tr>
                                            <th>时间</th>
                                            <th>用户</th>
                                            <th>变动</th>
                                            <th>变动前</th>
                                            <th>变动后</th>
                                            <th>原因</th>
                                            <th>操作者</th>
                                        </tr>
                                    </thead>
                                    <tbody id="logTableBody">
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td style="white-space:nowrap;"><?php echo date('Y-m-d H:i', $log['created_at']); ?></td>
                                            <td><?php echo htmlspecialchars($log['nickname']); ?></td>
                                            <td>
                                                <?php if ($log['score_change'] > 0): ?>
                                                <span class="win-text">+<?php echo $log['score_change']; ?></span>
                                                <?php else: ?>
                                                <span class="lose-text"><?php echo $log['score_change']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $log['score_before']; ?></td>
                                            <td><?php echo $log['score_after']; ?></td>
                                            <td><?php echo htmlspecialchars($log['reason']); ?></td>
                                            <td><?php echo htmlspecialchars($log['operator']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($logs)): ?>
                                        <tr><td colspan="7" class="wx-empty">暂无日志记录</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($logTotalPages > 1): ?>
                            <div class="pagination-admin" style="margin-top:0;" id="logPagination">
                                <?php
                                $logStart = max(1, $logPage - 2);
                                $logEnd = min($logTotalPages, $logPage + 2);
                                if ($logStart > 1) {
                                    echo '<a href="javascript:void(0)" onclick="loadLogsPage(1)" class="pagi-link">1</a>';
                                    if ($logStart > 2) echo '<span style="padding:6px 8px;color:#999;">...</span>';
                                }
                                for ($i = $logStart; $i <= $logEnd; $i++) {
                                    $active = $i == $logPage ? 'active' : '';
                                    echo '<a href="javascript:void(0)" onclick="loadLogsPage(' . $i . ')" class="pagi-link ' . $active . '">' . $i . '</a>';
                                }
                                if ($logEnd < $logTotalPages) {
                                    if ($logEnd < $logTotalPages - 1) echo '<span style="padding:6px 8px;color:#999;">...</span>';
                                    echo '<a href="javascript:void(0)" onclick="loadLogsPage(' . $logTotalPages . ')" class="pagi-link">' . $logTotalPages . '</a>';
                                }
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 用户积分列表 -->
            <div class="wx-card card-dark">
                <div class="card-header">用户积分列表</div>
                <div class="card-body" style="padding:0;">
                    <div style="padding:16px 22px;border-bottom:1px solid #f0f0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <span>共 <strong><?php echo $total_users_count; ?></strong> 条记录</span>
                        <form method="get" action="./plugin.php" class="form-inline" style="display:flex;gap:8px;">
                            <input type="hidden" name="plugin" value="wx_games">
                            <input type="hidden" name="tab" value="admin">
                            <input type="hidden" name="game" value="ddz">
                            <input type="text" name="search" class="form-control" placeholder="搜索用户ID或昵称" value="<?php echo htmlspecialchars($search); ?>" style="width:200px;">
                            <button type="submit" class="wx-btn wx-btn-sm">搜索</button>
                        </form>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>UID</th>
                                    <th>昵称</th>
                                    <th>当前积分</th>
                                    <th>场次</th>
                                    <th>胜/负/平</th>
                                    <th>最高分</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody">
                                <?php foreach ($users as $index => $user): ?>
                                <tr>
                                    <td><?php echo ($page - 1) * $pageSize + $index + 1; ?></td>
                                    <td><?php echo $user['uid']; ?></td>
                                    <td>
                                        <?php if ($user['avatar']): ?>
                                        <img src="<?php echo $user['avatar']; ?>" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:4px;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($user['nickname']); ?>
                                    </td>
                                    <td><span class="badge-score"><?php echo $user['score']; ?></span></td>
                                    <td><?php echo $user['total_games']; ?></td>
                                    <td>
                                        <span class="win-text"><?php echo $user['wins']; ?>胜</span> /
                                        <span class="lose-text"><?php echo $user['losses']; ?>负</span> /
                                        <span style="color:#999;"><?php echo $user['draws']; ?>平</span>
                                    </td>
                                    <td><?php echo $user['best_score']; ?></td>
                                    <td>
                                        <button type="button" class="wx-btn wx-btn-sm btn-change-score" data-uid="<?php echo $user['uid']; ?>" data-score="<?php echo $user['score']; ?>" data-nick="<?php echo htmlspecialchars($user['nickname'], ENT_QUOTES); ?>">修改积分</button>
                                        <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);margin-left:4px;" onclick="showUserLog(<?php echo $user['uid']; ?>, '<?php echo htmlspecialchars($user['nickname'], ENT_QUOTES); ?>')">流水</button>
                                        <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" style="margin-left:4px;" onclick="deleteUser(<?php echo $user['uid']; ?>)">删除</button>
                                        <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb);margin-left:4px;" onclick="openBackpack(<?php echo $user['uid']; ?>, '<?php echo htmlspecialchars($user['nickname'], ENT_QUOTES); ?>')">背包</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                <tr><td colspan="8" class="wx-empty">暂无数据</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-admin" id="userPagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="javascript:void(0)" onclick="loadUsersPage(<?php echo $i; ?>)" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


<div class="modal fade" id="scoreModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;border-radius:14px 14px 0 0;border:none;">
                <h5 class="modal-title" style="font-size:16px;" id="scoreModalTitle">修改积分</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
            </div>
            <form method="post" action="./plugin.php?plugin=wx_games&game=ddz">
                <input type="hidden" name="ddz_action" value="change_score">
                <input type="hidden" name="uid" id="scoreModalUid">
                <div class="modal-body" style="padding:24px;">
                    <div class="form-group">
                        <label>当前积分</label>
                        <input type="text" class="form-control" id="scoreModalCurrent" readonly style="background:#f8f9fe;">
                    </div>
                    <div class="form-group">
                        <label>积分变化（正数增加，负数减少）</label>
                        <input type="number" name="score_change" class="form-control" required placeholder="例如：100 或 -50">
                    </div>
                    <div class="form-group">
                        <label>变动原因</label>
                        <input type="text" name="reason" class="form-control" value="管理员手动调整">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f0f0f5;padding:16px 24px;">
                    <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" data-dismiss="modal" style="opacity:0.7;">取消</button>
                    <button type="submit" class="wx-btn wx-btn-sm">确认修改</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 添加商品弹窗 -->
<div class="modal fade" id="addShopModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;border-radius:14px 14px 0 0;border:none;">
                <h5 class="modal-title" style="font-size:16px;">添加商品</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
            </div>
            <form method="post" action="./plugin.php?plugin=wx_games&game=ddz">
                <input type="hidden" name="ddz_action" value="add_shop_item">
                <div class="modal-body" style="padding:24px;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>商品名称 <span style="color:red;">*</span></label>
                                <input type="text" name="name" class="form-control" required placeholder="例如：昵称变色">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>道具类型 <span style="font-size:11px;color:#999;font-weight:normal;">（选择后可参考下方说明）</span></label>
                                <select name="item_type" id="add_item_type" class="form-control" onchange="updateTypeHint('add')">
                                    <?php foreach ($item_types as $tk => $tl): 
                                        $ti = isset($item_type_icons[$tk]) ? $item_type_icons[$tk]['icon'] : '🎁';
                                    ?>
                                    <option value="<?php echo $tk; ?>" data-icon="<?php echo $ti; ?>"><?php echo $ti . ' ' . $tl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="addTypeHint" class="wx-info-block" style="margin-top:8px;font-size:12px;display:flex;align-items:center;gap:8px;">
                                    <span id="addTypeIcon">🎨</span>
                                    <span id="addTypeDesc">在游戏中昵称显示为彩色，如：{"color":"#ff4500"}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>商品描述</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="简短描述商品效果"></textarea>
                    </div>
                    <div class="form-group">
                        <label>图标URL</label>
                        <input type="text" name="icon" class="form-control" placeholder="可选，商品列表显示的图标URL">
                    </div>
                    <div class="form-group">
                        <label>效果参数 (JSON)</label>
                        <textarea name="effect_data" class="form-control" rows="2" style="font-family:monospace;font-size:12px;">{"color":"#ff4500"}</textarea>
                        <small class="form-text text-muted">不同类型道具的配置参数，JSON格式。昵称变色：{"color":"#ff4500"}</small>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>站点积分价</label>
                                <input type="number" name="price_emlog" class="form-control" value="0" min="0">
                                <small class="form-text text-muted">0=不支持站点积分购买</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>斗地主积分价</label>
                                <input type="number" name="price_ddz" class="form-control" value="0" min="0">
                                <small class="form-text text-muted">0=不支持斗地主积分购买</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>排序</label>
                                <input type="number" name="sort_order" class="form-control" value="0" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>库存 (-1=不限量)</label>
                                <input type="number" name="stock" class="form-control" value="-1" min="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>每人限购 (0=不限)</label>
                                <input type="number" name="max_per_user" class="form-control" value="0" min="0">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f0f0f5;padding:16px 24px;">
                    <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" data-dismiss="modal" style="opacity:0.7;">取消</button>
                    <button type="submit" class="wx-btn wx-btn-sm">添加商品</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 编辑商品弹窗 -->
<div class="modal fade" id="editShopModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;border-radius:14px 14px 0 0;border:none;">
                <h5 class="modal-title" style="font-size:16px;">编辑商品</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
            </div>
            <form method="post" action="./plugin.php?plugin=wx_games&game=ddz">
                <input type="hidden" name="ddz_action" value="edit_shop_item">
                <input type="hidden" name="item_id" id="edit_item_id" value="0">
                <div class="modal-body" style="padding:24px;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>商品名称 <span style="color:red;">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>道具类型 <span style="font-size:11px;color:#999;font-weight:normal;">（选择后可参考下方说明）</span></label>
                                <select name="item_type" id="edit_item_type" class="form-control" onchange="updateTypeHint('edit')">
                                    <?php foreach ($item_types as $tk => $tl): 
                                        $ti = isset($item_type_icons[$tk]) ? $item_type_icons[$tk]['icon'] : '🎁';
                                    ?>
                                    <option value="<?php echo $tk; ?>" data-icon="<?php echo $ti; ?>"><?php echo $ti . ' ' . $tl; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="editTypeHint" class="wx-info-block" style="margin-top:8px;font-size:12px;display:flex;align-items:center;gap:8px;">
                                    <span id="editTypeIcon">🎨</span>
                                    <span id="editTypeDesc">在游戏中昵称显示为彩色，如：{"color":"#ff4500"}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>商品描述</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>图标URL</label>
                        <input type="text" name="icon" id="edit_icon" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>效果参数 (JSON)</label>
                        <textarea name="effect_data" id="edit_effect_data" class="form-control" rows="2" style="font-family:monospace;font-size:12px;"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>站点积分价</label>
                                <input type="number" name="price_emlog" id="edit_price_emlog" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>斗地主积分价</label>
                                <input type="number" name="price_ddz" id="edit_price_ddz" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>排序</label>
                                <input type="number" name="sort_order" id="edit_sort_order" class="form-control" min="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>库存 (-1=不限量)</label>
                                <input type="number" name="stock" id="edit_stock" class="form-control" min="-1">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>每人限购 (0=不限)</label>
                                <input type="number" name="max_per_user" id="edit_max_per_user" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>状态</label>
                                <select name="status" id="edit_status" class="form-control">
                                    <option value="1">上架</option>
                                    <option value="0">下架</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f0f0f5;padding:16px 24px;">
                    <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" data-dismiss="modal" style="opacity:0.7;">取消</button>
                    <button type="submit" class="wx-btn wx-btn-sm">保存修改</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 用户流水弹窗 -->
<div class="modal fade" id="userLogModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;border-radius:14px 14px 0 0;border:none;">
                <h5 class="modal-title" id="userLogModalTitle" style="font-size:16px;">📝 用户积分流水</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
            </div>
            <div class="modal-body" style="padding:0;max-height:400px;overflow-y:auto;">
                <table class="table-admin" style="margin:0;">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>变动</th>
                            <th>变动前</th>
                            <th>变动后</th>
                            <th>原因</th>
                            <th>操作者</th>
                        </tr>
                    </thead>
                    <tbody id="userLogBody">
                        <tr><td colspan="6" class="wx-empty">加载中...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 用户背包管理弹窗 -->
<div class="modal fade" id="userBackpackModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;border-radius:14px 14px 0 0;border:none;">
                <h5 class="modal-title" id="backpackModalTitle" style="font-size:16px;">玩家背包管理</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <!-- 发放道具 -->
                <div style="background:#f8f9fe;border-radius:10px;padding:16px;margin-bottom:16px;">
                    <div style="font-size:14px;font-weight:600;color:#333;margin-bottom:12px;">📤 发放道具</div>
                    <div class="row" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <div style="flex:2;min-width:180px;">
                            <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">选择道具</label>
                            <select class="form-control" id="backpackGiveItem" style="font-size:13px;">
                                <option value="">-- 请选择道具 --</option>
                            </select>
                        </div>
                        <div style="flex:0 0 80px;">
                            <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">数量</label>
                            <input type="number" class="form-control" id="backpackGiveQty" value="1" min="1" max="999" style="font-size:13px;">
                        </div>
                        <div>
                            <button type="button" class="wx-btn wx-btn-sm" id="btnBackpackGive" onclick="adminGiveItem()">发放</button>
                        </div>
                    </div>
                </div>

                <!-- 当前背包物品列表 -->
                <div style="font-size:14px;font-weight:600;color:#333;margin-bottom:8px;">📦 当前背包物品</div>
                <div id="backpackItemsList" style="min-height:60px;">
                    <div style="text-align:center;color:#ccc;padding:20px;">加载中...</div>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- ========== 商城管理 ========== -->
        <div class="tab-pane fade" id="shop" role="tabpanel">
            <div class="wx-card card-dark">
                <div class="card-header">商品列表</div>
                <div class="card-body" style="padding:0;">
                    <div style="padding:16px 22px;border-bottom:1px solid #f0f0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span>共 <strong><?php echo count($shop_items); ?></strong> 件商品</span>
                            <select class="form-control" style="width:auto;display:inline-block;height:32px;font-size:13px;padding:2px 8px;" onchange="location.href='./plugin.php?plugin=wx_games&game=ddz&tab=shop&filter_type='+this.value">
                                <option value="">全部类型</option>
                                <?php foreach ($item_types as $tk => $tv): ?>
                                <option value="<?php echo $tk; ?>" <?php echo $filter_type === $tk ? 'selected' : ''; ?>><?php echo $tv; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="wx-btn wx-btn-sm" onclick="$('#addShopModal').modal('show')">+ 添加商品</button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名称</th>
                                    <th>类型</th>
                                    <th>站点积分价</th>
                                    <th>斗地主积分价</th>
                                    <th>库存</th>
                                    <th>限购</th>
                                    <th>排序</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($shop_items)): ?>
                                <tr><td colspan="10" class="wx-empty"><span class="empty-icon">🛒</span>暂无商品，点击上方"添加商品"开始</td></tr>
                                <?php else: ?>
                                <?php foreach ($shop_items as $item): ?>
                                <tr>
                                    <td><?php echo (int)$item['id']; ?></td>
                                    <td>
                                        <?php 
                                        $type_icon_name = isset($item_type_icons[$item['item_type']]['icon']) ? $item_type_icons[$item['item_type']]['icon'] : '🎁';
                                        echo $type_icon_name;
                                        ?>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td><span class="badge-score" style="font-size:11px;"><?php 
                                        $type_key = $item['item_type'];
                                        $type_name = isset($item_types[$type_key]) ? $item_types[$type_key] : $type_key;
                                        $type_icon = isset($item_type_icons[$type_key]['icon']) ? $item_type_icons[$type_key]['icon'] : '🎁';
                                        echo $type_icon . ' ' . $type_name;
                                    ?></span></td>
                                    <td><?php echo (int)$item['price_emlog'] > 0 ? (int)$item['price_emlog'] : '-'; ?></td>
                                    <td><?php echo (int)$item['price_game'] > 0 ? (int)$item['price_game'] : '-'; ?></td>
                                    <td><?php echo (int)$item['stock'] === -1 ? '不限' : (int)$item['stock']; ?></td>
                                    <td><?php echo (int)$item['max_per_user'] > 0 ? (int)$item['max_per_user'] : '不限'; ?></td>
                                    <td><?php echo (int)$item['sort_order']; ?></td>
                                    <td><?php echo (int)$item['status'] === 1 ? '<span style="color:#2ecc71;">上架</span>' : '<span style="color:#999;">下架</span>'; ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" class="wx-btn wx-btn-sm" onclick="editShopItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">编辑</button>
                                        <a href="./plugin.php?plugin=wx_games&game=ddz&ddz_action=delete_shop_item&item_id=<?php echo (int)$item['id']; ?>" class="wx-btn wx-btn-sm wx-btn-danger" onclick="return confirm('确定删除「<?php echo htmlspecialchars($item['name']); ?>」吗？')">删除</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <!-- 背包消耗统计 -->
            <div class="row">
        <div class="col-lg-6">
        <div class="wx-card card-dark">
            <div class="card-header">道具消耗统计</div>
            <div class="card-body" style="padding:0;">
                <div style="overflow-x:auto;">
                    <table class="table-admin">
                        <thead>
                            <tr>
                                <th>商品</th>
                                <th>购买人次</th>
                                <th>总购买数</th>
                                <th>已消耗</th>
                                <th>剩余</th>
                                <th>消耗率</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory_stats)): ?>
                            <tr><td colspan="6" class="wx-empty"><span class="empty-icon">📦</span>暂无购买数据</td></tr>
                            <?php else: ?>
                            <?php foreach ($inventory_stats as $stat): ?>
                            <?php
                                $total = (int)$stat['total_bought'];
                                $used = (int)$stat['total_used'];
                                $remain = $total - $used;
                                $rate = $total > 0 ? round($used / $total * 100) : 0;
                            ?>
                            <tr>
                                <td>
                                    <?php if ($stat['icon'] && (strpos($stat['icon'], 'http') === 0 || strpos($stat['icon'], '/') === 0)): ?><img src="<?php echo htmlspecialchars($stat['icon']); ?>" style="width:18px;height:18px;vertical-align:middle;margin-right:4px;border-radius:3px;" onerror="this.style.display='none'"><?php elseif ($stat['icon']): ?><span style="margin-right:4px;"><?php echo htmlspecialchars($stat['icon']); ?></span><?php endif; ?>
                                    <?php echo htmlspecialchars($stat['name']); ?>
                                </td>
                                <td><strong><?php echo (int)$stat['buyer_count']; ?></strong></td>
                                <td><?php echo $total; ?></td>
                                <td><?php echo $used; ?></td>
                                <td><?php echo $remain; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <div style="flex:1;height:6px;background:#eef0f5;border-radius:3px;overflow:hidden;min-width:60px;">
                                            <div style="height:100%;width:<?php echo $rate; ?>%;background:linear-gradient(90deg,#2d3436,#636e72);border-radius:3px;"></div>
                                        </div>
                                        <span style="font-size:11px;color:#666;"><?php echo $rate; ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo render_pagination($stat_page, $stat_total_pages, 'stat_page'); ?>
            </div>
        </div>
        </div>
        <div class="col-lg-6">
        <div class="wx-card card-dark">
            <div class="card-header">最近购买记录</div>
            <div class="card-body" style="padding:0;">
                <div style="overflow-x:auto;">
                    <table class="table-admin">
                        <thead>
                            <tr>
                                <th>时间</th>
                                <th>用户ID</th>
                                <th>商品</th>
                                <th>数量</th>
                                <th>已使用</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchase_history)): ?>
                            <tr><td colspan="5" class="wx-empty"><span class="empty-icon">🕐</span>暂无购买记录</td></tr>
                            <?php else: ?>
                            <?php foreach ($purchase_history as $ph): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo date('Y-m-d H:i', (int)$ph['purchased_at']); ?></td>
                                <td><?php echo (int)$ph['uid']; ?></td>
                                <td>
                                    <?php if ($ph['item_icon'] && (strpos($ph['item_icon'], 'http') === 0 || strpos($ph['item_icon'], '/') === 0)): ?><img src="<?php echo htmlspecialchars($ph['item_icon']); ?>" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;border-radius:3px;" onerror="this.style.display='none'"><?php elseif ($ph['item_icon']): ?><span style="margin-right:4px;"><?php echo htmlspecialchars($ph['item_icon']); ?></span><?php endif; ?>
                                    <?php echo htmlspecialchars($ph['item_name']); ?>
                                </td>
                                <td><?php echo (int)$ph['quantity']; ?></td>
                                <td><?php echo (int)$ph['used']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php echo render_pagination($buy_page, $buy_total_pages, 'buy_page'); ?>
            </div>
        </div>
        </div>
    </div>
<!-- 修改积分弹窗（动态单例） -->

<script>
// AI 台词标签点击切换编辑面板
document.querySelectorAll('.quote-tag').forEach(function(tag) {
    tag.addEventListener('click', function(e) {
        e.preventDefault();
        var targetId = this.getAttribute('data-target');
        var panel = document.getElementById(targetId);
        if (!panel) return;

        // 获取当前角色所有面板容器
        var panelsContainer = panel.closest('.quote-panels');
        if (!panelsContainer) return;

        // 显示面板容器
        panelsContainer.style.display = 'block';

        // 隐藏该角色下所有编辑面板，然后只显示当前点击的
        var allPanels = panelsContainer.querySelectorAll('.quote-panel');
        allPanels.forEach(function(p) { p.style.display = 'none'; });
        panel.style.display = 'block';

        // 高亮当前标签
        var tags = panelsContainer.closest('.ai-player-row').querySelectorAll('.quote-tag');
        tags.forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
    });
});

// 收起编辑
document.querySelectorAll('.quote-close-all').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        var panelsContainer = this.closest('.quote-panels');
        if (panelsContainer) {
            panelsContainer.style.display = 'none';
            // 移除高亮
            var row = panelsContainer.closest('.ai-player-row');
            if (row) {
                row.querySelectorAll('.quote-tag').forEach(function(t) { t.classList.remove('active'); });
            }
        }
    });
});

// AI数量变化
var aiCount = document.getElementById('aiCount');
if (aiCount) {
    aiCount.addEventListener('change', function() {
        var count = parseInt(this.value);
        var rows = document.querySelectorAll('.ai-player-row');
        rows.forEach(function(row, index) {
            if (index < count) { row.style.display = 'block'; row.querySelectorAll('input,select,textarea').forEach(function(el) { el.disabled = false; }); }
            else { row.style.display = 'none'; row.querySelectorAll('input,select,textarea').forEach(function(el) { el.disabled = true; }); }
        });
    });
}
(function() {
    var aiCount = document.getElementById('aiCount');
    if (!aiCount) return;
    var count = parseInt(aiCount.value);
    document.querySelectorAll('.ai-player-row').forEach(function(row, index) {
        if (index >= count) { row.style.display = 'none'; row.querySelectorAll('input,select,textarea').forEach(function(el) { el.disabled = true; }); }
    });
})();

// 道具类型选择切换提示
var TYPE_HINTS = <?php echo json_encode($item_type_icons, JSON_UNESCAPED_UNICODE); ?>;

function updateTypeHint(prefix) {
    var sel = document.getElementById(prefix + '_item_type');
    if (!sel) return;
    var val = sel.value;
    var hint = TYPE_HINTS[val] || { icon: '🎁', hint: '自定义道具' };
    var iconEl = document.getElementById(prefix + 'TypeIcon');
    var descEl = document.getElementById(prefix + 'TypeDesc');
    if (iconEl) iconEl.textContent = hint.icon;
    if (descEl) descEl.textContent = hint.hint;
}
// 初始化
document.addEventListener('DOMContentLoaded', function() {
    updateTypeHint('add');
    updateTypeHint('edit');
});

// ====== 修改积分弹窗 ======
$(document).on('click', '.btn-change-score', function(e) {
    var btn = this;
    document.getElementById('scoreModalUid').value = $(btn).attr('data-uid');
    document.getElementById('scoreModalCurrent').value = $(btn).attr('data-score');
    var nick = $(btn).attr('data-nick');
    document.getElementById('scoreModalTitle').textContent = '修改积分' + (nick ? ' - ' + nick : '');
    $('#scoreModal').modal('show');
});

// ====== 流水弹窗 ======
function showUserLog(uid, nickname) {
    document.getElementById('userLogModalTitle').textContent = (nickname || '用户') + ' 的积分流水';
    document.getElementById('userLogBody').innerHTML = '<tr><td colspan="6" class="wx-empty">加载中...</td></tr>';
    $('#userLogModal').modal('show');
    fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=ddz&ddz_action=get_user_logs&uid=' + uid, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 0 && data.data && data.data.length > 0) {
                var html = '';
                data.data.forEach(function(item) {
                    var time = item.created_at;
                    if (typeof time === 'number' || /^\d+$/.test(String(time))) {
                        var dt = new Date(parseInt(time) * 1000);
                        time = dt.getFullYear() + '-' + ('0'+(dt.getMonth()+1)).slice(-2) + '-' + ('0'+dt.getDate()).slice(-2) + ' ' + ('0'+dt.getHours()).slice(-2) + ':' + ('0'+dt.getMinutes()).slice(-2);
                    }
                    var change = parseInt(item.score_change);
                    var sign = change >= 0 ? '+' : '';
                    var color = change >= 0 ? '#2ecc71' : '#e74c3c';
                    html += '<tr><td style="white-space:nowrap;">' + time + '</td><td><span style="color:' + color + ';font-weight:600;">' + sign + change + '</span></td><td>' + (item.score_before || 0) + '</td><td>' + (item.score_after || 0) + '</td><td>' + (item.reason || '') + '</td><td>' + (item.operator || '') + '</td></tr>';
                });
                document.getElementById('userLogBody').innerHTML = html;
            } else {
                document.getElementById('userLogBody').innerHTML = '<tr><td colspan="6" class="wx-empty">暂无流水记录</td></tr>';
            }
        })
        .catch(function() {
            document.getElementById('userLogBody').innerHTML = '<tr><td colspan="6" class="wx-empty">加载失败</td></tr>';
        });
}

// ====== 删除玩家 ======
function deleteUser(uid) {
    if (!confirm('⚠️ 确定要删除该玩家的积分、游戏记录和流水吗？此操作不可撤销！')) return;
    if (!confirm('再次确认：所有数据将被永久删除！')) return;
    var formData = new FormData();
    formData.append('ddz_action', 'delete_user');
    formData.append('uid', uid);
    fetch('?plugin=wx_games&game=ddz', { method: 'POST', body: new URLSearchParams(formData) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.code === 0) { alert('删除成功'); location.reload(); }
            else { alert('删除失败: ' + d.message); }
        });
}

// ====== 背包管理 ======
var _backpackUid = 0;

function openBackpack(uid, nickname) {
    _backpackUid = uid;
    var titleEl = document.getElementById('backpackModalTitle');
    if (titleEl) titleEl.textContent = '🎒 ' + (nickname || '用户') + ' 的背包';
    loadUserBackpack(uid);
    loadShopItemsDropdown();
    $('#userBackpackModal').modal('show');
}

// ====== 用户列表分页 AJAX ======
function loadUsersPage(page) {
    var search = document.getElementById('logSearchInput') ? document.getElementById('logSearchInput').value : '';
    var tbody = document.getElementById('userTableBody');
    if (!tbody) { return; }
    tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">加载中...</td></tr>';
    fetch('?plugin=wx_games&game=ddz&ddz_action=get_users_page&page=' + page + '&search=' + encodeURIComponent(search))
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.code !== 0) { tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">加载失败</td></tr>'; return; }
        if (!d.data || d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">暂无数据</td></tr>'; return; }
        var html = '';
        d.data.forEach(function(u, idx) {
            var safeName = escapeHtml(u.nickname);
            html += '<tr>'
                + '<td>' + ((d.currentPage - 1) * 10 + idx + 1) + '</td>'
                + '<td>' + u.uid + '</td>'
                + '<td>' + (u.avatar ? '<img src="' + u.avatar + '" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:4px;">' : '') + safeName + '</td>'
                + '<td><span class="badge-score">' + u.score + '</span></td>'
                + '<td>' + u.total_games + '</td>'
                + '<td><span class="win-text">' + u.wins + '胜</span> / <span class="lose-text">' + u.losses + '负</span> / <span style="color:#999;">' + u.draws + '平</span></td>'
                + '<td>' + u.best_score + '</td>'
                + '<td>'
                + '<button type="button" class="wx-btn wx-btn-sm btn-change-score" data-uid="' + u.uid + '" data-score="' + u.score + '">修改积分</button>'
                + '<button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);margin-left:4px;" onclick="showUserLog(' + u.uid + ')">流水</button>'
                + '<button type="button" class="wx-btn wx-btn-sm wx-btn-danger" style="margin-left:4px;" onclick="deleteUser(' + u.uid + ')">删除</button>'
                + '<button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb);margin-left:4px;" onclick="openBackpack(' + u.uid + ')">背包</button>'
                + '</td></tr>';
        });
        tbody.innerHTML = html;
        updateUserPagination(d.currentPage, d.totalPages);
    })
    .catch(function() {
        tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">网络错误</td></tr>';
    });
}
function updateUserPagination(currentPage, totalPages) {
    var container = document.getElementById('userPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    var html = '';
    for (var i = 1; i <= totalPages; i++) {
        html += '<a href="javascript:void(0)" onclick="loadUsersPage(' + i + ')" class="' + (i == currentPage ? 'active' : '') + '">' + i + '</a>';
    }
    container.innerHTML = html;
}
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/'/g,'&#39;').replace(/"/g,'&quot;');
}

// ====== 日志分页 AJAX ======
function loadLogsPage(page) {
    var search = document.getElementById('logSearchInput') ? document.getElementById('logSearchInput').value : '';
    var excludeAi = document.getElementById('ddzExcludeAiLog');
    var excludeVal = excludeAi && excludeAi.checked ? '1' : '0';
    var tbody = document.getElementById('logTableBody');
    if (!tbody) { console.log('logTableBody not found'); return; }
    tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载中...</td></tr>';
    fetch('?plugin=wx_games&game=ddz&ddz_action=get_logs_page&log_page=' + page + '&search=' + encodeURIComponent(search) + '&exclude_ai=' + excludeVal, { credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        // 更新标题里的总数
        var titleSpan = document.querySelector('#logTableBody').parentElement.parentElement.parentElement.querySelector('.card-header span');
        if (titleSpan && typeof d.total === 'number') titleSpan.textContent = '积分变动日志（共 ' + d.total + ' 条）';
        if (d.code !== 0) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载失败</td></tr>'; return; }
        if (!d.data || d.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">暂无日志记录</td></tr>';
        } else {
            var html = '';
            d.data.forEach(function(log) {
                var time = log.created_at;
                if (typeof time === 'number' || /^\d+$/.test(String(time))) {
                    var dt = new Date(parseInt(time) * 1000);
                    time = dt.getFullYear() + '-' + ('0'+(dt.getMonth()+1)).slice(-2) + '-' + ('0'+dt.getDate()).slice(-2) + ' ' + ('0'+dt.getHours()).slice(-2) + ':' + ('0'+dt.getMinutes()).slice(-2);
                }
                var change = parseInt(log.score_change);
                var changeHtml = change > 0 ? '<span class="win-text">+' + change + '</span>' : '<span class="lose-text">' + change + '</span>';
                html += '<tr><td style="white-space:nowrap;">' + time + '</td><td>' + (log.nickname || '') + '</td><td>' + changeHtml + '</td><td>' + (log.score_before || 0) + '</td><td>' + (log.score_after || 0) + '</td><td>' + (log.reason || '') + '</td><td>' + (log.operator || '') + '</td></tr>';
            });
            tbody.innerHTML = html;
        }
        updateLogPagination(d.currentPage || page, d.totalPages || 1);
    })
    .catch(function() { if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">网络错误</td></tr>'; });
}
function updateLogPagination(currentPage, totalPages) {
    var container = document.getElementById('logPagination');
    if (!container) return;
    if (totalPages <= 1) { container.innerHTML = ''; return; }
    var html = '';
    var start = Math.max(1, currentPage - 2);
    var end = Math.min(totalPages, currentPage + 2);
    if (start > 1) {
        html += '<a href="javascript:void(0)" onclick="loadLogsPage(1)" class="pagi-link">1</a>';
        if (start > 2) html += '<span style="padding:6px 8px;color:#999;">...</span>';
    }
    for (var i = start; i <= end; i++) {
        html += '<a href="javascript:void(0)" onclick="loadLogsPage(' + i + ')" class="pagi-link' + (i == currentPage ? ' active' : '') + '">' + i + '</a>';
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += '<span style="padding:6px 8px;color:#999;">...</span>';
        html += '<a href="javascript:void(0)" onclick="loadLogsPage(' + totalPages + ')" class="pagi-link">' + totalPages + '</a>';
    }
    container.innerHTML = html;
}

function loadShopItemsDropdown() {
    var sel = document.getElementById('backpackGiveItem');
    fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=ddz&ddz_action=get_shop_items', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            sel.innerHTML = '<option value="">-- 请选择道具 --</option>';
            if (data.code === 0 && data.data && data.data.items) {
                data.data.items.forEach(function(item) {
                    var opt = document.createElement('option');
                    opt.value = item.id;
                    opt.textContent = item.icon + ' ' + item.name + ' (' + item.item_type + ')';
                    sel.appendChild(opt);
                });
            }
        })
        .catch(function() {
            sel.innerHTML = '<option value="">加载失败</option>';
        });
}

function loadUserBackpack(uid) {
    var container = document.getElementById('backpackItemsList');
    container.innerHTML = '<div style="text-align:center;color:#ccc;padding:20px;">加载中...</div>';

    fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=ddz&ddz_action=admin_get_inventory&uid=' + uid, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 0 && data.data && data.data.items && data.data.items.length > 0) {
                var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;">';
                var typeIcons = {
                    'title_colored': '🎨', 'title_effect': '✨', 'card_back': '🃏',
                    'emoticon': '😎', 'bomb_effect': '💥', 'score_buff': '⚡', 'title_badge': '👑'
                };
                data.data.items.forEach(function(item) {
                    var icon = typeIcons[item.item_type] || '🎁';
                    var remaining = Math.max(0, item.quantity - item.used);
                    var statusText = item.is_active ? '<span style="color:#2ecc71;font-weight:600;font-size:11px;">✅ 已激活</span>' : '';
                    html += '<div style="background:#fff;border:1px solid #eef0f5;border-radius:10px;padding:12px;display:flex;gap:10px;align-items:center;">' +
                        '<span style="font-size:24px;">' + icon + '</span>' +
                        '<div style="flex:1;min-width:0;">' +
                            '<div style="font-size:13px;font-weight:600;color:#333;">' + item.name + ' ' + statusText + '</div>' +
                            '<div style="font-size:11px;color:#999;">拥有: ' + item.quantity + ' | 已用: ' + item.used + ' | 剩余: ' + remaining + '</div>' +
                        '</div>' +
                        '<div style="display:flex;gap:4px;flex-direction:column;">' +
                            '<button type="button" class="wx-btn wx-btn-sm" style="font-size:10px;padding:3px 8px;background:linear-gradient(135deg,#2ecc71,#27ae60);" onclick="adminRemoveItem(' + uid + ',' + item.item_id + ',\'' + item.name + '\')">扣1</button>' +
                        '</div>' +
                    '</div>';
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="text-align:center;color:#ccc;padding:30px;"><span style="font-size:40px;display:block;margin-bottom:10px;">🎒</span>背包是空的</div>';
            }
        })
        .catch(function() {
            container.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:20px;">加载失败</div>';
        });
}

function adminGiveItem() {
    var itemId = document.getElementById('backpackGiveItem').value;
    var qty = parseInt(document.getElementById('backpackGiveQty').value) || 1;
    if (!itemId) { alert('请选择要发放的道具'); return; }
    if (qty < 1) { alert('数量至少为1'); return; }

    var formData = new FormData();
    formData.append('uid', _backpackUid);
    formData.append('item_id', itemId);
    formData.append('quantity', qty);

    fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=ddz&ddz_action=admin_give_item', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.code === 0) {
            alert('✅ 发放成功');
            loadUserBackpack(_backpackUid);
        } else {
            alert('❌ ' + (data.msg || '发放失败'));
        }
    })
    .catch(function() {
        alert('❌ 请求失败');
    });
}

function adminRemoveItem(uid, itemId, itemName) {
    if (!confirm('确定从该玩家背包中扣除 1 个「' + itemName + '」吗？')) return;

    var formData = new FormData();
    formData.append('uid', uid);
    formData.append('item_id', itemId);
    formData.append('quantity', 1);

    fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=ddz&ddz_action=admin_remove_item', {
        method: 'POST',
        credentials: 'include',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.code === 0) {
            alert('✅ 已扣除');
            loadUserBackpack(uid);
        } else {
            alert('❌ ' + (data.msg || '扣除失败'));
        }
    })
    .catch(function() {
        alert('❌ 请求失败');
    });
}

// 编辑商品 - 填充弹窗
function editShopItem(item) {
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_name').value = item.name || '';
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('edit_icon').value = item.icon || '';
    document.getElementById('edit_effect_data').value = item.effect_data || '{}';
    document.getElementById('edit_price_emlog').value = item.price_emlog || 0;
    document.getElementById('edit_price_ddz').value = item.price_game || 0;
    document.getElementById('edit_sort_order').value = item.sort_order || 0;
    document.getElementById('edit_stock').value = item.stock || -1;
    document.getElementById('edit_max_per_user').value = item.max_per_user || 0;
    document.getElementById('edit_status').value = item.status || 1;
    document.getElementById('edit_item_type').value = item.item_type || 'title_colored';
    $('#editShopModal').modal('show');
}

// URL参数控制页签
(function() {
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    if (tab === 'admin') {
        // 切换到"积分管理"页签
        document.querySelectorAll('#settingTabs a')[2].click();
    }
    if (tab === 'shop') {
        document.querySelectorAll('#settingTabs a')[3].click();
    }
    var search = params.get('search');
    if (search) {
        // 搜索结果为空时，用JS恢复搜索框内容
        document.querySelector('input[name="search"]').value = search;
    }
})();
</script>
<?php
}
