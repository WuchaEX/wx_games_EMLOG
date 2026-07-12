<?php
/**
 * wx_games 斗牛后台设置
 * 设计风格和功能对标斗地主后台
 */
!defined('EMLOG_ROOT') && exit('access denied!');

require_once __DIR__ . '/wx_games_niuniu_fn.php';

$config = wx_niuniu_get_config();
$db = Database::getInstance();
$plugin_assets_url = WX_GAMES_URL . 'games/ddz/assets/';  // 复用斗地主AI头像

// ========== 基本设置保存（含公告）==========
if (isset($_POST['niuniu_action']) && $_POST['niuniu_action'] === 'save_setting') {
    $storage = Storage::getInstance('wx_niuniu');
    $config = wx_niuniu_get_config();  // 读取已有配置
    // 只覆盖 POST 中实际提交的字段（防止不同表单互相覆盖）
    if (isset($_POST['title'])) {
        $config['title'] = addslashes(trim(Input::postStrVar('title', $config['title'])));
    }
    if (isset($_POST['guest_play'])) {
        $config['guest_play'] = $_POST['guest_play'] === '1' ? '1' : '0';
    }
    if (isset($_POST['base_bet'])) {
        $config['base_bet'] = max(10, intval(Input::postStrVar('base_bet', $config['base_bet'])));
    }
    if (isset($_POST['max_entries'])) {
        $config['max_entries'] = max(5, min(500, intval(Input::postStrVar('max_entries', $config['max_entries']))));
    }
    if (isset($_POST['recharge_link'])) {
        $config['recharge_link'] = addslashes(trim(Input::postStrVar('recharge_link', '')));
    }
    if (isset($_POST['penalty_multiplier'])) {
        $config['penalty_multiplier'] = max(0.1, min(10, floatval(str_replace(',', '.', Input::postStrVar('penalty_multiplier', '1.0')))));
    }
    if (isset($_POST['notice'])) {
        $config['notice'] = addslashes(trim(Input::postStrVar('notice', $config['notice'])));
    }
    if (isset($_POST['recent_updates'])) {
        $config['recent_updates'] = addslashes(trim(Input::postStrVar('recent_updates', $config['recent_updates'])));
    }
    $storage->setValue('config', $config, 'array');
    // 数据清理
    if (isset($_POST['do_reset'])) {
        if (isset($_POST['reset_scores'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'niuniu' AND `is_ai` = 0");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs`");
        }
        if (isset($_POST['reset_games'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_niuniu_games`");
        }
        if (isset($_POST['reset_items'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'niuniu'");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `game` = 'niuniu'");
        }
    }
    emMsg('设置已保存', './plugin.php?plugin=wx_games&game=niuniu');
}

// ========== AI设置保存（DDZ风格，同斗地主方式）==========
if (isset($_POST['niuniu_action']) && $_POST['niuniu_action'] === 'save_ai') {
    $storage = Storage::getInstance('wx_niuniu');
    $ai_count = max(2, min(10, Input::postIntVar('ai_count', 6)));
    $ai_players = [];
    $ai_names_arr = [];
    $avatar_files = ['boram.jpg','qri.jpg','soyeon.jpg','eunjung.jpg','hyomin.jpg','jiyeon.jpg'];
    $quote_keys = ['wu_xiao_niu','zha_dan','jin_niu','yin_niu','niu_niu','niu_9','niu_8','niu_7','niu_6','niu_1','no_niu','win','lose','draw'];
    for ($i = 0; $i < $ai_count; $i++) {
        // 注意：必须用 $_POST 直接读取数组键名，Input::postStrVar 不支持数组样式
        $name = isset($_POST['ai_name'][$i]) ? addslashes(trim($_POST['ai_name'][$i])) : 'AI牛人' . ($i + 1);
        if (empty($name)) $name = 'AI牛人' . ($i + 1);
        $avatar = isset($_POST['ai_avatar'][$i]) ? addslashes(trim($_POST['ai_avatar'][$i])) : $avatar_files[$i % 6];
        $ai_names_arr[] = $name;
        $quotes = [];
        foreach ($quote_keys as $qk) {
            $qt_key = 'ai_quotes_' . $qk . '_' . $i;
            $raw = isset($_POST[$qt_key]) ? trim($_POST[$qt_key]) : '';
            if (!empty($raw)) {
                $lines = explode("\n", str_replace("\r\n", "\n", $raw));
                $quotes[$qk] = [];
                foreach ($lines as $line) {
                    $line = addslashes(trim($line));
                    if (!empty($line)) $quotes[$qk][] = $line;
                }
            } else {
                $quotes[$qk] = [];
            }
        }
        $ai_players[] = ['name' => $name, 'avatar' => $avatar, 'quotes' => $quotes];
    }
    $config['ai_names'] = implode(',', $ai_names_arr);
    $storage->setValue('config', $config, 'array');
    $storage->setValue('ai_players', $ai_players, 'array');
    emMsg('AI设置已保存', './plugin.php?plugin=wx_games&game=niuniu');
}

// ========== 清空积分数据 ==========
// ========== 数据清理（复选框分项）==========
if (Input::postStrVar('niuniu_action') === 'reset_data') {
    $actions = [];
    if (isset($_POST['reset_scores'])) $actions[] = '积分';
    if (isset($_POST['reset_games']))  $actions[] = '战绩';
    if (isset($_POST['reset_items']))  $actions[] = '道具';
    if (!empty($actions)) {
        if (isset($_POST['reset_scores'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'niuniu' AND `is_ai` = 0");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs`");
        }
        if (isset($_POST['reset_games'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_niuniu_games`");
        }
        if (isset($_POST['reset_items'])) {
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'niuniu'");
            $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `game` = 'niuniu'");
        }
        emMsg('已清理：' . implode('、', $actions), './plugin.php?plugin=wx_games&game=niuniu');
    } else {
        emMsg('请至少勾选一项', './plugin.php?plugin=wx_games&game=niuniu');
    }
}

// ========== 积分管理 ==========
$search = addslashes(trim(Input::getStrVar('search', '')));
$page = max(1, Input::getIntVar('page', 1));
$pageSize = 10;
$table_scores = DB_PREFIX . 'wx_games_scores';
$table_logs = DB_PREFIX . 'wx_games_logs';
$total_users_count = 0;
$users = [];

if (isset($_POST['niuniu_action']) && $_POST['niuniu_action'] === 'change_score') {
    $admin_uid = Input::postIntVar('uid', 0);
    if ($admin_uid <= 0) emMsg('用户ID无效', './plugin.php?plugin=wx_games&game=niuniu');
    $score_change = Input::postIntVar('score_change', 0);
    $reason = addslashes(trim(Input::postStrVar('reason', '管理员手动调整')));
    if ($score_change !== 0) {
        $operator_nick = '';
        if (function_exists('LoginAuth') && LoginAuth::isLogin()) {
            $u = LoginAuth::getUserData();
            $operator_nick = isset($u['nickname']) ? $u['nickname'] : 'admin';
        }
        wx_niuniu_update_score($admin_uid, $score_change, 0);
        wx_niuniu_add_log($admin_uid, $score_change, $reason, $operator_nick);
        emMsg('积分修改成功', './plugin.php?plugin=wx_games&game=niuniu');
    } else {
        emMsg('积分变动不能为0', './plugin.php?plugin=wx_games&game=niuniu');
    }
}

// ========== 日志分页 AJAX ==========
if (Input::getStrVar('niuniu_action') === 'get_logs_page') {
    $log_page = max(1, Input::getIntVar('log_page', 1));
    $log_search = addslashes(trim(Input::getStrVar('search', '')));
    $logPageSize = 10;
    $log_offset = ($log_page - 1) * $logPageSize;
    $table_logs = DB_PREFIX . 'wx_games_logs';
    $log_where = "WHERE l.`game` = 'niuniu'";
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
    echo json_encode(['code' => 0, 'data' => $data, 'totalPages' => $totalPages, 'currentPage' => $log_page], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 积分管理：AJAX操作 ==========
if (Input::postStrVar('niuniu_action') === 'delete_user') {
    $del_uid = Input::postIntVar('uid', 0);
    if ($del_uid > 0) {
        $table_scores = DB_PREFIX . 'wx_games_scores';
        $table_games = DB_PREFIX . 'wx_niuniu_games';
        $table_logs = DB_PREFIX . 'wx_games_logs';
        $db->query("DELETE FROM `$table_scores` WHERE `uid` = $del_uid AND `is_ai` = 0");
        $db->query("DELETE FROM `$table_games` WHERE `uid` = $del_uid");
        $db->query("DELETE FROM `$table_logs` WHERE `uid` = $del_uid");
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 用户列表分页 AJAX ==========
if (Input::getStrVar('niuniu_action') === 'get_users_page') {
    $page = max(1, Input::getIntVar('page', 1));
    $search = addslashes(trim(Input::getStrVar('search', '')));
    $pageSize = 10;
    $offset = ($page - 1) * $pageSize;
    $db = Database::getInstance();
    $table_scores = DB_PREFIX . 'wx_games_scores';
    $where = "WHERE `game` = 'niuniu' AND `is_ai` = 0";
    if ($search) {
        $where = "WHERE (`nickname` LIKE '%$search%' OR `uid` = '$search') AND `game` = 'niuniu' AND `is_ai` = 0";
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

try {
    $where = '';
    $params = [];
    if (!empty($search)) {
        $search_esc = $db->escape_string($search);
        $where = "WHERE (`nickname` LIKE '%$search_esc%' OR `uid` = '" . intval($search) . "') AND `game` = 'niuniu' AND `is_ai` = 0";
    } else {
        $where = "WHERE `game` = 'niuniu' AND `is_ai` = 0";
    }
    $countRow = $db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `" . $table_scores . "` $where");
    $total_users_count = intval($countRow['cnt'] ?? 0);
    $totalPages = max(1, ceil($total_users_count / $pageSize));
    $offset = ($page - 1) * $pageSize;
    $rows = $db->query("SELECT * FROM `" . $table_scores . "` $where ORDER BY `score` DESC LIMIT $offset, $pageSize");
    while ($row = $db->fetch_array($rows)) {
        $uid = intval($row['uid']);
        $uinfo = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
        $row['_nickname'] = $uinfo ? $uinfo['nickname'] : $row['nickname'];
        $row['_avatar'] = wx_niuniu_resolve_avatar($uid, $uinfo ? $uinfo['photo'] : null);
        $users[] = $row;
    }
} catch (\Throwable $e) {}

// 日志（分页，每页10条）
$logPage = max(1, Input::getIntVar('log_page', 1));
$logPageSize = 10;
$logOffset = ($logPage - 1) * $logPageSize;
try {
    $logCountRow = $db->once_fetch_array("SELECT COUNT(*) as total FROM `$table_logs` WHERE `game` = 'niuniu'");
    $total_log_count = (int)($logCountRow['total'] ?? 0);
} catch (\Throwable $e) { $total_log_count = 0; }
$logTotalPages = max(1, ceil($total_log_count / $logPageSize));
$logs = [];
try {
    $logs_result = $db->query("SELECT l.*, IFNULL(u.nickname, '未知') AS nickname FROM `$table_logs` l LEFT JOIN `" . DB_PREFIX . "user` u ON l.uid = u.uid WHERE `game` = 'niuniu' ORDER BY l.`created_at` DESC LIMIT $logOffset, $logPageSize");
    while ($row = $db->fetch_array($logs_result)) {
        $row['created_at'] = isset($row['created_at']) ? (int)$row['created_at'] : 0;
        $logs[] = $row;
    }
} catch (\Throwable $e) {}

// ========== 商城管理 ==========
$shopTable = DB_PREFIX . 'wx_games_shop_items';
$shop_items = [];
$item_types = ['card_back' => '牌背皮肤', 'nickname_color' => '昵称颜色', 'title' => '称号', 'effect' => '特效', 'buff' => '积分加成'];
$item_type_icons = ['card_back' => '🃏', 'nickname_color' => '🎨', 'title' => '🏅', 'effect' => '✨', 'buff' => '⚡'];

if (isset($_POST['niuniu_action']) && $_POST['niuniu_action'] === 'save_shop_item') {
    $item_id = Input::postIntVar('item_id', 0);
    $data = [
        'game'         => "'niuniu'",
        'name'         => "'" . addslashes(trim(Input::postStrVar('name', ''))) . "'",
        'description'  => "'" . addslashes(trim(Input::postStrVar('description', ''))) . "'",
        'icon'         => "'" . addslashes(trim(Input::postStrVar('icon', ''))) . "'",
        'item_type'    => "'" . addslashes(trim(Input::postStrVar('item_type', ''))) . "'",
        'effect_data'  => "'" . addslashes(Input::postStrVar('effect_data', '')) . "'",
        'price_emlog'  => intval(Input::postStrVar('price_emlog', 0)),
        'price_game' => intval(Input::postStrVar('price_niuniu', 0)),
        'stock'        => intval(Input::postStrVar('stock', -1)),
        'max_per_user' => intval(Input::postStrVar('max_per_user', 0)),
        'sort_order'   => intval(Input::postStrVar('sort_order', 0)),
        'status'       => intval(Input::postStrVar('status', 1)),
    ];
    $sets = [];
    foreach ($data as $k => $v) {
        $sets[] = is_int($v) ? "`$k` = $v" : "`$k` = $v";
    }
    $setStr = implode(', ', $sets);
    if ($item_id > 0) {
        $db->query("UPDATE `" . $shopTable . "` SET $setStr WHERE `id` = $item_id");
    } else {
        $db->query("INSERT INTO `" . $shopTable . "` SET $setStr, `created_at` = " . time());
    }
    emMsg('商品已保存', './plugin.php?plugin=wx_games&game=niuniu');
}

if (isset($_GET['niuniu_action']) && $_GET['niuniu_action'] === 'del_shop_item') {
    $item_id = intval($_GET['item_id'] ?? 0);
    if ($item_id > 0) $db->query("DELETE FROM `" . $shopTable . "` WHERE `id` = $item_id");
    emMsg('商品已删除', './plugin.php?plugin=wx_games&game=niuniu');
}

$editItem = null;
if (isset($_GET['edit_shop'])) {
    $eid = intval($_GET['edit_shop']);
    $editItem = $db->once_fetch_array("SELECT * FROM `" . $shopTable . "` WHERE `id` = $eid LIMIT 1");
}

// 读取商城商品数据（支持按类型筛选）
$shop_items = [];
$filter_type = addslashes(trim(Input::getStrVar('filter_type', '')));
$shop_items_res = $db->query("SELECT * FROM `" . $shopTable . "`" . ($filter_type ? " WHERE `game` = 'niuniu' AND `item_type` = '$filter_type'" : " WHERE `game` = 'niuniu'") . " ORDER BY `sort_order` ASC, `id` ASC");
while ($it = $db->fetch_array($shop_items_res)) {
    $shop_items[] = $it;
}

// ========== 背包管理数据 ==========
$invTable = DB_PREFIX . 'wx_games_user_items';
$inventory_stats = [];
$purchase_history = [];
$buy_page = max(1, Input::getIntVar('buy_page', 1));
$buy_total_pages = 1;
$stat_page = max(1, Input::getIntVar('stat_page', 1));
$stat_total_pages = 1;
$pageSize2 = 15;

try {
    $buyCount = $db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `" . $invTable . "` WHERE `game` = 'niuniu'");
    $buy_total_pages = max(1, ceil(intval($buyCount['cnt'] ?? 0) / $pageSize2));
    $buy_offset = ($buy_page - 1) * $pageSize2;
    $buyRows = $db->query("SELECT ui.*, si.name AS item_name, si.item_type, si.icon FROM `" . $invTable . "` ui LEFT JOIN `" . $shopTable . "` si ON ui.item_id = si.id WHERE ui.`game` = 'niuniu' ORDER BY ui.purchased_at DESC LIMIT $buy_offset, $pageSize2");
    while ($br = $db->fetch_array($buyRows)) {
        $purchase_history[] = $br;
    }
} catch (\Throwable $e) {}

try {
    $statCount = $db->once_fetch_array("SELECT COUNT(*) AS cnt, SUM(quantity) AS total_qty, SUM(used) AS total_used FROM `" . $invTable . "` WHERE `game` = 'niuniu'");
    $stat_total_pages = 1;
    $statRows = $db->query("SELECT si.name, si.item_type, COUNT(*) AS buy_count, SUM(ui.quantity) AS total_qty, SUM(ui.used) AS total_used FROM `" . $invTable . "` ui LEFT JOIN `" . $shopTable . "` si ON ui.item_id = si.id WHERE ui.`game` = 'niuniu' GROUP BY ui.item_id ORDER BY buy_count DESC");
    while ($sr = $db->fetch_array($statRows)) {
        $inventory_stats[] = $sr;
    }
} catch (\Throwable $e) {}

// ========== 渲染函数 ==========
function wx_niuniu_admin_render() {
    global $config, $db, $plugin_assets_url;
    global $users, $logs, $logPage, $logTotalPages, $total_log_count, $search, $page, $totalPages, $total_users_count, $table_scores;
    global $shop_items, $editItem, $item_types, $item_type_icons, $filter_type;
    global $inventory_stats, $purchase_history, $stat_page, $stat_total_pages, $buy_page, $buy_total_pages, $pageSize;

    $ai_players = wx_niuniu_get_ai_players();
    $ai_count = count($ai_players);
?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">🐂 H5 斗牛 - 插件设置</h1>
    </div>

    <ul class="nav nav-tabs mb-4" id="settingTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">基本设置</a></li>
        <li class="nav-item"><a class="nav-link" id="ai-tab" data-toggle="tab" href="#ai" role="tab">AI玩家设置</a></li>
        <li class="nav-item"><a class="nav-link" id="score-tab" data-toggle="tab" href="#score" role="tab">积分管理</a></li>
        <li class="nav-item"><a class="nav-link" id="shop-tab" data-toggle="tab" href="#shop" role="tab">商城管理</a></li>
    </ul>

    <div class="tab-content" id="settingTabsContent">

        <!-- ========== 基本设置 ========== -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <form method="post" action="./plugin.php?plugin=wx_games&game=niuniu">
                <input type="hidden" name="niuniu_action" value="save_setting">
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
                                                <option value="0" <?php echo $config['guest_play'] == '0' ? 'selected' : ''; ?>>关闭（仅登录可玩）</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>底分</label>
                                            <input class="form-control" name="base_bet" type="number" value="<?php echo intval($config['base_bet']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>排行榜最大条目数</label>
                                            <input type="number" class="form-control" name="max_entries" value="<?php echo (int)($config['max_entries'] ?? 50); ?>" min="10" max="500">
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
                                        <input type="number" class="form-control" name="penalty_multiplier" value="<?php echo number_format($config['penalty_multiplier'] ?? 1.0, 1, '.', ''); ?>" min="0.1" max="10" step="0.1" style="max-width:180px">
                                        <span class="input-group-text" style="border-radius:0 8px 8px 0;background:#f8f9fe;border:1px solid #e0e2ea;border-left:none;padding:10px 14px;">x</span>
                                        <span style="margin-left:12px;align-self:center;font-size:13px;color:#888">惩罚 = 底分 × 游戏倍率 × 此倍率</span>
                                    </div>
                                </div>
                                <div style="font-size:13px;color:#888;margin-bottom:8px">
                                    <strong>当前：</strong>逃跑扣 <strong style="color:#e17055"><?php echo (int)($config['base_bet'] ?? 100) * ($config['penalty_multiplier'] ?? 1.0); ?>×游戏倍率</strong> 分
                                </div>
                            <hr>
                            <!-- 数据管理 -->
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                                    <span style="font-size:14px;font-weight:600">🗃️ 数据管理</span>
                                    <span style="color:#aaa;font-size:13px">玩家记录数：
                                        <?php
                                        try {
                                            $nn_cr = $db->query("SELECT COUNT(*) as total FROM `$table_scores` WHERE `game` = 'niuniu' AND `is_ai` = 0");
                                            $nn_crow = $db->fetch_array($nn_cr);
                                            echo '<strong>' . (int)$nn_crow['total'] . '</strong>';
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
                                    <textarea class="form-control" name="recent_updates" rows="6" style="width:100%;resize:vertical;"><?php echo htmlspecialchars($config['recent_updates'] ?? ''); ?></textarea>
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

        <!-- ========== AI玩家设置（DDZ风格网格）========== -->
        <div class="tab-pane fade" id="ai" role="tabpanel">
            <div class="wx-card card-dark">
                <div class="card-header">AI玩家设置</div>
                <div class="card-body">
                    <form method="post" action="./plugin.php?plugin=wx_games&game=niuniu" id="aiForm">
                        <input type="hidden" name="niuniu_action" value="save_ai">
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
                            $quote_types = ['wu_xiao_niu'=>'五小牛', 'zha_dan'=>'💣 炸弹', 'jin_niu'=>'👑 金牛', 'yin_niu'=>'🥈 银牛', 'niu_niu'=>'🐂 牛牛', 'niu_9'=>'9️⃣ 牛9', 'niu_8'=>'8️⃣ 牛8', 'niu_7'=>'7️⃣ 牛7', 'niu_6'=>'6️⃣ 牛6', 'niu_1'=>'1️⃣ 小牛', 'no_niu'=>'❌ 无牛', 'win'=>'🎉 胜利', 'lose'=>'😢 失败', 'draw'=>'🤝 平局'];
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
                                <div style="height:4px;background:linear-gradient(90deg,<?php echo $color; ?>,<?php echo $color; ?>88);"></div>
                                <div style="padding:16px 18px;">
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
                                            <div style="font-size:10px;color:#999;">/ <?php echo count($quote_types); ?> 类台词</div>
                                        </div>
                                    </div>
                                    <div style="height:4px;background:#eef0f5;border-radius:2px;margin-bottom:14px;overflow:hidden;">
                                        <div style="height:100%;width:<?php echo $filled*(100/count($quote_types)); ?>%;background:linear-gradient(90deg,<?php echo $color; ?>,<?php echo $color; ?>88);border-radius:2px;transition:width 0.3s;"></div>
                                    </div>
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
        <div class="tab-pane fade" id="score" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <!-- 积分查询与修改 -->
                    <div class="wx-card card-dark mb-4">
                        <div class="card-header">积分查询与修改</div>
                        <div class="card-body">
                            <form method="post" class="mb-3" style="max-width:400px">
                                <input type="hidden" name="niuniu_action" value="change_score">
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
                        <div class="card-header">积分变动日志（共 <?php echo $total_log_count; ?> 条）</div>
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
                                            <td style="white-space:nowrap;"><?php echo $log['created_at'] ? date('Y-m-d H:i', $log['created_at']) : $log['created']; ?></td>
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
                                        <tr><td colspan="7" class="wx-empty">暂无日志</td></tr>
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
                            <input type="hidden" name="game" value="niuniu">
                            <input type="hidden" name="tab" value="score">
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
                                <?php foreach ($users as $index => $u): ?>
                                <tr>
                                    <td><?php echo ($page - 1) * $pageSize + $index + 1; ?></td>
                                    <td><?php echo $u['uid']; ?></td>
                                    <td>
                                        <?php if ($u['_avatar']): ?>
                                        <img src="<?php echo $u['_avatar']; ?>" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:4px;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($u['_nickname']); ?>
                                    </td>
                                    <td><span class="badge-score"><?php echo intval($u['score']); ?></span></td>
                                    <td><?php echo intval($u['total_games']); ?></td>
                                    <td>
                                        <span class="win-text"><?php echo intval($u['wins']); ?>胜</span> /
                                        <span class="lose-text"><?php echo intval($u['losses']); ?>负</span> /
                                        <span style="color:#999;"><?php echo intval($u['draws']); ?>平</span>
                                    </td>
                                    <td><?php echo intval($u['best_score'] ?? $u['score']); ?></td>
                                    <td>
                                        <button type="button" class="wx-btn wx-btn-sm btn-change-score" data-uid="<?php echo $u['uid']; ?>" data-score="<?php echo intval($u['score']); ?>" data-nick="<?php echo htmlspecialchars($u['_nickname'] ?? $u['nickname'], ENT_QUOTES); ?>">修改积分</button>
                                        <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);margin-left:4px;" onclick="showUserLog(<?php echo $u['uid']; ?>, '<?php echo htmlspecialchars($u['_nickname'], ENT_QUOTES); ?>')">流水</button>
                                        <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" style="margin-left:4px;" onclick="deleteUser(<?php echo $u['uid']; ?>)">删除</button>
                                        <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb);margin-left:4px;" onclick="openBackpack(<?php echo $u['uid']; ?>, '<?php echo htmlspecialchars($u['_nickname'], ENT_QUOTES); ?>')">背包</button>
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

<!-- 修改积分弹窗（动态单例） -->
<div class="modal fade" id="scoreModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:14px 14px 0 0;border:none;">
                <h5 class="modal-title" style="font-size:16px;" id="scoreModalTitle">修改积分</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
            </div>
            <form method="post" action="./plugin.php?plugin=wx_games&game=niuniu">
                <input type="hidden" name="niuniu_action" value="change_score">
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

    <!-- 用户流水弹窗 -->
    <div class="modal fade" id="userLogModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;border-radius:14px 14px 0 0;border:none;">
                    <h5 class="modal-title" id="userLogModalTitle" style="font-size:16px;">用户积分流水</h5>
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

    <!-- 玩家背包管理弹窗 -->
    <div class="modal fade" id="backpackModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb);color:#fff;border-radius:14px 14px 0 0;border:none;">
                    <h5 class="modal-title" id="backpackModalTitle" style="font-size:16px;">玩家背包管理</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
                </div>
                <div class="modal-body" style="padding:20px;max-height:500px;overflow-y:auto;">
                    <!-- 发放道具表单 -->
                    <div class="wx-card card-dark" style="margin-bottom:16px;">
                        <div class="card-body" style="padding:12px 16px;">
                            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                                <div style="flex:2;min-width:160px;">
                                    <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">选择商品</label>
                                    <select id="bp_add_item_id" class="form-control">
                                        <option value="">-- 请选择 --</option>
                                        <?php
                                        $all_items_q = $db->query("SELECT `id`, `name`, `icon`, `item_type` FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'niuniu' ORDER BY id DESC");
                                        while ($ait = $db->fetch_array($all_items_q)) {
                                            $type_label = $item_types[$ait['item_type']] ?? $ait['item_type'];
                                            echo '<option value="' . $ait['id'] . '">' . $ait['icon'] . ' ' . htmlspecialchars($ait['name']) . ' (' . $type_label . ')</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div style="flex:1;min-width:80px;">
                                    <label style="font-size:12px;color:#666;display:block;margin-bottom:4px;">数量</label>
                                    <input type="number" id="bp_add_qty" class="form-control" value="1" min="1">
                                </div>
                                <div>
                                    <button class="wx-btn wx-btn-sm" onclick="addUserItem()" id="bp_add_btn" disabled>发放道具</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 玩家道具列表 -->
                    <div id="backpackItems"><div class="wx-empty">加载中...</div></div>
                </div>
            </div>
        </div>
    </div>

        <!-- ========== 商城管理 ========== -->
        <div class="tab-pane fade" id="shop" role="tabpanel">
            <!-- 商品列表 -->
            <div class="wx-card card-dark">
                <div class="card-header">商品列表</div>
                <div class="card-body" style="padding:0;">
                    <div style="padding:16px 22px;border-bottom:1px solid #f0f0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                            <span>共 <strong><?php echo count($shop_items); ?></strong> 件商品</span>
                            <select class="form-control" style="width:auto;display:inline-block;height:32px;font-size:13px;padding:2px 8px;" onchange="location.href='./plugin.php?plugin=wx_games&game=niuniu&tab=shop&filter_type='+this.value">
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
                                <tr><th>ID</th><th>名称</th><th>类型</th><th>站点积分</th><th>斗牛积分</th><th>库存</th><th>限购</th><th>排序</th><th>状态</th><th>操作</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($shop_items as $it): 
                                $type_key = $it['item_type'];
                                $type_name = $item_types[$type_key] ?? $type_key;
                                $type_icon = $item_type_icons[$type_key] ?? '🎁';
                                $type_icon_display = is_array($type_icon) ? ($type_icon['icon'] ?? '🎁') : $type_icon;
                            ?>
                                <tr>
                                    <td><?php echo $it['id']; ?></td>
                                    <td>
                                        <?php if ($it['icon']): ?><img src="<?php echo htmlspecialchars($it['icon']); ?>" style="width:20px;height:20px;vertical-align:middle;margin-right:4px;border-radius:4px;"><?php endif; ?>
                                        <?php echo htmlspecialchars($it['name']); ?>
                                    </td>
                                    <td><span class="badge-score" style="font-size:11px;"><?php echo $type_icon_display . ' ' . $type_name; ?></span></td>
                                    <td><?php echo intval($it['price_emlog']) > 0 ? intval($it['price_emlog']) : '-'; ?></td>
                                    <td><?php echo intval($it['price_game'] ?? 0) > 0 ? intval($it['price_game']) : '-'; ?></td>
                                    <td><?php echo intval($it['stock']) === -1 ? '不限' : intval($it['stock']); ?></td>
                                    <td><?php echo intval($it['max_per_user']) > 0 ? intval($it['max_per_user']) : '不限'; ?></td>
                                    <td><?php echo intval($it['sort_order']); ?></td>
                                    <td><?php echo intval($it['status']) === 1 ? '<span style="color:#2ecc71">上架</span>' : '<span style="color:#999">下架</span>'; ?></td>
                                    <td style="white-space:nowrap;">
                                        <button type="button" class="wx-btn wx-btn-sm" onclick="editShopItem(<?php echo htmlspecialchars(json_encode($it)); ?>)">编辑</button>
                                        <a href="./plugin.php?plugin=wx_games&game=niuniu&niuniu_action=del_shop_item&item_id=<?php echo $it['id']; ?>" class="wx-btn wx-btn-sm wx-btn-danger" onclick="return confirm('确定删除「<?php echo htmlspecialchars($it['name']); ?>」吗？')">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($shop_items)): ?>
                                <tr><td colspan="10" class="wx-empty">暂无商品</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row">
            <div class="col-lg-6">
            <!-- 道具消耗统计 -->
            <div class="wx-card card-dark mb-4">
                <div class="card-header">道具消耗统计</div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead><tr><th>商品</th><th>购买人数</th><th>总数量</th><th>已使用</th></tr></thead>
                            <tbody>
                            <?php foreach ($inventory_stats as $st): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($st['name']); ?></td>
                                    <td><strong><?php echo intval($st['buy_count'] ?? $st['buyer_count']); ?></strong></td>
                                    <td><?php echo intval($st['total_qty'] ?? $st['total_bought']); ?></td>
                                    <td><?php echo intval($st['total_used']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($inventory_stats)): ?>
                                <tr><td colspan="4" class="wx-empty">暂无数据</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($stat_total_pages > 1): ?>
                    <div class="pagination-admin">
                        <?php for ($i = 1; $i <= $stat_total_pages; $i++): ?>
                        <a href="./plugin.php?plugin=wx_games&game=niuniu&tab=shop&stat_page=<?php echo $i; ?>" class="<?php echo $i == $stat_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            </div>
            <div class="col-lg-6">
            <!-- 购买记录 -->
            <div class="wx-card card-dark mb-4">
                <div class="card-header">📋 购买记录</div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead><tr><th>时间</th><th>用户ID</th><th>商品</th><th>数量</th><th>已用</th></tr></thead>
                            <tbody>
                            <?php foreach ($purchase_history as $ph): ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?php echo date('Y-m-d H:i', intval($ph['purchased_at'])); ?></td>
                                    <td><?php echo intval($ph['uid']); ?></td>
                                    <td><?php echo htmlspecialchars($ph['item_name'] ?? '未知'); ?></td>
                                    <td><?php echo intval($ph['quantity']); ?></td>
                                    <td><?php echo intval($ph['used']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($purchase_history)): ?>
                                <tr><td colspan="5" class="wx-empty">暂无记录</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($buy_total_pages > 1): ?>
                    <div class="pagination-admin">
                        <?php for ($p = 1; $p <= $buy_total_pages; $p++): ?>
                        <a href="./plugin.php?plugin=wx_games&game=niuniu&tab=shop&buy_page=<?php echo $p; ?>" class="<?php echo $p == $buy_page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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
                <form method="post" action="./plugin.php?plugin=wx_games&game=niuniu">
                    <input type="hidden" name="niuniu_action" value="save_shop_item">
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
                                            $ti = isset($item_type_icons[$tk]) ? (is_array($item_type_icons[$tk]) ? $item_type_icons[$tk]['icon'] : $item_type_icons[$tk]) : '🎁';
                                        ?>
                                        <option value="<?php echo $tk; ?>" data-icon="<?php echo $ti; ?>"><?php echo $ti . ' ' . $tl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="addTypeHint" class="wx-info-block" style="margin-top:8px;font-size:12px;display:flex;align-items:center;gap:8px;">
                                        <span id="addTypeIcon">🎨</span>
                                        <span id="addTypeDesc"></span>
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
                                    <label>斗牛积分价</label>
                                    <input type="number" name="price_niuniu" class="form-control" value="0" min="0">
                                    <small class="form-text text-muted">0=不支持斗牛积分购买</small>
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
                <form method="post" action="./plugin.php?plugin=wx_games&game=niuniu">
                    <input type="hidden" name="niuniu_action" value="save_shop_item">
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
                                            $ti = isset($item_type_icons[$tk]) ? (is_array($item_type_icons[$tk]) ? $item_type_icons[$tk]['icon'] : $item_type_icons[$tk]) : '🎁';
                                        ?>
                                        <option value="<?php echo $tk; ?>" data-icon="<?php echo $ti; ?>"><?php echo $ti . ' ' . $tl; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="editTypeHint" class="wx-info-block" style="margin-top:8px;font-size:12px;display:flex;align-items:center;gap:8px;">
                                        <span id="editTypeIcon">🎨</span>
                                        <span id="editTypeDesc"></span>
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
                                    <label>斗牛积分价</label>
                                    <input type="number" name="price_niuniu" id="edit_price_niuniu" class="form-control" min="0">
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

    </div>
</div>

<script>
    // ====== 道具类型动态提示 ======
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
    setTimeout(function() { updateTypeHint('add'); updateTypeHint('edit'); }, 100);

    // 编辑商品 - 填充弹窗
    function editShopItem(item) {
        document.getElementById('edit_item_id').value = item.id;
        document.getElementById('edit_name').value = item.name || '';
        document.getElementById('edit_description').value = item.description || '';
        document.getElementById('edit_icon').value = item.icon || '';
        document.getElementById('edit_effect_data').value = item.effect_data || '{}';
        document.getElementById('edit_price_emlog').value = item.price_emlog || 0;
        document.getElementById('edit_price_niuniu').value = item.price_game || 0;
        document.getElementById('edit_sort_order').value = item.sort_order || 0;
        document.getElementById('edit_stock').value = item.stock || -1;
        document.getElementById('edit_max_per_user').value = item.max_per_user || 0;
        document.getElementById('edit_status').value = item.status || 1;
        document.getElementById('edit_item_type').value = item.item_type || 'title_colored';
        $('#editShopModal').modal('show');
    }

    // ====== 修改积分弹窗（事件委托） ======
    $(document).on('click', '.btn-change-score', function(e) {
        var btn = this;
        var uid = $(btn).attr('data-uid');
        var score = $(btn).attr('data-score');
        var nickname = $(btn).attr('data-nick');
        document.getElementById('scoreModalUid').value = uid;
        document.getElementById('scoreModalCurrent').value = score;
        var title = '修改积分';
        if (nickname) title += ' - ' + nickname;
        document.getElementById('scoreModalTitle').textContent = title;
        $('#scoreModal').modal('show');
    });

    // ====== 用户列表分页 AJAX ======
    function loadUsersPage(page) {
        var search = document.getElementById('logSearchInput') ? document.getElementById('logSearchInput').value : '';
        var tbody = document.getElementById('userTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">加载中...</td></tr>';
        fetch('?plugin=wx_games&game=niuniu&niuniu_action=get_users_page&page=' + page + '&search=' + encodeURIComponent(search))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.code !== 0) { tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">加载失败</td></tr>'; return; }
            if (!d.data || d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">暂无数据</td></tr>'; return; }
            var html = '';
            d.data.forEach(function(u, idx) {
                html += '<tr>'
                    + '<td>' + ((d.currentPage - 1) * 10 + idx + 1) + '</td>'
                    + '<td>' + u.uid + '</td>'
                    + '<td>' + (u.avatar ? '<img src="' + u.avatar + '" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:4px;">' : '') + u.nickname + '</td>'
                    + '<td><span class="badge-score">' + u.score + '</span></td>'
                    + '<td>' + u.total_games + '</td>'
                    + '<td><span class="win-text">' + u.wins + '胜</span> / <span class="lose-text">' + u.losses + '负</span> / <span style="color:#999;">' + u.draws + '平</span></td>'
                    + '<td>' + u.best_score + '</td>'
                    + '<td>'
                    + '<button type="button" class="wx-btn wx-btn-sm btn-change-score" data-uid="' + u.uid + '" data-score="' + u.score + '">修改积分</button>'
                    + '<button type="button" class="wx-btn wx-btn-sm wx-btn-danger" style="margin-left:4px;" onclick="deleteUser(' + u.uid + ')">删除</button>'
                    + '<button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);margin-left:4px;" onclick="showUserLog(' + u.uid + ')">流水</button>'
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

    // ====== 日志分页 AJAX ======
    function loadLogsPage(page) {
        var search = document.getElementById('logSearchInput') ? document.getElementById('logSearchInput').value : '';
        var tbody = document.getElementById('logTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载中...</td></tr>';
        fetch('?plugin=wx_games&game=niuniu&niuniu_action=get_logs_page&log_page=' + page + '&search=' + encodeURIComponent(search), { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.code !== 0) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载失败</td></tr>'; return; }
            if (!d.data || d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">暂无日志记录</td></tr>'; return; }
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

    // ====== 积分管理：用户积分流水 ======
    function showUserLog(uid, nickname) {
        document.getElementById('userLogModalTitle').textContent = nickname + ' 的积分流水';
        document.getElementById('userLogBody').innerHTML = '<tr><td colspan="6" class="wx-empty">加载中...</td></tr>';
        $('#userLogModal').modal('show');

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=niuniu&niuniu_action=get_user_logs&uid=' + uid, {
            credentials: 'include'
        })
        .then(r => r.json())
        .then(d => {
            var tbody = document.getElementById('userLogBody');
            if (d.code === 0 && d.data && d.data.length > 0) {
                tbody.innerHTML = '';
                d.data.forEach(function(log) {
                    var tr = document.createElement('tr');
                    var change = parseInt(log.score_change);
                    var changeHtml = change > 0
                        ? '<span class="win-text">+' + change + '</span>'
                        : '<span class="lose-text">' + change + '</span>';
                    tr.innerHTML = '<td>' + log.created_at + '</td>'
                        + '<td>' + changeHtml + '</td>'
                        + '<td>' + log.score_before + '</td>'
                        + '<td>' + log.score_after + '</td>'
                        + '<td>' + (log.reason || '') + '</td>'
                        + '<td>' + (log.operator || '') + '</td>';
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="wx-empty">暂无流水记录</td></tr>';
            }
        })
        .catch(function() {
            document.getElementById('userLogBody').innerHTML = '<tr><td colspan="6" class="wx-empty">加载失败</td></tr>';
        });
    }

    // ====== 积分管理：删除玩家 ======
    function deleteUser(uid) {
        if (!confirm('⚠️ 确定要删除该玩家的积分、游戏记录和流水吗？此操作不可撤销！')) return;
        if (!confirm('再次确认：所有数据将被永久删除！')) return;
        const formData = new FormData();
        formData.append('niuniu_action', 'delete_user');
        formData.append('uid', uid);

        fetch('?plugin=wx_games&game=niuniu', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('已删除');
                location.reload();
            } else {
                alert('删除失败: ' + (d.message || '未知错误'));
            }
        })
        .catch(function() { alert('网络错误'); });
    }

    // ====== 积分管理：背包管理 ======
    var _bpUid = 0;

    function openBackpack(uid, nickname) {
        _bpUid = uid;
        document.getElementById('backpackModalTitle').textContent = '🎒 ' + nickname + ' 的背包';
        document.getElementById('bp_add_btn').disabled = true;
        $('#backpackModal').modal('show');
        loadBackpack(uid);
    }

    function loadBackpack(uid) {
        uid = uid || _bpUid;
        if (!uid) return;
        document.getElementById('backpackItems').innerHTML = '<div class="wx-empty">加载中...</div>';

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=niuniu&niuniu_action=admin_get_inventory&uid=' + uid, {
            credentials: 'include'
        })
        .then(r => r.json())
        .then(d => {
            var container = document.getElementById('backpackItems');
            if (d.code !== 0 || !d.data || !d.data.items || d.data.items.length === 0) {
                container.innerHTML = '<div class="wx-empty">背包为空</div>';
                return;
            }
            var html = '<table class="table-admin" style="margin:0;">';
            html += '<thead><tr><th>商品</th><th>类型</th><th>数量</th><th>已用</th><th>生效</th><th>次数</th><th>到期</th><th>操作</th></tr></thead><tbody>';
            d.data.items.forEach(function(item) {
                html += '<tr>';
                html += '<td>' + (item.icon || '') + ' ' + (item.item_name || '') + '</td>';
                html += '<td>' + (item.type_label || '') + '</td>';
                html += '<td><input type="number" id="bp_qty_' + item.id + '" value="' + (item.quantity || 0) + '" class="form-control" style="width:60px;height:28px;font-size:12px;padding:2px 6px;"></td>';
                html += '<td><input type="number" id="bp_used_' + item.id + '" value="' + (item.used || 0) + '" class="form-control" style="width:60px;height:28px;font-size:12px;padding:2px 6px;"></td>';
                html += '<td><select id="bp_active_' + item.id + '" class="form-control" style="width:70px;height:28px;font-size:12px;padding:2px 4px;">';
                html += '<option value="1"' + (item.is_active ? ' selected' : '') + '>✓ 是</option>';
                html += '<option value="0"' + (!item.is_active ? ' selected' : '') + '>✗ 否</option>';
                html += '</select></td>';
                html += '<td><input type="number" id="bp_charges_' + item.id + '" value="' + (item.charges || 0) + '" class="form-control" style="width:60px;height:28px;font-size:12px;padding:2px 6px;"></td>';
                html += '<td><input type="text" id="bp_expires_' + item.id + '" value="' + (item.expires_at || '') + '" class="form-control" style="width:130px;height:28px;font-size:12px;padding:2px 6px;" placeholder="YYYY-MM-DD"></td>';
                html += '<td>';
                html += '<button class="wx-btn wx-btn-sm" onclick="updateUserItem(' + item.id + ')" style="font-size:11px;padding:2px 8px;">保存</button>';
                html += '<button class="wx-btn wx-btn-sm wx-btn-danger" onclick="deleteUserItem(' + item.id + ',\'' + (item.item_name || '').replace(/'/g, "\\'") + '\')" style="font-size:11px;padding:2px 8px;margin-left:2px;">删除</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('backpackItems').innerHTML = '<div class="wx-empty">加载失败</div>';
        });
    }

    function addUserItem() {
        var itemId = parseInt(document.getElementById('bp_add_item_id').value);
        var qty = parseInt(document.getElementById('bp_add_qty').value);
        if (!itemId || !qty) { alert('请选择商品'); return; }
        const formData = new FormData();
        formData.append('uid', _bpUid);
        formData.append('item_id', itemId);
        formData.append('quantity', qty);

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=niuniu&niuniu_action=admin_add_item', {
            method: 'POST',
            credentials: 'include',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('发放成功');
                loadBackpack(_bpUid);
            } else {
                alert('发放失败: ' + (d.message || ''));
            }
        });
    }

    function updateUserItem(invId) {
        var qty = parseInt(document.getElementById('bp_qty_' + invId).value);
        var used = parseInt(document.getElementById('bp_used_' + invId).value);
        var active = parseInt(document.getElementById('bp_active_' + invId).value);
        var charges = parseInt(document.getElementById('bp_charges_' + invId).value);
        var expires = document.getElementById('bp_expires_' + invId).value.trim();
        if (isNaN(qty) || qty < 0) { alert('数量无效'); return; }
        const formData = new FormData();
        formData.append('inv_id', invId);
        formData.append('quantity', qty);
        formData.append('used', used);
        formData.append('is_active', active);
        formData.append('charges', charges);
        formData.append('expires_at', expires);

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=niuniu&niuniu_action=admin_update_item', {
            method: 'POST',
            credentials: 'include',
            body: formData
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('更新成功');
                loadBackpack(_bpUid);
            } else {
                alert('更新失败: ' + d.message);
            }
        });
    }

    function deleteUserItem(invId, name) {
        if (!confirm('确定要从背包中删除「' + name + '」吗？')) return;
        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=niuniu&niuniu_action=admin_delete_item&inv_id=' + invId, {
            credentials: 'include'
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('删除成功');
                loadBackpack(_bpUid);
            } else {
                alert('删除失败: ' + d.message);
            }
        });
    }

    // 背包：选择商品后启用发放按钮
    document.getElementById('bp_add_item_id') && document.getElementById('bp_add_item_id').addEventListener('change', function() {
        document.getElementById('bp_add_btn').disabled = !this.value;
    });
// AI台词标签点击切换
document.querySelectorAll('.quote-tag').forEach(function(tag) {
    tag.addEventListener('click', function(e) {
        e.preventDefault();
        var panelId = this.dataset.target;
        var panels = this.closest('.ai-player-row').querySelector('.quote-panels');
        panels.style.display = 'block';
        panels.querySelectorAll('.quote-panel').forEach(function(p) { p.style.display = 'none'; });
        var target = document.getElementById(panelId);
        if (target) target.style.display = 'block';
        this.closest('.quote-tags').querySelectorAll('.quote-tag').forEach(function(t) { t.classList.remove('active'); });
        this.classList.add('active');
    });
});
document.querySelectorAll('.quote-close-all').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        this.closest('.quote-panels').style.display = 'none';
        this.closest('.ai-player-row').querySelectorAll('.quote-tag').forEach(function(t) { t.classList.remove('active'); });
    });
});
</script>
<?php
}
