<?php
/**
 * wx_games 斗地主函数模块
 * 从原 wx_ddz.php 移植的所有函数定义，保持逻辑不变。
 * 去除了 inline 路由和钩子注册，通过 wrapper 函数被 wx_games.php 调用。
 */
!defined('EMLOG_ROOT') && exit('access denied!');

// 斗地主常量
define('WX_DDZ_PLUGIN_NAME', 'wx_ddz');
define('WX_DDZ_PATH', WX_GAMES_PATH . 'games/ddz/');
define('WX_DDZ_URL', WX_GAMES_URL . 'games/ddz/');

function wx_ddz_get_plugin_url() { return WX_DDZ_URL; }

// ============================================================
// AJAX 路由分发（由 wx_games.php 调用）
// ============================================================
function wx_ddz_route_ajax($action) {
    header('Content-Type: application/json; charset=utf-8');

    // Referer 校验
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (!empty($ref)) {
        $ref_host = parse_url($ref, PHP_URL_HOST);
        $srv_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($ref_host && $srv_host && $ref_host !== $srv_host) {
            echo json_encode(['code' => -1, 'msg' => '非法请求'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    switch ($action) {
        case 'get_ranking':           wx_ddz_api_get_ranking();           break;
        case 'save_score':            wx_ddz_api_save_score();            break;
        case 'save_ai_score':         wx_ddz_api_save_ai_score();         break;
        case 'get_my_rank':           wx_ddz_api_get_my_rank();           break;
        case 'get_user_logs':         wx_ddz_api_get_user_logs();         break;
        case 'get_my_emlog_credits':  wx_ddz_api_get_my_emlog_credits();  break;
        case 'check_pending':         wx_ddz_api_check_pending();         break;
        case 'start_game':            wx_ddz_api_start_game();            break;
        case 'complete_game':         wx_ddz_api_complete_game();         break;
        case 'get_shop_items':        wx_ddz_api_get_shop_items();        break;
        case 'get_inventory':         wx_ddz_api_get_inventory();         break;
        case 'purchase_item':         wx_ddz_api_purchase_item();         break;
        case 'use_item':              wx_ddz_api_use_item();              break;
        case 'get_active_effects':    wx_ddz_api_get_active_effects();    break;
        case 'get_score_buff':        wx_ddz_api_get_score_buff();        break;
        case 'consume_score_buff':    wx_ddz_api_consume_score_buff();    break;
        case 'admin_get_inventory':   wx_ddz_api_admin_get_inventory();   break;
        case 'admin_give_item':       wx_ddz_api_admin_give_item();       break;
        case 'admin_remove_item':     wx_ddz_api_admin_remove_item();     break;
        default:
            echo json_encode(['code' => -1, 'msg' => '未知操作'], JSON_UNESCAPED_UNICODE);
            exit;
    }
}

// ============================================================
// 信号处理（由 wx_games.php 调用）
// ============================================================
function wx_ddz_handle_signal($signal) {
    $user = wx_ddz_check_user();
    if (!$user) return;
    $db = Database::getInstance();
    $suid = intval($user['uid']);
    $table_games = DB_PREFIX . 'wx_ddz_games';
    $now = time();

    if ($signal === 'start') {
        $nickname = $db->escape_string($user['nickname']);
        $db->query("INSERT INTO `" . $table_games . "`
            (`uid`, `nickname`, `score_change`, `result`, `status`, `created_at`)
            VALUES ($suid, '$nickname', 0, 'draw', 1, $now)");
    } elseif ($signal === 'end') {
        $db->query("UPDATE `" . $table_games . "` SET
            `status` = 0, `finished_at` = $now
            WHERE `uid` = $suid AND `status` = 1
            ORDER BY `id` DESC LIMIT 1");
    } elseif ($signal === 'penalty') {
        $penalty_points = isset($_GET['points']) ? intval($_GET['points']) : 100;
        $penalty_points = -abs($penalty_points);
        wx_ddz_apply_penalty($user['uid'], $penalty_points);
        $db->query("UPDATE `" . $table_games . "` SET
            `result` = 'lose', `score_change` = $penalty_points, `status` = 0, `finished_at` = $now
            WHERE `uid` = $suid AND `status` = 1
            ORDER BY `id` DESC LIMIT 1");
    }
}

// ============================================================
// 以下为原 wx_ddz.php 函数定义（完全保留，逻辑不变）
// ============================================================

function wx_ddz_get_config() {
    static $config = null;
    if ($config === null) {
        $defaults = [
            'title'              => 'H5 斗地主',
            'guest_play'         => '1',
            'ai_names'           => 'AI玩家1,AI玩家2',
            'max_entries'        => 100,
            'penalty_multiplier' => 1.0,
            'notice'             => '欢迎来到H5斗地主！游戏过程中请遵守规则，公平竞技。',
            'recent_updates'     => "v2.0.4 - 全量配色焕新：蓝紫色 → 赤陶橙#e17055 / 琥珀黄#fdcb6e / 朱红#d63031\nv2.0.4 - 游戏背景改为深暖红色系，导航栏日落暖阳渐变\nv2.0.4 - 后台卡片统一深灰（card-dark），去除所有EMOJI\nv2.0.4 - AI主题色同步更新，去除蓝色/紫色可选项\nv2.0.3 - 角色头像全面改用 emlog 官方机制（User::getAvatar），换头像即时刷新\nv2.0.3 - 排行榜昵称实时同步 emlog 用户表，改名后即生效\nv2.0.3 - 后台积分管理列表同步实时解析昵称+头像\nv2.0.2 - 后台积分管理新增背包管理（发放/扣除道具）\nv2.0.2 - 欢迎界面优化：去除登录提示、全宽开始按钮、商城/背包/充值三按钮等宽\nv2.0.2 - 新增充值按钮（后台配置跳转链接，留空隐藏）\nv2.0.2 - 积分卡提示移至开始按钮上方，无buff显示默认文案\nv2.0.2 - 全局配色调整为 #30557A 蓝色系\nv2.0.1 - Emlog积分改为站点积分，计算口径统一\nv2.0.1 - 商城描述支持2行展示，混合支付纯文字\nv2.0.0 - 新增道具商城系统（双货币/背包/激活制）\nv2.0.0 - 积分加成卡系统（手动激活/消耗次数）\nv2.0.0 - 昵称变色/特效/称号勋章/牌背皮肤/炸弹特效/专属表情\nv2.0.0 - 后台商城管理（商品设置/消耗统计/购买记录）\nv2.0.0 - 排行榜支持称号颜色和特效展示\nv2.0.0 - 粒子爆炸特效替代CSS炸弹特效\nv1.0.3 - AI新增台词气泡，后台支持台词编辑\nv1.0.3 - 后台AI设置页面优化为卡片网格布局\nv1.0.2 - 欢迎界面手动开始，新增公告与更新模块\nv1.0.2 - 修复飞机牌型Bug，后台UI改为卡片风格\nv1.0.2 - 新增积分管理（搜索/改分/流水）\nv1.0.1 - 新增防逃跑机制，惩罚倍率后台可调\nv1.0.0 - 正式上线，支持登录/游客双模式",
            'recharge_link'      => '',
        ];
        try {
            $storage = Storage::getInstance('wx_ddz');
            $saved = $storage->getValue('config');
            if (is_array($saved)) {
                $config = array_merge($defaults, $saved);
            } else {
                $config = [
                    'title'              => $storage->getValue('title')              ?: $defaults['title'],
                    'guest_play'         => $storage->getValue('guest_play')         ?: $defaults['guest_play'],
                    'max_entries'        => intval($storage->getValue('max_entries')) ?: $defaults['max_entries'],
                    'penalty_multiplier' => floatval($storage->getValue('penalty_multiplier') ?: $defaults['penalty_multiplier']),
                    'notice'             => $storage->getValue('notice')             ?: $defaults['notice'],
                    'recent_updates'     => $storage->getValue('recent_updates')     ?: $defaults['recent_updates'],
                ];
            }
        } catch (Throwable $e) {
            $config = $defaults;
        }
    }
    return $config;
}

function wx_ddz_get_ai_players() {
    static $ai_players = null;
    if ($ai_players === null) {
        $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
        try {
            $storage = Storage::getInstance('wx_ddz');
            $saved = $storage->getValue('ai_players');
            if (is_array($saved) && !empty($saved)) {
                $ai_players = $saved;
            }
        } catch (Throwable $e) {}
        if ($ai_players === null) {
            $config = wx_ddz_get_config();
            $names = isset($config['ai_names']) ? explode(',', $config['ai_names']) : ['AI玩家1', 'AI玩家2'];
            $ai_players = [];
            foreach ($names as $i => $name) {
                $name = trim($name);
                if (empty($name)) $name = 'AI玩家' . ($i + 1);
                $ai_players[] = [
                    'name'   => $name,
                    'avatar' => $avatar_files[$i % count($avatar_files)],
                ];
            }
        }
        foreach ($ai_players as &$player) {
            if (!isset($player['quotes']) || !is_array($player['quotes'])) {
                $player['quotes'] = [
                    'bomb'     => [], 'rocket'   => [], 'plane'    => [],
                    'straight' => [], 'bigCard'  => [], 'bid'      => [],
                    'pass'     => [], 'win'      => [], 'lose'     => []
                ];
            }
        }
        unset($player);
    }
    return $ai_players;
}

function wx_ddz_check_user() {
    $uid = 0;
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    if (defined('UID') && UID > 0) { $uid = intval(UID); }
    elseif (!empty($_SESSION['user']['uid'])) { $uid = intval($_SESSION['user']['uid']); }
    elseif (!empty($_SESSION['uid'])) { $uid = intval($_SESSION['uid']); }
    if ($uid == 0 && function_exists('ro_get_current_user')) {
        $u = ro_get_current_user();
        if (!empty($u['uid'])) { $uid = intval($u['uid']); }
    }
    if ($uid > 0) {
        $db = Database::getInstance();
        $row = $db->once_fetch_array("SELECT uid, nickname, photo FROM `" . DB_PREFIX . "user` WHERE uid = $uid LIMIT 1");
        if ($row) {
            $avatar = '';
            if (class_exists('User') && method_exists('User', 'getAvatar')) {
                $avatar = User::getAvatar($row['photo']);
            } elseif (!empty($row['photo'])) {
                $avatar = filter_var($row['photo'], FILTER_VALIDATE_URL)
                    ? $row['photo']
                    : BLOG_URL . str_replace('../', '', $row['photo']);
            } else {
                $avatar = BLOG_URL . 'admin/views/images/avatar.svg';
            }
            return ['uid' => (int)$row['uid'], 'nickname' => $row['nickname'], 'avatar' => $avatar];
        }
    }
    return null;
}

function wx_ddz_get_user_score($uid, $is_ai = 0) {
    try {
        $db = Database::getInstance();
        $uid = intval($uid);
        $is_ai = intval($is_ai);
        $table = DB_PREFIX . 'wx_ddz_scores';
        $row = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `uid` = $uid AND `is_ai` = $is_ai LIMIT 1");
        if ($row) {
            if ($is_ai === 0) {
                $user = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
                $nickname = $user ? $user['nickname'] : $row['nickname'];
                $avatar   = wx_ddz_resolve_avatar($uid, $user ? $user['photo'] : null);
            } else {
                $nickname = $row['nickname'];
                $avatar   = $row['avatar'];
            }
            return [
                'id'          => (int)$row['id'],
                'uid'         => $uid,
                'nickname'    => $nickname,
                'avatar'      => $avatar,
                'score'       => (int)$row['score'],
                'total_games' => (int)$row['total_games'],
                'wins'        => (int)$row['wins'],
                'losses'      => (int)$row['losses'],
                'draws'       => (int)$row['draws'],
                'best_score'  => (int)$row['best_score'],
                'is_ai'       => (int)$row['is_ai'],
            ];
        }
    } catch (Throwable $e) {}
    return null;
}

function wx_ddz_get_leaderboard($limit = 20, $include_ai = true) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_ddz_scores';
        $table_inv = DB_PREFIX . 'wx_ddz_user_items';
        $table_items = DB_PREFIX . 'wx_ddz_shop_items';
        $where_sub = $include_ai ? '' : 'WHERE `is_ai` = 0';
        $where_ai_check = $include_ai ? '' : ' AND sc.`is_ai` = 0';
        $result = $db->query("
            SELECT sc.*, si.`item_type`, si.`effect_data`
            FROM (SELECT * FROM `" . $table . "` " . $where_sub . " ORDER BY `score` DESC LIMIT " . intval($limit) . ") sc
            LEFT JOIN `" . $table_inv . "` i ON i.`uid` = sc.`uid` AND i.`is_active` = 1 " . $where_ai_check . "
            LEFT JOIN `" . $table_items . "` si ON si.`id` = i.`item_id` AND si.`item_type` IN ('title_colored','title_effect','title_badge')
            ORDER BY sc.`score` DESC");
        $entries = [];
        $rank = 1;
        $user_map = [];
        while ($row = $db->fetch_array($result)) {
            $uid = (int)$row['uid'];
            if (!isset($user_map[$uid])) {
                $user_map[$uid] = [
                    'rank'        => $rank++,
                    'uid'         => $uid,
                    'nickname'    => $row['nickname'],
                    'avatar'      => $row['avatar'],
                    'score'       => (int)$row['score'],
                    'total_games' => (int)$row['total_games'],
                    'wins'        => (int)$row['wins'],
                    'losses'      => (int)$row['losses'],
                    'draws'       => (int)$row['draws'],
                    'best_score'  => (int)$row['best_score'],
                    'is_ai'       => (int)$row['is_ai'],
                    'active_effects' => [],
                ];
            }
            if ($row['item_type'] && $row['effect_data']) {
                $user_map[$uid]['active_effects'][] = [
                    'type' => $row['item_type'],
                    'data' => stripslashes($row['effect_data']),
                ];
            }
        }
        $real_uids = [];
        foreach ($user_map as $uid => $entry) {
            if ((int)$entry['is_ai'] === 0) { $real_uids[] = $uid; }
        }
        if (!empty($real_uids)) {
            $uid_list = implode(',', array_map('intval', $real_uids));
            $db = Database::getInstance();
            $real_rows = $db->query("SELECT `uid`, `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` IN ($uid_list)");
            while ($ru = $db->fetch_array($real_rows)) {
                $ruid = (int)$ru['uid'];
                if (isset($user_map[$ruid])) {
                    $user_map[$ruid]['nickname'] = $ru['nickname'];
                    $user_map[$ruid]['avatar']   = wx_ddz_resolve_avatar($ruid, $ru['photo']);
                }
            }
        }
        foreach ($user_map as $entry) { $entries[] = $entry; }
        return $entries;
    } catch (Throwable $e) { return []; }
}

function wx_ddz_resolve_avatar($uid, $photo = null) {
    $uid = intval($uid);
    if ($uid <= 0) { return BLOG_URL . 'admin/views/images/avatar.svg'; }
    if ($photo === null) {
        $db = Database::getInstance();
        $row = $db->once_fetch_array("SELECT `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
        $photoPath = $row ? $row['photo'] : '';
    } else { $photoPath = $photo; }
    if (class_exists('User') && method_exists('User', 'getAvatar')) { return User::getAvatar($photoPath); }
    if (!empty($photoPath)) {
        return filter_var($photoPath, FILTER_VALIDATE_URL) ? $photoPath : BLOG_URL . str_replace('../', '', $photoPath);
    }
    return BLOG_URL . 'admin/views/images/avatar.svg';
}

function wx_ddz_save_score($uid, $nickname, $avatar, $score_change, $result, $is_ai = 0) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_ddz_scores';
        $now = time();
        $uid = intval($uid);
        $score_change = intval($score_change);
        $is_ai = intval($is_ai);
        $nickname = $db->escape_string(addslashes(trim($nickname)));
        $avatar = $db->escape_string(addslashes(trim($avatar)));
        $result = ($result === 'win' || $result === 'lose') ? $result : 'draw';
        $existing = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `uid` = $uid AND `is_ai` = $is_ai LIMIT 1");
        $table_games = DB_PREFIX . 'wx_ddz_games';
        $now = time();
        if ($is_ai == 0) {
            $db->query("UPDATE `" . $table_games . "` SET
                `status` = 0, `finished_at` = $now,
                `result` = '" . $result . "', `score_change` = $score_change
                WHERE `uid` = $uid AND `status` = 1
                ORDER BY `id` DESC LIMIT 1");
        }
        $table_logs = DB_PREFIX . 'wx_ddz_logs';
        $result_label = $result === 'win' ? '胜' : ($result === 'lose' ? '负' : '平');
        $reason = '游戏结算（' . $result_label . '）';
        if ($existing) {
            $score_before = (int)$existing['score'];
            $new_score   = $score_before + $score_change;
            $total_games = (int)$existing['total_games'] + 1;
            $wins        = (int)$existing['wins']        + ($result === 'win'  ? 1 : 0);
            $losses      = (int)$existing['losses']      + ($result === 'lose' ? 1 : 0);
            $draws       = (int)$existing['draws']       + ($result === 'draw' ? 1 : 0);
            $best_score  = max((int)$existing['best_score'], $new_score);
            $db->query("UPDATE `" . $table . "` SET
                `score` = $new_score, `total_games` = $total_games,
                `wins` = $wins, `losses` = $losses, `draws` = $draws,
                `best_score` = $best_score, `updated_at` = $now
                WHERE `id` = " . intval($existing['id']));
            $db->query("INSERT INTO `" . $table_logs . "`
                (`uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
                VALUES ($uid, '$nickname', $score_change, $score_before, $new_score, '$reason', 'system', $now)");
            return ['success' => true, 'msg' => '保存成功', 'score' => $new_score];
        } else {
            $score_before = 0;
            $wins   = $result === 'win'  ? 1 : 0;
            $losses = $result === 'lose' ? 1 : 0;
            $draws  = $result === 'draw' ? 1 : 0;
            $best_score = $score_change;
            $db->query("INSERT INTO `" . $table . "`
                (`uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `best_score`, `is_ai`, `updated_at`, `created_at`)
                VALUES ($uid, '" . $nickname . "', '" . $avatar . "', $score_change, 1, $wins, $losses, $draws, $best_score, $is_ai, $now, $now)");
            $db->query("INSERT INTO `" . $table_logs . "`
                (`uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
                VALUES ($uid, '$nickname', $score_change, $score_before, $score_change, '$reason', 'system', $now)");
            return ['success' => true, 'msg' => '保存成功', 'score' => $score_change];
        }
    } catch (Throwable $e) {
        return ['success' => false, 'msg' => '保存失败: ' . $e->getMessage(), 'score' => 0];
    }
}

function wx_ddz_apply_penalty($uid, $penalty_score) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_ddz_scores';
        $table_logs = DB_PREFIX . 'wx_ddz_logs';
        $uid = intval($uid);
        $penalty_score = intval($penalty_score);
        $now = time();
        $existing = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `uid` = $uid AND `is_ai` = 0 LIMIT 1");
        if ($existing) {
            $score_before = (int)$existing['score'];
            $new_score = $score_before + $penalty_score;
            $nickname = $existing['nickname'];
            $db->query("UPDATE `" . $table . "` SET `score` = $new_score, `updated_at` = $now WHERE `id` = " . intval($existing['id']));
        } else {
            $score_before = 0;
            $new_score = $penalty_score;
            $nickname = '';
            $db->query("INSERT INTO `" . $table . "`
                (`uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `best_score`, `is_ai`, `updated_at`, `created_at`)
                VALUES ($uid, '', '', $penalty_score, 1, 0, 1, 0, $penalty_score, 0, $now, $now)");
        }
        $nickname_esc = $db->escape_string($nickname);
        $db->query("INSERT INTO `" . $table_logs . "`
            (`uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
            VALUES ($uid, '$nickname_esc', $penalty_score, $score_before, $new_score, '逃跑惩罚（超时未完成）', 'system', $now)");
        $row = $db->once_fetch_array("SELECT `score` FROM `" . $table . "` WHERE `uid` = $uid AND `is_ai` = 0 LIMIT 1");
        return intval($row['score'] ?? 0);
    } catch (Throwable $e) { return 0; }
}

function wx_ddz_admin_change_score($uid, $score_change, $reason = '', $operator = '') {
    try {
        $db = Database::getInstance();
        $table_scores = DB_PREFIX . 'wx_ddz_scores';
        $table_logs  = DB_PREFIX . 'wx_ddz_logs';
        $now = time();
        $uid = intval($uid);
        $score_change = intval($score_change);
        $row = $db->once_fetch_array("SELECT * FROM `" . $table_scores . "` WHERE `uid` = $uid AND `is_ai` = 0 LIMIT 1");
        if (!$row) return false;
        $score_before = (int)$row['score'];
        $score_after  = $score_before + $score_change;
        $nickname_esc = $db->escape_string($row['nickname']);
        $reason_esc   = $db->escape_string(addslashes(trim($reason)));
        $operator_esc = $db->escape_string(addslashes(trim($operator)));
        $db->query("UPDATE `" . $table_scores . "` SET `score` = $score_after, `updated_at` = $now WHERE `id` = " . intval($row['id']));
        $db->query("INSERT INTO `" . $table_logs . "`
            (`uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
            VALUES ($uid, '" . $nickname_esc . "', $score_change, $score_before, $score_after, '" . $reason_esc . "', '" . $operator_esc . "', $now)");
        return true;
    } catch (Throwable $e) { return false; }
}

// ============================================================
// API 实现 - 每个 action 对应一个函数
// ============================================================

function wx_ddz_api_get_ranking() {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $entries = wx_ddz_get_leaderboard($limit);
    echo json_encode(['code' => 0, 'data' => ['entries' => $entries]], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_save_score() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $score_change = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $result = isset($_POST['result']) ? addslashes(trim($_POST['result'])) : 'draw';
    $save_result = wx_ddz_save_score($current_user['uid'], $current_user['nickname'], $current_user['avatar'], $score_change, $result);
    if ($save_result['success']) {
        echo json_encode(['code' => 0, 'msg' => '保存成功', 'score' => $save_result['score']], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['code' => -1, 'msg' => $save_result['msg']], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_ddz_api_save_ai_score() {
    $nickname    = isset($_POST['nickname']) ? addslashes(trim($_POST['nickname'])) : 'AI';
    $avatar       = isset($_POST['avatar'])   ? addslashes(trim($_POST['avatar']))    : '';
    $score_change = isset($_POST['score'])    ? intval($_POST['score'])             : 0;
    $result       = isset($_POST['result'])   ? addslashes(trim($_POST['result']))   : 'draw';
    $ai_uid       = abs(crc32($nickname)) % 1000000 + 1000000;
    $save_result  = wx_ddz_save_score($ai_uid, $nickname, $avatar, $score_change, $result, 1);
    if ($save_result['success']) {
        echo json_encode(['code' => 0, 'msg' => 'AI分数已保存'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['code' => -1, 'msg' => $save_result['msg']], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_ddz_api_get_my_rank() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $data = wx_ddz_get_user_score($current_user['uid']);
    echo json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_get_user_logs() {
    $target_uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($target_uid <= 0) { echo json_encode(['code' => -1, 'msg' => '无效的用户ID'], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $table_logs = DB_PREFIX . 'wx_ddz_logs';
    $result = $db->query("SELECT * FROM `$table_logs` WHERE `uid` = $target_uid ORDER BY `created_at` DESC LIMIT 50");
    $log_entries = [];
    while ($row = $db->fetch_array($result)) {
        $log_entries[] = [
            'score_change' => (int)$row['score_change'],
            'score_before'  => (int)$row['score_before'],
            'score_after'   => (int)$row['score_after'],
            'reason'        => $row['reason'],
            'operator'      => $row['operator'],
            'time'          => date('Y-m-d H:i', (int)$row['created_at']),
        ];
    }
    echo json_encode(['code' => 0, 'data' => $log_entries], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_get_my_emlog_credits() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'credits' => 0], JSON_UNESCAPED_UNICODE); exit; }
    try {
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser(intval($current_user['uid']));
        $credits = ($emlog_user && isset($emlog_user['credits'])) ? intval($emlog_user['credits']) : 0;
        echo json_encode(['code' => 0, 'credits' => $credits], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) { echo json_encode(['code' => -1, 'credits' => 0], JSON_UNESCAPED_UNICODE); }
    exit;
}

function wx_ddz_api_check_pending() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => 0, 'pending' => false], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $uid = intval($current_user['uid']);
    $table = DB_PREFIX . 'wx_ddz_games';
    $row = $db->once_fetch_array(
        "SELECT `id`, `score_change`, `multiplier`, `bomb_count`, `is_spring`, `created_at`, `game_token`
         FROM `" . $table . "` WHERE `uid` = $uid AND `status` = 1 AND `result` = 'pending'
         ORDER BY `id` DESC LIMIT 1"
    );
    if ($row) {
        $elapsed = time() - intval($row['created_at']);
        if ($elapsed > 300) {
            $penalty = -20;
            $now = time();
            $db->query("UPDATE `" . $table . "` SET
                `result` = 'lose', `score_change` = $penalty, `status` = 0, `finished_at` = $now
                WHERE `id` = " . intval($row['id']));
            wx_ddz_apply_penalty($uid, $penalty);
            echo json_encode(['code' => 0, 'pending' => false, 'penalty_applied' => true,
                'penalty_score' => $penalty, 'msg' => '因长时间未完成对局，已扣除 ' . abs($penalty) . ' 积分'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['code' => 0, 'pending' => true, 'game_id' => intval($row['id']),
            'game_token' => $row['game_token'], 'msg' => '有未完成的游戏，继续完成可免于惩罚'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['code' => 0, 'pending' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_start_game() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $uid = intval($current_user['uid']);
    $nickname = $db->escape_string(addslashes(trim($current_user['nickname'])));
    $table = DB_PREFIX . 'wx_ddz_games';
    $now = time();
    $existing = $db->once_fetch_array("SELECT `id` FROM `" . $table . "` WHERE `uid` = $uid AND `status` = 1 AND `result` = 'pending' LIMIT 1");
    if ($existing) {
        echo json_encode(['code' => -2, 'msg' => '有未完成的游戏，请先完成或等待超时',
            'game_id' => intval($existing['id'])], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $token = bin2hex(random_bytes(16));
    $db->query("INSERT INTO `" . $table . "`
        (`uid`, `nickname`, `score_change`, `result`, `game_token`, `status`, `created_at`)
        VALUES ($uid, '" . $nickname . "', 0, 'pending', '" . $token . "', 1, " . $now . ")");
    $game_id = $db->insert_id();
    echo json_encode(['code' => 0, 'game_id' => $game_id, 'game_token' => $token, 'msg' => '游戏开始'], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_complete_game() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $game_id     = isset($_POST['game_id'])     ? intval($_POST['game_id'])     : 0;
    $token        = isset($_POST['game_token'])  ? addslashes(trim($_POST['game_token'])) : '';
    $score_change = isset($_POST['score_change']) ? intval($_POST['score_change']) : 0;
    $result       = isset($_POST['result'])       ? addslashes(trim($_POST['result']))      : 'draw';
    $is_landlord = isset($_POST['is_landlord']) ? intval($_POST['is_landlord']) : 0;
    $multiplier   = isset($_POST['multiplier'])   ? intval($_POST['multiplier'])   : 1;
    $bomb_count  = isset($_POST['bomb_count'])  ? intval($_POST['bomb_count'])  : 0;
    $is_spring    = isset($_POST['is_spring'])    ? intval($_POST['is_spring'])    : 0;

    if ($game_id <= 0) { echo json_encode(['code' => -1, 'msg' => '无效的对局ID'], JSON_UNESCAPED_UNICODE); exit; }

    $db = Database::getInstance();
    $uid = intval($current_user['uid']);
    $table = DB_PREFIX . 'wx_ddz_games';
    $now = time();

    $check = $db->once_fetch_array("SELECT `id` FROM `" . $table . "` WHERE `id` = $game_id AND `uid` = $uid AND `game_token` = '" . $token . "' AND `status` = 1 LIMIT 1");
    if (!$check) { echo json_encode(['code' => -1, 'msg' => '对局验证失败，请重新开始'], JSON_UNESCAPED_UNICODE); exit; }

    $db->query("UPDATE `" . $table . "` SET
        `result` = '" . $result . "', `score_change` = $score_change,
        `is_landlord` = $is_landlord, `multiplier` = $multiplier,
        `bomb_count` = $bomb_count, `is_spring` = $is_spring,
        `status` = 0, `finished_at` = $now
        WHERE `id` = $game_id AND `uid` = $uid AND `game_token` = '" . $token . "' AND `status` = 1");

    $score_table = DB_PREFIX . 'wx_ddz_scores';
    $score_row = $db->once_fetch_array("SELECT * FROM `" . $score_table . "` WHERE `uid` = $uid AND `is_ai` = 0 LIMIT 1");
    if ($score_row) {
        $new_score   = (int)$score_row['score']      + $score_change;
        $new_wins   = (int)$score_row['wins']       + ($result === 'win'  ? 1 : 0);
        $new_losses = (int)$score_row['losses']     + ($result === 'lose' ? 1 : 0);
        $new_draws  = (int)$score_row['draws']      + ($result === 'draw' ? 1 : 0);
        $new_total   = (int)$score_row['total_games'] + 1;
        $best_score  = max((int)$score_row['best_score'], $new_score);
        $db->query("UPDATE `" . $score_table . "` SET
            `score` = $new_score, `total_games` = $new_total,
            `wins` = $new_wins, `losses` = $new_losses, `draws` = $new_draws,
            `best_score` = $best_score, `updated_at` = $now
            WHERE `id` = " . intval($score_row['id']));
    } else {
        $wins   = ($result === 'win')  ? 1 : 0;
        $losses = ($result === 'lose') ? 1 : 0;
        $draws  = ($result === 'draw') ? 1 : 0;
        $best   = $score_change > 0 ? $score_change : 0;
        $nickname_esc = $db->escape_string(addslashes(trim($current_user['nickname'])));
        $db->query("INSERT INTO `" . $score_table . "`
            (`uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `best_score`, `is_ai`, `updated_at`, `created_at`)
            VALUES ($uid, '" . $nickname_esc . "', '', $score_change, 1, $wins, $losses, $draws, $best, 0, $now, $now)");
        $new_score = $score_change;
    }
    echo json_encode(['code' => 0, 'msg' => '游戏记录已保存', 'score' => $new_score], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_get_shop_items() {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_ddz_shop_items';
    $result = $db->query("SELECT * FROM `" . $table . "` WHERE `status` = 1 ORDER BY `sort_order` ASC, `id` ASC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $items[] = [
            'id'            => (int)$row['id'],
            'name'          => $row['name'],
            'description'   => $row['description'],
            'icon'          => $row['icon'],
            'item_type'     => $row['item_type'],
            'effect_data'   => stripslashes($row['effect_data']),
            'price_emlog'   => (int)$row['price_emlog'],
            'price_ddz'     => (int)$row['price_ddz'],
            'stock'         => (int)$row['stock'],
            'max_per_user'  => (int)$row['max_per_user'],
        ];
    }
    echo json_encode(['code' => 0, 'data' => ['items' => $items]], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_get_inventory() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($current_user['uid']);
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $result = $db->query("
        SELECT i.*, s.`name`, s.`icon`, s.`item_type`, s.`effect_data`
        FROM `" . $table_inv . "` i
        JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`quantity` > i.`used`
        ORDER BY i.`purchased_at` DESC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $expired = $row['expires_at'] > 0 && $row['expires_at'] < time();
        if ($expired) continue;
        $items[] = [
            'inv_id'       => (int)$row['id'], 'item_id' => (int)$row['item_id'],
            'name'         => $row['name'], 'icon' => $row['icon'],
            'item_type'    => $row['item_type'],
            'effect_data'  => stripslashes($row['effect_data']),
            'quantity'     => max(0, (int)$row['quantity'] - (int)$row['used']),
            'is_active'    => (int)$row['is_active'],
        ];
    }
    echo json_encode(['code' => 0, 'data' => ['items' => $items]], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_purchase_item() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($current_user['uid']);
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $pay_type = isset($_POST['pay_type']) ? addslashes(trim($_POST['pay_type'])) : '';
    if ($item_id <= 0 || !in_array($pay_type, ['emlog', 'ddz'], true)) {
        echo json_encode(['code' => -1, 'msg' => '参数错误'], JSON_UNESCAPED_UNICODE); exit;
    }
    $db = Database::getInstance();
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $item = $db->once_fetch_array("SELECT * FROM `" . $table_items . "` WHERE `id` = $item_id AND `status` = 1 LIMIT 1");
    if (!$item) { echo json_encode(['code' => -1, 'msg' => '商品不存在或已下架'], JSON_UNESCAPED_UNICODE); exit; }
    $price_emlog = (int)$item['price_emlog'];
    $price_ddz   = (int)$item['price_ddz'];
    if ($pay_type === 'emlog' && $price_emlog <= 0) { echo json_encode(['code' => -1, 'msg' => '该商品不支持站点积分购买'], JSON_UNESCAPED_UNICODE); exit; }
    if ($pay_type === 'ddz' && $price_ddz <= 0) { echo json_encode(['code' => -1, 'msg' => '该商品不支持斗地主积分购买'], JSON_UNESCAPED_UNICODE); exit; }
    $stock = (int)$item['stock'];
    if ($stock !== -1 && $stock <= 0) { echo json_encode(['code' => -1, 'msg' => '商品已售罄'], JSON_UNESCAPED_UNICODE); exit; }
    $max_per_user = (int)$item['max_per_user'];
    if ($max_per_user > 0) {
        $table_inv = DB_PREFIX . 'wx_ddz_user_items';
        $owned = $db->once_fetch_array("SELECT SUM(`quantity` - `used`) AS cnt FROM `" . $table_inv . "` WHERE `uid` = $uid AND `item_id` = $item_id LIMIT 1");
        $current_cnt = intval($owned['cnt'] ?? 0);
        if ($current_cnt >= $max_per_user) { echo json_encode(['code' => -1, 'msg' => '已达限购数量'], JSON_UNESCAPED_UNICODE); exit; }
    }
    if ($pay_type === 'both') {
        $userModel = new User_Model();
        $user = $userModel->getOneUser($uid);
        $credits = isset($user['credits']) ? intval($user['credits']) : 0;
        if ($credits < $price_emlog) { echo json_encode(['code' => -1, 'msg' => '站点积分不足，需要' . $price_emlog . '积分'], JSON_UNESCAPED_UNICODE); exit; }
        $score_data = wx_ddz_get_user_score($uid);
        $ddz_score = $score_data ? (int)$score_data['score'] : 0;
        if ($ddz_score < $price_ddz) { echo json_encode(['code' => -1, 'msg' => '斗地主积分不足，需要' . $price_ddz . '积分'], JSON_UNESCAPED_UNICODE); exit; }
        $userModel->reduceCredits($uid, $price_emlog);
        if (function_exists('addCreditRecord')) {
            $game_title = (wx_ddz_get_config()['title'] ?? 'H5斗地主');
            addCreditRecord($uid, 'reduce', $price_emlog, $game_title . '_buy_' . $item['name'] . '_' . time());
        }
        wx_ddz_admin_change_score($uid, -$price_ddz, '商城购买：' . $item['name'], 'system');
    } elseif ($pay_type === 'emlog') {
        $userModel = new User_Model();
        $user = $userModel->getOneUser($uid);
        $credits = isset($user['credits']) ? intval($user['credits']) : 0;
        if ($credits < $price_emlog) { echo json_encode(['code' => -1, 'msg' => '站点积分不足，需要' . $price_emlog . '积分'], JSON_UNESCAPED_UNICODE); exit; }
        $userModel->reduceCredits($uid, $price_emlog);
        if (function_exists('addCreditRecord')) {
            $game_title = (wx_ddz_get_config()['title'] ?? 'H5斗地主');
            addCreditRecord($uid, 'reduce', $price_emlog, $game_title . '_buy_' . $item['name'] . '_' . time());
        }
    } else {
        $score_data = wx_ddz_get_user_score($uid);
        $ddz_score = $score_data ? (int)$score_data['score'] : 0;
        if ($ddz_score < $price_ddz) { echo json_encode(['code' => -1, 'msg' => '斗地主积分不足，需要' . $price_ddz . '积分'], JSON_UNESCAPED_UNICODE); exit; }
        wx_ddz_admin_change_score($uid, -$price_ddz, '商城购买：' . $item['name'], 'system');
    }
    $now = time();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $existing = $db->once_fetch_array("SELECT `id`, `quantity` FROM `" . $table_inv . "` WHERE `uid` = $uid AND `item_id` = $item_id LIMIT 1");
    if ($existing) {
        $db->query("UPDATE `" . $table_inv . "` SET `quantity` = `quantity` + 1, `purchased_at` = $now WHERE `id` = " . intval($existing['id']));
    } else {
        $db->query("INSERT INTO `" . $table_inv . "` (`uid`, `item_id`, `quantity`, `used`, `purchased_at`, `expires_at`)
            VALUES ($uid, $item_id, 1, 0, $now, 0)");
    }
    if ($stock !== -1) {
        $db->query("UPDATE `" . $table_items . "` SET `stock` = `stock` - 1 WHERE `id` = $item_id AND `stock` > 0");
    }
    echo json_encode(['code' => 0, 'msg' => '购买成功！'], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_use_item() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($current_user['uid']);
    $inv_id = isset($_POST['inv_id']) ? intval($_POST['inv_id']) : 0;
    if ($inv_id <= 0) { echo json_encode(['code' => -1, 'msg' => '参数错误'], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $row = $db->once_fetch_array("
        SELECT i.*, s.`item_type`, s.`effect_data` FROM `" . $table_inv . "` i
        JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`id` = $inv_id AND i.`uid` = $uid AND i.`quantity` > i.`used` LIMIT 1");
    if (!$row) { echo json_encode(['code' => -1, 'msg' => '道具不存在或已用完'], JSON_UNESCAPED_UNICODE); exit; }
    $item_type = $row['item_type'];
    $cosmetic_types = ['title_colored', 'title_effect', 'card_back', 'emoticon', 'bomb_effect', 'title_badge'];
    if (in_array($item_type, $cosmetic_types, true)) {
        $db->query("UPDATE `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = '" . $db->escape_string($item_type) . "'");
        $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1 WHERE `id` = " . intval($row['id']));
        echo json_encode(['code' => 0, 'msg' => '✅ 已激活', 'item_type' => $item_type, 'effect_data' => $row['effect_data']], JSON_UNESCAPED_UNICODE);
    } elseif ($item_type === 'score_buff') {
        $effect = json_decode(stripslashes($row['effect_data']), true);
        $multiplier = isset($effect['multiplier']) ? floatval($effect['multiplier']) : 2;
        $games = isset($effect['games']) ? intval($effect['games']) : 3;
        if ($multiplier <= 0) $multiplier = 2;
        if ($games <= 0) $games = 3;
        $db->query("UPDATE `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = 'score_buff'");
        $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1, `charges` = $games, `used` = 0 WHERE `id` = " . intval($row['id']));
        echo json_encode(['code' => 0, 'msg' => '✅ ' . $multiplier . '倍加成已激活，剩余' . $games . '局', 'multiplier' => $multiplier, 'games' => $games], JSON_UNESCAPED_UNICODE);
    } else {
        $db->query("UPDATE `" . $table_inv . "` SET `used` = `used` + 1 WHERE `id` = " . intval($row['id']));
        echo json_encode(['code' => 0, 'msg' => '✅ 使用成功'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_ddz_api_get_active_effects() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => 0, 'data' => []], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($current_user['uid']);
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
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

function wx_ddz_api_get_score_buff() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => 0, 'buffs' => []], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($current_user['uid']);
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $row = $db->once_fetch_array("
        SELECT i.`id`, i.`charges`, i.`used`, s.`effect_data`
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`is_active` = 1 AND s.`item_type` = 'score_buff' LIMIT 1");
    $buffs = [];
    if ($row) {
        $remaining = (int)$row['charges'] - (int)$row['used'];
        if ($remaining > 0) {
            $effect = json_decode(stripslashes($row['effect_data']), true);
            $buffs[] = ['multiplier' => isset($effect['multiplier']) ? floatval($effect['multiplier']) : 2, 'remaining' => $remaining];
        } else {
            $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 0, `used` = 0, `charges` = 0,
                `quantity` = GREATEST(`quantity` - 1, 0) WHERE `id` = " . (int)$row['id']);
        }
    }
    echo json_encode(['code' => 0, 'buffs' => $buffs], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_consume_score_buff() {
    $current_user = wx_ddz_check_user();
    if (!$current_user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($current_user['uid']);
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $row = $db->once_fetch_array("
        SELECT i.`id`, i.`charges`, i.`used`, s.`effect_data`
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`is_active` = 1 AND s.`item_type` = 'score_buff' LIMIT 1");
    if (!$row) { echo json_encode(['code' => 0, 'multiplier' => 1, 'remaining_buffs' => []]); exit; }
    $remaining = (int)$row['charges'] - (int)$row['used'] - 1;
    $effect = json_decode(stripslashes($row['effect_data']), true);
    $consumed_multiplier = isset($effect['multiplier']) ? floatval($effect['multiplier']) : 2;
    $db->query("UPDATE `" . $table_inv . "` SET `used` = `used` + 1 WHERE `id` = " . (int)$row['id']);
    if ($remaining <= 0) {
        $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 0, `used` = 0, `charges` = 0,
            `quantity` = GREATEST(`quantity` - 1, 0) WHERE `id` = " . (int)$row['id']);
    }
    $remaining_buffs = [];
    if ($remaining > 0) { $remaining_buffs[] = ['multiplier' => $consumed_multiplier, 'remaining' => $remaining]; }
    echo json_encode(['code' => 0, 'multiplier' => $consumed_multiplier, 'remaining_buffs' => $remaining_buffs]);
    exit;
}

function wx_ddz_api_admin_get_inventory() {
    if (!function_exists('LoginAuth') || !LoginAuth::isLogin()) { echo json_encode(['code' => -1, 'msg' => '无权限']); exit; }
    $admin_uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($admin_uid <= 0) { echo json_encode(['code' => -1, 'msg' => '无效的用户ID'], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $result = $db->query("SELECT i.*, s.`name`, s.`icon`, s.`item_type`, s.`effect_data`
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $admin_uid ORDER BY i.`purchased_at` DESC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $items[] = ['inv_id' => (int)$row['id'], 'item_id' => (int)$row['item_id'],
            'name' => $row['name'], 'icon' => $row['icon'], 'item_type' => $row['item_type'],
            'effect_data' => stripslashes($row['effect_data']),
            'quantity' => (int)$row['quantity'], 'used' => (int)$row['used'], 'is_active' => (int)$row['is_active']];
    }
    echo json_encode(['code' => 0, 'data' => ['items' => $items]], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_admin_give_item() {
    if (!function_exists('LoginAuth') || !LoginAuth::isLogin()) { echo json_encode(['code' => -1, 'msg' => '无权限']); exit; }
    $admin_uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
    if ($admin_uid <= 0 || $item_id <= 0) { echo json_encode(['code' => -1, 'msg' => '参数无效'], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $table_items = DB_PREFIX . 'wx_ddz_shop_items';
    $item_row = $db->once_fetch_array("SELECT `id`, `name` FROM `" . $table_items . "` WHERE `id` = $item_id LIMIT 1");
    if (!$item_row) { echo json_encode(['code' => -1, 'msg' => '商品不存在'], JSON_UNESCAPED_UNICODE); exit; }
    $existing = $db->once_fetch_array("SELECT `id`, `quantity` FROM `" . $table_inv . "` WHERE `uid` = $admin_uid AND `item_id` = $item_id LIMIT 1");
    if ($existing) {
        $db->query("UPDATE `" . $table_inv . "` SET `quantity` = `quantity` + $quantity WHERE `id` = " . (int)$existing['id']);
    } else {
        $now = time();
        $db->query("INSERT INTO `" . $table_inv . "` (`uid`, `item_id`, `quantity`, `used`, `is_active`, `purchased_at`, `expires_at`)
            VALUES ($admin_uid, $item_id, $quantity, 0, 0, $now, 0)");
    }
    echo json_encode(['code' => 0, 'msg' => '发放成功'], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_ddz_api_admin_remove_item() {
    if (!function_exists('LoginAuth') || !LoginAuth::isLogin()) { echo json_encode(['code' => -1, 'msg' => '无权限']); exit; }
    $admin_uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
    if ($admin_uid <= 0 || $item_id <= 0) { echo json_encode(['code' => -1, 'msg' => '参数无效'], JSON_UNESCAPED_UNICODE); exit; }
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_ddz_user_items';
    $existing = $db->once_fetch_array("SELECT `id`, `quantity`, `used` FROM `" . $table_inv . "` WHERE `uid` = $admin_uid AND `item_id` = $item_id LIMIT 1");
    if (!$existing) { echo json_encode(['code' => -1, 'msg' => '该玩家没有此道具'], JSON_UNESCAPED_UNICODE); exit; }
    $current_qty = (int)$existing['quantity'];
    if ($current_qty <= 0) { echo json_encode(['code' => -1, 'msg' => '库存已为0'], JSON_UNESCAPED_UNICODE); exit; }
    $remove_qty = min($quantity, $current_qty);
    $new_qty = $current_qty - $remove_qty;
    if ($new_qty <= 0) {
        $db->query("DELETE FROM `" . $table_inv . "` WHERE `id` = " . (int)$existing['id']);
    } else {
        $db->query("UPDATE `" . $table_inv . "` SET `quantity` = $new_qty WHERE `id` = " . (int)$existing['id']);
    }
    echo json_encode(['code' => 0, 'msg' => '扣除成功'], JSON_UNESCAPED_UNICODE);
    exit;
}
