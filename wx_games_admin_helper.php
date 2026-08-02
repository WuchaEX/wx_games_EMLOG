<?php
/**
 * 积分管理通用辅助函数
 * 各游戏 admin 统一 include，减少重复代码
 */
if (!defined('EMLOG_ROOT')) { exit('access denied!'); }

/**
 * 处理积分管理 POST 操作（change_score / delete_user）
 * @param string $game 游戏标识
 * @param string $game_table 游戏特有记录表（用于 delete_user）
 * @param string $use_accounts_table plinko专用：是否使用 wx_plinko_accounts 而非 wx_games_scores
 */
function wx_admin_score_ops($game, $game_table = '', $use_accounts_table = false) {
    $db = Database::getInstance();
    $action_key = $game . '_action';

    // 修改积分（plinko 有独立处理逻辑，优先走）
    if ($use_accounts_table) {
        return; // plinko 已有自己的 change_score 逻辑，不拦截
    }

    if (Input::postStrVar($action_key) === 'change_score') {
        $admin_uid = Input::postIntVar('uid', 0);
        if ($admin_uid <= 0) {
            emMsg('用户ID无效', './plugin.php?plugin=wx_games&game=' . $game);
        }
        $score_change = Input::postIntVar('score_change', 0);
        $reason = addslashes(trim(Input::postStrVar('reason', '管理员手动调整')));
        if ($score_change !== 0) {
            $operator_nick = '';
            if (function_exists('LoginAuth') && LoginAuth::isLogin()) {
                $u = LoginAuth::getUserData();
                $operator_nick = isset($u['nickname']) ? $u['nickname'] : 'admin';
            }
            $table = DB_PREFIX . 'wx_games_scores';
            $row = $db->once_fetch_array("SELECT * FROM `$table` WHERE `uid` = $admin_uid AND `game` = '$game' LIMIT 1");
            $before = $row ? (int)$row['score'] : 0;
            if (!$row) {
                $db->query("INSERT INTO `$table` (`game`,`uid`,`nickname`,`score`) VALUES ('$game',$admin_uid,'',0)");
            }
            $db->query("UPDATE `$table` SET `score` = `score` + $score_change WHERE `uid` = $admin_uid AND `game` = '$game'");
            $after = $before + $score_change;
            $table_logs = DB_PREFIX . 'wx_games_logs';
            $db->query("INSERT INTO `$table_logs` (`game`,`uid`,`nickname`,`score_change`,`score_before`,`score_after`,`reason`,`operator`,`created_at`)
                VALUES ('$game', $admin_uid, '', $score_change, $before, $after, '$reason', '" . addslashes($operator_nick) . "', " . time() . ")");
            emMsg('积分修改成功', './plugin.php?plugin=wx_games&game=' . $game);
        } else {
            emMsg('积分变化不能为0', './plugin.php?plugin=wx_games&game=' . $game);
        }
    }

    // 删除用户
    if (Input::postStrVar($action_key) === 'delete_user') {
        $admin_uid = Input::postIntVar('uid', 0);
        if ($admin_uid > 0) {
            $table_scores = DB_PREFIX . 'wx_games_scores';
            $table_logs = DB_PREFIX . 'wx_games_logs';
            if ($use_accounts_table) {
                $table_accounts = DB_PREFIX . 'wx_plinko_accounts';
                $db->query("DELETE FROM `$table_accounts` WHERE `uid` = $admin_uid");
            } else {
                $db->query("DELETE FROM `$table_scores` WHERE `uid` = $admin_uid AND `game` = '$game' AND `is_ai` = 0");
            }
            if (!empty($game_table)) {
                $db->query("DELETE FROM `" . DB_PREFIX . "$game_table` WHERE `uid` = $admin_uid");
            }
            $db->query("DELETE FROM `$table_logs` WHERE `uid` = $admin_uid AND `game` = '$game'");
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'message' => '已删除'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * 通用 AJAX - 用户列表分页
 */
function wx_admin_ajax_users_page($game, $use_accounts_table = false) {
    $page = max(1, Input::getIntVar('page', 1));
    $search = addslashes(trim(Input::getStrVar('search', '')));
    $pageSize = 10;
    $offset = ($page - 1) * $pageSize;
    $db = Database::getInstance();

    if ($use_accounts_table) {
        // plinko 专用
        $table = DB_PREFIX . 'wx_plinko_accounts';
        $where = "WHERE 1=1";
        if ($search) {
            $where = "WHERE `uid` = '" . intval($search) . "'";
            // uid 精确匹配，也可以按 nickname 模糊
            $uid_search = intval($search);
            if ($uid_search > 0) {
                $where = "WHERE `uid` = $uid_search";
            } else {
                $where = "WHERE `uid` IN (SELECT `uid` FROM `" . DB_PREFIX . "user` WHERE `nickname` LIKE '%$search%')";
            }
        }
        $total = (int)$db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table` $where")['cnt'];
        $totalPages = max(1, ceil($total / $pageSize));
        $rows = $db->query("SELECT * FROM `$table` $where ORDER BY `balance` DESC LIMIT $offset, $pageSize");
        $data = [];
        while ($row = $db->fetch_array($rows)) {
            $uid = (int)$row['uid'];
            $user_row = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
            $data[] = [
                'uid' => $uid,
                'nickname' => $user_row ? $user_row['nickname'] : '未知',
                'avatar' => $user_row ? (wx_games_resolve_avatar($uid, $user_row['photo'] ?? null) ?: '') : '',
                'score' => floatval($row['balance']),
                'total_games' => (int)$row['play_count'],
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'best_score' => (int)$row['exp'],  // plinko 用 exp 替代最高分
            ];
        }
    } else {
        $table = DB_PREFIX . 'wx_games_scores';
        $where = "WHERE `game` = '$game' AND `is_ai` = 0";
        if ($search) {
            $where = "WHERE (`nickname` LIKE '%$search%' OR `uid` = '" . intval($search) . "') AND `game` = '$game' AND `is_ai` = 0";
        }
        $total = (int)$db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table` $where")['cnt'];
        $totalPages = max(1, ceil($total / $pageSize));
        $rows = $db->query("SELECT * FROM `$table` $where ORDER BY `score` DESC LIMIT $offset, $pageSize");
        $data = [];
        while ($row = $db->fetch_array($rows)) {
            $uid = (int)$row['uid'];
            $user_row = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
            $data[] = [
                'uid' => $uid,
                'nickname' => $user_row ? $user_row['nickname'] : '未知',
                'avatar' => $user_row ? (wx_games_resolve_avatar($uid, $user_row['photo'] ?? null) ?: '') : '',
                'score' => (int)$row['score'],
                'total_games' => (int)$row['total_games'],
                'wins' => (int)$row['wins'],
                'losses' => (int)$row['losses'],
            ];
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'data' => $data, 'totalPages' => $totalPages, 'currentPage' => $page], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 通用 AJAX - 积分流水分页
 */
function wx_admin_ajax_logs_page($game) {
    $log_page = max(1, Input::getIntVar('log_page', 1));
    $log_search = addslashes(trim(Input::getStrVar('search', '')));
    $logPageSize = 10;
    $log_offset = ($log_page - 1) * $logPageSize;
    $db = Database::getInstance();

    if ($game === 'plinko') {
        // plinko 流水：每颗弹珠的记录来自 wx_plinko_games
        $table_logs = DB_PREFIX . 'wx_plinko_games';
        $log_where = "WHERE 1=1";
        if ($log_search) {
            $uid_search = intval($log_search);
            if ($uid_search > 0) {
                $log_where .= " AND g.`uid` = $uid_search";
            } else {
                $log_where .= " AND g.`uid` IN (SELECT `uid` FROM `" . DB_PREFIX . "user` WHERE `nickname` LIKE '%$log_search%')";
            }
        }
        $total = (int)$db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table_logs` g $log_where")['cnt'];
        $totalPages = max(1, ceil($total / $logPageSize));
        $rows = $db->query("SELECT g.*, IFNULL(u.nickname, '游客') AS nickname FROM `$table_logs` g LEFT JOIN `" . DB_PREFIX . "user` u ON g.uid = u.uid $log_where ORDER BY g.`created_at` DESC LIMIT $log_offset, $logPageSize");
        $data = [];
        while ($r = $db->fetch_array($rows)) {
            $data[] = [
                'uid' => (int)$r['uid'],
                'nickname' => $r['nickname'],
                'risk' => [0=>'低',1=>'中',2=>'高'][(int)$r['risk']] ?? '中',
                'rows' => (int)$r['rows'],
                'bin' => (int)$r['bin'],
                'bet' => (int)$r['bet'],
                'multiplier' => floatval($r['multiplier']),
                'payout' => (int)$r['payout'],
                'profit' => (int)$r['profit'],
                'created_at' => (int)$r['created_at'],
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 0, 'data' => $data, 'totalPages' => $totalPages, 'currentPage' => $log_page, 'log_type' => 'plinko'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $table_logs = DB_PREFIX . 'wx_games_logs';
    $log_where = "WHERE `game` = '$game'";
    if ($log_search) {
        $log_where .= " AND (`nickname` LIKE '%$log_search%' OR `uid` = '" . intval($log_search) . "')";
    }
    $total = (int)$db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `$table_logs` $log_where")['cnt'];
    $totalPages = max(1, ceil($total / $logPageSize));
    $rows = $db->query("SELECT * FROM `$table_logs` $log_where ORDER BY `created_at` DESC LIMIT $log_offset, $logPageSize");
    $data = [];
    while ($r = $db->fetch_array($rows)) {
        $data[] = $r;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'data' => $data, 'totalPages' => $totalPages, 'currentPage' => $log_page, 'log_type' => 'score'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 通用 AJAX - 背包查看
 */
function wx_admin_ajax_backpack($game) {
    $uid = Input::getIntVar('uid', 0);
    if ($uid <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 1, 'message' => 'UID无效'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = Database::getInstance();
    // 通用道具 (is_global=1) + 游戏专属道具
    $table_items = DB_PREFIX . 'wx_games_user_items';
    $items = [];
    $rows = $db->query("SELECT i.*, s.name, s.item_type, s.icon
        FROM `$table_items` i
        LEFT JOIN `" . DB_PREFIX . "wx_games_shop_items` s ON i.item_id = s.id
        WHERE i.uid = $uid AND (s.`game` = '$game' OR s.`is_global` = 1 OR s.`game` = 'plinko')
        ORDER BY i.created_at DESC LIMIT 50");
    while ($r = $db->fetch_array($rows)) {
        $items[] = $r;
    }

    // plinko 额外读取已解锁 AI 角色（从 wx_plinko_accounts.members JSON）
    if ($game === 'plinko') {
        $acc = $db->once_fetch_array("SELECT `members`, `member_exp`, `exp` FROM `" . DB_PREFIX . "wx_plinko_accounts` WHERE `uid` = $uid LIMIT 1");
        if ($acc && !empty($acc['members'])) {
            $members = json_decode($acc['members'], true);
            if (is_array($members)) {
                // 读取成员配置（含名字 + 头像）
                $member_cfg = [];
                $cfg_row = $db->once_fetch_array("SELECT `value` FROM `" . DB_PREFIX . "wx_games_storage` WHERE `key` = 'plinko.member_config' LIMIT 1");
                // 简化：从 shop_items 找 member_unlock 道具的 effect_data 反推 ID
                $cfg_items = $db->query("SELECT `effect_data` FROM `" . DB_PREFIX . "wx_games_shop_items` WHERE `item_type` = 'member_unlock' AND (`game` = 'plinko' OR `is_global` = 1)");
                while ($c = $db->fetch_array($cfg_items)) {
                    $d = json_decode($c['effect_data'], true);
                    if (is_array($d) && isset($d['member'])) $member_cfg[$d['member']] = $d;
                }
                // 默认成员名字表
                $default_names = ['boram'=>'全宝蓝','qri'=>'李居丽','soyeon'=>'朴素妍','eunjung'=>'恩静','hyomin'=>'孝敏','jiyeon'=>'智妍'];
                foreach ($members as $mid => $m) {
                    if (!is_array($m) || empty($m['unlocked'])) continue;
                    $name = $default_names[$mid] ?? $mid;
                    $items[] = [
                        'name' => "👤 $name ($mid)",
                        'item_type' => 'plinko_member',
                        'icon' => '',
                        'created_at' => 0,
                        'extra' => 'Lv' . intval($m['level'] ?? 1),
                        'quantity' => 1,
                    ];
                }
                // 额外显示 EXP
                if ($acc['exp'] > 0 || $acc['member_exp'] > 0) {
                    $items[] = [
                        'name' => '⭐ 经验值',
                        'item_type' => 'plinko_exp',
                        'icon' => '',
                        'created_at' => 0,
                        'extra' => 'EXP ' . intval($acc['exp']) . ' / 成员EXP ' . intval($acc['member_exp']),
                        'quantity' => 1,
                    ];
                }
            }
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'data' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 生成积分管理页签 HTML（各 admin 共用）
 * @param string $game 游戏标识
 * @param bool $is_plinko plinko特殊处理
 * @return string
 */
function wx_admin_score_tab_html($game, $is_plinko = false) {
    $action_key = $game . '_action';
    $scoreLabel = $is_plinko ? '弹珠币余额' : '积分';
    ob_start();
?>
<!-- ========== 积分管理 ========== -->
<div class="tab-pane" id="score-mgmt">
    <div class="row">
        <div class="col-lg-6">
            <!-- 积分查询与修改 -->
            <div class="wx-card card-dark mb-4">
                <div class="card-header">积分查询与修改</div>
                <div class="card-body">
                    <form method="post" class="mb-3" style="max-width:400px">
                        <input type="hidden" name="<?= $action_key ?>" value="change_score">
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
                <div class="card-header">积分变动日志</div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table class="table-admin">
                            <thead>
                                <tr>
                                    <?php if ($is_plinko): ?>
                                    <th>时间</th>
                                    <th>用户</th>
                                    <th>风险/行数</th>
                                    <th>落槽</th>
                                    <th>倍率</th>
                                    <th>下注→返奖</th>
                                    <th>盈亏</th>
                                <?php else: ?>
                                    <th>时间</th>
                                    <th>用户</th>
                                    <th>变动</th>
                                    <th>变动前</th>
                                    <th>变动后</th>
                                    <th>原因</th>
                                    <th>操作者</th>
                                <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="logTableBody"><tr><td colspan="7" class="wx-empty">加载中...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="wx-card card-dark">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>用户<?= $scoreLabel ?>列表</span>
            <div class="input-group" style="width:280px">
                <input type="text" class="form-control form-control-sm" id="scoreSearch" placeholder="搜索用户PID或昵称" onkeydown="if(event.keyCode===13)loadUsers(1)">
                <div class="input-group-append"><button class="wx-btn wx-btn-sm" onclick="loadUsers(1)">搜索</button></div>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="table-admin">
                    <thead>
                        <tr>
                            <th>排名</th>
                            <th>UID</th>
                            <th>昵称</th>
                            <th>当前<?= $scoreLabel ?></th>
                            <?php if ($is_plinko): ?><th>总球数</th><?php else: ?><th>场次</th><th>胜/负/平</th><?php endif; ?>
                            <th><?= $is_plinko ? 'EXP' : '最高分' ?></th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="scoreTbody"><tr><td colspan="8" class="wx-empty">加载中...</td></tr></tbody>
                </table>
            </div>
            <div class="pagination-admin" id="scorePager" style="margin-top:0;"></div>
        </div>
    </div>
</div>
<!-- 流水弹窗 -->
<div class="modal fade" id="logModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5>积分流水</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body" style="max-height:500px;overflow-y:auto">
            <table class="table table-sm table-striped">
                <thead id="logModalHead"></thead>
                <tbody id="logTbody"></tbody>
            </table>
            <div class="d-flex justify-content-between mt-2"><small class="text-muted" id="logPageInfo"></small><div class="btn-group btn-group-sm" id="logPager"></div></div>
        </div>
    </div></div>
</div>
<!-- 背包弹窗 -->
<div class="modal fade" id="backpackModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5>玩家背包</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
        <div class="modal-body" style="max-height:400px;overflow-y:auto"><div id="backpackContent"></div></div>
    </div></div>
</div>
<script>
// ====== 积分管理 JS ======
const GAME = '<?= $game ?>';
const ACTION_KEY = '<?= $action_key ?>';
let scorePage = 1, logPage = 1, scoreSearch = '';

function loadUsers(p) {
    scorePage = p || scorePage;
    try {
        const el = document.getElementById('scoreSearch');
        scoreSearch = el ? el.value.trim() : '';
    } catch(e) { scoreSearch = ''; }
    fetch(`?plugin=wx_games&game=${GAME}&${ACTION_KEY}=get_users_page&page=${scorePage}&search=${encodeURIComponent(scoreSearch)}`)
        .then(r => r.json()).then(d => {
            if (d.code !== 0) { document.getElementById('scoreTbody').innerHTML = '<tr><td colspan="8" class="wx-empty">加载失败</td></tr>'; return; }
            if (!d.data || d.data.length === 0) {
                document.getElementById('scoreTbody').innerHTML = '<tr><td colspan="8" class="wx-empty">暂无数据</td></tr>';
                document.getElementById('scorePager').innerHTML = '';
                return;
            }
            const tbody = document.getElementById('scoreTbody');
            const isPlinko = <?= $is_plinko ? 'true' : 'false' ?>;
            tbody.innerHTML = d.data.map((u, idx) => {
                const rank = (d.currentPage - 1) * 10 + idx + 1;
                const score = Number(u.score||0);
                const nick = (u.nickname||'').replace(/'/g, "\\'");
                const avatar = u.avatar ? `<img src="${u.avatar}" style="width:24px;height:24px;border-radius:50%;vertical-align:middle;margin-right:4px;">` : '';
                const extraCols = isPlinko ? `<td>${u.total_games||0}</td>` : `<td>${u.total_games||0}</td><td><span class="win-text">${u.wins||0}胜</span> / <span class="lose-text">${u.losses||0}负</span> / <span style="color:#999;">${u.draws||0}平</span></td>`;
                return `<tr>
                    <td>${rank}</td>
                    <td>${u.uid}</td>
                    <td>${avatar}${nick || '未知'}</td>
                    <td><span class="badge-score">${isPlinko ? score.toFixed(1) : score}</span></td>
                    ${extraCols}
                    <td>${u.best_score||0}</td>
                    <td>
                        <button type="button" class="wx-btn wx-btn-sm btn-change-score" data-uid="${u.uid}" data-score="${score}" data-nick="${nick}">修改积分</button>
                        <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#4facfe,#00f2fe);margin-left:4px;" onclick="showUserLog(${u.uid},'${nick}')">流水</button>
                        <button type="button" class="wx-btn wx-btn-sm wx-btn-danger" style="margin-left:4px;" onclick="deleteUser(${u.uid})">删除</button>
                        <button type="button" class="wx-btn wx-btn-sm" style="background:linear-gradient(135deg,#a18cd1,#fbc2eb);margin-left:4px;" onclick="showBackpack(${u.uid})">背包</button>
                    </td>
                </tr>`;
            }).join('');
            let phtml = '';
            for (let i = 1; i <= d.totalPages; i++) {
                phtml += `<a href="javascript:void(0)" onclick="loadUsers(${i})" class="${i===d.currentPage?'active':''}">${i}</a>`;
            }
            document.getElementById('scorePager').innerHTML = phtml;
        }).catch(err => {
            console.error('loadUsers error:', err);
            document.getElementById('scoreTbody').innerHTML = '<tr><td colspan="8" class="wx-empty">加载出错</td></tr>';
        });
}

function showModifyScore(uid, score, nick) {
    const change = prompt(`修改 ${nick}(UID:${uid}) 的积分\n当前: ${score}\n输入变化量（正数增加，负数减少）:`, '0');
    if (change === null || change === '0') return;
    const reason = prompt('修改原因:', '管理员手动调整') || '管理员手动调整';
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input name="${ACTION_KEY}" value="change_score"><input name="uid" value="${uid}"><input name="score_change" value="${change}"><input name="reason" value="${reason}">`;
    document.body.appendChild(form);
    form.submit();
}

function showUserLog(uid, nick) {
    logPage = 1;
    document.getElementById('logModal').querySelector('h5').textContent = `积分流水 - ${nick}(UID:${uid})`;
    loadLogs(uid);
    jQuery('#logModal').modal('show');
}
function loadLogs(uid, p) {
    logPage = p || logPage;
    fetch(`./plugin.php?plugin=wx_games&game=${GAME}&${ACTION_KEY}=get_logs_page&log_page=${logPage}&search=${uid}`)
        .then(r => r.json()).then(d => {
            const head = document.getElementById('logModalHead');
            const tbody = document.getElementById('logTbody');
            if (d.code !== 0 || !d.data) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">加载失败</td></tr>'; return; }
            if (d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">暂无流水记录</td></tr>'; return; }
            if (d.log_type === 'plinko') {
                head.innerHTML = '<tr><th>时间</th><th>风险/行数</th><th>落槽</th><th>倍率</th><th>下注→返奖</th><th>盈亏</th></tr>';
                tbody.innerHTML = d.data.map(l => {
                    const dt = l.created_at ? new Date((l.created_at||0)*1000).toLocaleString('zh-CN', {hour12:false}) : '-';
                    return `<tr>
                        <td><small>${dt}</small></td>
                        <td>${l.risk}/${l.rows}行</td>
                        <td>${l.bin}槽</td>
                        <td style="font-weight:bold;color:#e17055">${l.multiplier.toFixed(1)}x</td>
                        <td>${l.bet}→${l.payout}</td>
                        <td style="color:${l.profit>=0?'#2ecc71':'#e74c3c'}">${l.profit>=0?'+':''}${l.profit}</td>
                    </tr>`;
                }).join('');
            } else {
                head.innerHTML = '<tr><th>时间</th><th>变化</th><th>前</th><th>后</th><th>原因</th><th>操作者</th></tr>';
                tbody.innerHTML = d.data.map(l => {
                    const dt = l.created_at ? new Date((l.created_at||0)*1000).toLocaleString('zh-CN', {hour12:false}) : '-';
                    return `<tr>
                        <td><small>${dt}</small></td>
                        <td style="color:${l.score_change>=0?'#2ecc71':'#e74c3c'}">${l.score_change>=0?'+':''}${l.score_change}</td>
                        <td>${l.score_before}</td><td>${l.score_after}</td>
                        <td>${l.reason||''}</td><td>${l.operator||''}</td>
                    </tr>`;
                }).join('');
            }
            document.getElementById('logPageInfo').textContent = `第 ${d.currentPage}/${d.totalPages} 页`;
            let ph = '';
            for (let i = 1; i <= d.totalPages; i++) {
                ph += `<button class="btn btn-xs btn-${i===d.currentPage?'primary':'outline-secondary'} mr-1" onclick="loadLogs(${uid},${i})">${i}</button>`;
            }
            document.getElementById('logPager').innerHTML = ph;
        }).catch(e => { document.getElementById('logTbody').innerHTML = '<tr><td colspan="6" class="text-danger text-center">加载出错</td></tr>'; });
}

function deleteUser(uid) {
    if (!confirm(`确定删除 UID:${uid} 的所有游戏数据？此操作不可恢复。`)) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input name="${ACTION_KEY}" value="delete_user"><input name="uid" value="${uid}">`;
    document.body.appendChild(form);
    form.submit();
}

function showBackpack(uid) {
    fetch(`./plugin.php?plugin=wx_games&game=${GAME}&${ACTION_KEY}=get_backpack&uid=${uid}`)
        .then(r => r.json()).then(d => {
            const c = document.getElementById('backpackContent');
            if (!d.data || d.data.length === 0) { c.innerHTML = '<p class="text-muted">该玩家暂无道具</p>'; }
            else c.innerHTML = '<table class="table table-sm"><thead><tr><th>道具</th><th>类型</th><th>详情</th><th>获得时间</th></tr></thead><tbody>'
                + d.data.map(i => `<tr><td>${i.icon||''} ${i.name||'未知'}</td><td>${i.item_type||''}</td><td>${i.extra || (i.quantity||1)}</td><td><small>${i.created_at && i.created_at>0 ? new Date(i.created_at*1000).toLocaleDateString() : '—'}</small></td></tr>`).join('')
                + '</tbody></table>';
        });
    jQuery('#backpackModal').modal('show');
}

// 初始加载 + 页签切换
document.addEventListener('DOMContentLoaded', function() {
    const tab = document.querySelector('a[href="#score-mgmt"]');
    if (tab) tab.addEventListener('shown.bs.tab', function() { loadUsers(1); loadAllLogs(1); });
    // 兜底：无论是否点击 tab，直接加载一次
    setTimeout(function() {
        if (typeof loadUsers === 'function') loadUsers(1);
        if (typeof loadAllLogs === 'function') loadAllLogs(1);
    }, 100);
});
// 如果 DOMContentLoaded 已错过，直接执行
if (document.readyState !== 'loading') {
    setTimeout(function() {
        if (typeof loadUsers === 'function') loadUsers(1);
        if (typeof loadAllLogs === 'function') loadAllLogs(1);
    }, 100);
}
// 兜底2：脚本刚执行完就调用一次（捕获所有加载时机）
if (typeof loadUsers === 'function') {
    setTimeout(function() { loadUsers(1); }, 50);
    setTimeout(function() { if (typeof loadAllLogs === 'function') loadAllLogs(1); }, 80);
}

// 加载所有用户的流水（plinko 走逐球，其他走积分流水）
function loadAllLogs(page) {
    const tbody = document.getElementById('logTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载中...</td></tr>';
    fetch(`?plugin=wx_games&game=${GAME}&${ACTION_KEY}=get_logs_page&log_page=${page}`)
        .then(r => r.json()).then(d => {
            if (d.code !== 0 || !d.data) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载失败</td></tr>'; return; }
            if (d.data.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">暂无流水记录</td></tr>'; return; }
            if (d.log_type === 'plinko') {
                tbody.innerHTML = d.data.map(l => {
                    const dt = l.created_at ? new Date((l.created_at || 0)*1000).toLocaleString('zh-CN', {hour12:false}) : '-';
                    return `<tr>
                        <td><small>${dt}</small></td>
                        <td>${l.nickname || ''} (UID:${l.uid})</td>
                        <td>${l.risk}/${l.rows}行</td>
                        <td>${l.bin}槽</td>
                        <td style="font-weight:bold;color:#e17055">${l.multiplier.toFixed(1)}x</td>
                        <td>下注 ${l.bet} → 返 ${l.payout}</td>
                        <td style="color:${l.profit>=0?'#2ecc71':'#e74c3c'}">${l.profit>=0?'+':''}${l.profit}</td>
                    </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = d.data.map(l => {
                    const dt = l.created_at ? new Date((l.created_at || 0)*1000).toLocaleString('zh-CN', {hour12:false}) : '-';
                    return `<tr>
                        <td><small>${dt}</small></td>
                        <td>${l.nickname || ''} (UID:${l.uid})</td>
                        <td style="color:${l.score_change>=0?'#2ecc71':'#e74c3c'};font-weight:bold">${l.score_change>=0?'+':''}${l.score_change}</td>
                        <td>${l.score_before}</td>
                        <td>${l.score_after}</td>
                        <td>${l.reason||''}</td>
                        <td>${l.operator||''}</td>
                    </tr>`;
                }).join('');
            }
        }).catch(e => { tbody.innerHTML = '<tr><td colspan="7" class="wx-empty">加载出错</td></tr>'; });
}

// 修改积分按钮点击事件（仿 ddz 样式）
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-change-score');
    if (!btn) return;
    var uid = btn.getAttribute('data-uid');
    var score = btn.getAttribute('data-score');
    var nick = btn.getAttribute('data-nick');
    var change = prompt(`修改 ${nick}(UID:${uid}) 的积分\n当前: ${score}\n输入变化量（正数增加，负数减少）:`, '0');
    if (change === null || change === '0') return;
    var reason = prompt('修改原因:', '管理员手动调整') || '管理员手动调整';
    var form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    form.innerHTML = `<input name="${ACTION_KEY}" value="change_score"><input name="uid" value="${uid}"><input name="score_change" value="${change}"><input name="reason" value="${reason}">`;
    document.body.appendChild(form);
    form.submit();
});
</script>
<?php
    return ob_get_clean();
}
