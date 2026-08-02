<?php
/**
 * wx_mojang 后台设置页
 * 麻将玩法插件 - 后台管理界面
 */
!defined('EMLOG_ROOT') && exit('access denied!');

require_once __DIR__ . '/wx_games_mojang_fn.php';
require_once __DIR__ . '/wx_games_admin_helper.php';

$db = Database::getInstance();

// ============================================================
// 道具类型定义（与 wx_ddz 完全一致）
// ============================================================
$item_types = [
    'title_colored' => '昵称变色',
    'title_effect'  => '昵称特效',
    'title_badge'   => '称号徽章',
    'emoticon'      => '专属表情',
    'win_effect'    => '获胜效果',
    'score_buff'    => '积分加成卡',
];

$item_type_icons = [
    'title_colored' => ['icon' => '🎨', 'hint' => '昵称显示为彩色，如：{"color":"#ff4500"}'],
    'title_effect'  => ['icon' => '✨', 'hint' => '昵称带光晕特效，如：{"effect":"glow","color":"gold"}'],
    'score_buff'    => ['icon' => '⚡', 'hint' => '下N局积分加成，如：{"multiplier":2,"games":5}'],
    'title_badge'   => ['icon' => '👑', 'hint' => '名称旁显示称号，如：{"badge":"麻将大师"}'],
];

// ============================================================
// 处理POST请求
// ============================================================
$action = Input::postStrVar('mj_action', '');
$tab = Input::getStrVar('tab', 'basic');

if ($action === 'save_setting') {
    $storage = Storage::getInstance('wx_mojang');
    // 读取现有配置，只覆盖提交的字段（防止单个表单覆盖其他设置）
    $config = wx_mojang_get_config();
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
    if (isset($_POST['base_score'])) {
        $config['base_score'] = Input::postIntVar('base_score', 100);
    }
    if (isset($_POST['min_fan_to_win'])) {
        $config['min_fan_to_win'] = Input::postIntVar('min_fan_to_win', 8);
    }
    if (isset($_POST['notice'])) {
        $config['notice'] = addslashes(trim(Input::postStrVar('notice', '')));
    }
    if (isset($_POST['recent_updates'])) {
        $config['recent_updates'] = addslashes(trim(Input::postStrVar('recent_updates', '')));
    }
    if (isset($_POST['recharge_link'])) {
        $config['recharge_link'] = addslashes(trim(Input::postStrVar('recharge_link', '')));
    }
    $storage->setValue('config', $config, 'array');
    // 数据清理
    if (isset($_POST['do_reset'])) {
        $db_rst = Database::getInstance();
        if (isset($_POST['reset_scores'])) {
            $db_rst->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'mj' AND `is_ai` = 0");
            $db_rst->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs`");
        }
        if (isset($_POST['reset_games'])) {
            $db_rst->query("DELETE FROM `" . DB_PREFIX . "wx_mojang_games`");
        }
        if (isset($_POST['reset_items'])) {
            $db_rst->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'mj'");
            $db_rst->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `game` = 'mj'");
        }
    }
    emMsg('设置已保存', './plugin.php?plugin=wx_games&game=mj&tab=basic&saved=1');
} elseif ($action === 'save_content') {
    $storage = Storage::getInstance('wx_mojang');
    $config = wx_mojang_get_config();
    $config['notice'] = addslashes(trim(Input::postStrVar('notice', $config['notice'] ?? '')));
    $config['recent_updates'] = addslashes(trim(Input::postStrVar('recent_updates', $config['recent_updates'] ?? '')));
    $config['recharge_link'] = addslashes(trim(Input::postStrVar('recharge_link', $config['recharge_link'] ?? '')));
    $storage->setValue('config', $config, 'array');
    emMsg('内容已保存', './plugin.php?plugin=wx_games&game=mj&tab=basic&saved=1');
} elseif ($action === 'save_ai_setting') {
    $storage = Storage::getInstance('wx_mojang');
    $ai_count = max(2, min(10, Input::postIntVar('ai_count', 2)));
    $ai_players = [];
    $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
    $quote_types = ['good', 'bad', 'win', 'lose'];
    for ($i = 0; $i < $ai_count; $i++) {
        $name = isset($_POST['ai_name'][$i]) ? addslashes(trim($_POST['ai_name'][$i])) : 'AI玩家' . ($i + 1);
        $avatar = isset($_POST['ai_avatar'][$i]) ? addslashes(trim($_POST['ai_avatar'][$i])) : $avatar_files[$i % count($avatar_files)];
        if (empty($name)) $name = 'AI玩家' . ($i + 1);
        $quotes = [];
        foreach ($quote_types as $qt) {
            $qt_key = 'ai_quotes_' . $qt . '_' . $i;
            $raw = isset($_POST[$qt_key]) ? trim($_POST[$qt_key]) : '';
            if (!empty($raw)) {
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
    emMsg('AI设置已保存', './plugin.php?plugin=wx_games&game=mj&tab=basic&saved=1');
} elseif ($action === 'delete_user') {
    $del_uid = Input::postIntVar('uid', 0);
    if ($del_uid > 0) {
        $db = Database::getInstance();
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `uid` = $del_uid AND `is_ai` = 0");
        // 同时清理该用户的游戏记录和日志
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_mojang_games` WHERE `uid` = $del_uid");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs` WHERE `uid` = $del_uid");
    }
    wx_mojang_ok();
} elseif ($action === 'add_shop_item') {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_shop_items';
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) { emMsg('商品名称不能为空', './plugin.php?plugin=wx_games&game=mj&tab=shop'); }
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $item_type = addslashes(trim(Input::postStrVar('item_type', '')));
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_majiang = Input::postIntVar('price_majiang', 0);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $sort_order = Input::postIntVar('sort_order', 0);
    $now = time();

    $db->query("INSERT INTO `{$table}` (`game`, `name`, `description`, `icon`, `item_type`, `effect_data`, `price_emlog`, `price_game`, `stock`, `max_per_user`, `sort_order`, `status`, `created`)
                VALUES ('mj', '{$name}', '{$description}', '{$icon}', '{$item_type}', '{$effect_data}', {$price_emlog}, {$price_majiang}, {$stock}, {$max_per_user}, {$sort_order}, 1, NOW())");
    emMsg('商品已添加', './plugin.php?plugin=wx_games&game=mj&tab=shop');
} elseif ($action === 'edit_shop_item') {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_shop_items';
    $id = Input::postIntVar('item_id', 0);
    if ($id <= 0) { emMsg('参数错误', './plugin.php?plugin=wx_games&game=mj&tab=shop'); }
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) { emMsg('商品名称不能为空', './plugin.php?plugin=wx_games&game=mj&tab=shop'); }
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $item_type = addslashes(trim(Input::postStrVar('item_type', '')));
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_majiang = Input::postIntVar('price_majiang', 0);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $sort_order = Input::postIntVar('sort_order', 0);
    $status = Input::postIntVar('status', 1);

    $db->query("UPDATE `{$table}` SET
        `name` = '{$name}', `description` = '{$description}', `icon` = '{$icon}',
        `item_type` = '{$item_type}', `effect_data` = '{$effect_data}',
        `price_emlog` = {$price_emlog}, `price_game` = {$price_majiang},
        `stock` = {$stock}, `max_per_user` = {$max_per_user},
        `sort_order` = {$sort_order}, `status` = {$status}
        WHERE `id` = {$id}");
    emMsg('商品已更新', './plugin.php?plugin=wx_games&game=mj&tab=shop');
} elseif ($action === 'delete_shop_item') {
    $id = isset($_POST['item_id']) ? intval($_POST['item_id']) : (isset($_GET['item_id']) ? intval($_GET['item_id']) : 0);
    if ($id > 0) {
        $db = Database::getInstance();
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE id={$id}");
    }
    emMsg('商品已删除', './plugin.php?plugin=wx_games&game=mj&tab=shop');
} elseif ($action === 'reset') {
    // POST 方式重置（由 JS resetAllScores 触发）
    $db = Database::getInstance();
    $db->query("TRUNCATE TABLE `" . DB_PREFIX . "wx_games_scores`");
    $db->query("TRUNCATE TABLE `" . DB_PREFIX . "wx_mojang_games`");
    $db->query("TRUNCATE TABLE `" . DB_PREFIX . "wx_games_logs`");
    wx_mojang_ok();
}

// ========== 积分管理 AJAX ==========
wx_admin_score_ops('mj', 'wx_mojang_games');
if (Input::getStrVar('mj_action') === 'get_users_page') { wx_admin_ajax_users_page('mj'); }
if (Input::getStrVar('mj_action') === 'get_logs_page') { wx_admin_ajax_logs_page('mj'); }
if (Input::getStrVar('mj_action') === 'get_backpack') { wx_admin_ajax_backpack('mj'); }

// ========== 积分管理数据 ==========
$table_scores = DB_PREFIX . 'wx_games_scores';
$table_logs = DB_PREFIX . 'wx_games_logs';

// 搜索 & 分页
$search = addslashes(trim(Input::getStrVar('search', '')));
$where = "WHERE `game` = 'mj' AND `is_ai` = 0";
if ($search) {
    $where = "WHERE (`nickname` LIKE '%$search%' OR `uid` = '$search') AND `game` = 'mj' AND `is_ai` = 0";
}
$page = max(1, Input::getIntVar('page', 1));
$pageSize = 10;
$offset = ($page - 1) * $pageSize;

// 用户列表
$result = $db->query("SELECT * FROM `$table_scores` $where ORDER BY `score` DESC LIMIT $offset, $pageSize");
$users = [];
while ($row = $db->fetch_array($result)) {
    $users[] = [
        'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => wx_mojang_resolve_nickname((int)$row['uid']),
        'avatar' => wx_mojang_resolve_avatar((int)$row['uid']), 'score' => (int)$row['score'],
        'total_games' => (int)$row['total_games'], 'wins' => (int)$row['wins'],
        'losses' => (int)$row['losses'], 'draws' => (int)$row['draws'],
        'best_score' => (int)$row['best_score'],
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
    $logCountRow = $db->once_fetch_array("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'mj'");
    $total_log_count = (int)($logCountRow ? $logCountRow['total'] : 0);
    $logTotalPages = max(1, ceil($total_log_count / $logPageSize));
    $logs_result = $db->query("SELECT l.*, IFNULL(u.nickname, '未知') AS nickname FROM `" . DB_PREFIX . "wx_games_logs` l LEFT JOIN `" . DB_PREFIX . "user` u ON l.uid = u.uid WHERE l.`game` = 'mj' ORDER BY l.`created_at` DESC LIMIT $logOffset, $logPageSize");
    while ($row = $db->fetch_array($logs_result)) {
        $logs[] = [
            'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => $row['nickname'],
            'score_change' => (int)$row['score_change'], 'score_before' => (int)$row['score_before'],
            'score_after' => (int)$row['score_after'], 'reason' => $row['reason'],
            'operator' => $row['operator'], 'created' => $row['created_at'],
        ];
    }
} catch (\Throwable $e) {}

// ========== 商城相关数据 ==========
$filter_type = addslashes(trim(Input::getStrVar('filter_type', '')));
$shopTable = DB_PREFIX . 'wx_games_shop_items';
$shop_items = [];
try {
    $shop_where = $filter_type ? "WHERE `game` = 'mj' AND `item_type` = '$filter_type'" : "WHERE `game` = 'mj'";
    $items_q = $db->query("SELECT * FROM `" . $shopTable . "` $shop_where ORDER BY sort_order ASC, id ASC");
    while ($it = $db->fetch_array($items_q)) {
        if (isset($it['effect_data'])) { $it['effect_data'] = stripslashes($it['effect_data']); }
        $shop_items[] = $it;
    }
} catch (\Throwable $e) {}

// 背包统计 + 购买记录（带分页）
$table_inv = DB_PREFIX . 'wx_games_user_items';
$pageSize = 20;
$inventory_stats = []; $purchase_history = [];
$stat_page = max(1, Input::getIntVar('stat_page', 1));
$stat_offset = ($stat_page - 1) * $pageSize;
$buy_page = max(1, Input::getIntVar('buy_page', 1));
$buy_offset = ($buy_page - 1) * $pageSize;
$stat_total_pages = 1; $buy_total_pages = 1;
try {
    $stat_count = $db->once_fetch_array("SELECT COUNT(DISTINCT i.item_id) AS cnt FROM `$table_inv` i JOIN `$shopTable` s ON i.item_id = s.id WHERE i.`game` = 'mj'");
    $stat_total = (int)($stat_count['cnt'] ?? 0);
    $stat_total_pages = max(1, ceil($stat_total / $pageSize));
    $inv_result = $db->query("SELECT i.item_id, s.name, s.icon, SUM(i.quantity) AS total_bought, SUM(i.used) AS total_used, COUNT(DISTINCT i.uid) AS buyer_count FROM `$table_inv` i JOIN `$shopTable` s ON i.item_id = s.id WHERE i.`game` = 'mj' GROUP BY i.item_id ORDER BY total_bought DESC LIMIT $pageSize OFFSET $stat_offset");
    while ($row = $db->fetch_array($inv_result)) { $inventory_stats[] = $row; }
} catch (\Throwable $e) {}
try {
    $buy_count = $db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table_inv` i JOIN `$shopTable` s ON i.item_id = s.id WHERE i.`game` = 'mj'");
    $buy_total = (int)($buy_count['cnt'] ?? 0);
    $buy_total_pages = max(1, ceil($buy_total / $pageSize));
    $purchase_result = $db->query("SELECT i.*, s.name AS item_name, s.icon AS item_icon FROM `$table_inv` i JOIN `$shopTable` s ON i.item_id = s.id WHERE i.`game` = 'mj' ORDER BY i.id DESC LIMIT $pageSize OFFSET $buy_offset");
    while ($row = $db->fetch_array($purchase_result)) { $purchase_history[] = $row; }
} catch (\Throwable $e) {}

// ============================================================
// 页面渲染
// ============================================================
function wx_mojang_admin_render() {
    global $item_types, $item_type_icons;
    global $users, $logs, $search, $page, $totalPages, $total_users_count;
    global $logPage, $logTotalPages, $total_log_count;
    global $shop_items, $filter_type, $inventory_stats, $purchase_history, $stat_page, $stat_total_pages, $buy_page, $buy_total_pages, $pageSize;
    $tab = Input::getStrVar('tab', 'basic');
    $config = wx_mojang_get_config();
    $ai_players = wx_mojang_get_ai_players();
    $db = Database::getInstance();
    $penalty_multiplier = isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0;
    // 如果顶层查询 count 为 0，使用硬编码表名重查（兼容变量作用域问题）
    if ($total_log_count === 0) {
        try {
            $logCountRow2 = $db->once_fetch_array("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'mj'");
            if ($logCountRow2 && (int)$logCountRow2['total'] > 0) {
                $total_log_count = (int)$logCountRow2['total'];
                $logTotalPages = max(1, ceil($total_log_count / 10));
                $logs_result2 = $db->query("SELECT * FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'mj' ORDER BY `created_at` DESC LIMIT " . (($logPage - 1) * 10) . ", 10");
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
    $ai_count = count($ai_players);

    // 如果POST成功有消息
    $success_msg = '';
    if (isset($_GET['saved'])) {
        $success_msg = '设置已保存';
    }
    $plugin_assets_url = WX_MOJANG_URL . 'assets/';

    ?>

    <div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">🀄 H5 国标麻将 - 插件设置</h1>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_msg ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

<!-- Tab 导航 -->
<ul class="nav nav-tabs mb-4" id="mjSettingTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" id="mj-basic-tab" data-toggle="tab" href="#mj-basic" role="tab">基本设置</a></li>
    <li class="nav-item"><a class="nav-link" id="mj-ai-tab" data-toggle="tab" href="#mj-ai" role="tab">AI玩家设置</a></li>
    <li class="nav-item"><a class="nav-link" id="mj-score-tab" data-toggle="tab" href="#score-mgmt" role="tab">积分管理</a></li>
</ul>

<div class="tab-content" id="mjSettingTabsContent">

<div class="tab-pane fade show active" id="mj-basic" role="tabpanel">
    <!-- ========== 基本设置 ========== -->
    <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
        <input type="hidden" name="mj_action" value="save_setting">
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
                                    <input class="form-control" name="base_score" type="number" value="<?php echo (int)$config['base_score']; ?>">
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
                                <span style="margin-left:12px;align-self:center;font-size:13px;color:#888">惩罚 = 底分 × 此倍率</span>
                            </div>
                        </div>
                        <div style="font-size:13px;color:#888;margin-bottom:8px">
                            <strong>当前：</strong>逃跑扣 <strong style="color:#e17055"><?php echo $config['base_score'] * $penalty_multiplier; ?></strong> 分
                        </div>
                    <hr>
                    <!-- 数据管理 -->
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                            <span style="font-size:14px;font-weight:600">🗃️ 数据管理</span>
                            <span style="color:#aaa;font-size:13px">玩家记录数：
                                <?php
                                try {
                                    $mj_cr = $db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_games_scores` WHERE `game` = 'mj' AND `is_ai` = 0");
                                    $mj_crow = $db->fetch_array($mj_cr);
                                    echo '<strong>' . (int)$mj_crow['total'] . '</strong>';
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

<div class="tab-pane fade" id="mj-ai" role="tabpanel">
    <!-- ========== AI玩家设置 ========== -->
            <div class="wx-card card-dark">
                <div class="card-header">AI玩家设置</div>
                <div class="card-body">
                    <form method="post" action="./plugin.php?plugin=wx_games&game=mj" id="aiForm">
                        <input type="hidden" name="mj_action" value="save_ai_setting">
                        <div class="d-flex align-items-center" style="gap:16px;margin-bottom:20px;flex-wrap:wrap;">
                            <div>
                                <label style="font-size:13px;font-weight:600;color:#555;display:block;margin-bottom:4px;">AI玩家数量</label>
                                <select class="form-control" name="ai_count" id="aiCount" style="width:auto;min-width:120px;">
                                    <?php for ($n = 2; $n <= 10; $n++) : ?>
                                    <option value="<?php echo $n; ?>" <?php echo $ai_count == $n ? 'selected' : ''; ?>><?php echo $n; ?> 个</option>
                                    <?php endfor; ?>
                                </select>
                                <small class="form-text text-muted">每局随机选3个</small>
                            </div>
                            <button type="submit" class="wx-btn" style="margin-top:18px;">💾 保存所有AI设置</button>
                        </div>

                        <div class="ai-player-grid" id="aiPlayersContainer">
                            <?php
                            $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
                            $theme_colors = ['#e74c3c','#9b59b6','#3498db','#2ecc71','#e67e22','#1abc9c'];
                            $quote_types = ['good' => '好牌', 'bad' => '差牌', 'win' => '胡牌', 'lose' => '放铳'];
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
                                            <input type="text" class="form-control" name="ai_name[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($ai['name'] ?? ''); ?>" placeholder="输入玩家名称" style="font-size:15px;font-weight:600;border:none;padding:4px 0;background:transparent;border-bottom:2px solid #eef0f5;border-radius:0;width:100%;">
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
                                        <div style="height:100%;width:<?php echo $filled*25; ?>%;background:linear-gradient(90deg,<?php echo $color; ?>,<?php echo $color; ?>88);border-radius:2px;transition:width 0.3s;"></div>
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

<?php echo wx_admin_score_tab_html('mj'); ?>

<div class="tab-pane fade" id="mj-shop" role="tabpanel">
    <!-- ========== 商城管理 ========== -->
    <!-- 商品列表 -->
    <div class="wx-card card-dark">
        <div class="card-header">商品列表</div>
        <div class="card-body" style="padding:0;">
            <div style="padding:16px 22px;border-bottom:1px solid #f0f0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span>共 <strong><?php echo count($shop_items); ?></strong> 件商品</span>
                    <select class="form-control" style="width:auto;display:inline-block;height:32px;font-size:13px;padding:2px 8px;" onchange="location.href='./plugin.php?plugin=wx_games&game=mj&tab=shop&filter_type='+this.value">
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
                        <tr><th>ID</th><th>名称</th><th>类型</th><th>站点积分</th><th>麻将积分</th><th>库存</th><th>限购</th><th>排序</th><th>状态</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shop_items as $item):
                        $type_name = $item_types[$item['item_type']] ?? $item['item_type'];
                        $type_icon = $item_type_icons[$item['item_type']]['icon'] ?? '🎁';
                        ?>
                        <tr>
                            <td><code><?php echo $item['id'] ?></code></td>
                            <td><?php echo $type_icon . ' ' . htmlspecialchars($item['name']) ?></td>
                            <td><span class="badge-score" style="font-size:11px;"><?php echo $type_name ?></span></td>
                            <td><?php echo (int)$item['price_emlog'] > 0 ? (int)$item['price_emlog'] : '-'; ?></td>
                            <td><?php echo (int)$item['price_game'] > 0 ? (int)$item['price_game'] : '-'; ?></td>
                            <td><?php echo (int)$item['stock'] === -1 ? '不限' : (int)$item['stock']; ?></td>
                            <td><?php echo (int)$item['max_per_user'] > 0 ? (int)$item['max_per_user'] : '不限'; ?></td>
                            <td><?php echo (int)$item['sort_order']; ?></td>
                            <td><?php echo (int)$item['status'] === 1 ? '<span style="color:#2ecc71;">上架</span>' : '<span style="color:#999;">下架</span>'; ?></td>
                            <td>
                                <button class="wx-btn wx-btn-sm" onclick="openEditModal(<?php echo $item['id'] ?>)">编辑</button>
                                <a href="./plugin.php?plugin=wx_games&game=mj&mj_action=delete_shop_item&item_id=<?php echo $item['id'] ?>" class="wx-btn wx-btn-sm wx-btn-danger" onclick="return confirm('确定删除「<?php echo htmlspecialchars($item['name']); ?>」吗？')">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($shop_items)): ?>
                        <tr><td colspan="9" class="wx-empty">暂无商品</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
        <div class="wx-card card-dark">
            <div class="card-header">道具消耗统计</div>
            <div class="card-body" style="padding:0;">
                <div style="overflow-x:auto;">
                    <table class="table-admin">
                        <thead><tr><th>商品</th><th>购买人数</th><th>总数量</th><th>已使用</th></tr></thead>
                        <tbody>
                            <?php foreach ($inventory_stats as $st): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($st['name']); ?></td>
                                <td><strong><?php echo intval($st['buyer_count']); ?></strong></td>
                                <td><?php echo intval($st['total_bought']); ?></td>
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
                    <a href="./plugin.php?plugin=wx_games&game=mj&tab=shop&stat_page=<?php echo $i; ?>" class="<?php echo $i == $stat_page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
        <div class="col-lg-6">
        <div class="wx-card card-dark">
            <div class="card-header">最近购买记录</div>
            <div class="card-body" style="padding:0;">
                <div style="overflow-x:auto;">
                    <table class="table-admin">
                        <thead><tr><th>时间</th><th>用户ID</th><th>商品</th><th>数量</th><th>已用</th></tr></thead>
                        <tbody>
                            <?php foreach ($purchase_history as $ph): ?>
                            <tr>
                                <td style="white-space:nowrap;"><?php echo htmlspecialchars($ph['created']); ?></td>
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
                    <a href="./plugin.php?plugin=wx_games&game=mj&tab=shop&buy_page=<?php echo $p; ?>" class="<?php echo $p == $buy_page ? 'active' : ''; ?>"><?php echo $p; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script>
    // ====== 道具类型动态提示（同 ddz） ======
    var TYPE_ICONS = <?= json_encode($item_type_icons, JSON_UNESCAPED_UNICODE) ?>;

    function updateTypeHint() {
        var sel = document.getElementById('shop_type');
        var val = sel.value;
        var hint = TYPE_ICONS[val] || { icon: '🎁', hint: '自定义道具' };
        document.getElementById('typeHintIcon').textContent = hint.icon;
        document.getElementById('typeHintDesc').textContent = hint.hint;
    }
    updateTypeHint();
</script>
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
            <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                <input type="hidden" name="mj_action" value="add_shop_item">
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
                                        $ti = $item_type_icons[$tk]['icon'] ?? '🎁';
                                    ?>
                                    <option value="<?php echo $tk; ?>"><?php echo $ti . ' ' . $tl; ?></option>
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
                        <small class="form-text text-muted">不同类型道具的配置参数，JSON格式。昵称变色：{"color":"#ff4500"}</small>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>站点积分价</label>
                                <input type="number" name="price_emlog" class="form-control" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>麻将积分价</label>
                                <input type="number" name="price_majiang" class="form-control" value="0" min="0">
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
            <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                <input type="hidden" name="mj_action" value="edit_shop_item">
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
                                        $ti = $item_type_icons[$tk]['icon'] ?? '🎁';
                                    ?>
                                    <option value="<?php echo $tk; ?>"><?php echo $ti . ' ' . $tl; ?></option>
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
                                <label>麻将积分价</label>
                                <input type="number" name="price_majiang" id="edit_price_majiang" class="form-control" min="0">
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

<script>
// ====== 商城管理：模态框JS ======
var TYPE_ICONS = <?php echo json_encode($item_type_icons, JSON_UNESCAPED_UNICODE); ?>;
function updateTypeHint(prefix) {
    var sel = document.getElementById(prefix + '_item_type');
    if (!sel) return;
    var val = sel.value;
    var hint = TYPE_ICONS[val] || { icon: '🎁', hint: '自定义道具' };
    var iconEl = document.getElementById(prefix + 'TypeIcon');
    var descEl = document.getElementById(prefix + 'TypeDesc');
    if (iconEl) iconEl.textContent = hint.icon;
    if (descEl) descEl.textContent = hint.hint;
}
setTimeout(function() { updateTypeHint('add'); updateTypeHint('edit'); }, 100);

function saveShopItemModal(form) {
    var formData = new FormData(form);
    var id = formData.get('item_id');
    formData.append('mj_action', id > 0 ? 'edit_shop_item' : 'add_shop_item');
    fetch('?plugin=wx_games&game=mj', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
        if (d.code === 0) { alert('保存成功'); location.reload(); }
        else { alert('保存失败: ' + (d.message || '')); }
    });
    return false;
}

function openAddModal() { $('#addShopModal').modal('show'); }

function openEditModal(id) {
    // 从PHP注入的数据中查找商品
    var items = <?php echo json_encode($shop_items, JSON_UNESCAPED_UNICODE); ?>;
    var item = items.find(function(i) { return parseInt(i.id) === parseInt(id); });
    if (!item) { alert('商品数据未找到'); return; }
    document.getElementById('edit_item_id').value = item.id;
    document.getElementById('edit_name').value = item.name || '';
    document.getElementById('edit_description').value = item.description || '';
    document.getElementById('edit_icon').value = item.icon || '';
    document.getElementById('edit_effect_data').value = item.effect_data || '{}';
    document.getElementById('edit_price_emlog').value = item.price_emlog || 0;
    document.getElementById('edit_price_majiang').value = item.price_game || 0;
    document.getElementById('edit_sort_order').value = item.sort_order || 0;
    document.getElementById('edit_stock').value = item.stock || -1;
    document.getElementById('edit_max_per_user').value = item.max_per_user || 0;
    document.getElementById('edit_status').value = item.status || 1;
    document.getElementById('edit_item_type').value = item.item_type || 'title_colored';
    $('#editShopModal').modal('show');
}
</script>

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
    fetch('?plugin=wx_games&game=mj&mj_action=get_users_page&page=' + page + '&search=' + encodeURIComponent(search))
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.code !== 0) { tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">加载失败</td></tr>'; return; }
        if (!d.data || d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="8" class="wx-empty">暂无数据</td></tr>'; return; }
        var html = '';
        d.data.forEach(function(u, idx) {
            var safeName = u.nickname ? u.nickname.replace(/'/g,"\\'") : '';
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
    fetch('?plugin=wx_games&game=mj&mj_action=get_logs_page&log_page=' + page + '&search=' + encodeURIComponent(search), { credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.code !== 0) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载失败</td></tr>'; return; }
        if (!d.data || d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">暂无日志记录</td></tr>'; return; }
        var html = '';
        d.data.forEach(function(log) {
            var time = log.created || '';
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

// 用户积分流水弹窗
function showUserLog(uid, nickname) {
    document.getElementById('userLogModalTitle').textContent = nickname + ' 的积分流水';
    document.getElementById('userLogBody').innerHTML = '<tr><td colspan="6" class="wx-empty">加载中...</td></tr>';
    $('#userLogModal').modal('show');

    fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=mj&mj_action=get_user_logs&uid=' + uid, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 0 && data.data && data.data.length > 0) {
                var html = '';
                data.data.forEach(function(item) {
                    var sign = item.score_change >= 0 ? '+' : '';
                    var color = item.score_change >= 0 ? '#2ecc71' : '#e74c3c';
                    html += '<tr>' +
                        '<td style="white-space:nowrap;">' + item.time + '</td>' +
                        '<td><span style="color:' + color + ';font-weight:600;">' + sign + item.score_change + '</span></td>' +
                        '<td>' + item.score_before + '</td>' +
                        '<td>' + item.score_after + '</td>' +
                        '<td>' + (item.reason || '-') + '</td>' +
                        '<td>' + (item.operator || '-') + '</td>' +
                        '</tr>';
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

    // ====== 基本设置 ======
    function saveSetting() {
        const form = document.querySelector('form');
        const actionInput = form.querySelector('input[name="action"]');
        actionInput.value = 'save_setting';
        const formData = new FormData(form);

        fetch('?plugin=wx_games&game=mj&tab=basic', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('保存成功');
                location.reload();
            } else {
                alert('保存失败: ' + d.message);
            }
        });
    }

    // ====== AI设置 ======
    function saveAISetting() {
        const form = document.querySelector('form');
        const actionInput = form.querySelector('input[name="action"]');
        actionInput.value = 'save_ai_setting';
        const formData = new FormData(form);

        fetch('?plugin=wx_games&game=mj&tab=ai', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('保存成功');
                location.reload();
            } else {
                alert('保存失败: ' + d.message);
            }
        });
    }

    // ====== 积分管理 ======
    function searchUser() {
        const uid = document.getElementById('search_uid').value;
        fetch('?plugin=wx_games&game=mj&mj_action=get_my_rank&uid=' + uid)
            .then(r => r.json())
            .then(d => {
                if (d.code === 0 && d.data) {
                    const info = document.getElementById('user_info');
                    info.innerHTML = `
                        <div class="alert alert-info">
                            <strong>用户UID:</strong> ${uid} |
                            <strong>积分:</strong> ${d.data.score} |
                            <strong>总局:</strong> ${d.data.total_games} |
                            <strong>胜:</strong> ${d.data.wins} |
                            <strong>负:</strong> ${d.data.losses} |
                            <strong>排名:</strong> ${d.data.rank}
                        </div>
                    `;
                    document.getElementById('score_action').style.display = 'block';
                } else {
                    document.getElementById('user_info').innerHTML = '<div class="alert alert-warning">未找到该用户</div>';
                }
            });
    }

    function changeScore() {
        const uid = document.getElementById('search_uid').value;
        const change = parseInt(document.getElementById('change_score_val').value);
        const reason = document.getElementById('change_reason').value;

        if (isNaN(change) || change === 0) {
            alert('请输入有效的积分变动值');
            return;
        }
        if (!confirm('确认对用户 ' + uid + ' 执行积分变动: ' + change + ' ?')) return;

        const formData = new FormData();
        formData.append('mj_action', 'change_score');
        formData.append('target_uid', uid);
        formData.append('score_change', change);
        formData.append('reason', reason);

        fetch('?plugin=wx_games&game=mj', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('修改成功');
                searchUser();
                location.reload();
            } else {
                alert('修改失败: ' + d.message);
            }
        });
    }

    function resetAllScores() {
        if (!confirm('⚠️ 确定要清空所有积分数据吗？此操作不可撤销！')) return;
        if (!confirm('再次确认：所有玩家积分、游戏记录和流水都将被删除！')) return;

        const formData = new FormData();
        formData.append('mj_action', 'reset');

        fetch('?plugin=wx_games&game=mj', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('已清空所有积分数据');
                location.reload();
            }
        });
    }

    // ====== 商城管理 ======
    let shopItems = {};

    function saveShopItem() {
        const id = document.getElementById('edit_item_id').value;
        const action = id > 0 ? 'edit_shop_item' : 'add_shop_item';

        const formData = new FormData();
        formData.append('mj_action', action);
        if (id > 0) formData.append('item_id', id);
        formData.append('name', document.getElementById('shop_name').value);
        formData.append('description', document.getElementById('shop_desc').value);
        formData.append('icon', document.getElementById('shop_icon').value);
        formData.append('item_type', document.getElementById('shop_type').value);
        formData.append('effect_data', document.getElementById('shop_effect').value);
        formData.append('price_majiang', document.getElementById('shop_price_mj').value);
        formData.append('price_emlog', document.getElementById('shop_price_emlog').value);
        formData.append('stock', document.getElementById('shop_stock').value);
        formData.append('max_per_user', document.getElementById('shop_max').value);
        formData.append('sort_order', document.getElementById('shop_sort').value);
        if (id > 0) formData.append('status', 1);

        fetch('?plugin=wx_games&game=mj&tab=shop', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('保存成功');
                location.reload();
            } else {
                alert('保存失败: ' + d.message);
            }
        });
    }

    function editShopItem(id) {
        <?php
        $items_data = [];
        $items_q = $db->query("SELECT * FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'mj' ORDER BY sort_order ASC, id ASC");
        while ($it = $db->fetch_array($items_q)) {
            // stripslashes 修复存量被 addslashes 污染的 effect_data
            if (isset($it['effect_data'])) {
                $it['effect_data'] = stripslashes($it['effect_data']);
            }
            $items_data[$it['id']] = $it;
        }
        ?>
        const items = <?= json_encode($items_data) ?>;
        const item = items[id];
        if (!item) return;

        window.scrollTo(0, 0);  // 跳转到页面顶部编辑

        document.getElementById('edit_item_id').value = id;
        document.getElementById('shop_name').value = item.name;
        document.getElementById('shop_desc').value = item.description;
        document.getElementById('shop_icon').value = item.icon;
        document.getElementById('shop_type').value = item.item_type;
        document.getElementById('shop_effect').value = item.effect_data;
        document.getElementById('shop_price_mj').value = item.price_game;
        document.getElementById('shop_price_emlog').value = item.price_emlog;
        document.getElementById('shop_stock').value = item.stock;
        document.getElementById('shop_max').value = item.max_per_user;
        document.getElementById('shop_sort').value = item.sort_order;
        document.getElementById('btn_cancel_edit').style.display = 'inline-block';
    }

    function resetShopForm() {
        document.getElementById('edit_item_id').value = 0;
        document.getElementById('shop_name').value = '';
        document.getElementById('shop_desc').value = '';
        document.getElementById('shop_icon').value = '';
        document.getElementById('shop_effect').value = '{"color":"#ff0000"}';
        document.getElementById('shop_price_mj').value = 0;
        document.getElementById('shop_price_emlog').value = 0;
        document.getElementById('shop_stock').value = -1;
        document.getElementById('shop_max').value = -1;
        document.getElementById('shop_sort').value = 0;
        document.getElementById('btn_cancel_edit').style.display = 'none';
    }

    function deleteShopItem(id) {
        if (!confirm('确定要删除这个商品吗？')) return;
        const formData = new FormData();
        formData.append('mj_action', 'delete_shop_item');
        formData.append('item_id', id);

        fetch('?plugin=wx_games&game=mj&tab=shop', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('删除成功');
                location.reload();
            }
        });
    }

    // ====== 积分管理：删除玩家 ======
    function deleteUser(uid) {
        if (!confirm('⚠️ 确定要删除该玩家的积分、游戏记录和流水吗？此操作不可撤销！')) return;
        if (!confirm('再次确认：所有数据将被永久删除！')) return;
        const formData = new FormData();
        formData.append('mj_action', 'delete_user');
        formData.append('uid', uid);

        fetch('?plugin=wx_games&game=mj', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(r => r.json())
        .then(d => {
            if (d.code === 0) {
                alert('删除成功');
                location.reload();
            } else {
                alert('删除失败: ' + d.message);
            }
        });
    }

    // ====== 积分管理：背包管理 ======
    var _bpUid = 0;

    function openBackpack(uid, nickname) {
        _bpUid = uid;
        document.getElementById('backpackModalTitle').textContent = '🎒 ' + nickname + ' 的背包';
        document.getElementById('bp_add_item_id').value = '';
        document.getElementById('bp_add_qty').value = 1;
        document.getElementById('bp_add_btn').disabled = true;
        document.getElementById('backpackItems').innerHTML = '<p class="wx-empty">加载中...</p>';
        $('#backpackModal').modal('show');
        loadBackpack(uid);
    }

    function loadBackpack(uid) {
        uid = uid || _bpUid;
        if (!uid) return;

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=mj&mj_action=admin_get_inventory&uid=' + uid, {
            credentials: 'include'
        })
        .then(r => r.text())
        .then(function(text) {
            try {
                var d = JSON.parse(text);
                if (d.code === 0 && d.data && d.data.items) {
                    renderBackpack(d.data.items);
                } else {
                    document.getElementById('backpackItems').innerHTML = '<p class="wx-empty">加载失败' + (d.message ? '：' + d.message : '') + '</p>';
                }
            } catch(e) {
                document.getElementById('backpackItems').innerHTML = '<p class="wx-empty">JSON解析失败：' + e.message + '<br><small>' + text.substring(0, 300) + '</small></p>';
            }
        })
        .catch(function(err) {
            document.getElementById('backpackItems').innerHTML = '<p class="wx-empty">请求失败: ' + err.message + '</p>';
        });
    }

    function renderBackpack(items) {
        var icons = <?= json_encode($item_type_icons, JSON_UNESCAPED_UNICODE) ?>;
        var html = '';
        if (items.length === 0) {
            html = '<p class="wx-empty">背包为空，在上方选择商品发放道具</p>';
        } else {
            html += '<table class="table-admin" style="margin:0;">';
            html += '<thead><tr>' +
                '<th>道具</th><th>类型</th><th>数量</th><th>已使用</th><th>激活</th><th>次数</th><th>过期</th><th>操作</th>' +
                '</tr></thead><tbody>';
            items.forEach(function(item) {
                var typeName = icons[item.item_type] ? icons[item.item_type].icon + ' ' : '';
                typeName += item.item_type;
                var activeHtml = item.is_active
                    ? '<span class="text-success">● 是</span>'
                    : '<span class="text-muted">○ 否</span>';
                var expiresDisplay = item.expires_at && item.expires_at !== '0000-00-00 00:00:00' ? item.expires_at : '永久';
                html += '<tr>' +
                    '<td>' + item.icon + ' ' + item.name + '</td>' +
                    '<td style="font-size:12px;color:#888;">' + typeName + '</td>' +
                    '<td><input type="number" class="form-control" style="width:70px;height:30px;font-size:13px;" value="' + item.quantity + '" id="bp_qty_' + item.id + '"></td>' +
                    '<td><input type="number" class="form-control" style="width:70px;height:30px;font-size:13px;" value="' + item.used + '" id="bp_used_' + item.id + '" min="0"></td>' +
                    '<td style="text-align:center;">' +
                        '<select id="bp_active_' + item.id + '" class="form-control" style="width:70px;height:30px;font-size:13px;">' +
                            '<option value="0"' + (item.is_active === 0 ? ' selected' : '') + '>否</option>' +
                            '<option value="1"' + (item.is_active === 1 ? ' selected' : '') + '>是</option>' +
                        '</select>' +
                    '</td>' +
                    '<td><input type="number" class="form-control" style="width:70px;height:30px;font-size:13px;" value="' + item.charges + '" id="bp_charges_' + item.id + '"></td>' +
                    '<td><input type="text" class="form-control" style="width:120px;height:30px;font-size:13px;" value="' + (expiresDisplay === '永久' ? '' : expiresDisplay) + '" id="bp_expires_' + item.id + '" placeholder="永久"></td>' +
                    '<td style="white-space:nowrap;">' +
                        '<button class="wx-btn wx-btn-sm" onclick="updateUserItem(' + item.id + ')" style="font-size:12px;">💾</button> ' +
                        '<button class="wx-btn wx-btn-sm wx-btn-danger" onclick="deleteUserItem(' + item.id + ', \'' + item.name + '\')" style="font-size:12px;">🗑</button>' +
                    '</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
        }
        document.getElementById('backpackItems').innerHTML = html;
    }

    function addUserItem() {
        var itemId = parseInt(document.getElementById('bp_add_item_id').value);
        var qty = parseInt(document.getElementById('bp_add_qty').value);
        if (!itemId || !qty || qty < 1) { alert('请选择商品并填写有效的数量'); return; }
        if (!_bpUid) return;
        if (!confirm('确认为用户发放此道具 x' + qty + ' 吗？')) return;

        const formData = new FormData();
        formData.append('uid', _bpUid);
        formData.append('item_id', itemId);
        formData.append('quantity', qty);

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=mj&mj_action=admin_add_item', {
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
                alert('发放失败: ' + d.message);
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

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=mj&mj_action=admin_update_item', {
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

        fetch('<?php echo BLOG_URL; ?>?plugin=wx_games&game=mj&mj_action=admin_delete_item&inv_id=' + invId, {
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
    </script>
<!-- 关闭：mj-shop tab-pane / tab-content / container-fluid / function -->
</div>
</div>
</div>
<?php }
