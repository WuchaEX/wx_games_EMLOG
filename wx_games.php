<?php
/**
 * wx_games - 棋牌大厅 Emlog 插件
 * 主入口：路由分发、钩子注册、通用工具
 *
 * Plugin Name: 棋牌大厅
 * Version: 1.0.0
 * Plugin URL: https://www.emlog.net/plugin/detail/wx_games/
 * Description: 棋牌游戏合集插件，支持逐步添加多款游戏。当前包含：H5斗地主、H5国标麻将。
 * Author: 舞嗏EX
 * Author URL: https://www.emlog.net
 */
!defined('EMLOG_ROOT') && exit('access denied!');

// ============================================================
// 插件常量
// ============================================================
define('WX_GAMES_NAME', 'wx_games');
define('WX_GAMES_PATH', EMLOG_ROOT . 'content/plugins/' . WX_GAMES_NAME . '/');
define('WX_GAMES_URL', BLOG_URL . 'content/plugins/' . WX_GAMES_NAME . '/');

// ============================================================
// 已注册游戏清单
// 注：wx_games_ddz_fn.php 和 wx_games_mojang_fn.php
//     需在 include 后保证各自的函数定义可用
// ============================================================
$wx_games_list = [
    'ddz' => [
        'name'       => 'H5斗地主',
        'desc'       => '经典斗地主，AI对战，商城系统',
        'icon'       => '♠',
        'action_key' => 'ddz_action',
        'signal_key' => 'wxddz_signal',
        'fn_file'    => __DIR__ . '/wx_games_ddz_fn.php',
        'show_file'  => 'wx_games_ddz_show.php',
        'settings'   => ['title', 'guest_play', 'ai_names', 'notice'],
    ],
    'mj' => [
        'name'       => 'H5麻将',
        'desc'       => '国标麻将，8番起胡，完整番型计算',
        'icon'       => '🀄',
        'action_key' => 'mj_action',
        'signal_key' => 'wx_mojang_signal',
        'fn_file'    => __DIR__ . '/wx_games_mojang_fn.php',
        'show_file'  => 'wx_games_mojang_show.php',
        'settings'   => ['title', 'guest_play', 'ai_names', 'notice', 'base_score', 'min_fan_to_win'],
    ],
];

// ============================================================
// 检测当前访问的游戏
// ============================================================
$wx_games_game = isset($_GET['game']) ? preg_replace('/[^a-z_]/', '', $_GET['game']) : '';

// ============================================================
// 信号处理（必须在输出之前，返回 1x1 GIF）
// ============================================================
if (!empty($wx_games_game) && isset($wx_games_list[$wx_games_game])) {
    $g = $wx_games_list[$wx_games_game];
    $signal = isset($_GET[$g['signal_key']]) ? trim($_GET[$g['signal_key']]) : '';
    if (!empty($signal) && in_array($signal, ['start', 'end', 'penalty'], true)) {
        require_once $g['fn_file'];
        // 调用游戏对应的信号处理函数 —— 各游戏 fn.php 需提供：
        // wx_{game}_handle_signal($signal)
        $handler = str_replace('wx_', '', $wx_games_game) === 'ddz'
            ? 'wx_ddz_handle_signal'
            : 'wx_mojang_handle_signal';
        if (function_exists($handler)) {
            $handler($signal);
        }
        // 返回 1x1 透明 GIF
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
}

// ============================================================
// AJAX 路由处理
// ============================================================
if (!empty($wx_games_game) && isset($wx_games_list[$wx_games_game])) {
    // 在后台 admin 上下文中跳过 AJAX 路由（留给 setting.php 处理）
    $is_admin = defined('ISADMIN') || strpos($_SERVER['SCRIPT_NAME'] ?? '', 'admin/') !== false;
    if ($is_admin) {
        // 不执行 AJAX 路由
    } else {
        $g = $wx_games_list[$wx_games_game];
        $action = isset($_GET[$g['action_key']]) ? addslashes(trim($_GET[$g['action_key']])) : '';
    if (!empty($action)) {
        require_once $g['fn_file'];
        // 调用游戏对应的 AJAX 路由函数
        $router = str_replace('wx_', '', $wx_games_game) === 'ddz'
            ? 'wx_ddz_route_ajax'
            : 'wx_mojang_route_ajax';
        if (function_exists($router)) {
            $router($action);
        }
        exit;
        }
    }
}

// ============================================================
// 钩子注册
// ============================================================

/**
 * 前台头部：加载游戏 CSS
 */
addAction('index_head', 'wx_games_index_head');
function wx_games_index_head() {
    if (Input::getStrVar('plugin', '') !== 'wx_games') return;
    $game = isset($_GET['game']) ? preg_replace('/[^a-z_]/', '', $_GET['game']) : '';

    if (empty($game)) {
        // 游戏大厅页
        echo '<link rel="stylesheet" href="' . WX_GAMES_URL . 'css/hub.css?v=1.0.0">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    } elseif ($game === 'ddz') {
        echo '<link rel="stylesheet" href="' . WX_GAMES_URL . 'games/ddz/css/style.css?v=1.0.0">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">' . "\n";
    } elseif ($game === 'mj') {
        echo '<link rel="stylesheet" href="' . WX_GAMES_URL . 'games/mojang/css/style.css?v=1.0.0">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">' . "\n";
    }
}

/**
 * 后台头部：加载游戏后台 CSS
 */
addAction('adm_head', 'wx_games_adm_head');
function wx_games_adm_head() {
    if (Input::getStrVar('plugin', '') !== 'wx_games') return;
    // 暂无需独立后台 CSS
}

/**
 * 导航菜单：游戏大厅入口
 */
addAction('index_menu', 'wx_games_add_menu');
function wx_games_add_menu() {
    echo '<a href="' . BLOG_URL . '?plugin=wx_games">🎮 棋牌大厅</a>' . "\n";
}

// ============================================================
// 通用工具函数
// ============================================================

/**
 * 获取已启用的游戏列表（前台可见）
 */
function wx_games_get_list() {
    global $wx_games_list;
    $storage = Storage::getInstance('wx_games');
    $game_status = $storage->getValue('game_status');
    if (!is_array($game_status)) return $wx_games_list;
    $list = [];
    foreach ($wx_games_list as $key => $g) {
        if (isset($game_status[$key]) && $game_status[$key] === '0') continue;
        $list[$key] = $g;
    }
    return $list ?: $wx_games_list; // 全关时兜底
}

/**
 * 检查游戏是否启用
 */
function wx_games_is_enabled($game_key) {
    $storage = Storage::getInstance('wx_games');
    $game_status = $storage->getValue('game_status');
    if (!is_array($game_status)) return true;
    return !(isset($game_status[$game_key]) && $game_status[$game_key] === '0');
}

/**
 * 检查用户登录状态
 * @return array|null ['uid'=>int, 'nickname'=>string, 'avatar'=>string]
 */
function wx_games_check_user() {
    $uid = 0;

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (defined('UID') && UID > 0) {
        $uid = intval(UID);
    } elseif (!empty($_SESSION['user']['uid'])) {
        $uid = intval($_SESSION['user']['uid']);
    } elseif (!empty($_SESSION['uid'])) {
        $uid = intval($_SESSION['uid']);
    }

    // ro_get_current_user() 后备
    if ($uid == 0 && function_exists('ro_get_current_user')) {
        $u = ro_get_current_user();
        if (!empty($u['uid'])) {
            $uid = intval($u['uid']);
        }
    }

    if ($uid > 0) {
        $db = Database::getInstance();
        $row = $db->once_fetch_array(
            "SELECT uid, nickname, photo FROM `" . DB_PREFIX . "user` WHERE uid = $uid LIMIT 1"
        );
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
            return [
                'uid'      => (int)$row['uid'],
                'nickname' => $row['nickname'],
                'avatar'   => $avatar,
            ];
        }
    }
    return null;
}

/**
 * 从 Emlog user 表实时获取头像 URL
 * @param int    $uid
 * @param string|null $photo 可选，已有 photo 时免查库
 */
function wx_games_resolve_avatar($uid, $photo = null) {
    $uid = intval($uid);
    if ($uid <= 0) {
        return BLOG_URL . 'admin/views/images/avatar.svg';
    }
    if ($photo === null) {
        $db = Database::getInstance();
        $row = $db->once_fetch_array(
            "SELECT `photo` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1"
        );
        $photoPath = $row ? $row['photo'] : '';
    } else {
        $photoPath = $photo;
    }
    if (class_exists('User') && method_exists('User', 'getAvatar')) {
        return User::getAvatar($photoPath);
    }
    if (!empty($photoPath)) {
        return filter_var($photoPath, FILTER_VALIDATE_URL)
            ? $photoPath
            : BLOG_URL . str_replace('../', '', $photoPath);
    }
    return BLOG_URL . 'admin/views/images/avatar.svg';
}

/**
 * 从 Emlog user 表实时获取昵称
 */
function wx_games_resolve_nickname($uid) {
    $uid = intval($uid);
    if ($uid <= 0) return '';
    try {
        $db = Database::getInstance();
        $row = $db->once_fetch_array(
            "SELECT `nickname` FROM `" . DB_PREFIX . "user` WHERE `uid` = $uid LIMIT 1"
        );
        return $row ? $row['nickname'] : '';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * 获取 Emlog 站点积分
 */
function wx_games_get_credits($uid) {
    try {
        $userModel = new User_Model();
        $user = $userModel->getOneUser(intval($uid));
        return ($user && isset($user['credits'])) ? intval($user['credits']) : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * 扣除 Emlog 站点积分 + 记流水
 */
function wx_games_reduce_credits($uid, $amount, $reason = '') {
    $uid = intval($uid);
    $amount = intval($amount);
    if ($uid <= 0 || $amount <= 0) return false;
    try {
        $userModel = new User_Model();
        $userModel->reduceCredits($uid, $amount);
        if (function_exists('addCreditRecord')) {
            addCreditRecord($uid, 'reduce', $amount, $reason ?: 'wx_games_reduce_' . time());
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * 增加 Emlog 站点积分 + 记流水
 */
function wx_games_add_credits($uid, $amount, $reason = '') {
    $uid = intval($uid);
    $amount = intval($amount);
    if ($uid <= 0 || $amount <= 0) return false;
    try {
        $userModel = new User_Model();
        $userModel->addCredits($uid, $amount);
        if (function_exists('addCreditRecord')) {
            addCreditRecord($uid, 'add', $amount, $reason ?: 'wx_games_add_' . time());
        }
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
