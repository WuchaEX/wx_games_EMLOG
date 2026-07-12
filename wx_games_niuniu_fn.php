<?php
/**
 * wx_games 斗牛函数模块
 * 包含：牌型计算、AI逻辑、AJAX路由、信号处理
 */
!defined('EMLOG_ROOT') && exit('access denied!');

// 斗牛常量
!defined('WX_NIUNIU_PLUGIN_NAME') && define('WX_NIUNIU_PLUGIN_NAME', 'wx_niuniu');
!defined('WX_NIUNIU_PATH') && define('WX_NIUNIU_PATH', WX_GAMES_PATH . 'games/niuniu/');
!defined('WX_NIUNIU_URL') && define('WX_NIUNIU_URL', WX_GAMES_URL . 'games/niuniu/');

function wx_niuniu_get_plugin_url() { return WX_NIUNIU_URL; }

// ============================================================
// 牛型定义（权重越高越大）
// ============================================================
function wx_niuniu_types() {
    return [
        'no_niu'      => ['name' => '无牛', 'weight' => 0,  'multiplier' => 1],
        'niu_1'       => ['name' => '牛1',  'weight' => 1,  'multiplier' => 1],
        'niu_2'       => ['name' => '牛2',  'weight' => 2,  'multiplier' => 1],
        'niu_3'       => ['name' => '牛3',  'weight' => 3,  'multiplier' => 1],
        'niu_4'       => ['name' => '牛4',  'weight' => 4,  'multiplier' => 1],
        'niu_5'       => ['name' => '牛5',  'weight' => 5,  'multiplier' => 1],
        'niu_6'       => ['name' => '牛6',  'weight' => 6,  'multiplier' => 1],
        'niu_7'       => ['name' => '牛7',  'weight' => 7,  'multiplier' => 2],
        'niu_8'       => ['name' => '牛8',  'weight' => 8,  'multiplier' => 3],
        'niu_9'       => ['name' => '牛9',  'weight' => 9,  'multiplier' => 4],
        'niu_niu'     => ['name' => '牛牛',  'weight' => 10, 'multiplier' => 5],
        'yin_niu'     => ['name' => '银牛',  'weight' => 11, 'multiplier' => 7],
        'jin_niu'     => ['name' => '金牛',  'weight' => 12, 'multiplier' => 10],
        'zha_dan'     => ['name' => '炸弹',  'weight' => 13, 'multiplier' => 15],
        'wu_xiao_niu' => ['name' => '五小牛','weight' => 14, 'multiplier' => 20],
    ];
}

// ============================================================
// 配置
// ============================================================
function wx_niuniu_get_config() {
    static $config = null;
    if ($config === null) {
        $defaults = [
            'title'              => 'H5 斗牛',
            'guest_play'         => '1',
            'ai_names'           => '全宝蓝,李居丽,朴素妍,咸恩静,朴孝敏,朴智妍',
            'max_entries'        => 50,
            'penalty_multiplier' => 1.0,
            'base_bet'           => 100,
            'notice'             => '欢迎来到H5斗牛！五张牌比大小，祝你好运！',
            'recent_updates'     => '',
            'recharge_link'      => '',
        ];
        try {
            $storage = Storage::getInstance('wx_niuniu');
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

// ============================================================
// AI玩家（6人 + 台词，同斗地主规格）
// ============================================================
function wx_niuniu_get_ai_players() {
    static $ai_players = null;
    if ($ai_players === null) {
        $avatar_files = ['boram.jpg', 'qri.jpg', 'soyeon.jpg', 'eunjung.jpg', 'hyomin.jpg', 'jiyeon.jpg'];
        try {
            $storage = Storage::getInstance('wx_niuniu');
            $saved = $storage->getValue('ai_players');
            if (is_array($saved) && !empty($saved)) {
                $ai_players = $saved;
            }
        } catch (Throwable $e) {}
        if ($ai_players === null) {
            $names = ['全宝蓝', '李居丽', '朴素妍', '咸恩静', '朴孝敏', '朴智妍'];
            $ai_players = [];
            foreach ($names as $i => $name) {
                $ai_players[] = [
                    'name'   => $name,
                    'avatar' => $avatar_files[$i % count($avatar_files)],
                ];
            }
        }
        // 确保至少6个AI
        while (count($ai_players) < 6) {
            $idx = count($ai_players);
            $ai_players[] = [
                'name'   => 'AI牛人' . ($idx + 1),
                'avatar' => $avatar_files[$idx % count($avatar_files)],
            ];
        }
        // 补全默认台词（仅当无台词时用通用默认值）
        $defaultQuotes = [
            'wu_xiao_niu' => ['五小牛！', '五小牛！绝杀！'],
            'zha_dan'     => ['炸弹！', '炸弹牛！'],
            'jin_niu'     => ['金牛！', '金闪闪！'],
            'yin_niu'     => ['银牛', '银光一闪'],
            'niu_niu'     => ['牛牛！', '牛气冲天！'],
            'niu_9'       => ['牛9', '嗯'],
            'niu_8'       => ['牛8', '还可以'],
            'niu_7'       => ['牛7', '凑合'],
            'niu_6'       => ['牛6', '勉强'],
            'niu_1'       => ['牛1', '唉'],
            'no_niu'      => ['没牛', '无牛……'],
            'win'         => ['赢了！', '轻松拿捏'],
            'lose'        => ['输了', '下一把'],
            'draw'        => ['平局', '势均力敌'],
        ];
        foreach ($ai_players as &$player) {
            if (!isset($player['quotes']) || !is_array($player['quotes'])) {
                $player['quotes'] = $defaultQuotes;
            }
        }
        unset($player);
    }
    return $ai_players;
}

// ============================================================
// 用户工具
// ============================================================
function wx_niuniu_check_user() {
    return wx_games_check_user();
}

function wx_niuniu_resolve_avatar($uid, $photo = null) {
    return wx_games_resolve_avatar($uid, $photo);
}

function wx_niuniu_resolve_nickname($uid) {
    return wx_games_resolve_nickname($uid);
}

// ============================================================
// 积分操作
// ============================================================
function wx_niuniu_get_user_score($uid, $is_ai = 0) {
    try {
        $db = Database::getInstance();
        $uid = intval($uid);
        $is_ai = intval($is_ai);
        $table = DB_PREFIX . 'wx_games_scores';
        $row = $db->once_fetch_array("SELECT * FROM `" . $table . "` WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = $is_ai LIMIT 1");
        if ($row) {
            if ($is_ai === 0) {
                $user = $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
                $nickname = $user ? $user['nickname'] : $row['nickname'];
                $avatar   = wx_niuniu_resolve_avatar($uid, $user ? $user['photo'] : null);
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
        return null;
    } catch (Throwable $e) {
        return null;
    }
}

function wx_niuniu_ensure_user_score($uid, $nickname, $avatar, $is_ai = 0) {
    $db = Database::getInstance();
    $uid = intval($uid);
    $is_ai = intval($is_ai);
    $table = DB_PREFIX . 'wx_games_scores';
    $now = time();
    $row = $db->once_fetch_array("SELECT `id` FROM `" . $table . "` WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = $is_ai LIMIT 1");
    if (!$row) {
        $nickname = $db->escape_string($nickname);
        $avatar = $db->escape_string($avatar);
        $db->query("INSERT INTO `" . $table . "` (`game`, `uid`, `nickname`, `avatar`, `score`, `total_games`, `wins`, `losses`, `draws`, `is_ai`, `updated_at`, `created_at`)
            VALUES ('niuniu', $uid, '$nickname', '$avatar', 0, 0, 0, 0, 0, $is_ai, $now, $now)");
        return [
            'id'          => $db->insert_id(),
            'uid'         => $uid,
            'nickname'    => $nickname,
            'avatar'      => $avatar,
            'score'       => 0,
            'total_games' => 0,
            'wins'        => 0,
            'losses'      => 0,
            'draws'       => 0,
            'best_score'  => 0,
            'is_ai'       => $is_ai,
        ];
    }
    return wx_niuniu_get_user_score($uid, $is_ai);
}

function wx_niuniu_update_score($uid, $score_change, $is_ai = 0) {
    $db = Database::getInstance();
    $uid = intval($uid);
    $score_change = intval($score_change);
    $is_ai = intval($is_ai);
    $table = DB_PREFIX . 'wx_games_scores';
    $now = time();

    $row = $db->once_fetch_array("SELECT `score`, `wins`, `losses`, `draws`, `best_score` FROM `" . $table . "` WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = $is_ai LIMIT 1");
    if (!$row) return;

    $new_score = intval($row['score']) + $score_change;
    if ($score_change > 0) {
        $db->query("UPDATE `" . $table . "` SET `score` = $new_score, `wins` = `wins` + 1, `total_games` = `total_games` + 1, `best_score` = GREATEST(`best_score`, $new_score), `updated_at` = $now WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = $is_ai");
    } elseif ($score_change < 0) {
        $db->query("UPDATE `" . $table . "` SET `score` = $new_score, `losses` = `losses` + 1, `total_games` = `total_games` + 1, `updated_at` = $now WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = $is_ai");
    } else {
        $db->query("UPDATE `" . $table . "` SET `draws` = `draws` + 1, `total_games` = `total_games` + 1, `updated_at` = $now WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = $is_ai");
    }
}

function wx_niuniu_add_log($uid, $score_change, $reason, $operator = '') {
    $db = Database::getInstance();
    $uid = intval($uid);
    $score_change = intval($score_change);
    $table_scores = DB_PREFIX . 'wx_games_scores';
    $table_logs = DB_PREFIX . 'wx_games_logs';
    $row = $db->once_fetch_array("SELECT `score` FROM `" . $table_scores . "` WHERE `game` = 'niuniu' AND `uid` = $uid AND `is_ai` = 0 LIMIT 1");
    $score_before = $row ? intval($row['score']) : 0;
    $score_after = $score_before + $score_change;
    $nickname = wx_niuniu_resolve_nickname($uid);
    $now = time();
    $reason = $db->escape_string($reason);
    $operator = $db->escape_string($operator);
    $nickname = $db->escape_string($nickname);
    $db->query("INSERT INTO `" . $table_logs . "` (`game`, `uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
        VALUES ('niuniu', $uid, '$nickname', $score_change, $score_before, $score_after, '$reason', '$operator', $now)");
}

function wx_niuniu_apply_penalty($uid, $penalty_score) {
    $penalty_score = intval($penalty_score);
    if ($penalty_score >= 0) return;
    wx_niuniu_update_score($uid, $penalty_score, 0);
    wx_niuniu_add_log($uid, $penalty_score, '中途退出惩罚', 'system');
}

// ============================================================
// 斗牛核心：牌型计算
// ============================================================
function wx_niuniu_calc_type($cards) {
    $pointMap = ['A' => 1, 'J' => 10, 'Q' => 10, 'K' => 10];
    $values = [];
    $ranks = [];
    foreach ($cards as $c) {
        $v = $c['value'];
        $ranks[] = $v;
        $values[] = isset($pointMap[$v]) ? $pointMap[$v] : intval($v);
    }

    $total = array_sum($values);
    $types = wx_niuniu_types();

    // 1. 五小牛：5张总点数 ≤ 10
    if ($total <= 10) {
        return ['type' => 'wu_xiao_niu', 'name' => $types['wu_xiao_niu']['name'], 'weight' => $types['wu_xiao_niu']['weight'], 'multiplier' => $types['wu_xiao_niu']['multiplier']];
    }

    // 2. 炸弹：4张同点
    $counts = array_count_values($ranks);
    foreach ($counts as $v => $cnt) {
        if ($cnt >= 4) {
            return ['type' => 'zha_dan', 'name' => $types['zha_dan']['name'], 'weight' => $types['zha_dan']['weight'], 'multiplier' => $types['zha_dan']['multiplier']];
        }
    }

    // 3. 金牛：5张全J/Q/K
    $allFace = true;
    foreach ($cards as $c) { if (!in_array($c['value'], ['J','Q','K'])) { $allFace = false; break; } }
    if ($allFace) {
        return ['type' => 'jin_niu', 'name' => $types['jin_niu']['name'], 'weight' => $types['jin_niu']['weight'], 'multiplier' => $types['jin_niu']['multiplier']];
    }

    // 4. 银牛：5张全10/J/Q/K
    $allTenFace = true;
    foreach ($cards as $c) { if (!in_array($c['value'], ['10','J','Q','K'])) { $allTenFace = false; break; } }
    if ($allTenFace) {
        return ['type' => 'yin_niu', 'name' => $types['yin_niu']['name'], 'weight' => $types['yin_niu']['weight'], 'multiplier' => $types['yin_niu']['multiplier']];
    }

    // 5. 普通牛型
    $combos = [
        [0,1,2],[0,1,3],[0,1,4],[0,2,3],[0,2,4],
        [0,3,4],[1,2,3],[1,2,4],[1,3,4],[2,3,4]
    ];
    $bestWeight = -1;
    $bestType = 'no_niu';

    foreach ($combos as $idx) {
        $sum3 = $values[$idx[0]] + $values[$idx[1]] + $values[$idx[2]];
        if ($sum3 % 10 === 0) {
            $remainingIdx = array_values(array_diff([0,1,2,3,4], $idx));
            $sum2 = $values[$remainingIdx[0]] + $values[$remainingIdx[1]];
            $niuPoint = $sum2 % 10;
            $typeKey = ($niuPoint === 0) ? 'niu_niu' : 'niu_' . $niuPoint;
            if ($types[$typeKey]['weight'] > $bestWeight) {
                $bestWeight = $types[$typeKey]['weight'];
                $bestType = $typeKey;
            }
        }
    }

    return [
        'type'       => $bestType,
        'name'       => $types[$bestType]['name'],
        'weight'     => $types[$bestType]['weight'],
        'multiplier' => $types[$bestType]['multiplier'],
    ];
}

// 同牌型时比最大牌点数+花色
function wx_niuniu_compare_same_type($aCards, $bCards) {
    $pointMap = ['A' => 1, 'J' => 10, 'Q' => 10, 'K' => 10];
    $suitWeight = ['♠' => 4, '♥' => 3, '♣' => 2, '♦' => 1];
    $aMax = ['point' => -1, 'suit' => 0];
    $bMax = ['point' => -1, 'suit' => 0];
    foreach ($aCards as $c) {
        $p = isset($pointMap[$c['value']]) ? $pointMap[$c['value']] : intval($c['value']);
        $s = $suitWeight[$c['suit']] ?? 0;
        if ($p > $aMax['point'] || ($p === $aMax['point'] && $s > $aMax['suit'])) {
            $aMax = ['point' => $p, 'suit' => $s];
        }
    }
    foreach ($bCards as $c) {
        $p = isset($pointMap[$c['value']]) ? $pointMap[$c['value']] : intval($c['value']);
        $s = $suitWeight[$c['suit']] ?? 0;
        if ($p > $bMax['point'] || ($p === $bMax['point'] && $s > $bMax['suit'])) {
            $bMax = ['point' => $p, 'suit' => $s];
        }
    }
    if ($aMax['point'] > $bMax['point']) return 1;
    if ($aMax['point'] < $bMax['point']) return -1;
    return $aMax['suit'] <=> $bMax['suit'];
}

function wx_niuniu_compare($aCards, $bCards) {
    $aType = wx_niuniu_calc_type($aCards);
    $bType = wx_niuniu_calc_type($bCards);

    if ($aType['weight'] > $bType['weight']) return 1;
    if ($aType['weight'] < $bType['weight']) return -1;
    // 同牌型比最大牌
    return wx_niuniu_compare_same_type($aCards, $bCards);
}

// ============================================================
// 发牌（52张标准牌，无王）
// ============================================================
function wx_niuniu_deal() {
    $suits = ['♠', '♥', '♣', '♦'];
    $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    $deck = [];
    $id = 1;
    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = ['id' => $id++, 'suit' => $suit, 'value' => $value];
        }
    }
    shuffle($deck);

    // 7人局：玩家 + 6 AI（35张牌）
    $hands = [];
    for ($i = 0; $i < 7; $i++) {
        $hands[] = array_slice($deck, $i * 5, 5);
    }
    return ['hands' => $hands];
}

// ============================================================
// AI 比牌（一句台词）
// ============================================================
function wx_niuniu_ai_quote($typeName) {
    $quotes = [
        'wu_xiao_niu' => ['五小牛！天胡！', '五小牛！我无敌了！'],
        'zha_dan'     => ['炸弹！炸翻全场！', '炸弹牛来了！'],
        'jin_niu'     => ['金牛！纯金打造！', '金牛金灿灿！'],
        'yin_niu'     => ['银牛！也不错', '银牛，稳稳的'],
        'niuniu'      => ['牛牛！通杀！', '牛气冲天！', '不好意思，牛牛'],
        'niu_9'       => ['牛9，不小了', '可以可以，牛9'],
        'niu_8'       => ['牛8，还行', '嗯，牛8'],
        'niu_7'       => ['牛7，马马虎虎', '牛7，凑合'],
        'niu_1'       => ['牛1...真小', '唉，才牛1'],
        'no_niu'      => ['没牛...', '无牛，认了', '没牛，下一把'],
    ];
    foreach ($quotes as $key => $list) {
        if (strpos($typeName, $key) !== false) {
            return $list[array_rand($list)];
        }
    }
    return '看看谁大';
}

// ============================================================
// AJAX 路由
// ============================================================
function wx_niuniu_route_ajax($action) {
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
        case 'get_ranking':          wx_niuniu_api_get_ranking();          break;
        case 'get_my_rank':          wx_niuniu_api_get_my_rank();          break;
        case 'get_user_logs':        wx_niuniu_api_get_user_logs();        break;
        case 'deal':                 wx_niuniu_api_deal();                 break;
        case 'showdown':             wx_niuniu_api_showdown();             break;
        case 'save_ai_score':        wx_niuniu_api_save_ai_score();        break;
        case 'get_shop_items':       wx_niuniu_api_get_shop_items();       break;
        case 'get_inventory':        wx_niuniu_api_get_inventory();        break;
        case 'use_item':             wx_niuniu_api_use_item();             break;
        case 'get_active_effects':   wx_niuniu_api_get_active_effects();   break;
        case 'purchase_item':        wx_niuniu_api_purchase_item();        break;
        case 'admin_get_inventory':  wx_niuniu_admin_get_inventory();      break;
        case 'admin_add_item':       wx_niuniu_admin_add_item();           break;
        case 'admin_update_item':    wx_niuniu_admin_update_item();        break;
        case 'admin_delete_item':    wx_niuniu_admin_delete_item();        break;
        default:
            echo json_encode(['code' => -1, 'msg' => '未知操作'], JSON_UNESCAPED_UNICODE);
            exit;
    }
}

// ============================================================
// 信号处理
// ============================================================
function wx_niuniu_handle_signal($signal) {
    $user = wx_niuniu_check_user();
    if (!$user) return;
    $db = Database::getInstance();
    $suid = intval($user['uid']);
    $table_games = DB_PREFIX . 'wx_niuniu_games';
    $now = time();

    if ($signal === 'start') {
        // 先关闭所有历史未完成记录
        $db->query("UPDATE `" . $table_games . "` SET
            `status` = 0, `finished_at` = $now
            WHERE `uid` = $suid AND `status` = 1");
        $nickname = $db->escape_string($user['nickname']);
        $db->query("INSERT INTO `" . $table_games . "`
            (`uid`, `nickname`, `score_change`, `result`, `status`, `created_at`)
            VALUES ($suid, '$nickname', 0, 'draw', 1, $now)");
    } elseif ($signal === 'end') {
        $db->query("UPDATE `" . $table_games . "` SET
            `status` = 0, `finished_at` = $now
            WHERE `uid` = $suid AND `status` = 1");
    } elseif ($signal === 'penalty') {
        $penalty_points = isset($_GET['points']) ? intval($_GET['points']) : 100;
        $penalty_points = -abs($penalty_points);
        wx_niuniu_apply_penalty($user['uid'], $penalty_points);
        $db->query("UPDATE `" . $table_games . "` SET
            `result` = 'lose', `score_change` = $penalty_points, `status` = 0, `finished_at` = $now
            WHERE `uid` = $suid AND `status` = 1");
    }
}

// ============================================================
// API: 发牌（先看牌，后押注）
// ============================================================
function wx_niuniu_api_deal() {
    $user = wx_niuniu_check_user();
    if (!$user) {
        echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $config = wx_niuniu_get_config();
    $aiPlayers = wx_niuniu_get_ai_players();
    $baseBet = intval($config['base_bet'] ?? 100);

    // 发牌（7人：玩家 + 6 AI）
    $deal = wx_niuniu_deal();
    $playerCards = $deal['hands'][0];

    // 计算玩家牌型
    $playerType = wx_niuniu_calc_type($playerCards);

    // 计算所有AI的牌型（预计算，用于后续亮牌）
    $aiData = [];
    for ($i = 0; $i < 6; $i++) {
        $aiCards = $deal['hands'][$i + 1];
        $aiType = wx_niuniu_calc_type($aiCards);
        $aiData[] = [
            'cards' => $aiCards,
            'type'  => $aiType,
            'name'  => $aiPlayers[$i]['name'] ?? ('AI牛人' . ($i+1)),
            'quote' => wx_niuniu_ai_quote($aiType['name']),
            'avatar'=> $aiPlayers[$i]['avatar'] ?? '',
        ];
    }

    // 获取或创建积分记录
    $scoreData = wx_niuniu_get_user_score($user['uid'], 0);
    if (!$scoreData) {
        $scoreData = wx_niuniu_ensure_user_score($user['uid'], $user['nickname'], '', 0);
    }

    echo json_encode(['code' => 0, 'data' => [
        'player_cards'  => $playerCards,
        'player_type'   => $playerType,
        'ai_players'    => $aiData,
        'base_bet'      => $baseBet,
        'current_score' => $scoreData['score'] ?? 0,
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// API: 结算（接受bet + 逐一亮牌）
// ============================================================
function wx_niuniu_api_showdown() {
    $user = wx_niuniu_check_user();
    if (!$user) {
        echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['total_change'])) {
        echo json_encode(['code' => -1, 'msg' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $totalChange = intval($input['total_change']);
    $resultStr = $totalChange > 0 ? 'win' : ($totalChange < 0 ? 'lose' : 'draw');

    // 更新用户积分
    wx_niuniu_update_score($user['uid'], $totalChange, 0);

    // 记流水
    $reason = $totalChange > 0 ? '斗牛胜利+' . $totalChange : '斗牛失利' . $totalChange;
    wx_niuniu_add_log($user['uid'], $totalChange, $reason);

    // 关闭本局游戏记录（防逃跑误检）
    $table_games = DB_PREFIX . 'wx_niuniu_games';
    $now = time();
    $db = Database::getInstance();
    $db->query("UPDATE `" . $table_games . "` SET
        `status` = 0, `finished_at` = $now,
        `result` = '$resultStr', `score_change` = $totalChange
        WHERE `uid` = " . intval($user['uid']) . " AND `status` = 1");

    $scoreData = wx_niuniu_get_user_score($user['uid'], 0);
    $currentScore = $scoreData ? $scoreData['score'] : 0;

    echo json_encode(['code' => 0, 'data' => [
        'result'        => $totalChange > 0 ? 'win' : ($totalChange < 0 ? 'lose' : 'draw'),
        'total_change'  => $totalChange,
        'current_score' => $currentScore,
        'message'       => $totalChange > 0 ? '🎉 赢了 ' . $totalChange . ' 分！' : ($totalChange < 0 ? '😅 输了 ' . abs($totalChange) . ' 分' : '🤝 平局'),
    ]], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// API: AI 分数保存
// ============================================================
function wx_niuniu_api_save_ai_score() {
    $nickname    = isset($_POST['nickname']) ? addslashes(trim($_POST['nickname'])) : 'AI';
    $avatar      = isset($_POST['avatar'])   ? addslashes(trim($_POST['avatar']))   : '';
    $score_change = isset($_POST['score'])   ? intval($_POST['score'])              : 0;
    $result      = isset($_POST['result'])   ? addslashes(trim($_POST['result']))   : 'draw';
    $ai_uid      = wx_games_get_ai_uid($nickname);
    
    // 确保有积分记录（创建 or 已有）
    $scoreData = wx_niuniu_ensure_user_score($ai_uid, $nickname, $avatar, 1);
    if (!$scoreData) {
        echo json_encode(['code' => -1, 'msg' => '创建AI积分记录失败', 'nickname' => $nickname, 'uid' => $ai_uid], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 读取当前积分
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_scores';
    $row = $db->once_fetch_array("SELECT `score` FROM `" . $table . "` WHERE `game` = 'niuniu' AND `uid` = $ai_uid AND `is_ai` = 1 LIMIT 1");
    if (!$row) {
        echo json_encode(['code' => -1, 'msg' => '找不到AI积分记录', 'uid' => $ai_uid, 'nickname' => $nickname], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 更新积分
    $new_score = intval($row['score']) + $score_change;
    $now = time();
    if ($score_change > 0) {
        $db->query("UPDATE `" . $table . "` SET `score` = $new_score, `wins` = `wins` + 1, `total_games` = `total_games` + 1, `best_score` = GREATEST(`best_score`, $new_score), `updated_at` = $now WHERE `game` = 'niuniu' AND `uid` = $ai_uid AND `is_ai` = 1");
    } elseif ($score_change < 0) {
        $db->query("UPDATE `" . $table . "` SET `score` = $new_score, `losses` = `losses` + 1, `total_games` = `total_games` + 1, `updated_at` = $now WHERE `game` = 'niuniu' AND `uid` = $ai_uid AND `is_ai` = 1");
    } else {
        $db->query("UPDATE `" . $table . "` SET `draws` = `draws` + 1, `total_games` = `total_games` + 1, `updated_at` = $now WHERE `game` = 'niuniu' AND `uid` = $ai_uid AND `is_ai` = 1");
    }
    
    // 记流水
    $nickname_esc = $db->escape_string($nickname);
    $table_logs = DB_PREFIX . 'wx_games_logs';
    $reason = 'AI对战 (' . $result . ')';
    $db->query("INSERT INTO `" . $table_logs . "` (`game`, `uid`, `nickname`, `score_change`, `score_before`, `score_after`, `reason`, `operator`, `created_at`)
        VALUES ('niuniu', $ai_uid, '$nickname_esc', $score_change, " . intval($row['score']) . ", $new_score, '$reason', 'system', $now)");
    
    echo json_encode(['code' => 0, 'msg' => 'AI分数已保存', 'uid' => $ai_uid, 'score' => $new_score], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// API: 排行榜
// ============================================================
function wx_niuniu_api_get_ranking() {
    $config = wx_niuniu_get_config();
    $max = intval($config['max_entries'] ?? 50);
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $rows = $db->query("SELECT * FROM `" . $table . "` WHERE `game` = 'niuniu' ORDER BY `score` DESC LIMIT $max");
        $list = [];
        while ($row = $db->fetch_array($rows)) {
            $uid = intval($row['uid']);
            $is_ai = (int)$row['is_ai'];
            $user = $is_ai ? null : $db->once_fetch_array("SELECT `nickname`, `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1");
            $list[] = [
                'uid'         => $uid,
                'nickname'    => $user ? $user['nickname'] : $row['nickname'],
                'avatar'      => $is_ai ? $row['avatar'] : wx_niuniu_resolve_avatar($uid, $user ? $user['photo'] : null),
                'score'       => (int)$row['score'],
                'total_games' => (int)$row['total_games'],
                'wins'        => (int)$row['wins'],
                'losses'      => (int)$row['losses'],
                'is_ai'       => $is_ai,
            ];
        }
        echo json_encode(['code' => 0, 'data' => $list], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['code' => -1, 'msg' => '获取排行榜失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_niuniu_api_get_my_rank() {
    $user = wx_niuniu_check_user();
    if (!$user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_scores';
        $row = $db->once_fetch_array("SELECT `id`, `score`, `total_games`, `wins`, `losses` FROM `" . $table . "` WHERE `uid` = " . intval($user['uid']) . " AND `is_ai` = 0 LIMIT 1");
        if (!$row) { echo json_encode(['code' => 0, 'data' => null], JSON_UNESCAPED_UNICODE); exit; }
        $rank = $db->once_fetch_array("SELECT COUNT(*) AS cnt FROM `" . $table . "` WHERE `game` = 'niuniu' AND `is_ai` = 0 AND `score` > " . intval($row['score']));
        $row['rank'] = ($rank ? intval($rank['cnt']) : 0) + 1;
        echo json_encode(['code' => 0, 'data' => $row], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['code' => -1, 'msg' => '获取排名失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ============================================================
// API: 积分流水
// ============================================================
function wx_niuniu_api_get_user_logs() {
    $target_uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($target_uid <= 0) {
        // 默认取当前用户（前台用）
        $user = wx_niuniu_check_user();
        if (!$user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
        $target_uid = intval($user['uid']);
    }
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_logs';
        $rows = $db->query("SELECT * FROM `" . $table . "` WHERE `game` = 'niuniu' AND `uid` = $target_uid ORDER BY `created_at` DESC LIMIT 50");
        $list = [];
        while ($row = $db->fetch_array($rows)) {
            $list[] = [
                'score_change' => (int)$row['score_change'],
                'score_before' => (int)$row['score_before'],
                'score_after' => (int)$row['score_after'],
                'reason' => $row['reason'],
                'operator' => $row['operator'],
                'created_at' => $row['created_at'] ? date('Y-m-d H:i', (int)$row['created_at']) : $row['created'],
            ];
        }
        echo json_encode(['code' => 0, 'data' => $list], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['code' => -1, 'msg' => '获取流水失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ============================================================
// API: 商城
// ============================================================
function wx_niuniu_api_get_shop_items() {
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_shop_items';
        $table_inv = DB_PREFIX . 'wx_games_user_items';

        $user = wx_niuniu_check_user();
        $uid = $user ? intval($user['uid']) : 0;

        $rows = $db->query("SELECT * FROM `" . $table . "` WHERE (`game` = 'niuniu' OR `is_global` = 1) AND `status` = 1 ORDER BY `sort_order` ASC, `id` ASC");
        $list = [];
        while ($row = $db->fetch_array($rows)) {
            $item_id = (int)$row['id'];
            $owned = false;
            $owned_qty = 0;
            if ($uid > 0) {
                $own = $db->once_fetch_array("SELECT SUM(CAST(`quantity` AS SIGNED) - CAST(`used` AS SIGNED)) AS cnt FROM `" . $table_inv . "` WHERE `uid` = $uid AND `item_id` = $item_id LIMIT 1");
                $owned_qty = intval($own['cnt'] ?? 0);
                $owned = $owned_qty > 0;
            }
            $list[] = [
                'id'            => $item_id,
                'name'          => $row['name'],
                'description'   => $row['description'],
                'icon'          => $row['icon'],
                'item_type'     => $row['item_type'],
                'effect_data'   => $row['effect_data'],
                'price_emlog'   => (int)$row['price_emlog'],
                'price_niuniu'  => (int)$row['price_game'],
                'stock'         => (int)$row['stock'],
                'max_per_user'  => (int)$row['max_per_user'],
                'is_global'     => !empty($row['is_global']),
                'owned'         => $owned,
                'owned_qty'     => $owned_qty,
            ];
        }
        echo json_encode(['code' => 0, 'data' => $list], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['code' => -1, 'msg' => '获取商城失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_niuniu_api_get_inventory() {
    $user = wx_niuniu_check_user();
    if (!$user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    try {
        $db = Database::getInstance();
        $table = DB_PREFIX . 'wx_games_user_items';
        $shop = DB_PREFIX . 'wx_games_shop_items';
        $uid = intval($user['uid']);

        // 自动修复：同类别道具如果有多个 is_active=1，只保留最近购买的一个
        $active_check = $db->query("SELECT ui.id, si.item_type, ui.purchased_at FROM `" . $table . "` ui
            JOIN `" . $shop . "` si ON ui.item_id = si.id
            WHERE ui.`game` = 'niuniu' AND ui.uid = $uid AND ui.is_active = 1 ORDER BY ui.purchased_at DESC");
        $seen_groups = [];
        $to_deactivate = [];
        while ($ac = $db->fetch_array($active_check)) {
            $grp = $ac['item_type'];
            if (isset($seen_groups[$grp])) {
                $to_deactivate[] = (int)$ac['id'];
            } else {
                $seen_groups[$grp] = true;
            }
        }
        if (!empty($to_deactivate)) {
            $db->query("UPDATE `" . $table . "` SET `is_active` = 0 WHERE `id` IN (" . implode(',', $to_deactivate) . ")");
        }

        $rows = $db->query("SELECT ui.*, si.name, si.description, si.icon, si.item_type, si.effect_data
            FROM `" . $table . "` ui LEFT JOIN `" . $shop . "` si ON ui.item_id = si.id
            WHERE ui.`game` = 'niuniu' AND ui.uid = $uid AND ui.quantity > 0 ORDER BY ui.is_active DESC, ui.purchased_at DESC");
        $list = [];
        while ($row = $db->fetch_array($rows)) {
            $list[] = $row;
        }
        echo json_encode(['code' => 0, 'data' => $list], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['code' => -1, 'msg' => '获取背包失败'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_niuniu_api_purchase_item() {
    $user = wx_niuniu_check_user();
    if (!$user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $itemId = isset($input['item_id']) ? intval($input['item_id']) : 0;
    if ($itemId <= 0) {
        echo json_encode(['code' => -1, 'msg' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $db = Database::getInstance();
        $shop = DB_PREFIX . 'wx_games_shop_items';
        $item = $db->once_fetch_array("SELECT * FROM `" . $shop . "` WHERE `id` = $itemId AND `status` = 1 AND (`game` = 'niuniu' OR `is_global` = 1) LIMIT 1");
        if (!$item) {
            echo json_encode(['code' => -1, 'msg' => '商品不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 确定支付方式
        $priceEmlog = intval($item['price_emlog']);
        $priceNiuniu = intval($item['price_game']);
        $payCurrency = $input['currency'] ?? 'niuniu';

        if ($payCurrency === 'emlog' && $priceEmlog > 0) {
            // 站点积分支付
            $uid = intval($user['uid']);
            $userModel = new User_Model();
            $emlogUser = $userModel->getOneUser($uid);
            $credits = isset($emlogUser['credits']) ? intval($emlogUser['credits']) : 0;
            if ($credits < $priceEmlog) {
                echo json_encode(['code' => -1, 'msg' => '站点积分不足'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $userModel->reduceCredits($uid, $priceEmlog);
            if (function_exists('addCreditRecord')) {
                $game_title = (wx_niuniu_get_config()['title'] ?? 'H5斗牛');
                addCreditRecord($uid, 'reduce', $priceEmlog, $game_title . '_buy_' . $item['name'] . '_' . time());
            }
        } elseif ($payCurrency === 'niuniu' && $priceNiuniu > 0) {
            // 斗牛积分支付
            $scoreData = wx_niuniu_get_user_score($user['uid'], 0);
            if (!$scoreData || $scoreData['score'] < $priceNiuniu) {
                echo json_encode(['code' => -1, 'msg' => '斗牛积分不足'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            wx_niuniu_update_score($user['uid'], -$priceNiuniu, 0);
            wx_niuniu_add_log($user['uid'], -$priceNiuniu, '商城消费：' . $item['name']);
        } else {
            echo json_encode(['code' => -1, 'msg' => '价格无效'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 发放到背包
        $invTable = DB_PREFIX . 'wx_games_user_items';
        $uid = intval($user['uid']);
        $now = time();
        $is_global = !empty($item['is_global']);
        if ($is_global) {
            $all_games = ['ddz', 'mj', 'niuniu'];
            foreach ($all_games as $g) {
                $db->query("INSERT INTO `" . $invTable . "` (`game`, `uid`, `item_id`, `quantity`, `purchased_at`, `expires_at`)
                    VALUES ('$g', $uid, $itemId, 1, $now, 0)
                    ON DUPLICATE KEY UPDATE `quantity` = `quantity` + 1");
            }
        } else {
            $existing = $db->once_fetch_array("SELECT `id`, `quantity` FROM `" . $invTable . "` WHERE `game` = 'niuniu' AND `uid` = $uid AND `item_id` = $itemId LIMIT 1");
            if ($existing && intval($item['max_per_user'] ?? 0) > 0 && (intval($existing['quantity']) + 1) > intval($item['max_per_user'])) {
                echo json_encode(['code' => -1, 'msg' => '已达限购数量'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $now = time();
            if ($existing) {
                $db->query("UPDATE `" . $invTable . "` SET `quantity` = `quantity` + 1 WHERE `id` = " . intval($existing['id']));
            } else {
                $db->query("INSERT INTO `" . $invTable . "` (`game`, `uid`, `item_id`, `quantity`, `purchased_at`, `expires_at`) VALUES ('niuniu', $uid, $itemId, 1, $now, 0)");
            }
        }

        echo json_encode(['code' => 0, 'msg' => '购买成功'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['code' => -1, 'msg' => '购买失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ============================================================
// 道具激活 / 已激活效果查询
// ============================================================
function wx_niuniu_api_use_item() {
    $user = wx_niuniu_check_user();
    if (!$user) { echo json_encode(['code' => -1, 'msg' => '未登录'], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($user['uid']);

    $input = json_decode(file_get_contents('php://input'), true);
    $inv_id = isset($input['inv_id']) ? intval($input['inv_id']) : 0;
    if ($inv_id <= 0) { $inv_id = isset($_POST['inv_id']) ? intval($_POST['inv_id']) : 0; }
    if ($inv_id <= 0) { echo json_encode(['code' => -1, 'msg' => '参数错误'], JSON_UNESCAPED_UNICODE); exit; }

    $db = Database::getInstance();
    $table_inv   = DB_PREFIX . 'wx_games_user_items';
    $table_items = DB_PREFIX . 'wx_games_shop_items';
    $row = $db->once_fetch_array("SELECT i.*, s.`item_type`, s.`effect_data` FROM `" . $table_inv . "` i
        JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
        WHERE i.`id` = $inv_id AND i.`uid` = $uid AND i.`quantity` > i.`used` LIMIT 1");
    if (!$row) { echo json_encode(['code' => -1, 'msg' => '道具不存在或已用完'], JSON_UNESCAPED_UNICODE); exit; }

    $item_type = $row['item_type'];
    $global_types = ['title_colored', 'title_effect'];
    $cosmetic_types = ['title_colored', 'title_effect', 'card_back', 'emoticon', 'title_badge'];
    if (in_array($item_type, $cosmetic_types, true)) {
        $db->query("UPDATE `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = '" . $db->escape_string($item_type) . "'");
        if (in_array($item_type, $global_types, true)) {
            $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1 WHERE `uid` = $uid AND `item_id` = " . intval($row['item_id']));
        } else {
            $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1 WHERE `game` = 'niuniu' AND `id` = " . intval($row['id']));
        }
        echo json_encode(['code' => 0, 'msg' => '已激活', 'item_type' => $item_type, 'effect_data' => $row['effect_data']], JSON_UNESCAPED_UNICODE);
    } elseif ($item_type === 'score_buff') {
        $effect = json_decode(stripslashes($row['effect_data']), true);
        $multiplier = isset($effect['multiplier']) ? floatval($effect['multiplier']) : 2;
        $games = isset($effect['games']) ? intval($effect['games']) : 3;
        if ($multiplier <= 0) $multiplier = 2;
        if ($games <= 0) $games = 3;
        $db->query("UPDATE `" . $table_inv . "` i JOIN `" . $table_items . "` s ON i.`item_id` = s.`id`
            SET i.`is_active` = 0 WHERE i.`uid` = $uid AND s.`item_type` = 'score_buff'");
        $db->query("UPDATE `" . $table_inv . "` SET `is_active` = 1, `charges` = $games, `used` = 0 WHERE `id` = " . intval($row['id']));
        echo json_encode(['code' => 0, 'msg' => $multiplier . '倍加成已激活，剩余' . $games . '局', 'multiplier' => $multiplier, 'games' => $games], JSON_UNESCAPED_UNICODE);
    } else {
        $db->query("UPDATE `" . $table_inv . "` SET `used` = `used` + 1 WHERE `game` = 'niuniu' AND `id` = " . intval($row['id']));
        echo json_encode(['code' => 0, 'msg' => '使用成功'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function wx_niuniu_api_get_active_effects() {
    $user = wx_niuniu_check_user();
    if (!$user) { echo json_encode(['code' => 0, 'data' => []], JSON_UNESCAPED_UNICODE); exit; }
    $uid = intval($user['uid']);
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
    echo json_encode(['code' => 0, 'data' => $effects], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// 管理员：背包管理API
// ============================================================
function wx_niuniu_admin_get_inventory() {
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($uid <= 0) {
        echo json_encode(['code' => -1, 'message' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_user_items';
    $items = [];
    $result = $db->query("SELECT ui.*, si.name AS item_name, si.icon, si.item_type, si.effect_data
        FROM `$table` ui
        LEFT JOIN `" . DB_PREFIX . "wx_games_shop_items` si ON ui.item_id = si.id
        WHERE ui.uid = $uid AND ui.`game` = 'niuniu'
        ORDER BY ui.id DESC");
    while ($row = $db->fetch_array($result)) {
        $items[] = [
            'id' => (int)$row['id'],
            'item_id' => (int)$row['item_id'],
            'item_name' => $row['item_name'],
            'icon' => $row['icon'],
            'item_type' => $row['item_type'],
            'type_label' => '',
            'quantity' => (int)$row['quantity'],
            'used' => (int)$row['used'],
            'is_active' => (int)$row['is_active'],
            'charges' => (int)$row['charges'],
            'expires_at' => $row['expires_at'] ?? '',
        ];
    }
    echo json_encode(['code' => 0, 'data' => ['items' => $items]], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_niuniu_admin_add_item() {
    $uid = isset($_POST['uid']) ? intval($_POST['uid']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    if ($uid <= 0 || $item_id <= 0 || $qty <= 0) {
        echo json_encode(['code' => -1, 'message' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_user_items';
    $db->query("INSERT INTO `$table` (`game`, `uid`, `item_id`, `quantity`, `used`, `is_active`, `charges`, `purchased_at`)
        VALUES ('niuniu', $uid, $item_id, $qty, 0, 1, 0, NOW())");
    echo json_encode(['code' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_niuniu_admin_update_item() {
    $inv_id = isset($_POST['inv_id']) ? intval($_POST['inv_id']) : 0;
    $qty = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $used = isset($_POST['used']) ? intval($_POST['used']) : 0;
    $active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    $charges = isset($_POST['charges']) ? intval($_POST['charges']) : 0;
    $expires = isset($_POST['expires_at']) ? addslashes(trim($_POST['expires_at'])) : '';
    if ($inv_id <= 0) {
        echo json_encode(['code' => -1, 'message' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_user_items';
    $db->query("UPDATE `$table` SET
        `quantity` = $qty, `used` = $used, `is_active` = $active,
        `charges` = $charges
        " . ($expires ? ", `expires_at` = '$expires'" : "") . "
        WHERE `id` = $inv_id");
    echo json_encode(['code' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}

function wx_niuniu_admin_delete_item() {
    $inv_id = isset($_GET['inv_id']) ? intval($_GET['inv_id']) : 0;
    if ($inv_id <= 0) {
        echo json_encode(['code' => -1, 'message' => '参数错误'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_games_user_items';
    $db->query("DELETE FROM `$table` WHERE `game` = 'niuniu' AND `id` = $inv_id");
    echo json_encode(['code' => 0], JSON_UNESCAPED_UNICODE);
    exit;
}
