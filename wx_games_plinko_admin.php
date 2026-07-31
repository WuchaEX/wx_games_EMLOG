<?php
defined('EMLOG_ROOT') || exit('access denied!');

require_once __DIR__ . '/wx_games_plinko_fn.php';

$db = Database::getInstance();
$storage = Storage::getInstance('wx_plinko'); // config 仍走 emlog_storage，与 ddz/mj/niuniu 一致

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
    if (!empty($actions)) {
        emMsg('设置已保存，已清理：' . implode('、', $actions), './plugin.php?plugin=wx_games&game=plinko');
    }
    emMsg('设置已保存', './plugin.php?plugin=wx_games&game=plinko');
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
    $del_uid = Input::postIntVar('uid', 0);
    if ($del_uid > 0) {
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_scores` WHERE `uid` = $del_uid AND `game` = 'plinko' AND `is_ai` = 0");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_logs` WHERE `uid` = $del_uid AND `game` = 'plinko'");
        $db->query("DELETE FROM `$table_accounts` WHERE `uid` = $del_uid");
        $db->query("DELETE FROM `" . DB_PREFIX . "wx_plinko_games` WHERE `uid` = $del_uid");
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== 商城管理操作 ==========
$table_shop = DB_PREFIX . 'wx_games_shop_items';

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

// ========== 读取设置 ==========
$config = wx_plinko_get_config();
$init_balance = isset($config['init_balance']) ? intval($config['init_balance']) : 200;

// 读取商城商品
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

    // 道具类型默认 icon 和 hint
    $item_type_icons = [
        'plinko_coin_pack' => ['icon' => '💰', 'hint' => '{"coins":1000}'],
        'plinko_skin'      => ['icon' => '🎨', 'hint' => '{"skin_name":"金球"}'],
        'plinko_theme'     => ['icon' => '🌈', 'hint' => '{"theme_name":"暗金"}'],
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
        <li class="nav-item"><a class="nav-link" id="admin-tab" data-toggle="tab" href="#admin" role="tab">积分管理</a></li>
        <li class="nav-item"><a class="nav-link" id="shop-tab" data-toggle="tab" href="#shop" role="tab">商城管理</a></li>
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
            <div style="text-align:center;margin-top:16px">
                <button type="submit" class="wx-btn" style="padding:10px 48px;font-size:15px">💾 保存全部设置</button>
            </div>
            </form>
        </div>

        <!-- ========== 积分管理 ========== -->
        <div class="tab-pane fade" id="admin" role="tabpanel">
            <div class="row">
                <div class="col-lg-6">
                    <div class="wx-card card-dark mb-4">
                        <div class="card-header">余额查询与修改</div>
                        <div class="card-body">
                            <form method="post" class="mb-3" style="max-width:400px">
                                <input type="hidden" name="plinko_action" value="change_score">
                                <div class="form-group">
                                    <label>用户ID</label>
                                    <input class="form-control" name="uid" type="number" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label>余额变动（正=增加，负=扣除）</label>
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
                                    <tbody>
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
                            <div class="pagination-admin" style="margin-top:0;">
                                <?php
                                $logStart = max(1, $logPage - 2);
                                $logEnd = min($logTotalPages, $logPage + 2);
                                for ($i = 1; $i <= $logTotalPages; $i++) {
                                    $active = $i == $logPage ? 'active' : '';
                                    echo '<a href="./plugin.php?plugin=wx_games&game=plinko&tab=admin&log_page=' . $i . '" class="pagi-link ' . $active . '">' . $i . '</a>';
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
                            <input type="hidden" name="game" value="plinko">
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
                                    <th>当前弹珠</th>
                                    <th>更新时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $index => $user): ?>
                                <tr>
                                    <td><?php echo ($page - 1) * $pageSize + $index + 1; ?></td>
                                    <td><?php echo $user['uid']; ?></td>
                                    <td><?php echo htmlspecialchars($user['nickname']); ?></td>
                                    <td><span class="badge-score">💎 <?php echo $user['balance'] % 1 === 0 ? number_format($user['balance'], 0) : number_format($user['balance'], 1); ?></span></td>
                                    <td style="white-space:nowrap;"><?php echo $user['updated_at'] ? date('Y-m-d H:i', $user['updated_at']) : '-'; ?></td>
                                    <td>
                                        <button class="wx-btn wx-btn-sm wx-btn-danger" onclick="if(confirm('确定删除该用户的所有H5弹珠台数据？')){deletePlinkoUser(<?php echo $user['uid']; ?>)}">删除</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                <tr><td colspan="6" class="wx-empty">暂无玩家数据</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination-admin" style="margin-top:0;">
                        <?php for ($i = 1; $i <= $totalPages; $i++):
                            $active = $i == $page ? 'active' : '';
                            echo '<a href="./plugin.php?plugin=wx_games&game=plinko&tab=admin&page=' . $i . '&search=' . urlencode($search) . '" class="pagi-link ' . $active . '">' . $i . '</a>';
                        endfor; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ========== 商城管理 ========== -->
        <div class="tab-pane fade" id="shop" role="tabpanel">
            <!-- 新增道具表单 -->
            <div class="wx-card card-dark mb-4">
                <div class="card-header">➕ 新增道具</div>
                <div class="card-body">
                    <form method="post" action="./plugin.php?plugin=wx_games&game=plinko">
                        <input type="hidden" name="plinko_action" value="add_item">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>道具名称</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>道具类型</label>
                                    <select class="form-control" name="item_type" onchange="updateAddEffectHint(this.value)">
                                        <?php foreach ($item_types as $type => $label): ?>
                                        <option value="<?php echo $type; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>图标</label>
                                    <input type="text" class="form-control" name="icon" value="💰">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>站点积分价格</label>
                                    <input type="number" class="form-control" name="price_emlog" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>游戏积分价格</label>
                                    <input type="number" class="form-control" name="price_game" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>排序</label>
                                    <input type="number" class="form-control" name="sort_order" value="10" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>效果数据（JSON）<span id="addEffectHint" style="color:#999;font-weight:400;margin-left:8px;">{"coins":1000}</span></label>
                                    <input type="text" class="form-control" name="effect_data" value='{"coins":1000}'>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>通用道具</label>
                                    <select class="form-control" name="is_global">
                                        <option value="0">否</option>
                                        <option value="1">是</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>状态</label>
                                    <select class="form-control" name="status">
                                        <option value="1">上架</option>
                                        <option value="0">下架</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>道具描述</label>
                            <input type="text" class="form-control" name="description" placeholder="简短描述">
                        </div>
                        <button type="submit" class="wx-btn">添加道具</button>
                    </form>
                </div>
            </div>

            <!-- 现有道具列表 -->
            <div class="wx-card card-dark">
                <div class="card-header">📦 道具列表（共 <?php echo count($shop_items); ?> 个）</div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>名称</th>
                                    <th>类型</th>
                                    <th>效果数据</th>
                                    <th>站点积分</th>
                                    <th>游戏积分</th>
                                    <th>通用</th>
                                    <th>排序</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shop_items as $item): ?>
                                <tr>
                                    <td><?php echo $item['id']; ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><span style="font-size:12px;background:#f0f0f5;padding:2px 8px;border-radius:4px;"><?php echo isset($item_types[$item['item_type']]) ? $item_types[$item['item_type']] : $item['item_type']; ?></span></td>
                                    <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:11px;"><?php echo htmlspecialchars($item['effect_data']); ?></td>
                                    <td><?php echo $item['price_emlog']; ?></td>
                                    <td><?php echo $item['price_game']; ?></td>
                                    <td><?php echo $item['is_global'] ? '✅' : '-'; ?></td>
                                    <td><?php echo $item['sort_order']; ?></td>
                                    <td><?php echo $item['status'] ? '🟢' : '⚫'; ?></td>
                                    <td>
                                        <a href="./plugin.php?plugin=wx_games&game=plinko&plinko_action=del_item&id=<?php echo $item['id']; ?>" class="wx-btn wx-btn-sm wx-btn-danger" onclick="return confirm('确定删除该道具？')">删除</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($shop_items)): ?>
                                <tr><td colspan="10" class="wx-empty">暂无道具，请添加</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
</script>
<?php
}
