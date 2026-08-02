<?php
/**
 * wx_games 麻将函数模块
 * 从原 wx_mojang.php 移植的所有函数定义，保持逻辑不变。
 * 去除了 inline 路由和钩子注册，通过 wrapper 函数被 wx_games.php 调用。
 */
!defined('EMLOG_ROOT') && exit('access denied!');

// 麻将常量
define('WX_MOJANG_PLUGIN_NAME', 'wx_mojang');
define('WX_MOJANG_PATH', WX_GAMES_PATH . 'games/mojang/');
define('WX_MOJANG_URL', WX_GAMES_URL . 'games/mojang/');

// ============================================================
// AJAX 路由分发（由 wx_games.php 调用）
// ============================================================
function wx_mojang_route_ajax($action) {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    // Referer 校验
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if (!empty($ref)) {
        $ref_host = parse_url($ref, PHP_URL_HOST);
        $srv_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($ref_host && $srv_host && $ref_host !== $srv_host) {
            echo json_encode(['code' => -1, 'message' => '非法请求'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $userData = wx_mojang_check_user();
    $uid = $userData ? (int)$userData['uid'] : 0;

    switch ($action) {
        case 'get_ranking':            wx_mojang_api_get_ranking();            break;
        case 'save_score':             wx_mojang_api_save_score($uid);         break;
        case 'get_my_rank':            wx_mojang_api_get_my_rank($uid);        break;
        case 'get_user_logs':          wx_mojang_api_get_user_logs($uid);      break;
        case 'get_my_emlog_credits':   wx_mojang_api_get_emlog_credits($uid);  break;
        case 'check_pending':          wx_mojang_api_check_pending($uid);      break;
        case 'start_game':             wx_mojang_api_start_game($uid);         break;
        case 'complete_game':          wx_mojang_api_complete_game($uid, $userData); break;
        case 'get_shop_items':         wx_mojang_api_get_shop_items();         break;
        case 'get_inventory':          wx_mojang_api_get_inventory($uid);      break;
        case 'purchase_item':          wx_mojang_api_purchase_item($uid);      break;
        case 'use_item':               wx_mojang_api_use_item($uid);           break;
        case 'get_active_effects':     wx_mojang_api_get_active_effects($uid); break;
        case 'save_ai_score':
            $nickname    = isset($_POST['nickname']) ? addslashes(trim($_POST['nickname'])) : 'AI';
            $avatar      = isset($_POST['avatar'])   ? addslashes(trim($_POST['avatar']))    : '';
            $score_change = isset($_POST['score_change']) ? intval($_POST['score_change']) : 0;
            $result      = isset($_POST['result'])   ? addslashes(trim($_POST['result']))   : 'draw';
            $ai_uid      = wx_games_get_ai_uid($nickname);
            $save_result = wx_mojang_save_score($ai_uid, $nickname, $avatar, $score_change, $result, 1);
            if ($save_result['success']) { wx_mojang_ok(['msg' => 'AI分数已保存']); }
            else { wx_mojang_error($save_result['msg']); }
            break;
        case 'get_score_buff':         wx_mojang_api_get_score_buff($uid);     break;
        case 'consume_score_buff':     wx_mojang_api_consume_score_buff($uid); break;
        case 'admin_get_inventory':    wx_mojang_admin_get_inventory();        break;
        case 'admin_add_item':         wx_mojang_admin_add_item();            break;
        case 'admin_update_item':      wx_mojang_admin_update_item();         break;
        case 'admin_delete_item':      wx_mojang_admin_delete_item();         break;
        default:
            echo json_encode(['code' => -1, 'message' => '未知操作'], JSON_UNESCAPED_UNICODE);
            exit;
    }
    exit;
}

// ============================================================
// 信号处理（由 wx_games.php 调用）
// ============================================================
function wx_mojang_handle_signal($signal) {
    $user = wx_mojang_check_user();
    if (!$user || $user['uid'] <= 0) return;
    $db = Database::getInstance();
    $suid = $user['uid'];

    if ($signal === 'start') {
        $table = DB_PREFIX . 'wx_mojang_games';
        // 先关闭所有历史未完成记录
        $db->query("UPDATE `{$table}` SET status=0, finished_at=NOW() WHERE uid={$suid} AND status=1");
        $db->query("INSERT INTO `{$table}` (uid, result, status) VALUES ('mj', {$suid}, 'pending', 1)");
    } elseif ($signal === 'end') {
        $table = DB_PREFIX . 'wx_mojang_games';
        $token = isset($_GET['token']) ? addslashes(trim($_GET['token'])) : '';
        if ($token) {
            $db->query("UPDATE `{$table}` SET status=0, result='draw', finished_at=NOW() WHERE uid={$suid} AND game_token='{$token}' AND status=1");
        } else {
            $db->query("UPDATE `{$table}` SET status=0, result='draw', finished_at=NOW() WHERE uid={$suid} AND status=1");
        }
    } elseif ($signal === 'penalty') {
        $penalty_points = isset($_GET['points']) ? intval($_GET['points']) : 100;
        $penalty_points = -abs($penalty_points);
        wx_mojang_apply_penalty($suid, $penalty_points);
        $table = DB_PREFIX . 'wx_mojang_games';
        $db->query("UPDATE `{$table}` SET result='lose', score_change={$penalty_points}, status=0, finished_at=NOW() WHERE uid={$suid} AND status=1");
    }
}

// ============================================================
// JSON 辅助函数
// ============================================================
function wx_mojang_json($code = 0, $data = [], $msg = '') {
    $result = ['code' => $code];
    if ($data !== [] && $data !== null) $result['data'] = $data;
    if ($msg !== '') $result['message'] = $msg;
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}
function wx_mojang_ok($data = []) { wx_mojang_json(0, $data); }
function wx_mojang_error($msg = '') { wx_mojang_json(-1, [], $msg); }

// ============================================================
// 以下为原 wx_mojang.php 函数定义（完全保留，逻辑不变）
// ============================================================

function wx_mojang_get_config() {
    static $config = null;
    if ($config === null) {
        $defaults = [
            'title'              => 'H5 国标麻将',
            'guest_play'         => '1',
            'ai_names'           => '全宝蓝,李居丽,朴素妍,咸恩静,朴孝敏,朴智妍',
            'max_entries'        => 50,
            'penalty_multiplier' => 2,
            'base_score'         => 100,
            'min_fan_to_win'     => 8,
            'notice'             => '欢迎来到国标麻将！8番起胡，祝您旗开得胜！',
            'recent_updates'     => '',
        ];
        try {
            $storage = Storage::getInstance('wx_mojang');
            $saved = $storage->getValue('config');
            if (is_array($saved)) {
                $config = array_merge($defaults, $saved);
            } else {
                $config = [
                    'title'              => $storage->getValue('title')              ?: $defaults['title'],
                    'guest_play'         => $storage->getValue('guest_play')         ?: $defaults['guest_play'],
                    'max_entries'        => intval($storage->getValue('max_entries')) ?: $defaults['max_entries'],
                    'penalty_multiplier' => floatval($storage->getValue('penalty_multiplier') ?: $defaults['penalty_multiplier']),
                    'base_score'         => intval($storage->getValue('base_score') ?: $defaults['base_score']),
                    'min_fan_to_win'     => intval($storage->getValue('min_fan_to_win') ?: $defaults['min_fan_to_win']),
                    'notice'             => $storage->getValue('notice')             ?: $defaults['notice'],
                    'recent_updates'     => $storage->getValue('recent_updates')     ?: $defaults['recent_updates'],
                ];
            }
        } catch (Throwable $e) { $config = $defaults; }
    }
    return $config;
}

function wx_mojang_get_ai_players() {
    static $ai_players = null;
    if ($ai_players === null) {
        $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
        try {
            $storage = Storage::getInstance('wx_mojang');
            $saved = $storage->getValue('ai_players');
            if (is_array($saved) && !empty($saved)) {
                // 兼容旧版关联数组转为索引数组
                $ai_players = array_values($saved);
            }
        } catch (Throwable $e) {}
        if ($ai_players === null) {
            $names = ['全宝蓝', '李居丽', '朴素妍', '咸恩静', '朴孝敏', '朴智妍'];
            $ai_players = [];
            foreach ($names as $i => $name) {
                $ai_players[] = [
                    'name'   => $name,
                    'avatar' => $avatar_files[$i % count($avatar_files)],
                    'quotes' => ['good' => [], 'bad' => [], 'win' => [], 'lose' => []],
                ];
            }
        }
        foreach ($ai_players as &$player) {
            if (!isset($player['quotes']) || !is_array($player['quotes'])) {
                $player['quotes'] = ['good' => [], 'bad' => [], 'win' => [], 'lose' => []];
            }
        }
        unset($player);
    }
    return $ai_players;
}

function wx_mojang_check_user() {
    return wx_games_check_user();
}

function wx_mojang_resolve_avatar($uid) {
    if ($uid <= 0) return '';
    try {
        $db = Database::getInstance();
        $row = $db->once_fetch_array("SELECT `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = " . intval($uid) . " LIMIT 1");
        if ($row && !empty($row['photo'])) {
            if (strpos($row['photo'], 'http') === 0) { return $row['photo']; }
            return BLOG_URL . ltrim($row['photo'], './');
        }
    } catch (Throwable $e) {}
    return '';
}

function wx_mojang_resolve_nickname($uid) {
    if ($uid <= 0) return '';
    try {
        $db = Database::getInstance();
        $row = $db->once_fetch_array("SELECT `nickname` FROM `" . DB_PREFIX . "user` WHERE `uid` = " . intval($uid) . " LIMIT 1");
        if ($row && !empty($row['nickname'])) { return $row['nickname']; }
    } catch (Throwable $e) {}
    return '';
}

function wx_mojang_get_user_score($uid, $is_ai = 0) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $row = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `game` = 'mj' AND `uid` = " . intval($uid) . " AND `is_ai` = " . intval($is_ai) . " LIMIT 1");
        if ($row) {
            return [
                'id'             => (int)$row['id'],
                'uid'            => (int)$row['uid'],
                'nickname'       => ($is_ai == 0) ? wx_mojang_resolve_nickname((int)$row['uid']) : $row['nickname'],
                'avatar'         => ($is_ai == 0) ? wx_mojang_resolve_avatar((int)$row['uid']) : $row['avatar'],
                'score'          => (int)$row['score'],
                'total_games'    => (int)$row['total_games'],
                'wins'           => (int)$row['wins'],
                'losses'         => (int)$row['losses'],
                'draws'          => (int)$row['draws'],
                'self_draw_wins' => (int)$row['self_draw_wins'],
                'discard_wins'   => (int)$row['discard_wins'],
                'big_fan_wins'   => (int)$row['big_fan_wins'],
                'best_score'     => (int)$row['best_score'],
                'max_fan'        => (int)$row['max_fan'],
                'is_ai'          => (int)$row['is_ai'],
            ];
        }
    } catch (Throwable $e) {}
    return null;
}

function wx_mojang_get_leaderboard($limit = 20, $include_ai = true) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $table_inv = DB_PREFIX . 'wx_games_user_items';
        $table_items = DB_PREFIX . 'wx_games_shop_items';
        $where_sub = $include_ai ? "WHERE `game` = 'mj'" : "WHERE `game` = 'mj' AND `is_ai` = 0";
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
                    'rank'           => $rank++,
                    'uid'            => $uid,
                    'nickname'       => ((int)$row['is_ai'] === 0) ? wx_mojang_resolve_nickname($uid) : $row['nickname'],
                    'avatar'         => ((int)$row['is_ai'] === 0) ? wx_mojang_resolve_avatar($uid) : $row['avatar'],
                    'score'          => (int)$row['score'],
                    'total_games'    => (int)$row['total_games'],
                    'wins'           => (int)$row['wins'],
                    'losses'         => (int)$row['losses'],
                    'draws'          => (int)$row['draws'],
                    'self_draw_wins' => (int)$row['self_draw_wins'],
                    'discard_wins'   => (int)$row['discard_wins'],
                    'big_fan_wins'   => (int)$row['big_fan_wins'],
                    'best_score'     => (int)$row['best_score'],
                    'max_fan'        => (int)$row['max_fan'],
                    'is_ai'          => (int)$row['is_ai'],
                    'active_effects' => [],
                ];
            }
            if ($row['item_type'] && $row['effect_data']) {
                $user_map[$uid]['active_effects'][] = ['type' => $row['item_type'], 'data' => stripslashes($row['effect_data'])];
            }
        }
        foreach ($user_map as $entry) { $entries[] = $entry; }
        return $entries;
    } catch (Throwable $e) { return []; }
}

function wx_mojang_save_score($uid, $nickname, $avatar, $score_change, $result, $is_ai = 0) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $table_logs = DB_PREFIX . 'wx_games_logs';
        $table_games = DB_PREFIX . 'wx_mojang_games';
        $uid = intval($uid);
        $score_change = intval($score_change);
        $is_ai = intval($is_ai);
        $nickname = $db->escape_string(trim($nickname));
        $avatar = $db->escape_string(trim($avatar));
        $result = ($result === 'win' || $result === 'lose') ? $result : 'draw';
        $existing = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `game` = 'mj' AND `uid` = $uid AND `is_ai` = $is_ai LIMIT 1");
        if ($is_ai == 0) {
            $db->query("UPDATE `" . $table_games . "` SET
                `status` = 0, `finished_at` = NOW(),
                `result` = '" . $result . "', `score_change` = $score_change
                WHERE `uid` = $uid AND `status` = 1");
        }
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
            $query = "UPDATE `" . $table . "` SET
                `score` = $new_score, `total_games` = $total_games,
                `wins` = $wins, `losses` = $losses, `draws` = $draws,
                `best_score` = $best_score";
            if ($is_ai) { $query .= ", `nickname` = '" . $nickname . "', `avatar` = '" . $avatar . "'"; }
            $query .= " WHERE `game` = 'mj' AND `id` = " . intval($existing['id']);
            $db->query($query);
            $db->query("INSERT INTO `" . $table_logs . "`
                (`game`, `uid`, `score_change`, `score_before`, `score_after`, `reason`, `operator`)
                VALUES ('mj', $uid, $score_change, $score_before, $new_score, '$reason', 'system')");
            return ['success' => true, 'msg' => '保存成功', 'score' => $new_score];
        } else {
            $score_before = 0;
            $wins   = $result === 'win'  ? 1 : 0;
            $losses = $result === 'lose' ? 1 : 0;
            $draws  = $result === 'draw' ? 1 : 0;
            $best_score = $score_change;
            $_nick = $is_ai ? "'" . $nickname . "'" : "''";
            $_avat = $is_ai ? "'" . $avatar . "'" : "''";
            $db->query("INSERT INTO `" . $table . "`
                (`game`, `uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `best_score`, `is_ai`)
                VALUES ('mj', $uid, $_nick, $_avat, $score_change, 1, $wins, $losses, $draws, $best_score, $is_ai)");
            $db->query("INSERT INTO `" . $table_logs . "`
                (`game`, `uid`, `score_change`, `score_before`, `score_after`, `reason`, `operator`)
                VALUES ('mj', $uid, $score_change, $score_before, $score_change, '$reason', 'system')");
            return ['success' => true, 'msg' => '保存成功', 'score' => $score_change];
        }
    } catch (Throwable $e) {
        return ['success' => false, 'msg' => '保存失败: ' . $e->getMessage(), 'score' => 0];
    }
}

function wx_mojang_apply_penalty($uid, $penalty_score = null) {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $table_logs = DB_PREFIX . 'wx_games_logs';
        $uid = intval($uid);
        if ($penalty_score === null) {
            $cfg = wx_mojang_get_config();
            $penalty_score = -abs((int)$cfg['base_score'] * $cfg['penalty_multiplier']);
        } else { $penalty_score = -abs(intval($penalty_score)); }
        $existing = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `game` = 'mj' AND `uid` = $uid AND `is_ai` = 0 LIMIT 1");
        if ($existing) {
            $score_before = (int)$existing['score'];
            $new_score = $score_before + $penalty_score;
            $db->query("UPDATE `" . $table . "` SET `score` = $new_score WHERE `game` = 'mj' AND `id` = " . intval($existing['id']));
        } else {
            $score_before = 0;
            $new_score = $penalty_score;
            $db->query("INSERT INTO `" . $table . "`
                (`game`, `uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `best_score`, `is_ai`)
                VALUES ('mj', $uid, '', '', $penalty_score, 1, 0, 1, 0, $penalty_score, 0)");
        }
        $db->query("INSERT INTO `" . $table_logs . "`
            (`game`, `uid`, `score_change`, `score_before`, `score_after`, `reason`, `operator`)
            VALUES ('mj', $uid, $penalty_score, $score_before, $new_score, '逃跑惩罚（超时未完成）', 'system')");
        return $new_score;
    } catch (Throwable $e) { return 0; }
}

function wx_mojang_admin_change_score($uid, $score_change, $reason = '', $operator = '') {
    try {
        $db = Database::getInstance();
        $table_scores = DB_PREFIX . 'wx_games_scores';
        $table_logs   = DB_PREFIX . 'wx_games_logs';
        $uid = intval($uid);
        $score_change = intval($score_change);
        $row = $db->once_fetch_array("SELECT * FROM `" . $table_scores . "` WHERE `game` = 'mj' AND `uid` = $uid AND `is_ai` = 0 LIMIT 1");
        if (!$row) return false;
        $score_before = (int)$row['score'];
        $score_after  = $score_before + $score_change;
        $reason_esc   = $db->escape_string($reason);
        $operator_esc = $db->escape_string($operator);
        $db->query("UPDATE `" . $table_scores . "` SET `score` = $score_after WHERE `game` = 'mj' AND `id` = " . intval($row['id']));
        $db->query("INSERT INTO `" . $table_logs . "`
            (`game`, `uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
            VALUES ('mj', $uid, '" . $db->escape_string(trim($row['nickname'] ?? '')) . "', $score_change, $score_before, $score_after, '$reason_esc', '$operator_esc', " . time() . ")");
        return true;
    } catch (Throwable $e) { return false; }
}

// ============================================================
// API 实现
// ============================================================

function wx_mojang_api_get_ranking() {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $entries = wx_mojang_get_leaderboard($limit);
    wx_mojang_ok(['entries' => $entries]);
}

function wx_mojang_api_save_score($uid) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $score_change = isset($_POST['score_change']) ? intval($_POST['score_change']) : 0;
    $result       = isset($_POST['result'])       ? addslashes(trim($_POST['result']))       : 'draw';
    $winner       = isset($_POST['winner'])        ? addslashes(trim($_POST['winner']))       : '';
    $win_type     = isset($_POST['win_type'])      ? addslashes(trim($_POST['win_type']))     : '';
    $fan_count    = isset($_POST['fan_count'])     ? intval($_POST['fan_count'])              : 0;
    $fan_type     = isset($_POST['fan_type'])      ? addslashes(trim($_POST['fan_type']))     : '';
    $hand_tiles   = isset($_POST['hand_tiles'])    ? addslashes(trim($_POST['hand_tiles']))   : '';
    $final_hand   = isset($_POST['final_hand'])    ? addslashes(trim($_POST['final_hand']))   : '';
    $win_tile     = isset($_POST['win_tile'])      ? addslashes(trim($_POST['win_tile']))     : '';
    $game_token   = isset($_POST['game_token'])    ? addslashes(trim($_POST['game_token']))   : '';
    $where = "`uid` = $uid";
    if (!empty($game_token)) { $where .= " AND `game_token` = '$game_token'"; }
    else { $where .= " AND `status` = 1"; }
    $db = Database::getInstance();
    $table_games = DB_PREFIX . 'wx_mojang_games';
    $db->query("UPDATE `" . $table_games . "` SET
        `status` = 0, `finished_at` = NOW(),
        `result` = '" . $result . "', `score_change` = $score_change,
        `winner` = '" . $winner . "', `win_type` = '" . $win_type . "',
        `fan_count` = $fan_count, `fan_type` = '" . $fan_type . "',
        `hand_tiles` = '" . $hand_tiles . "', `final_hand` = '" . $final_hand . "', `win_tile` = '" . $win_tile . "'
        WHERE $where ORDER BY `id` DESC LIMIT 1");
    $userData = wx_mojang_check_user();
    if (!$userData) { wx_mojang_ok(['msg' => '游戏已关闭（未登录，分数未保存）']); }
    $save_result = wx_mojang_save_score($uid, $userData['nickname'], $userData['avatar'], $score_change, $result);
    if ($save_result['success']) { wx_mojang_ok(['msg' => '保存成功', 'score' => $save_result['score']]); }
    else { wx_mojang_error($save_result['msg']); }
}

function wx_mojang_api_get_my_rank($uid) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $data = wx_mojang_get_user_score($uid);
    if (!$data) {
        $data = ['score' => 0, 'total_games' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'best_score' => 0, 'max_fan' => 0];
    }
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $rank_row = $db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `" . $table . "` WHERE `game` = 'mj' AND `is_ai` = 0 AND `score` > " . intval($data['score']));
        $data['rank'] = ($rank_row ? (int)$rank_row['cnt'] : 0) + 1;
    } catch (Throwable $e) { $data['rank'] = 0; }
    wx_mojang_ok($data);
}

function wx_mojang_api_get_user_logs($uid) {
    $target_uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($target_uid <= 0) { wx_mojang_error('无效的用户ID'); }
    $db = Database::getInstance();
    $table_logs = DB_PREFIX . 'wx_games_logs';
    $result = $db->query("SELECT * FROM `" . $table_logs . "` WHERE `game` = 'mj' AND `uid` = $target_uid ORDER BY `created_at` DESC LIMIT 50");
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
    wx_mojang_ok($log_entries);
}

function wx_mojang_api_get_emlog_credits($uid) {
    if ($uid <= 0) { wx_mojang_ok(['credits' => 0]); }
    try {
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser(intval($uid));
        $credits = ($emlog_user && isset($emlog_user['credits'])) ? intval($emlog_user['credits']) : 0;
        wx_mojang_ok(['credits' => $credits]);
    } catch (Throwable $e) { wx_mojang_ok(['credits' => 0]); }
}

function wx_mojang_api_check_pending($uid) {
    if ($uid <= 0) { wx_mojang_ok(['pending' => false]); }
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_mojang_games';
    $row = $db->once_fetch_array("SELECT `id`, `game_token`, `created` FROM `" . $table . "` WHERE `uid` = $uid AND `status` = 1 AND `result` = 'pending' ORDER BY `id` DESC LIMIT 1");
    if ($row) {
        $db->query("UPDATE `" . $table . "` SET `result` = 'draw', `score_change` = 0, `status` = 0, `finished_at` = NOW() WHERE `id` = " . intval($row['id']));
        wx_mojang_ok(['pending' => false, 'cleaned' => true, 'msg' => '已清理未完成的对局记录']);
    }
    wx_mojang_ok(['pending' => false]);
}

function wx_mojang_api_start_game($uid) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_mojang_games';
    $db->query("UPDATE `" . $table . "` SET `status` = 0, `finished_at` = NOW(), `result` = 'draw', `score_change` = 0 WHERE `uid` = $uid AND `status` = 1");
    $token = bin2hex(random_bytes(16));
    $db->query("INSERT INTO `" . $table . "` (`game`, `uid`, `result`, `game_token`, `status`) VALUES ('mj', $uid, 'pending', '$token', 1)");
    $game_id = $db->insert_id();
    wx_mojang_ok(['game_id' => $game_id, 'game_token' => $token, 'msg' => '游戏开始']);
}

function wx_mojang_api_complete_game($uid, $userData) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $game_id     = isset($_POST['game_id'])     ? intval($_POST['game_id'])     : 0;
    $token       = isset($_POST['game_token'])  ? addslashes(trim($_POST['game_token'])) : '';
    if ($game_id <= 0 && !empty($token)) {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_mojang_games';
        $found = $db->once_fetch_array("SELECT `id` FROM `" . $table . "` WHERE `uid` = $uid AND `game_token` = '" . $token . "' AND `status` = 1 LIMIT 1");
        if ($found) { $game_id = (int)$found['id']; }
    }
    $score_change = isset($_POST['score_change']) ? intval($_POST['score_change']) : 0;
    $result      = isset($_POST['result'])       ? addslashes(trim($_POST['result']))      : 'draw';
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_mojang_games';
    $db->query("UPDATE `" . $table . "` SET
        `status` = 0, `finished_at` = NOW(),
        `result` = '" . $result . "', `score_change` = $score_change
        WHERE `uid` = $uid AND `status` = 1");
    $fan_count   = isset($_POST['fan_count'])   ? intval($_POST['fan_count'])   : 0;
    $fan_type    = isset($_POST['fan_type'])     ? addslashes(trim($_POST['fan_type']))     : '';
    $win_type    = isset($_POST['win_type'])     ? addslashes(trim($_POST['win_type']))     : '';
    $hand_tiles  = isset($_POST['hand_tiles'])   ? addslashes(trim($_POST['hand_tiles']))   : '';
    $final_hand  = isset($_POST['final_hand'])   ? addslashes(trim($_POST['final_hand']))   : '';
    $win_tile    = isset($_POST['win_tile'])     ? addslashes(trim($_POST['win_tile']))     : '';
    $winner      = isset($_POST['winner'])       ? addslashes(trim($_POST['winner']))       : '';
    if ($game_id <= 0) { wx_mojang_error('无效的对局ID'); }
    $check = $db->once_fetch_array("SELECT `id` FROM `" . $table . "` WHERE `id` = $game_id AND `uid` = $uid AND `game_token` = '" . $token . "' AND `status` = 1 LIMIT 1");
    if (!$check) { wx_mojang_error('对局验证失败，请重新开始'); }
    $db->query("UPDATE `" . $table . "` SET
        `result` = '" . $result . "', `score_change` = $score_change,
        `winner` = '" . $winner . "', `win_type` = '" . $win_type . "',
        `fan_count` = $fan_count, `fan_type` = '" . $fan_type . "',
        `hand_tiles` = '" . $hand_tiles . "', `final_hand` = '" . $final_hand . "',
        `win_tile` = '" . $win_tile . "', `status` = 0, `finished_at` = NOW()
        WHERE `id` = $game_id AND `uid` = $uid AND `game_token` = '" . $token . "' AND `status` = 1");
    $score_table = DB_PREFIX . 'wx_games_scores';
    $score_row = $db->once_fetch_array("SELECT * FROM `" . $score_table . "` WHERE `game` = 'mj' AND `uid` = $uid AND `is_ai` = 0 LIMIT 1");
    if ($score_row) {
        $new_score   = (int)$score_row['score'] + $score_change;
        $new_wins    = (int)$score_row['wins']    + ($result === 'win'  ? 1 : 0);
        $new_losses  = (int)$score_row['losses']  + ($result === 'lose' ? 1 : 0);
        $new_draws   = (int)$score_row['draws']   + ($result === 'draw' ? 1 : 0);
        $new_total   = (int)$score_row['total_games'] + 1;
        $best_score  = max((int)$score_row['best_score'], $new_score);
        $max_fan     = max((int)$score_row['max_fan'], $fan_count);
        $self_draw   = (int)$score_row['self_draw_wins'] + ($result === 'win' && $win_type === 'self_draw' ? 1 : 0);
        $discard_w   = (int)$score_row['discard_wins']   + ($result === 'win' && $win_type === 'discard'  ? 1 : 0);
        $big_fan_w   = (int)$score_row['big_fan_wins']   + ($result === 'win' && $fan_count >= 6 ? 1 : 0);
        $db->query("UPDATE `" . $score_table . "` SET
            `score` = $new_score, `total_games` = $new_total,
            `wins` = $new_wins, `losses` = $new_losses, `draws` = $new_draws,
            `self_draw_wins` = $self_draw, `discard_wins` = $discard_w,
            `big_fan_wins` = $big_fan_w,
            `best_score` = $best_score, `max_fan` = $max_fan
            WHERE `id` = " . intval($score_row['id']));
    } else {
        $wins   = $result === 'win'  ? 1 : 0; $losses = $result === 'lose' ? 1 : 0; $draws  = $result === 'draw' ? 1 : 0;
        $best   = $score_change > 0 ? $score_change : 0;
        $self_draw_win = ($result === 'win' && $win_type === 'self_draw') ? 1 : 0;
        $discard_win   = ($result === 'win' && $win_type === 'discard')   ? 1 : 0;
        $big_fan_win   = ($result === 'win' && $fan_count >= 6) ? 1 : 0;
        $db->query("INSERT INTO `" . $score_table . "`
            (`game`, `uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `self_draw_wins`, `discard_wins`, `big_fan_wins`, `best_score`, `max_fan`, `is_ai`)
            VALUES ('mj', $uid, '', '', $score_change, 1, $wins, $losses, $draws, $self_draw_win, $discard_win, $big_fan_win, $best, $fan_count, 0)");
        $new_score = $score_change;
    }
    wx_mojang_ok(['msg' => '游戏记录已保存', 'score' => $new_score]);
}

function wx_mojang_api_get_shop_items() {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_shop_items';
    $table_inv = DB_PREFIX . 'wx_games_user_items';

    // 读取当前登录用户
    $current_user = wx_mojang_check_user();
    $uid = $current_user ? intval($current_user['uid']) : 0;

    $result = $db->query("SELECT * FROM `" . $table . "` WHERE (`game` = 'mj' OR `is_global` = 1) AND `status` = 1 ORDER BY `sort_order` ASC, `id` ASC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $item_id = (int)$row['id'];
        $owned = false;
        $owned_qty = 0;
        if ($uid > 0) {
            $own = $db->once_fetch_array("SELECT SUM(CAST(`quantity` AS SIGNED) - CAST(`used` AS SIGNED)) AS cnt FROM `" . $table_inv . "` WHERE `uid` = $uid AND `item_id` = $item_id LIMIT 1");
            $owned_qty = intval($own['cnt'] ?? 0);
            $owned = $owned_qty > 0;
        }
        $items[] = [
            'id'             => $item_id, 'name' => $row['name'],
            'description'    => $row['description'], 'icon' => $row['icon'],
            'item_type'      => $row['item_type'],
            'effect_data'    => stripslashes($row['effect_data']),
            'price_emlog'    => (int)$row['price_emlog'], 'price_majiang' => (int)$row['price_game'],
            'stock'          => (int)$row['stock'], 'max_per_user' => (int)$row['max_per_user'],
            'is_global'      => !empty($row['is_global']),
            'owned'          => $owned,
            'owned_qty'      => $owned_qty,
        ];
    }
    wx_mojang_ok(['items' => $items]);
}

function wx_mojang_api_get_inventory($uid) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $result = $db->query("SELECT MIN(i.`id`) AS inv_id, i.`item_id`,
               SUM(CAST(i.`quantity` AS SIGNED) - CAST(i.`used` AS SIGNED)) AS qty,
               MAX(i.`is_active`) AS is_active, MAX(i.`game`) AS from_game,
               MAX(s.`name`) AS name, MAX(s.`icon`) AS icon,
               MAX(s.`item_type`) AS item_type, MAX(s.`effect_data`) AS effect_data,
               MAX(s.`is_global`) AS is_global
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`quantity` > i.`used`
          AND (i.`game` = 'mj' OR s.`is_global` = 1)
        GROUP BY i.`item_id`
        ORDER BY MAX(i.`is_active`) DESC, MAX(i.`purchased_at`) DESC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $expired = $row['expires_at'] && $row['expires_at'] !== '0000-00-00 00:00:00' && strtotime($row['expires_at']) < time();
        if ($expired) continue;
        $items[] = ['inv_id' => (int)$row['inv_id'], 'item_id' => (int)$row['item_id'],
            'name' => $row['name'], 'icon' => $row['icon'], 'item_type' => $row['item_type'],
            'effect_data' => stripslashes($row['effect_data']),
            'is_global' => (int)$row['is_global'],
            'from_game' => $row['from_game'],
            'quantity' => (int)$row['qty'], 'is_active' => (int)$row['is_active']];
    }
    wx_mojang_ok(['items' => $items]);
}

function wx_mojang_api_purchase_item($uid) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $item_id  = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $pay_type = isset($_POST['pay_type']) ? addslashes(trim($_POST['pay_type'])) : 'both';
    if ($item_id <= 0) { wx_mojang_error('参数错误'); }
    $db = Database::getInstance();
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $item = $db->once_fetch_array("SELECT * FROM `" . $table_items . "` WHERE `id` = $item_id AND `status` = 1 AND (`game` = 'mj' OR `is_global` = 1) LIMIT 1");
    if (!$item) { wx_mojang_error('商品不存在或已下架'); }
    $price_emlog  = (int)$item['price_emlog']; $price_majiang = (int)$item['price_game'];
    $stock = (int)$item['stock'];
    if ($stock !== -1 && $stock <= 0) { wx_mojang_error('商品已售罄'); }
    $max_per_user = (int)$item['max_per_user'];
    if ($max_per_user > 0) {
        $table_inv = DB_PREFIX . 'wx_games_user_items';
        $owned = $db->once_fetch_array("SELECT SUM(`quantity`) AS cnt FROM `" . $table_inv . "` WHERE `game` = 'mj' AND `uid` = $uid AND `item_id` = $item_id LIMIT 1");
        $current_cnt = intval($owned['cnt'] ?? 0);
        if ($current_cnt >= $max_per_user) { wx_mojang_error('已达限购数量'); }
    }
    if ($pay_type === 'both' || ($pay_type === 'both' && $price_emlog > 0 && $price_majiang > 0)) {
        if ($price_emlog > 0) {
            $userModel = new User_Model();
            $user = $userModel->getOneUser($uid);
            $credits = isset($user['credits']) ? intval($user['credits']) : 0;
            if ($credits < $price_emlog) { wx_mojang_error('站点积分不足，需要' . $price_emlog . '积分'); }
            $userModel->reduceCredits($uid, $price_emlog);
            if (function_exists('addCreditRecord')) {
                $game_title = (wx_mojang_get_config()['title'] ?? 'H5麻将');
                addCreditRecord($uid, 'reduce', $price_emlog, $game_title . '_buy_' . $item['name'] . '_' . time());
            }
        }
        if ($price_majiang > 0) {
            $score_data = wx_mojang_get_user_score($uid);
            $mj_score = $score_data ? (int)$score_data['score'] : 0;
            if ($mj_score < $price_majiang) { wx_mojang_error('麻将积分不足，需要' . $price_majiang . '积分'); }
            wx_mojang_admin_change_score($uid, -$price_majiang, '商城购买：' . $item['name'], 'system');
        }
    } elseif ($pay_type === 'emlog') {
        if ($price_emlog <= 0) { wx_mojang_error('该商品不支持站点积分购买'); }
        $userModel = new User_Model();
        $user = $userModel->getOneUser($uid);
        $credits = isset($user['credits']) ? intval($user['credits']) : 0;
        if ($credits < $price_emlog) { wx_mojang_error('站点积分不足，需要' . $price_emlog . '积分'); }
        $userModel->reduceCredits($uid, $price_emlog);
        if (function_exists('addCreditRecord')) {
            $game_title = (wx_mojang_get_config()['title'] ?? 'H5麻将');
            addCreditRecord($uid, 'reduce', $price_emlog, $game_title . '_buy_' . $item['name'] . '_' . time());
        }
    } else {
        if ($price_majiang <= 0) { wx_mojang_error('该商品不支持麻将积分购买'); }
        $score_data = wx_mojang_get_user_score($uid);
        $mj_score = $score_data ? (int)$score_data['score'] : 0;
        if ($mj_score < $price_majiang) { wx_mojang_error('麻将积分不足，需要' . $price_majiang . '积分'); }
        wx_mojang_admin_change_score($uid, -$price_majiang, '商城购买：' . $item['name'], 'system');
    }
    $table_inv = DB_PREFIX . 'wx_games_user_items';
    $is_global = !empty($item['is_global']);
    if ($is_global) {
        $all_games = ['ddz', 'mj', 'niuniu'];
        foreach ($all_games as $g) {
            $db->query("INSERT INTO `" . $table_inv . "` (`game`, `uid`, `item_id`, `quantity`, `purchased_at`, `expires_at`)
                VALUES ('$g', $uid, $item_id, 1, " . time() . ", 0)
                ON DUPLICATE KEY UPDATE `quantity` = `quantity` + 1");
        }
    } else {
        $existing = $db->once_fetch_array("SELECT `id`, `quantity` FROM `" . $table_inv . "` WHERE `game` = 'mj' AND `uid` = $uid AND `item_id` = $item_id LIMIT 1");
        if ($existing) {
            $db->query("UPDATE `" . $table_inv . "` SET `quantity` = `quantity` + 1 WHERE `game` = 'mj' AND `id` = " . intval($existing['id']));
        } else {
            $db->query("INSERT INTO `" . $table_inv . "` (`game`, `uid`, `item_id`, `quantity`, `used`) VALUES ('mj', $uid, $item_id, 1, 0)");
        }
    }
    if ($stock !== -1) { $db->query("UPDATE `" . $table_items . "` SET `stock` = `stock` - 1 WHERE `game` = 'mj' AND `id` = $item_id AND `stock` > 0"); }
    wx_mojang_ok(['msg' => '购买成功！']);
}

function wx_mojang_api_use_item($uid) {
    if ($uid <= 0) { wx_mojang_error('未登录'); }
    $inv_id = isset($_POST['inv_id']) ? intval($_POST['inv_id']) : 0;
    if ($inv_id <= 0) { $inv_id = isset($_POST['user_item_id']) ? intval($_POST['user_item_id']) : 0; }
    if ($inv_id <= 0) { wx_mojang_error('参数错误'); }
    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $row = $db->once_fetch_array("SELECT i.*, s.`item_type`, s.`effect_data` FROM `" . $table_inv . "` i
        JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`id` = $inv_id AND i.`uid` = $uid AND i.`quantity` > i.`used` LIMIT 1");
    if (!$row) { wx_mojang_error('道具不存在或已用完'); }
    $item_type = $row['item_type'];
    $global_types = ['title_colored', 'title_effect'];
    $cosmetic_types = ['title_colored', 'title_effect', 'emoticon', 'win_effect', 'title_badge'];
    if (in_array($item_type, $cosmetic_types, true)) {
        $db->query("UPDATE `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = '" . $db->escape_string($item_type) . "'");
        if (in_array($item_type, $global_types, true)) {
            $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1 WHERE `uid` = $uid AND `item_id` = " . intval($row['item_id']));
        } else {
            $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1 WHERE `id` = " . intval($row['id']));
        }
        wx_mojang_ok(['msg' => '已激活', 'item_type' => $item_type, 'effect_data' => $row['effect_data']]);
    } elseif ($item_type === 'score_buff') {
        $effect = json_decode(stripslashes($row['effect_data']), true);
        $multiplier = isset($effect['multiplier']) ? floatval($effect['multiplier']) : 2;
        $games = isset($effect['games']) ? intval($effect['games']) : 3;
        if ($multiplier <= 0) $multiplier = 2;
        if ($games <= 0) $games = 3;
        $db->query("UPDATE `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = 'score_buff'");
        $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1, `charges` = $games, `used` = 0 WHERE `id` = " . intval($row['id']));
        wx_mojang_ok(['msg' => $multiplier . '倍加成已激活，剩余' . $games . '局', 'multiplier' => $multiplier, 'games' => $games]);
    } else {
        $db->query("UPDATE `" . $table_inv . "` SET `used` = `used` + 1 WHERE `game` = 'mj' AND `id` = " . intval($row['id']));
        wx_mojang_ok(['msg' => '使用成功']);
    }
}

function wx_mojang_api_get_active_effects($uid) {
    if ($uid <= 0) { wx_mojang_ok([]); }
    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $result = $db->query("SELECT i.`id` AS inv_id, i.`item_id`, s.`item_type`, s.`effect_data`, s.`name`
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`is_active` = 1");
    $effects = [];
    while ($row = $db->fetch_array($result)) {
        $effects[] = ['inv_id' => (int)$row['inv_id'], 'item_id' => (int)$row['item_id'],
            'item_type' => $row['item_type'], 'effect_data' => stripslashes($row['effect_data']), 'name' => $row['name']];
    }
    wx_mojang_ok($effects);
}

function wx_mojang_api_get_score_buff($uid) {
    if ($uid <= 0) { wx_mojang_ok(['has_buff' => false, 'buffs' => []]); }
    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $row = $db->once_fetch_array("SELECT i.`id`, i.`charges`, i.`used`, s.`effect_data`
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
    wx_mojang_ok(['has_buff' => !empty($buffs), 'buffs' => $buffs]);
}

function wx_mojang_api_consume_score_buff($uid) {
    if ($uid <= 0) { wx_mojang_ok(['multiplier' => 1, 'remaining_buffs' => []]); }
    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $row = $db->once_fetch_array("SELECT i.`id`, i.`charges`, i.`used`, s.`effect_data`
        FROM `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`is_active` = 1 AND s.`item_type` = 'score_buff' LIMIT 1");
    if (!$row) { wx_mojang_ok(['multiplier' => 1, 'remaining_buffs' => []]); }
    $remaining = (int)$row['charges'] - (int)$row['used'] - 1;
    $effect = json_decode(stripslashes($row['effect_data']), true);
    $consumed_multiplier = isset($effect['multiplier']) ? floatval($effect['multiplier']) : 2;
    $db->query("UPDATE `" . $table_inv . "` SET `used` = `used` + 1 WHERE `game` = 'mj' AND `id` = " . (int)$row['id']);
    if ($remaining <= 0) {
        $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 0, `used` = 0, `charges` = 0,
            `quantity` = GREATEST(`quantity` - 1, 0) WHERE `id` = " . (int)$row['id']);
    }
    $remaining_buffs = [];
    if ($remaining > 0) { $remaining_buffs[] = ['multiplier' => $consumed_multiplier, 'remaining' => $remaining]; }
    wx_mojang_ok(['multiplier' => $consumed_multiplier, 'remaining_buffs' => $remaining_buffs]);
}

function wx_mojang_admin_get_inventory() {
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($uid <= 0) { wx_mojang_error('参数错误'); }
    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $result = $db->query("SELECT i.*, s.`name`, s.`icon`, s.`item_type`, s.`effect_data`
        FROM `{$table_inv}` i JOIN `{$table_items}` s ON i.`item_id` = s.`id`
        WHERE i.`uid` = $uid AND i.`game` = 'mj' ORDER BY i.`purchased_at` DESC");
    $items = [];
    while ($row = $db->fetch_array($result)) {
        $items[] = ['id' => (int)$row['id'], 'item_id' => (int)$row['item_id'],
            'name' => $row['name'], 'icon' => $row['icon'], 'item_type' => $row['item_type'],
            'effect_data' => stripslashes($row['effect_data']),
            'quantity' => (int)$row['quantity'], 'used' => (int)$row['used'],
            'is_active' => (int)$row['is_active'], 'charges' => (int)$row['charges'], 'expires_at' => $row['expires_at']];
    }
    wx_mojang_ok(['items' => $items]);
}

function wx_mojang_admin_add_item() {
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($uid <= 0 || $item_id <= 0 || $qty <= 0) { wx_mojang_error('参数错误'); }
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_games_user_items';
    $existing = $db->once_fetch_array("SELECT `id`, `quantity` FROM `{$table_inv}` WHERE `game` = 'mj' AND `uid` = $uid AND `item_id` = $item_id LIMIT 1");
    if ($existing) {
        $db->query("UPDATE `{$table_inv}` SET `quantity` = `quantity` + $qty WHERE `game` = 'mj' AND `id` = " . intval($existing['id']));
    } else {
        $db->query("INSERT INTO `{$table_inv}` (`game`, `uid`, `item_id`, `quantity`, `used`, `is_active`, `charges`, `expires_at`, `created`)
            VALUES ('mj', $uid, $item_id, $qty, 0, 0, -1, NULL, NOW())");
    }
    wx_mojang_ok();
}

function wx_mojang_admin_update_item() {
    $inv_id = isset($_POST['inv_id']) ? intval($_POST['inv_id']) : 0;
    $qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $used = isset($_POST['used']) ? intval($_POST['used']) : 0;
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;
    $charges = isset($_POST['charges']) ? intval($_POST['charges']) : -1;
    $expires_at = isset($_POST['expires_at']) ? addslashes(trim($_POST['expires_at'])) : '';
    if ($inv_id <= 0) { wx_mojang_error('参数错误'); }
    $db = Database::getInstance();
    $table_inv = DB_PREFIX . 'wx_games_user_items';
    $expires_sql = $expires_at ? "'{$expires_at}'" : "NULL";
    $db->query("UPDATE `{$table_inv}` SET `quantity` = $qty, `used` = $used, `is_active` = $is_active,
        `charges` = $charges, `expires_at` = {$expires_sql} WHERE `id` = $inv_id");
    wx_mojang_ok();
}

function wx_mojang_admin_delete_item() {
    $inv_id = isset($_GET['inv_id']) ? intval($_GET['inv_id']) : 0;
    if ($inv_id <= 0) { wx_mojang_error('参数错误'); }
    $db = Database::getInstance();
    $db->query("DELETE FROM `" . DB_PREFIX . "wx_games_user_items` WHERE `id` = $inv_id");
    wx_mojang_ok();
}
