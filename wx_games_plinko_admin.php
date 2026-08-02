<?php
defined('EMLOG_ROOT') || exit('access denied!');

require_once __DIR__ . '/wx_games_plinko_fn.php';
require_once __DIR__ . '/wx_games_admin_helper.php';

$db = Database::getInstance();
$storage = Storage::getInstance('wx_plinko'); // config 仍走 emlog_storage，与 ddz/mj/niuniu 一致
$table_accounts = DB_PREFIX . 'wx_plinko_accounts';
$table_shop = DB_PREFIX . 'wx_games_shop_items';

// ========== 保存基本设置 ==========
if (Input::postStrVar('plinko_action') === 'save_setting') {
    $config = wx_plinko_get_config();
    if (isset($_POST['title'])) {
        $config['title'] = addslashes(trim(Input::postStrVar('title', $config['title'])));
    }
    if (isset($_POST['notice'])) {
        $config['notice'] = addslashes(trim(Input::postStrVar('notice', $config['notice'])));
    }
    if (isset($_POST['recent_updates'])) {
        $config['recent_updates'] = addslashes(trim(Input::postStrVar('recent_updates', $config['recent_updates'])));
    }
    if (isset($_POST['recharge_link'])) {
        $config['recharge_link'] = addslashes(trim(Input::postStrVar('recharge_link', '')));
    }
    if (isset($_POST['init_balance'])) {
        $config['init_balance'] = max(100, min(100000, Input::postIntVar('init_balance', $config['init_balance'])));
    }
    // 经验值设置（写入 config，与 ddz/mj/niuniu 完全一致模式）
    if (isset($_POST['exp_mode'])) {
        $config['exp_mode'] = Input::postStrVar('exp_mode', 'ball') === 'payout' ? 'payout' : 'ball';
    }
    if (isset($_POST['exp_multiplier'])) {
        $config['exp_multiplier'] = max(0.1, floatval(str_replace(',', '.', Input::postStrVar('exp_multiplier', '1.0'))));
    }
    $storage->setValue('config', $config, 'array');

    // 数据清理
    if (isset($_POST['do_reset']) && !empty($_POST['reset_scores'])) {
        $db->query("DELETE FROM `$table_accounts`");
        $actions[] = '积分';
    }
    if (isset($_POST['do_reset']) && !empty($_POST['reset_items'])) {
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `game` = 'plinko'");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `game` = 'plinko'");
        $actions[] = '道具';
    }
    if (isset($_POST['do_reset']) && !empty($_POST['reset_logs'])) {
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs` WHERE `game` = 'plinko'");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_plinko_games`");
        $actions[] = '日志';
    }
    emMsg('设置已保存 [EXP=' . ($config['exp_mode'] ?? '?') . ' ×' . ($config['exp_multiplier'] ?? '?') . ']', './plugin.php?plugin=wx_games&game=plinko');
}

// ========== 积分管理操作 ==========
if (Input::postStrVar('plinko_action') === 'change_score') {
    $target_uid = Input::postIntVar('uid', 0);
    if ($target_uid <= 0) {
        emMsg('用户ID无效', './plugin.php?plugin=wx_games&game=plinko');
    }
    $score_change = Input::postIntVar('score_change', 0);
    $reason = addslashes(trim(Input::postStrVar('reason', '管理员手动调整')));
    if ($score_change !== 0) {
        $operator_nick = '';
        if (function_exists('LoginAuth') && LoginAuth::isLogin()) {
            $u = LoginAuth::getUserData();
            $operator_nick = isset($u['nickname']) ? $u['nickname'] : 'admin';
        }
        $row = wx_plinko_get_account($target_uid);
        $before = $row ? floatval($row['balance']) : 0;
        $after = $before + $score_change;
        wx_plinko_save_account($target_uid, ['balance' => $after, 'updated_at' => time()]);
        // 记日志
        $db->query("INSERT INTO `" . DB_PREFIX . "wx_games_logs` (`game`,`uid`,`nickname`,`score_change`,`score_before`,`score_after`,`reason`,`operator`,`created_at`)
            VALUES ('plinko', $target_uid, '', " . $score_change . ", " . $before . ", " . $after . ", '" . addslashes($reason) . "', '" . addslashes($operator_nick) . "', " . time() . ")");
        emMsg('余额修改成功', './plugin.php?plugin=wx_games&game=plinko');
    } else {
        emMsg('余额变化不能为0', './plugin.php?plugin=wx_games&game=plinko');
    }
}

if (Input::postStrVar('plinko_action') === 'delete_user') {
    $uid = Input::postIntVar('uid', 0);
    if ($uid > 0) {
        $db = Database::getInstance();
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_plinko_accounts` WHERE `uid` = $uid");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_plinko_games` WHERE `uid` = $uid");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs` WHERE `uid` = $uid AND `game` = 'plinko'");
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 商城管理操作 ==========

if (Input::postStrVar('plinko_action') === 'add_item') {
    $name = addslashes(trim(Input::postStrVar('name', '')));
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '📦')));
    $item_type = addslashes(trim(Input::postStrVar('item_type', 'plinko_coin_pack')));
    $effect_data = addslashes(trim(Input::postStrVar('effect_data', '{}')));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_game = Input::postIntVar('price_game', 0);
    $sort_order = Input::postIntVar('sort_order', 10);
    $status = Input::postIntVar('status', 1);
    $is_global = Input::postIntVar('is_global', 0);
    $game = 'plinko';

    $db->query("INSERT INTO `$table_shop` (`game`,`name`,`description`,`icon`,`item_type`,`effect_data`,`price_emlog`,`price_game`,`sort_order`,`status`,`is_global`,`created_at`)
        VALUES ('$game','$name','$description','$icon','$item_type','$effect_data',$price_emlog,$price_game,$sort_order,$status,$is_global," . time() . ")");
    emMsg('道具已添加', './plugin.php?plugin=wx_games&game=plinko');
}

if (Input::postStrVar('plinko_action') === 'edit_item') {
    $edit_id = Input::postIntVar('id', 0);
    if ($edit_id > 0) {
        $name = addslashes(trim(Input::postStrVar('name', '')));
        $description = addslashes(trim(Input::postStrVar('description', '')));
        $icon = addslashes(trim(Input::postStrVar('icon', '📦')));
        $item_type = addslashes(trim(Input::postStrVar('item_type', 'plinko_coin_pack')));
        $effect_data = addslashes(trim(Input::postStrVar('effect_data', '{}')));
        $price_emlog = Input::postIntVar('price_emlog', 0);
        $price_game = Input::postIntVar('price_game', 0);
        $sort_order = Input::postIntVar('sort_order', 10);
        $status = Input::postIntVar('status', 1);
        $is_global = Input::postIntVar('is_global', 0);
        $db->query("UPDATE `$table_shop` SET
            `name`='$name',`description`='$description',`icon`='$icon',`item_type`='$item_type',
            `effect_data`='$effect_data',`price_emlog`=$price_emlog,`price_game`=$price_game,
            `sort_order`=$sort_order,`status`=$status,`is_global`=$is_global
            WHERE `id`=$edit_id");
        emMsg('道具已更新', './plugin.php?plugin=wx_games&game=plinko');
    }
}

if (Input::getStrVar('plinko_action') === 'del_item') {
    $del_id = Input::getIntVar('id', 0);
    if ($del_id > 0) {
        $db->query("DELETE FROM `$table_shop` WHERE `id` = $del_id");
    }
    emMsg('道具已删除', './plugin.php?plugin=wx_games&game=plinko');
}

// ========== 保存 AI 配置 ==========
if (Input::postStrVar('plinko_action') === 'save_member_config') {
    $memberKeys = ['boram','qri','soyeon','eunjung','hyomin','jiyeon'];
    $cfg = wx_plinko_get_member_config();
    foreach ($memberKeys as $k) {
        $cfg[$k]['name'] = addslashes(trim(Input::postStrVar($k . '_name', $cfg[$k]['name'])));
        $cfg[$k]['skill_desc'] = addslashes(trim(Input::postStrVar($k . '_skill_desc', $cfg[$k]['skill_desc'])));

        // 动态等级
        $levels = [];
        $li = 0;
        while (isset($_POST[$k . '_lv' . $li . '_cost'])) {
            $cost = max(0, Input::postIntVar($k . '_lv' . $li . '_cost', 0));
            $paramsRaw = isset($_POST[$k . '_lv' . $li . '_params']) ? $_POST[$k . '_lv' . $li . '_params'] : '{}';
            // Input::postStrVar 可能 addslashes 破坏 JSON，直接用 $_POST
            $paramsRaw = trim($paramsRaw);
            $params = json_decode($paramsRaw, true);
            if (!is_array($params)) $params = json_decode('{}', true);
            $levels[] = ['level' => $li + 1, 'exp_cost' => $cost, 'params' => $params];
            $li++;
        }
        if (!empty($levels)) $cfg[$k]['levels'] = $levels;
    }
    $storage->setValue('member_config', $cfg, 'array');
    emMsg('AI配置已保存', './plugin.php?plugin=wx_games&game=plinko');
}

// ========== 积分管理 AJAX ==========
if (Input::getStrVar('plinko_action') === 'get_users_page') { wx_admin_ajax_users_page('plinko', true); }
if (Input::getStrVar('plinko_action') === 'get_logs_page') { wx_admin_ajax_logs_page('plinko'); }
if (Input::getStrVar('plinko_action') === 'get_backpack') { wx_admin_ajax_backpack('plinko'); }

// ========== 读取设置 ==========
$config = wx_plinko_get_config();
$init_balance = isset($config['init_balance']) ? intval($config['init_balance']) : 200;
$expMode = $config['exp_mode'] ?? 'ball';
$expMult = floatval($config['exp_multiplier'] ?? 1.0);
$ballSel = ($expMode === 'ball') ? 'selected' : '';
$payoutSel = ($expMode === 'payout') ? 'selected' : '';
$shop_items = [];
try {
    $shop_result = $db->query("SELECT * FROM `$table_shop` WHERE (`game` = 'plinko' OR `is_global` = 1) ORDER BY `sort_order` ASC, `id` ASC");
    while ($row = $db->fetch_array($shop_result)) {
        if (isset($row['effect_data'])) $row['effect_data'] = stripslashes($row['effect_data']);
        $shop_items[] = $row;
    }
} catch (\Throwable $e) {}

$item_types = [
    'plinko_coin_pack' => '币包',
    'plinko_skin'      => '弹珠皮肤',
    'plinko_theme'     => '钉阵主题',
    'member_unlock'    => '成员解锁',
    'title_colored'    => '昵称变色',
    'title_effect'     => '昵称特效',
    'title_badge'      => '称号徽章',
    'card_back'        => '牌背皮肤',
    'emoticon'         => '专属表情',
    'score_buff'       => '积分加成卡',
];

// ========== 积分管理数据（读 wx_plinko_accounts，不是 wx_games_scores） ==========
$table_accounts = DB_PREFIX . 'wx_plinko_accounts';
$table_logs = DB_PREFIX . 'wx_games_logs';

$search = addslashes(trim(Input::getStrVar('search', '')));
$page = max(1, Input::getIntVar('page', 1));
$pageSize = 10;
$offset = ($page - 1) * $pageSize;

// 用户列表
$user_count = $db->once_fetch_array("SELECT COUNT(*) AS total FROM `$table_accounts`");
$total_users_count = (int)($user_count['total'] ?? 0);
$totalPages = ceil($total_users_count / $pageSize);

$users = [];
$result = $db->query("SELECT * FROM `$table_accounts` ORDER BY `balance` DESC LIMIT $offset, $pageSize");
while ($row = $db->fetch_array($result)) {
    $uid = (int)$row['uid'];
    $nickname = 'UID:' . $uid;
    $user_row = $db->once_fetch_array("SELECT `nickname` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
    if ($user_row) $nickname = $user_row['nickname'];
    $users[] = [
        'uid'      => $uid,
        'nickname' => $nickname,
        'balance'  => floatval($row['balance']),
        'total_bet'=> floatval($row['total_bet']),
        'total_payout'=> floatval($row['total_payout']),
        'play_count'=> intval($row['play_count']),
        'updated_at'=> intval($row['updated_at']),
    ];
}

// 日志
$logPage = max(1, Input::getIntVar('log_page', 1));
$logPageSize = 10;
$logOffset = ($logPage - 1) * $logPageSize;
$logs = [];
$total_log_count = 0;
$logTotalPages = 1;
try {
    $logCountRow = $db->once_fetch_array("SELECT COUNT(*) as total FROM `$table_logs` WHERE `game` = 'plinko'");
    $total_log_count = (int)($logCountRow ? $logCountRow['total'] : 0);
    $logTotalPages = max(1, ceil($total_log_count / $logPageSize));
    $logs_result = $db->query("SELECT * FROM `$table_logs` WHERE `game` = 'plinko' ORDER BY `created_at` DESC LIMIT $logOffset, $logPageSize");
    while ($row = $db->fetch_array($logs_result)) {
        $logs[] = [
            'uid' => (int)$row['uid'], 'nickname' => $row['nickname'],
            'score_change' => (int)$row['score_change'], 'score_before' => (int)$row['score_before'],
            'score_after' => (int)$row['score_after'], 'reason' => $row['reason'],
            'operator' => $row['operator'], 'created_at' => (int)$row['created_at'],
        ];
    }
} catch (\Throwable $e) {}

// ========== 渲染函数 ==========
function wx_plinko_admin_render() {
    global $config, $init_balance, $shop_items, $item_types, $db, $table_shop;
    global $users, $logs, $search, $page, $totalPages, $total_users_count;
    global $logPage, $logTotalPages, $total_log_count;
    global $expMode, $expMult, $ballSel, $payoutSel; // EXP 设置变量
    if (!function_exists('wx_admin_score_tab_html')) return '<div class="alert alert-danger">admin_helper.php 加载失败</div>';
    error_log('[PLINKO_ADMIN_RENDER] start');

    // 道具类型默认 icon 和 hint
    $item_type_icons = [
        'plinko_coin_pack' => ['icon' => '💰', 'hint' => '{"coins":1000}'],
        'plinko_skin'      => ['icon' => '🎨', 'hint' => '{"skin_name":"金球"}'],
        'plinko_theme'     => ['icon' => '🌈', 'hint' => '{"theme_name":"暗金"}'],
        'member_unlock'    => ['icon' => '🔓', 'hint' => '{"member":"boram"}'],
        'title_colored'    => ['icon' => '🎨', 'hint' => '{"color":"#ff4500"}'],
        'title_effect'     => ['icon' => '✨', 'hint' => '{"effect":"glow","color":"gold"}'],
        'title_badge'      => ['icon' => '👑', 'hint' => '{"badge":"地主之王"}'],
        'card_back'        => ['icon' => '🃏', 'hint' => '{"skin":"diamond"}'],
        'emoticon'         => ['icon' => '😎', 'hint' => '{"code":"victory"}'],
        'score_buff'       => ['icon' => '⚡', 'hint' => '{"multiplier":1.5,"games":5}'],
    ];
?>
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">🎱 H5弹珠台 - 插件设置</h1>
    </div>

    <ul class="nav nav-tabs mb-4" id="settingTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="basic-tab" data-toggle="tab" href="#basic" role="tab">基本设置</a></li>
        <li class="nav-item"><a class="nav-link" id="ai-tab" data-toggle="tab" href="#ai" role="tab">AI管理</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#score-mgmt">积分管理</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#profit-analysis">收益分析</a></li>
    </ul>

    <div class="tab-content" id="settingTabsContent">
        <!-- ========== 基本设置 ========== -->
        <div class="tab-pane fade show active" id="basic" role="tabpanel">
            <form method="post" action="./plugin.php?plugin=wx_games&game=plinko">
                <input type="hidden" name="plinko_action" value="save_setting">
            <div class="row">
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">基本设置</div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>游戏标题</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($config['title']); ?>">
                            </div>
                            <div class="form-group">
                                <label>初始弹珠余额</label>
                                <input class="form-control" name="init_balance" type="number" value="<?php echo $init_balance; ?>" min="100" max="100000">
                                <small class="form-text text-muted">新玩家首次进入时赠送的弹珠数量</small>
                            </div>
                            <div class="form-group">
                                <label>积分充值链接</label>
                                <input type="url" class="form-control" name="recharge_link" value="<?php echo htmlspecialchars(isset($config['recharge_link']) ? $config['recharge_link'] : ''); ?>" placeholder="https://...">
                            </div>
                            <hr>
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px">
                                    <span style="font-size:14px;font-weight:600">🗃️ 数据管理</span>
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
                                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;font-weight:400">
                                        <input type="checkbox" name="reset_scores" value="1"> 🏆 清空积分
                                    </label>
                                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;font-weight:400">
                                        <input type="checkbox" name="reset_items" value="1"> 🎒 清空道具
                                    </label>
                                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:13px;font-weight:400">
                                        <input type="checkbox" name="reset_logs" value="1"> 📊 清空日志
                                    </label>
                                    <button type="submit" name="do_reset" value="1" class="wx-btn wx-btn-danger" style="padding:4px 16px;font-size:12px" onclick="return confirm('⚠️ 确定要清理所选数据吗？此操作不可恢复！')">执行清理</button>
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
            <!-- EXP 经验值获取设置 -->
            <div class="row" style="margin-top:16px">
                <div class="col-lg-6">
                    <div class="wx-card card-dark">
                        <div class="card-header">⚡ 经验值获取设置</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>获取模式</label>
                                        <select class="form-control" name="exp_mode">
                                            <option value="ball" <?= $ballSel ?>>每个球增加1 EXP</option>
                                            <option value="payout" <?= $payoutSel ?>>下注等价增加 1 EXP</option>
                                        </select>
                                        <small class="form-text text-muted">选择结算EXP的方式</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>全局倍率</label>
                                        <input class="form-control" name="exp_multiplier" type="number" step="0.1" min="0.1" value="<?= $expMult ?>">
                                        <small class="form-text text-muted">所有模式乘以此倍率（如 2.0 = 双倍经验）</small>
                                    </div>
                                </div>
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



        <!-- ========== AI管理 ========== -->
        <div class="tab-pane fade" id="ai" role="tabpanel">
            <div class="alert alert-info" style="font-size:12px;margin-bottom:16px;background:rgba(226,176,74,0.08);border-color:rgba(226,176,74,0.2);color:#b8935a;">
                💡 商城解锁用：创建 <code>member_unlock</code> 道具时，<code>effect_data</code> 填入 <code>{"member":"ID"}</code>（ID 见每个角色卡片上的 <b>ID 标签</b>）
            </div>
<?php
$memberKeys = ['boram','qri','soyeon','eunjung','hyomin','jiyeon'];
$memberCfg = wx_plinko_get_member_config();
$plugin_assets_url = BLOG_URL . 'content/plugins/wx_games/games/ddz/assets/';
// 读取 ddz 头像列表（与斗地主一致）
$avatar_files = ['boram.jpg','qri.jpg','soyeon.jpg','eunjung.jpg','hyomin.jpg','jiyeon.jpg'];
$colors = ['#e74c3c','#d63031','#e17055','#2ecc71','#e67e22','#fdcb6e'];
?>
            <form method="post" action="./plugin.php?plugin=wx_games&game=plinko" id="aiForm">
                <input type="hidden" name="plinko_action" value="save_member_config">
            <div class="row">
<?php foreach ($memberKeys as $idx => $k):
    $ai = $memberCfg[$k];
    $levels = isset($ai['levels']) ? $ai['levels'] : [];
    $color = $colors[$idx];
?>
                <div class="col-lg-6 mb-4">
                    <div class="wx-card card-dark" style="height:100%;">
                        <div class="card-header" style="padding:12px 16px;">
                            <div style="display:flex;align-items:center;gap:14px;">
                                <img src="<?php echo $plugin_assets_url . $ai['avatar']; ?>" id="preview_<?php echo $k; ?>" style="width:56px;height:56px;border-radius:50%;border:3px solid <?php echo $color; ?>;object-fit:cover;flex-shrink:0;">
                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                        <input class="form-control" name="<?php echo $k; ?>_name" value="<?php echo htmlspecialchars($ai['name']); ?>" style="font-size:16px;font-weight:700;width:120px;border:none;background:transparent;border-bottom:2px solid #444;border-radius:0;color:#fff;padding:3px 0;">
                                        <select class="form-control" name="<?php echo $k; ?>_avatar" onchange="document.getElementById('preview_<?php echo $k; ?>').src='<?php echo $plugin_assets_url; ?>'+this.value" style="font-size:11px;padding:2px 6px;height:24px;width:auto;">
                                            <?php foreach ($avatar_files as $af): ?>
                                            <option value="<?php echo $af; ?>" <?php echo $ai['avatar'] === $af ? 'selected' : ''; ?>><?php echo $af; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="form-control" name="<?php echo $k; ?>_skill_desc" value="<?php echo htmlspecialchars($ai['skill_desc']); ?>" style="font-size:11px;padding:3px 8px;width:200px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#e0704a;" placeholder="技能描述">
                                        <span style="font-size:10px;color:#8f867a;background:rgba(226,176,74,0.10);border:1px solid rgba(226,176,74,0.25);padding:1px 8px;border-radius:10px;font-family:monospace;">ID: <?php echo $k; ?></span>
                                    </div>

                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <table class="table-admin" style="margin:0;font-size:12px;">
                                <thead>
                                    <tr>
                                        <th style="width:50px;padding:6px 10px;">等级</th>
                                        <th style="width:80px;padding:6px 10px;">EXP</th>
                                        <th style="padding:6px 10px;">技能参数（JSON）</th>
                                        <th style="width:40px;padding:6px 10px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="levels_<?php echo $k; ?>">
<?php foreach ($levels as $li => $lv): ?>
                                    <tr>
                                        <td style="padding:4px 10px;"><strong style="font-size:12px;color:<?php echo $color; ?>;">Lv<?php echo (int)$lv['level']; ?></strong></td>
                                        <td style="padding:4px 10px;"><input class="form-control" name="<?php echo $k; ?>_lv<?php echo $li; ?>_cost" type="number" value="<?php echo (int)$lv['exp_cost']; ?>" min="0" style="width:70px;padding:3px 5px;font-size:11px;"></td>
                                        <td style="padding:4px 10px;"><input class="form-control" name="<?php echo $k; ?>_lv<?php echo $li; ?>_params" value="<?php echo htmlspecialchars(json_encode($lv['params'], JSON_UNESCAPED_UNICODE)); ?>" style="font-size:11px;font-family:monospace;padding:3px 6px;"></td>
                                        <td style="padding:4px 10px;"><button type="button" class="wx-btn wx-btn-sm wx-btn-danger" onclick="removeLevel(this,'<?php echo $k; ?>')" style="padding:1px 5px;font-size:11px;">✕</button></td>
                                    </tr>
<?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="padding:6px 12px;border-top:1px solid #333;">
                                <button type="button" class="wx-btn wx-btn-sm" onclick="addLevel('<?php echo $k; ?>')" style="font-size:11px;padding:2px 10px;">+ 添加等级</button>
                            </div>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>
            </div>
            <div style="text-align:center;margin-top:16px">
                <button type="submit" class="wx-btn" style="padding:10px 48px;font-size:15px">💾 保存 AI 配置</button>
            </div>
            </form>
        </div>

        <!-- 积分管理 Tab -->
        <div class="tab-pane fade" id="score-mgmt">
        <?php echo wx_admin_score_tab_html('plinko', true); ?>
        </div>

        <!-- 收益分析 Tab -->
        <div class="tab-pane fade" id="profit-analysis">
        <div class="wx-card card-dark">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>📊 收益分析 — 风险×行数组合性价比</span>
                <button class="wx-btn wx-btn-sm" onclick="loadAnalysis()">刷新分析</button>
            </div>
            <div class="card-body">
                <p class="text-muted small">统计所有用户逐球记录，对比理论期望收益 (EV) 与实际平均收益，找出最优风险×行数组合。</p>
                <div id="analysisContent" class="table-responsive"><p class="text-center text-muted py-4">点击「刷新分析」开始计算</p></div>
            </div>
        </div>
        </div>

        </div> <!-- end tab-content -->
    </div> <!-- end container-fluid -->

<script>
// 动态添加/删除等级行
var memberKeys = <?php echo json_encode($memberKeys); ?>;
function addLevel(key) {
    var tbody = document.getElementById('levels_'+key);
    var rowCount = tbody.querySelectorAll('tr').length;
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><strong>Lv'+(rowCount+1)+'</strong></td>'
        + '<td><input class="form-control" name="'+key+'_lv'+rowCount+'_cost" type="number" value="0" min="0" style="width:90px;padding:4px 6px;font-size:12px;"></td>'
        + '<td><input class="form-control" name="'+key+'_lv'+rowCount+'_params" value="{}" style="font-size:12px;font-family:monospace;"></td>'
        + '<td><button type="button" class="wx-btn wx-btn-sm wx-btn-danger" onclick="removeLevel(this,\''+key+'\')">✕</button></td>';
    tbody.appendChild(tr);
}
function removeLevel(btn, key) {
    var tr = btn.closest('tr');
    tr.parentNode.removeChild(tr);
    // 重新编号
    var rows = document.querySelectorAll('#levels_'+key+' tr');
    rows.forEach(function(r, i) {
        r.querySelector('td strong').textContent = 'Lv'+(i+1);
        r.querySelectorAll('input').forEach(function(inp) {
            var oldName = inp.name;
            inp.name = oldName.replace(/lv\d+/, 'lv'+i);
        });
    });
}
function updateAddEffectHint(type) {
    var hints = <?php echo json_encode(array_map(function($v) { return $v['hint']; }, $item_type_icons)); ?>;
    var el = document.getElementById('addEffectHint');
    if (el && hints[type]) el.textContent = hints[type];
    else if (el) el.textContent = '{}';
}
function deletePlinkoUser(uid) {
    var fd = new FormData();
    fd.append('plinko_action', 'delete_user');
    fd.append('uid', uid);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', './plugin.php?plugin=wx_games&game=plinko', true);
    xhr.onload = function() { location.reload(); };
    xhr.send(fd);
}

// Toast notification
(function(){
  var params = new URLSearchParams(location.search);
  var toast = params.get('toast');
  if(toast){
    var div = document.createElement('div');
    div.className = 'wx-toast';
    div.textContent = decodeURIComponent(toast);
    document.body.appendChild(div);
    setTimeout(function(){ div.remove(); }, 2500);
    if(window.history.replaceState){
      params.delete('toast');
      window.history.replaceState({}, '', location.pathname + '?' + params.toString());
    }
  }
})();

function loadAnalysis() {
    document.getElementById('analysisContent').innerHTML = '<p class="text-center text-muted py-4">正在分析...</p>';
    const fd = new FormData();
    fd.append('plinko_action', 'get_analysis');
    fd.append('game', 'plinko');
    fetch('./plugin.php?plugin=wx_games&game=plinko', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => {
            if (d.code !== 0 || !d.data) {
                document.getElementById('analysisContent').innerHTML = '<p class="text-danger">分析失败</p>';
                return;
            }
            renderAnalysis(d.data);
        });
}
function renderAnalysis(data) {
    const rows = data.combos || [];
    if (!rows.length) {
        document.getElementById('analysisContent').innerHTML = '<p class="text-muted">暂无游戏记录</p>';
        return;
    }
    let html = '<table class="table table-sm table-striped"><thead><tr><th>风险</th><th>行数</th><th>总局</th><th>总投注</th><th>总收益</th><th>ROI%</th><th>理论EV%</th><th>差值%</th></tr></thead><tbody>';
    rows.forEach(r => {
        const roi = r.total_bet > 0 ? (r.total_profit / r.total_bet * 100) : 0;
        const diff = roi - (r.ev || 0);
        html += '<tr><td>'+r.risk+'</td><td>'+r.rows+'行</td><td>'+r.plays+'</td><td>'+r.total_bet.toFixed(0)+'</td><td style="color:'+(r.total_profit>=0?'#2ecc71':'#e74c3c')+'">'+(r.total_profit>=0?'+':'')+r.total_profit.toFixed(0)+'</td><td>'+(roi>=0?'+':'')+roi.toFixed(2)+'%</td><td>'+(r.ev||0).toFixed(2)+'%</td><td>'+(diff>=0?'+':'')+diff.toFixed(2)+'%</td></tr>';
    });
    html += '</tbody></table>';
    document.getElementById('analysisContent').innerHTML = html;
}
</script>
<?php
}
