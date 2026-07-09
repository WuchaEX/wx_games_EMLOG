<?php
/**
 * wx_mojang 后台设置页
 * 麻将玩法插件 - 后台管理界面
 */
!defined('EMLOG_ROOT') && exit('access denied!');

require_once __DIR__ . '/wx_games_mojang_fn.php';

$db = Database::getInstance();

// ============================================================
// 道具类型定义（与 wx_ddz 完全一致）
// ============================================================
$item_types = [
    'title_colored' => '昵称变色',
    'title_effect'  => '昵称特效',
    'card_back'     => '牌背皮肤',
    'emoticon'      => '专属表情',
    'win_effect'    => '胡牌特效',
    'score_buff'    => '积分加成卡',
    'title_badge'   => '称号徽章',
];

$item_type_icons = [
    'title_colored' => ['icon' => '🎨', 'hint' => '昵称显示为彩色，如：{"color":"#ff4500"}'],
    'title_effect'  => ['icon' => '✨', 'hint' => '昵称带光晕特效，如：{"effect":"glow","color":"gold"}'],
    'card_back'     => ['icon' => '🀄', 'hint' => '更换牌背图案，填写图片URL，如：{"url":"https://..."}'],
    'emoticon'      => ['icon' => '😎', 'hint' => '游戏中发送专属弹幕，如：{"code":"victory","text":"稳了！"}'],
    'win_effect'    => ['icon' => '💥', 'hint' => '胡牌时触发特效，如：{"color":"#ff4500","particles":80}'],
    'score_buff'    => ['icon' => '⚡', 'hint' => '下N局积分加成，如：{"multiplier":2,"games":5}'],
    'title_badge'   => ['icon' => '👑', 'hint' => '名称旁显示称号，如：{"badge":"麻将大师"}'],
];

// ============================================================
// 处理POST请求
// ============================================================
$action = Input::postStrVar('do', '');
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
} elseif ($action === 'change_score') {
    $target_uid = Input::postIntVar('target_uid', 0);
    $score_change = Input::postIntVar('score_change', 0);
    $reason = addslashes(trim(Input::postStrVar('reason', '管理员手动调整')));
    if ($target_uid > 0 && $score_change != 0) {
        $operator_nick = '';
        if (function_exists('LoginAuth') && LoginAuth::isLogin()) {
            $u = LoginAuth::getUserData();
            $operator_nick = isset($u['nickname']) ? $u['nickname'] : 'admin';
        }
        wx_mojang_admin_change_score($target_uid, $score_change, $reason, $operator_nick);
        wx_mojang_ok();
    } else {
        wx_mojang_error('Invalid parameters');
    }
} elseif ($action === 'delete_user') {
    $del_uid = Input::postIntVar('uid', 0);
    if ($del_uid > 0) {
        $db = Database::getInstance();
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_mojang_scores` WHERE `uid` = $del_uid AND `is_ai` = 0");
        // 同时清理该用户的游戏记录和日志
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_mojang_games` WHERE `uid` = $del_uid");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_mojang_logs` WHERE `uid` = $del_uid");
    }
    wx_mojang_ok();
} elseif ($action === 'add_shop_item') {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_mojang_shop_items';
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) { wx_mojang_error('商品名称不能为空'); }
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $item_type = addslashes(trim(Input::postStrVar('item_type', '')));
    // stripslashes 修复表单提交的存量反斜杠数据，再由 $db->escape_string 统一安全转义
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_majiang = Input::postIntVar('price_majiang', 0);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $sort_order = Input::postIntVar('sort_order', 0);
    $now = time();

    $db->query("INSERT INTO `{$table}` (`name`, `description`, `icon`, `item_type`, `effect_data`, `price_emlog`, `price_majiang`, `stock`, `max_per_user`, `sort_order`, `is_active`, `created`)
                VALUES ('{$name}', '{$description}', '{$icon}', '{$item_type}', '{$effect_data}', {$price_emlog}, {$price_majiang}, {$stock}, {$max_per_user}, {$sort_order}, 1, NOW())");
    wx_mojang_ok();
} elseif ($action === 'edit_shop_item') {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_mojang_shop_items';
    $id = Input::postIntVar('item_id', 0);
    if ($id <= 0) { wx_mojang_error('参数错误'); }
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) { wx_mojang_error('商品名称不能为空'); }
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
        `price_emlog` = {$price_emlog}, `price_majiang` = {$price_majiang},
        `stock` = {$stock}, `max_per_user` = {$max_per_user},
        `sort_order` = {$sort_order}, `is_active` = {$status}
        WHERE `id` = {$id}");
    wx_mojang_ok();
} elseif ($action === 'delete_shop_item') {
    $id = Input::postIntVar('item_id', 0);
    if ($id > 0) {
        $db = Database::getInstance();
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_mojang_shop_items` WHERE id={$id}");
    }
    wx_mojang_ok();
} elseif ($action === 'reset' || (empty($action) && Input::getStrVar('do', '') === 'reset')) {
    $db = Database::getInstance();
    $db->query("TRUNCATE TABLE `" . DB_PREFIX . "wx_mojang_scores`");
    $db->query("TRUNCATE TABLE `" . DB_PREFIX . "wx_mojang_games`");
    $db->query("TRUNCATE TABLE `" . DB_PREFIX . "wx_mojang_logs`");
    wx_mojang_ok();
}

// ========== 积分管理数据 ==========
$table_scores_mj = DB_PREFIX . 'wx_mojang_scores';
$table_logs_mj = DB_PREFIX . 'wx_mojang_logs';

// 搜索 & 分页
$search_mj = addslashes(trim(Input::getStrVar('search', '')));
$where_mj = "WHERE `is_ai` = 0";
if ($search_mj) {
    $where_mj = "WHERE (`nickname` LIKE '%$search_mj%' OR `uid` = '$search_mj') AND `is_ai` = 0";
}
$page_mj = max(1, Input::getIntVar('page', 1));
$pageSize_mj = 20;
$offset_mj = ($page_mj - 1) * $pageSize_mj;

// 用户列表
$result_mj = $db->query("SELECT * FROM `$table_scores_mj` $where_mj ORDER BY `score` DESC LIMIT $offset_mj, $pageSize_mj");
$users_mj = [];
while ($row = $db->fetch_array($result_mj)) {
    $users_mj[] = [
        'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => wx_mojang_resolve_nickname((int)$row['uid']),
        'avatar' => wx_mojang_resolve_avatar((int)$row['uid']), 'score' => (int)$row['score'],
        'total_games' => (int)$row['total_games'], 'wins' => (int)$row['wins'],
        'losses' => (int)$row['losses'], 'draws' => (int)$row['draws'],
        'best_score' => (int)$row['best_score'],
    ];
}
$count_row_mj = $db->once_fetch_array("SELECT COUNT(*) as total FROM `$table_scores_mj` $where_mj");
$total_users_count_mj = (int)$count_row_mj['total'];
$totalPages_mj = ceil($total_users_count_mj / $pageSize_mj);

// 日志（最近50条）—— JOIN user 表获取昵称（实时）
$logs_result_mj = $db->query("SELECT l.*, IFNULL(u.nickname, '未知') AS nickname FROM `$table_logs_mj` l LEFT JOIN `" . DB_PREFIX . "user` u ON l.uid = u.uid ORDER BY l.`created` DESC LIMIT 50");
$logs_mj = [];
while ($row = $db->fetch_array($logs_result_mj)) {
    $logs_mj[] = [
        'id' => (int)$row['id'], 'uid' => (int)$row['uid'], 'nickname' => $row['nickname'],
        'score_change' => (int)$row['score_change'], 'score_before' => (int)$row['score_before'],
        'score_after' => (int)$row['score_after'], 'reason' => $row['reason'],
        'operator' => $row['operator'], 'created' => $row['created'],
    ];
}

// ============================================================
// 页面渲染
// ============================================================
function wx_mojang_admin_render() {
    global $item_types, $item_type_icons;
    global $users_mj, $logs_mj, $search_mj, $page_mj, $totalPages_mj, $total_users_count_mj;
    $tab = Input::getStrVar('tab', 'basic');
    $config = wx_mojang_get_config();
    $ai_players = wx_mojang_get_ai_players();
    $db = Database::getInstance();
    $penalty_multiplier = isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0;
    $ai_count = count($ai_players);

    // 如果POST成功有消息
    $success_msg = '';
    if (isset($_GET['saved'])) {
        $success_msg = '设置已保存';
    }
    $plugin_assets_url = WX_MOJANG_URL . 'assets/';

    ?>
<style>
.wx-card {
    background: #fff;
    border-radius: 14px;
    border: none;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
    margin-bottom: 22px;
    overflow: hidden;
    transition: box-shadow 0.3s, transform 0.3s;
    position: relative;
    animation: fadeIn 0.35s ease both;
}
.wx-card:hover { box-shadow: 0 6px 30px rgba(0, 0, 0, 0.1); }
.wx-card .card-header {
    background: linear-gradient(135deg, #2d3436 0%, #636e72 100%);
    color: #fff;
    font-weight: 600;
    font-size: 15px;
    letter-spacing: 0.5px;
    padding: 15px 22px;
    border-bottom: none;
    display: flex;
    align-items: center;
    gap: 8px;
}
.wx-card .card-body { padding: 22px; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.form-control {
    border-radius: 8px;
    border: 1px solid #e0e2ea;
    padding: 10px 14px;
    font-size: 14px;
    color: #333;
}
.form-control:focus {
    border-color: #2d3436;
    box-shadow: 0 0 0 3px rgba(45, 52, 54, 0.12);
    outline: none;
}
.wx-btn {
    background: linear-gradient(135deg, #2d3436, #636e72);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.25s;
    text-decoration: none;
    display: inline-block;
}
.wx-btn:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); color: #fff; }
.wx-btn-danger { background: linear-gradient(135deg, #f093fb, #f5576c); }
.wx-btn-sm { padding: 6px 14px; font-size: 12px; }
.wx-info-block {
    background: #f8f9fe;
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 12px;
    color: #666;
}
.wx-empty { text-align: center; padding: 30px 20px; color: #ccc; }
.wx-empty .empty-icon { font-size: 40px; display: block; margin-bottom: 10px; }

/* ===== 表格样式（同 ddz） ===== */
.table-admin {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.table-admin th {
    background: #f8f9fe;
    color: #667eea;
    font-weight: 600;
    padding: 10px 12px;
    text-align: left;
    border-bottom: 2px solid #e8e8f0;
    white-space: nowrap;
}
.table-admin td {
    padding: 10px 12px;
    border-bottom: 1px solid #f0f0f5;
    color: #333;
    vertical-align: middle;
}
.table-admin tr:hover td { background: #f8f9fe; }
.table-admin .badge-score {
    display: inline-block;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
}
.table-admin .win-text { color: #2ecc71; font-weight: 600; }
.table-admin .lose-text { color: #e74c3c; font-weight: 600; }
.pagination-admin {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin-top: 16px;
}
.pagination-admin a, .pagination-admin span {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    text-decoration: none;
    color: #667eea;
    background: #f8f9fe;
}
.pagination-admin a:hover { background: #667eea; color: #fff; }
.pagination-admin .active { background: #667eea; color: #fff; }

.row > [class*="col-"] { display: flex; }
.row > [class*="col-"] > .wx-card { flex: 1; display: flex; flex-direction: column; width: 100%; }
.wx-card .card-body { flex: 1; }

@media (max-width: 768px) {
    .wx-card .card-body { padding: 16px; }
    .table-admin { font-size: 12px; }
    .table-admin th, .table-admin td { padding: 6px 8px; }
}
</style>

    <div class="d-sm-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0 text-gray-800">H5 国标麻将 - 插件设置</h1>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_msg ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    <?php endif; ?>

<div class="container-fluid">

    <!-- ========== 基本设置 ========== -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">基本设置</div>
                        <div class="card-body">
                            <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                                <input type="hidden" name="do" value="save_setting">
                                <div class="form-group">
                                    <label>游戏标题</label>
                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($config['title']); ?>">
                                    <small class="form-text text-muted">显示在游戏页面和导航菜单中的标题</small>
                                </div>
                                <div class="form-group">
                                    <label>游客模式</label>
                                    <select class="form-control" name="guest_play">
                                        <option value="1" <?php echo $config['guest_play'] == '1' ? 'selected' : ''; ?>>开启</option>
                                        <option value="0" <?php echo $config['guest_play'] == '0' ? 'selected' : ''; ?>>关闭</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>排行榜最大条目数</label>
                                    <input type="number" class="form-control" name="max_entries" value="<?php echo (int)$config['max_entries']; ?>" min="10" max="500">
                                </div>
                                <div class="form-group">
                                    <label>积分充值链接</label>
                                    <input type="url" class="form-control" name="recharge_link" value="<?php echo htmlspecialchars(isset($config['recharge_link']) ? $config['recharge_link'] : ''); ?>" placeholder="例如：https://example.com/recharge">
                                    <small class="form-text text-muted">前台"充值"按钮的跳转链接，留空则不显示充值按钮</small>
                                </div>
                                <button type="submit" class="wx-btn">保存设置</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">防逃跑惩罚设置</div>
                        <div class="card-body">
                            <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                                <input type="hidden" name="do" value="save_setting">
                                <div class="form-group">
                                    <label>惩罚倍率</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="penalty_multiplier" value="<?php echo number_format($penalty_multiplier, 1, '.', ''); ?>" min="0.1" max="10" step="0.1">
                                        <span class="input-group-text" style="border-radius:0 8px 8px 0;background:#f8f9fe;border:1px solid #e0e2ea;border-left:none;padding:10px 14px;">x</span>
                                    </div>
                                    <small class="form-text text-muted">惩罚积分 = 底分 × 此倍率。例如倍率设为 2.0，底分 100，逃跑扣 100×2 = 200 分</small>
                                </div>
                                <div class="wx-info-block">
                                    <strong>当前生效：</strong>逃跑扣除 <strong><?php echo $config['base_score'] * $penalty_multiplier; ?></strong> 分
                                </div>
                                <button type="submit" class="wx-btn" style="margin-top:12px;">保存设置</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">使用说明</div>
                        <div class="card-body">
                            <ul style="margin:0;padding-left:18px;line-height:2;">
                                <li>游戏前台地址：<a href="<?php echo BLOG_URL; ?>?plugin=wx_games&game=mj" target="_blank"><?php echo BLOG_URL; ?>?plugin=wx_games&game=mj</a></li>
                                <li>积分数据存储在数据库中，确保数据持久化</li>
                                <li>用户登录后游戏积分会自动保存到服务器</li>
                                <li>游客模式下数据仅保存在本地浏览器</li>
                                <li>国标麻将规则：8番起胡，自摸三家付，点炮全铳</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">数据管理</div>
                        <div class="card-body">
                            <p>数据库中的玩家记录数：
                                <?php
                                try {
                                    $cr = $db->query("SELECT COUNT(*) as total FROM `" . DB_PREFIX . "wx_mojang_scores` WHERE `is_ai` = 0");
                                    $crow = $db->fetch_array($cr);
                                    echo '<strong>' . (int)$crow['total'] . '</strong>';
                                } catch (\Throwable $e) {
                                    echo '0';
                                }
                                ?>
                            </p>
                            <a href="./plugin.php?plugin=wx_games&game=mj&do=reset" class="wx-btn wx-btn-danger" onclick="return confirm('确定要清空所有积分数据吗？此操作不可恢复！')">清空所有积分数据</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== 公告与更新内容编辑 ========== -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">游戏公告</div>
                        <div class="card-body">
                            <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                                <input type="hidden" name="do" value="save_content">
                                <div class="form-group">
                                    <label>公告内容</label>
                                    <textarea class="form-control" name="notice" rows="5" style="resize:vertical;"><?php echo htmlspecialchars($config['notice']); ?></textarea>
                                    <small class="form-text text-muted">显示在游戏首页欢迎界面</small>
                                </div>
                                <button type="submit" class="wx-btn">保存公告</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">最近更新</div>
                        <div class="card-body">
                            <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                                <input type="hidden" name="do" value="save_content">
                                <div class="form-group">
                                    <label>更新内容（每行一条）</label>
                                    <textarea class="form-control" name="recent_updates" rows="8" style="resize:vertical;font-family:monospace;"><?php echo htmlspecialchars($config['recent_updates']); ?></textarea>
                                    <small class="form-text text-muted">每行一条更新记录，格式：版本号 - 内容</small>
                                </div>
                                <button type="submit" class="wx-btn">保存更新</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

    <!-- ========== AI玩家设置 ========== -->
            <div class="wx-card card-dark">
                <div class="card-header">AI玩家设置</div>
                <div class="card-body">
                    <form method="post" action="./plugin.php?plugin=wx_games&game=mj" id="aiForm">
                        <input type="hidden" name="do" value="save_ai_setting">
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

    <!-- ========== 积分管理 ========== -->
    <!-- 搜索 & 用户列表 -->
    <div class="wx-card card-dark">
        <div class="card-header">用户积分列表</div>
        <div class="card-body" style="padding:0;">
            <div style="padding:16px 22px;border-bottom:1px solid #f0f0f5;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <span>共 <strong><?php echo $total_users_count_mj; ?></strong> 条记录</span>
                <form method="get" action="./plugin.php" class="form-inline" style="display:flex;gap:8px;">
                    <input type="hidden" name="plugin" value="wx_mojang">
                    <input type="hidden" name="tab" value="score">
                    <input type="text" name="search" class="form-control" placeholder="搜索用户ID或昵称" value="<?php echo htmlspecialchars($search_mj); ?>" style="width:200px;">
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
                    <tbody>
                        <?php foreach ($users_mj as $index => $user): ?>
                        <tr>
                            <td><?php echo ($page_mj - 1) * 20 + $index + 1; ?></td>
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
                                <button type="button" class="wx-btn wx-btn-sm" onclick="$('#scoreModal<?php echo $user['uid']; ?>').modal('show')">修改积分</button>
                                <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);margin-left:4px;" onclick="showUserLog(<?php echo $user['uid']; ?>, '<?php echo htmlspecialchars($user['nickname'], ENT_QUOTES); ?>')">流水</button>
                                <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" style="margin-left:4px;" onclick="deleteUser(<?php echo $user['uid']; ?>)">删除</button>
                                <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb);margin-left:4px;" onclick="openBackpack(<?php echo $user['uid']; ?>, '<?php echo htmlspecialchars($user['nickname'], ENT_QUOTES); ?>')">背包</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users_mj)): ?>
                        <tr><td colspan="8" class="wx-empty">暂无数据</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages_mj > 1): ?>
            <div class="pagination-admin">
                <?php for ($i = 1; $i <= $totalPages_mj; $i++): ?>
                <a href="./plugin.php?plugin=wx_games&game=mj&tab=score&page=<?php echo $i; ?>&search=<?php echo urlencode($search_mj); ?>" class="<?php echo $i == $page_mj ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 积分变动日志 -->
    <div class="wx-card card-dark">
        <div class="card-header">积分变动日志（最近50条）</div>
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
                    <tbody>
                        <?php foreach ($logs_mj as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo $log['created']; ?></td>
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
                        <?php if (empty($logs_mj)): ?>
                        <tr><td colspan="7" class="wx-empty">暂无日志</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php foreach ($users_mj as $user): ?>
    <div class="modal fade" id="scoreModal<?php echo $user['uid']; ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="border-radius:14px;border:none;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:14px 14px 0 0;border:none;">
                    <h5 class="modal-title" style="font-size:16px;">修改积分 - <?php echo htmlspecialchars($user['nickname']); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;">&times;</button>
                </div>
                <form method="post" action="./plugin.php?plugin=wx_games&game=mj">
                    <input type="hidden" name="do" value="change_score">
                    <input type="hidden" name="target_uid" value="<?php echo $user['uid']; ?>">
                    <div class="modal-body" style="padding:24px;">
                        <div class="form-group">
                            <label>当前积分</label>
                            <input type="text" class="form-control" value="<?php echo $user['score']; ?>" readonly style="background:#f8f9fe;">
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
    <?php endforeach; ?>

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
                                        $all_items_q = $db->query("SELECT `id`, `name`, `icon`, `item_type` FROM `" . DB_PREFIX . "wx_mojang_shop_items` ORDER BY id DESC");
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
                    <!-- 背包列表 -->
                    <div id="backpackItems">
                        <p class="wx-empty">加载中...</p>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #f0f0f5;padding:12px 20px;">
                    <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" data-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== 商城管理 ========== -->
    <div class="row">
        <div class="col-md-6">
            <div class="wx-card card-dark">
                <div class="card-header">添加/编辑商品</div>
                <div class="card-body">
                    <input type="hidden" id="edit_item_id" value="0">
                    <div class="form-group">
                        <label>名称</label>
                        <input type="text" id="shop_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>图标（Emoji）</label>
                        <input type="text" id="shop_icon" class="form-control" placeholder="如 🎨">
                    </div>
                    <div class="form-group">
                        <label>道具类型</label>
                        <select id="shop_type" class="form-control" onchange="updateTypeHint()">
                            <?php foreach ($item_types as $tk => $tl):
                                $ti = $item_type_icons[$tk]['icon'] ?? '🎁';
                            ?>
                            <option value="<?= $tk ?>" data-icon="<?= $ti ?>"><?= $ti . ' ' . $tl ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="typeHint" class="wx-info-block" style="margin-top:8px;font-size:12px;display:flex;align-items:center;gap:8px;">
                            <span id="typeHintIcon">🎨</span>
                            <span id="typeHintDesc">昵称显示为彩色，如：{"color":"#ff4500"}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>描述</label>
                        <textarea id="shop_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>效果数据（JSON）</label>
                        <textarea id="shop_effect" class="form-control" rows="2" placeholder='{"color":"#ff0000"}'></textarea>
                    </div>
                    <button class="wx-btn" onclick="saveShopItem()">保存商品</button>
                    <button class="wx-btn wx-btn-danger" onclick="resetShopForm()" style="display:none" id="btn_cancel_edit">取消编辑</button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="wx-card card-dark">
                <div class="card-header">价格与库存</div>
                <div class="card-body">
                    <div class="form-group">
                        <label>麻将积分价格</label>
                        <input type="number" id="shop_price_mj" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>站点积分价格</label>
                        <input type="number" id="shop_price_emlog" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>库存（-1不限）</label>
                        <input type="number" id="shop_stock" class="form-control" value="-1">
                    </div>
                    <div class="form-group">
                        <label>每人限购（0不限）</label>
                        <input type="number" id="shop_max" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" id="shop_sort" class="form-control" value="0">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wx-card card-dark">
        <div class="card-header">商品列表（按ID降序，最新在前）</div>
        <div class="card-body" style="padding:0;">
            <table class="table table-sm table-striped" style="margin-bottom:0;">
                <thead>
                    <tr><th>ID</th><th>名称</th><th>类型</th><th>排序值</th><th>麻将积分</th><th>站点积分</th><th>库存</th><th>状态</th><th>操作</th></tr>
                </thead>
                <tbody>
                    <?php
                    $items = $db->query("SELECT * FROM `" . DB_PREFIX . "wx_mojang_shop_items` ORDER BY id DESC");
                    while ($item = $db->fetch_array($items)):
                    $type_name = $item_types[$item['item_type']] ?? $item['item_type'];
                    $type_icon = $item_type_icons[$item['item_type']]['icon'] ?? '🎁';
                    ?>
                    <tr>
                        <td><code><?= $item['id'] ?></code></td>
                        <td><?= $type_icon . ' ' . $item['name'] ?></td>
                        <td><?= $type_name ?></td>
                        <td><?= $item['sort_order'] ?></td>
                        <td><?= $item['price_majiang'] ?></td>
                        <td><?= $item['price_emlog'] ?></td>
                        <td><?= $item['stock'] >= 0 ? $item['stock'] : '不限' ?></td>
                        <td><?= $item['is_active'] ? '<span class="text-success">上架</span>' : '<span class="text-muted">下架</span>' ?></td>
                        <td>
                            <button class="wx-btn wx-btn-sm" onclick="editShopItem(<?= $item['id'] ?>)">编辑</button>
                            <button class="wx-btn wx-btn-sm wx-btn-danger" onclick="deleteShopItem(<?= $item['id'] ?>)">删除</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
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
        formData.append('do', 'change_score');
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
        formData.append('do', 'reset');

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
        formData.append('do', action);
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
        $items_q = $db->query("SELECT * FROM `" . DB_PREFIX . "wx_mojang_shop_items` ORDER BY id DESC");
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
        document.getElementById('shop_price_mj').value = item.price_majiang;
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
        formData.append('do', 'delete_shop_item');
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
        formData.append('do', 'delete_user');
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
    <?php
}
