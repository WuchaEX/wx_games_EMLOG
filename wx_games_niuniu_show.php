<?php
/**
 * wx_games 斗牛前台页面
 */
!defined('EMLOG_ROOT') && exit('access denied!');

$plugin_url = wx_niuniu_get_plugin_url();
$config = wx_niuniu_get_config();
$current_user = wx_niuniu_check_user();

$user_score_data = null;
if ($current_user) {
    $user_score_data = wx_niuniu_get_user_score($current_user['uid']);
}

$base_url = BLOG_URL;
$login_url = $base_url . 'admin/account.php?action=signin';

// ========== 防逃跑：检查未完成游戏，发现即惩罚 ==========
$pending_game_warning = null;
$penalty_message = null;
$db_check = null;
if ($current_user) {
    $db_check = Database::getInstance();
    $table_games = DB_PREFIX . 'wx_niuniu_games';
    $check_uid = intval($current_user['uid']);
    $pending_row = $db_check->once_fetch_array(
        "SELECT `id`, `created_at` FROM `$table_games` 
         WHERE `uid` = $check_uid AND `status` = 1 
         ORDER BY `id` DESC LIMIT 1"
    );

    if ($pending_row) {
        $penalty_mul = isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0;
        $penalty = intval(-($config['base_bet'] ?? 100) * $penalty_mul);
        $now = time();
        $db_check->query("UPDATE `$table_games` SET 
            `result` = 'lose', `score_change` = $penalty, `status` = 0, `finished_at` = $now
            WHERE `uid` = $check_uid AND `status` = 1");
        wx_niuniu_apply_penalty($current_user['uid'], $penalty);
        $penalty_message = '检测到你上一局中途退出，已扣除 ' . abs($penalty) . ' 积分';
        $user_score_data = wx_niuniu_get_user_score($current_user['uid']);
    }
}

$emlog_credits = 0;
if ($current_user) {
    try {
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser($current_user['uid']);
        if ($emlog_user && isset($emlog_user['credits'])) {
            $emlog_credits = intval($emlog_user['credits']);
        }
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= htmlspecialchars($config['title'] ?? 'H5斗牛') ?></title>
<style>
/* ====== 基础重置 ====== */
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{width:100%;height:100%;overflow:hidden;touch-action:manipulation}
body{font-family:'Microsoft YaHei','PingFang SC','Helvetica Neue',sans-serif;
     background:linear-gradient(135deg,#2D1A12 0%,#1A0E08 100%);color:#fff;min-height:100vh}

/* ====== 导航栏（DDZ 统一风格） ====== */
.nn-nav{width:100%;margin:0;height:60px;background:linear-gradient(135deg,#e17055 0%,#d94a2e 100%);
        display:flex;align-items:center;position:fixed;top:0;left:0;z-index:5000;
        box-shadow:0 2px 20px rgba(225,112,85,0.3);
        transition:background 0.3s ease,box-shadow 0.3s ease}
.nn-nav-inner{width:100%;padding:0 24px;display:flex;align-items:center;justify-content:space-between;box-sizing:border-box}
.nn-nav-left{display:flex;align-items:center;gap:10px;flex-shrink:0}
.nn-nav-icon{font-size:24px;line-height:1}
.nn-nav-title{color:#fff;font-size:20px;font-weight:600;letter-spacing:2px;margin:0}
.nn-nav-right{display:flex;gap:12px;align-items:center;margin-left:auto}
.nn-nav-right a{color:rgba(255,255,255,0.85);text-decoration:none;font-size:14px;
                padding:6px 14px;border-radius:6px;transition:all 0.3s ease;white-space:nowrap}
.nn-nav-right a:hover{background:rgba(255,255,255,0.18);color:#fff}
.nn-nav-right a.nav-home-btn{background:rgba(255,255,255,0.2);color:#fff;font-weight:500;border:1px solid rgba(255,255,255,0.3)}
.nn-nav-right a.nav-home-btn:hover{background:rgba(255,255,255,0.3);border-color:rgba(255,255,255,0.5)}

/* 导航用户控制 */
.nav-user-info,.nav-score,.nav-btn,.nav-home-btn{display:flex;align-items:center;justify-content:center;height:34px;border-radius:17px;box-sizing:border-box;flex-shrink:0}
.nav-user-info{gap:6px;padding:0 10px;background:rgba(0,0,0,0.25);max-width:220px}
.nav-avatar{width:24px;height:24px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,215,0,0.7);flex-shrink:0}
.nav-user-name{font-size:13px;color:#fff;font-weight:500;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav-score{gap:4px;padding:0 10px;background:rgba(0,0,0,0.25);cursor:pointer;transition:background 0.2s ease}
.nav-score:hover{background:rgba(0,0,0,0.4)}
.nav-score-label{font-size:12px;color:rgba(255,255,255,0.8)}
.nav-score-value{font-size:14px;font-weight:bold;color:#ffd700}
.nav-btn{display:flex;align-items:center;justify-content:center;gap:4px;padding:0 12px;background:rgba(255,255,255,0.18);color:#fff;border:none;font-size:12px;cursor:pointer;transition:all 0.2s ease;white-space:nowrap;-webkit-appearance:none;appearance:none}
.nav-btn:hover{background:rgba(255,255,255,0.3)}
.nav-btn:active{transform:scale(0.95)}
.nav-home-btn{background:rgba(255,255,255,0.2);color:#fff;font-weight:500;border:1px solid rgba(255,255,255,0.3);padding:0 14px;text-decoration:none;font-size:14px}
.nav-home-btn:hover{background:rgba(255,255,255,0.3);border-color:rgba(255,255,255,0.5)}

/* ====== 登录/欢迎界面（深色卡片风格，同 DDZ 截图） ====== */
.nn-login-screen{position:fixed;top:60px;left:0;right:0;bottom:0;
                 background:linear-gradient(135deg,#2D1A12 0%,#1A0E08 100%);
                 display:flex;flex-direction:column;justify-content:center;align-items:center;
                 z-index:3000;padding:20px;overflow-y:auto}
.nn-login-container{background:linear-gradient(135deg,#2D1A12 0%,#1E0F08 100%);border-radius:20px;padding:35px;
                    min-width:340px;max-width:400px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,0.5),0 0 0 1px rgba(225,112,85,0.25);color:#f0e6dc;box-sizing:border-box}
.nn-login-subtitle{font-size:15px;text-align:center;margin-bottom:25px;color:rgba(240,230,220,0.7);font-weight:500}
.nn-login-tip{background:rgba(255,255,255,0.08);color:#fdcb6e;padding:12px;border-radius:8px;font-size:13px;margin-bottom:20px;line-height:1.5;border:1px solid rgba(255,255,255,0.06)}
.nn-login-tip strong{display:block;margin-bottom:5px;color:#e17055}
.btn-redirect-login{padding:14px;background:linear-gradient(135deg,#27ae60 0%,#2ecc71 100%);color:white;
                    border:none;border-radius:10px;font-size:15px;cursor:pointer;transition:all 0.2s ease;
                    text-decoration:none;display:block;text-align:center;min-height:44px;-webkit-appearance:none;appearance:none;box-shadow:0 4px 15px rgba(39,174,96,0.4)}
.btn-redirect-login:active{transform:scale(0.98)}
.btn-guest{padding:12px;background:transparent;color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.2);border-radius:10px;
            font-size:14px;cursor:pointer;transition:all 0.2s ease;margin-top:10px;min-height:44px;width:100%;-webkit-appearance:none;appearance:none}
.btn-guest:hover{background:rgba(255,255,255,0.08);color:#fff}

/* 欢迎用户信息 */
.welcome-user{display:flex;flex-direction:column;align-items:center;gap:10px;margin:0 0 14px 0}
.welcome-avatar-wrap{position:relative;width:84px;height:84px}
.welcome-avatar{width:84px;height:84px;border-radius:50%;border:3px solid #d63031;object-fit:cover;background:#2D1A12}
.welcome-avatar-placeholder{width:84px;height:84px;border-radius:50%;border:3px solid #d63031;background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;font-size:36px}
.welcome-name{font-size:20px;font-weight:700;color:#fff}
.welcome-score{font-size:14px;color:#bbb;margin-bottom:4px}
.welcome-score strong{color:#f1c40f;font-size:18px;font-weight:700}
.welcome-buff-info{font-size:12px;color:#aaa;margin-bottom:16px;text-align:center;min-height:18px}
.welcome-start-btn{display:block;width:100%;margin:16px 0 12px;font-size:18px;padding:14px 40px;box-sizing:border-box;
                   background:linear-gradient(135deg,#e74c3c,#c0392b);border-radius:10px;border:none;color:#fff;font-weight:600;cursor:pointer;transition:all 0.2s;-webkit-appearance:none;appearance:none;box-shadow:0 4px 15px rgba(231,76,60,0.4)}
.welcome-start-btn:hover{opacity:0.92;transform:translateY(-1px)}
.welcome-start-btn:active{transform:scale(0.97)}
.welcome-actions{display:flex;gap:10px;margin-top:4px}
.welcome-action-btn{flex:1;min-width:0;padding:10px 6px;font-size:13px;font-weight:600;border-radius:10px;
                    border:none;cursor:pointer;transition:all 0.2s ease;text-align:center;white-space:nowrap;-webkit-appearance:none;color:#fff}
.welcome-action-btn:hover{opacity:0.92;transform:translateY(-1px)}
.welcome-action-btn:active{transform:scale(0.97)}

/* 公告与最近更新 */
.welcome-modules{margin-top:20px;display:flex;flex-direction:column;gap:12px;text-align:left}
.welcome-notice,.welcome-updates{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px 16px}
.welcome-notice .module-title,.welcome-updates .module-title{font-size:13px;font-weight:600;color:#fdcb6e;margin-bottom:10px}
.welcome-notice .module-body{font-size:13px;color:rgba(255,255,255,0.75);line-height:1.7;white-space:pre-wrap}
.welcome-updates .module-body{font-size:12px;color:rgba(255,255,255,0.65);line-height:1.8;max-height:calc(1.8em * 5);overflow-y:auto;padding-right:4px;scrollbar-width:thin}
.welcome-updates .module-body::-webkit-scrollbar{width:4px}
.welcome-updates .module-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:2px}
.welcome-updates .update-item{padding:3px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
.welcome-updates .update-item:last-child{border-bottom:none}
.loading{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20px}
.loading-spinner{width:40px;height:40px;border:4px solid rgba(255,255,255,0.1);border-top-color:#e17055;border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.loading-text{margin-top:15px;color:rgba(255,255,255,0.6);font-size:14px}
.hidden{display:none!important}

/* ====== 游戏容器 ====== */
.nn-container{width:100vw;height:calc(100vh - 60px);margin-top:60px;position:relative;
              overflow:hidden;display:flex;flex-direction:column;align-items:center}

/* ====== 欢迎界面（游戏内，用于开始游戏前） ====== */
.nn-welcome{position:absolute;top:0;left:0;right:0;bottom:0;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:20px;z-index:100;background:linear-gradient(180deg,#2D1A12,#1E0F08)}
.nn-welcome p{color:rgba(255,255,255,0.6);margin-bottom:24px;font-size:14px;text-align:center;line-height:1.6}
.nn-btn-start{display:block;width:100%;max-width:300px;padding:16px;border:none;border-radius:12px;
              background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;font-size:18px;
              font-weight:600;cursor:pointer;transition:all 0.2s;text-align:center;
              -webkit-appearance:none;margin-bottom:12px}
.nn-btn-start:active{transform:scale(0.97)}
.nn-btn-secondary{display:block;width:100%;max-width:300px;padding:12px;border:1px solid rgba(255,255,255,0.2);
                  border-radius:10px;background:transparent;color:rgba(255,255,255,0.7);
                  font-size:14px;cursor:pointer;text-align:center;text-decoration:none;margin-bottom:8px;
                  -webkit-appearance:none}
.nn-btn-secondary:active{background:rgba(255,255,255,0.1)}

/* ====== 游戏区 ====== */
.nn-game-area{display:none;width:100%;height:100%;position:relative;
              flex-direction:column;align-items:center;justify-content:center;padding:20px}

/* ====== 牌桌 ====== */
.nn-table{width:100%;max-width:800px;margin:0 auto;text-align:center;padding-top:6px}
.nn-ai-row{display:flex;flex-direction:column;gap:4px;margin-bottom:10px}

/* ====== 中心区域 ====== */
.nn-center{text-align:center;margin-bottom:20px}
.nn-vs-text{font-size:24px;color:#e17055;font-weight:bold;margin-bottom:8px}
.nn-result-text{font-size:18px;font-weight:bold;min-height:28px;margin-bottom:4px}
.nn-score-change{font-size:16px;min-height:22px;margin-bottom:8px}
.nn-btn-deal{display:inline-block;padding:12px 36px;border:none;border-radius:25px;
             background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;font-size:16px;
             font-weight:600;cursor:pointer;transition:all 0.2s;-webkit-appearance:none}
.nn-btn-hint{background:linear-gradient(135deg,#00b894,#55efc4);padding:12px 20px;font-size:14px}
/* ====== 押注按钮 ====== */
.nn-bet-phase{text-align:center;padding:10px}
.nn-bet-buttons{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.nn-bet-btn{width:90px;padding:12px 8px;border:2px solid rgba(255,255,255,0.2);border-radius:12px;
            background:rgba(255,255,255,0.08);color:#fff;font-size:16px;font-weight:600;
            cursor:pointer;transition:all .2s;text-align:center;-webkit-appearance:none}
.nn-bet-btn small{display:block;font-size:11px;font-weight:400;color:rgba(255,255,255,0.5);margin-top:4px}
.nn-bet-btn:hover{border-color:#fdcb6e;background:rgba(253,203,110,0.15)}
.nn-bet-btn-active{border-color:#fdcb6e!important;background:rgba(253,203,110,0.2)!important;box-shadow:0 0 15px rgba(253,203,110,0.3)}
.nn-bet-btn-disabled{opacity:.3;cursor:not-allowed;pointer-events:none}

/* ====== 结算详情 ====== */
.nn-detail-item{padding:4px 10px;border-radius:6px;background:rgba(255,255,255,0.06);margin-bottom:4px;font-size:13px}
.hidden{display:none!important}

/* ====== 我的手牌 ====== */
.nn-my-area{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);
            text-align:center;width:100%;max-width:500px;z-index:1000;
            pointer-events:none;padding:10px 0 0}
.nn-my-area::before{content:'';position:absolute;bottom:0;left:0;right:0;height:calc(100% + 20px);
                    background:linear-gradient(180deg,transparent 0%,rgba(20,10,6,0.7) 30%,rgba(20,10,6,0.95) 100%);
                    pointer-events:none;z-index:-1}
.nn-my-area > *{pointer-events:auto;position:relative;z-index:2}
.nn-my-label{display:inline-block;font-size:13px;color:rgba(255,255,255,0.7);margin-bottom:10px;text-shadow:0 1px 4px rgba(0,0,0,0.8);
             background:rgba(0,0,0,0.25);padding:3px 12px;border-radius:12px;z-index:2}
.nn-my-cards{display:flex;justify-content:center;gap:6px;padding:14px 10px 12px;
             flex-wrap:nowrap;position:relative;z-index:10}
.nn-card{width:52px;height:72px;border-radius:6px;overflow:hidden;position:relative;
         background:#fff;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.3);
         transition:transform .2s,box-shadow .2s;z-index:300}
.nn-card-selectable{cursor:pointer}
.nn-card-selectable:hover{transform:translateY(-4px) scale(1.03);box-shadow:0 6px 20px rgba(253,203,110,0.4);z-index:400}
.nn-card-selected{transform:translateY(-10px) scale(1.05)!important;box-shadow:0 8px 25px rgba(253,203,110,0.6)!important;border:2px solid #fdcb6e;border-radius:8px;z-index:500}
.nn-card-locked{opacity:0.9;pointer-events:none}
.nn-card-img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;pointer-events:none}

/* ====== AI区域分两行 ====== */
.nn-ai-row-inner{display:flex;justify-content:center;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.nn-ai-player{flex:0 0 auto;width:145px;min-width:0}
.nn-ai-player-inner{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:10px 8px 8px;text-align:center;transition:all 0.2s}
.nn-ai-player-inner:hover{background:rgba(255,255,255,0.1);border-color:rgba(255,255,255,0.15)}
.nn-ai-header{display:flex;flex-direction:row;align-items:center;justify-content:center;gap:6px;margin-bottom:8px}
.nn-ai-avatar{width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid rgba(225,112,85,0.6);flex-shrink:0;background:#2D1A12}
.nn-ai-avatar-placeholder{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,0.08);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.nn-ai-name{font-size:12px;color:#fdcb6e;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nn-ai-cards{display:flex;justify-content:center;margin-bottom:3px}
.nn-ai-card-back{width:30px;height:43px;background:linear-gradient(135deg,#e17055,#d63031);border-radius:3px;box-shadow:0 1px 2px rgba(0,0,0,0.3);margin-right:-6px}
.nn-ai-card-back:last-child{margin-right:0}
.nn-ai-card{width:30px!important;height:43px!important;border-radius:3px;overflow:hidden;position:relative;background:#fff;flex-shrink:0;box-shadow:0 1px 3px rgba(0,0,0,0.3);margin-right:-6px}
.nn-ai-card:last-child{margin-right:0}
.nn-ai-card .nn-card-img{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;pointer-events:none}
.nn-ai-result{font-size:10px;font-weight:600;min-height:13px;color:#ffd700;margin-top:2px}
.nn-ai-change{font-size:11px;font-weight:bold;min-height:15px;margin-top:2px}
.nn-ai-win{color:#2ecc71}
.nn-ai-lose{color:#e74c3c}
.nn-ai-quote{font-size:10px;color:rgba(255,255,255,0.4);min-height:13px;margin-top:2px;line-height:1.3}

/* ====== 弹窗 ====== */
.nn-modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);
          display:flex;justify-content:center;align-items:center;z-index:4000}
.nn-modal-content{background:linear-gradient(135deg,#2D1A12,#1E0F08);border-radius:20px;
                  padding:25px;text-align:center;color:#fff;border:2px solid #ffd700;
                  width:90vw;max-width:500px;max-height:80vh;overflow-y:auto;box-sizing:border-box}
.nn-modal-title{font-size:22px;font-weight:bold;text-align:center;margin-bottom:15px;color:#ffd700}
.nn-modal-close{display:inline-block;margin-top:16px;padding:10px 28px;border:none;border-radius:25px;
                background:#e17055;color:#fff;font-size:14px;cursor:pointer;-webkit-appearance:none}
.nn-modal-list{text-align:left;max-height:300px;overflow-y:auto;margin-bottom:10px}
.nn-modal-list-item{display:flex;justify-content:space-between;padding:8px 0;
                    border-bottom:1px solid rgba(255,255,255,0.08);font-size:13px}
/* 积分流水列表（统一三栏布局） */
.score-log-list{text-align:left;max-height:350px;overflow-y:auto;margin-bottom:10px;padding:5px 0}
.score-log-item{display:flex;align-items:center;padding:8px 12px;border-bottom:1px solid rgba(255,255,255,0.08);gap:8px;font-size:13px}
.score-log-item:last-child{border-bottom:none}
.log-reason{flex:1;color:#ccc;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.log-time{color:#666;font-size:11px;white-space:nowrap;min-width:90px;text-align:center}
.log-change{min-width:50px;text-align:right;font-family:'Courier New',monospace}

/* ====== 倍率表 ====== */
.mult-table{width:100%;border-collapse:collapse;margin:0 auto}
.mult-table th,.mult-table td{padding:8px 12px;text-align:center;border:1px solid rgba(255,255,255,0.1)}
.mult-table th{background:rgba(225,112,85,0.3);color:#ffd700;font-weight:600;font-size:14px}
.mult-table td{font-size:13px;color:rgba(255,255,255,0.85)}
.mult-table tr:nth-child(even){background:rgba(255,255,255,0.04)}
.mult-table .mult-val{color:#55efc4;font-weight:bold;font-size:14px}
.mult-table .mult-icon{font-size:18px}

/* ====== 响应式 ====== */
@media(max-width:768px){
    .nn-nav{height:50px}.nn-nav-inner{padding:0 8px}.nn-nav-icon{font-size:20px}.nn-nav-left{gap:6px}.nn-nav-right{gap:4px}
    .nn-nav-title{display:none !important}
    .nn-nav-right a{font-size:12px;padding:4px 8px}
    .nav-user-info{padding:0 6px;max-width:42px}.nav-avatar{width:22px;height:22px}.nav-user-name{display:none}
    .nav-score{padding:0 6px}.nav-score-label{display:none}
    .nav-btn{padding:0 8px}.nav-btn .nav-btn-text{display:none}
    .nav-home-btn:not(.nav-btn){padding:0 8px;font-size:0}.nav-home-btn:not(.nav-btn)::before{content:"🏠";font-size:14px}
    .nn-login-screen{top:50px}.nn-login-container{min-width:auto;padding:25px 20px}
    .nn-container{height:calc(100vh - 50px);margin-top:50px}
    .nn-ai-row-inner{gap:8px;margin-bottom:10px;padding:0 8px;justify-content:space-between}
    .nn-ai-player{width:30%;max-width:150px;min-width:100px;flex:0 0 auto;margin-bottom:6px}
    .nn-ai-player-inner{padding:8px 6px 6px}
    .nn-ai-card-back{width:24px;height:35px;margin-right:-5px}
    .nn-ai-card{width:24px!important;height:35px!important;margin-right:-5px!important;border-radius:3px}
    .nn-ai-avatar{width:22px;height:22px}
    .nn-ai-avatar-placeholder{width:22px;height:22px;font-size:12px}
    .nn-ai-name{font-size:12px}
    .nn-ai-result{font-size:10px}
    .nn-ai-change{font-size:12px}
    .nn-ai-quote{font-size:9px}
    .nn-card{width:44px;height:62px}
    .nn-bet-btn{width:70px;padding:8px 4px;font-size:14px}
    .nn-my-area{bottom:10px}
    .nn-game-area{padding:10px}
    .nn-center{margin-bottom:10px}
    .nn-vs-text{font-size:18px}
    .nn-result-text{font-size:14px}
    #finalResult{font-size:20px}
    #finalScore{font-size:16px}
    #finalDetailList{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;max-height:140px;padding:6px}
    .nn-detail-item{margin-bottom:0;padding:3px 5px;font-size:10px;line-height:1.4;overflow-wrap:break-word}
    #phaseResult .nn-btn-deal{display:block;width:100%;max-width:240px;margin:8px auto;padding:10px 24px;font-size:14px}
}
@media(max-width:400px){
    .nn-card{width:38px;height:54px}
    .nn-ai-player{width:30%;max-width:120px;min-width:92px}
    .nn-ai-card-back{width:22px!important;height:32px!important;margin-right:-5px!important}
    .nn-ai-card{width:22px!important;height:32px!important;margin-right:-5px!important}
    .nn-ai-name{font-size:11px}
    .nn-ai-result{font-size:9px}
    .nn-ai-change{font-size:11px}
    .nn-bet-btn{width:60px;padding:6px 2px;font-size:12px}
    .nn-vs-text{font-size:16px}
    #finalResult{font-size:18px}
    #finalScore{font-size:15px}
    #finalDetailList{grid-template-columns:repeat(3,1fr);max-height:120px}
    .nn-detail-item{font-size:9px}
    .mult-table td,.mult-table th{padding:5px 8px;font-size:11px}
    .mult-table .mult-val{font-size:12px}
    .mult-table .mult-icon{font-size:15px}
}
/* ====== 横屏大屏：AI 区域等比放大 ====== */
@media(min-width:900px) and (min-aspect-ratio:3/2){
    .nn-ai-row-inner{gap:16px;margin-bottom:20px;padding:0 20px}
    .nn-ai-player{width:210px}
    .nn-ai-player-inner{padding:14px 10px 10px}
    .nn-ai-header{gap:8px;margin-bottom:10px}
    .nn-ai-avatar{width:36px;height:36px}
    .nn-ai-avatar-placeholder{width:36px;height:36px;font-size:18px}
    .nn-ai-name{font-size:16px}
    .nn-ai-cards{margin-bottom:4px}
    .nn-ai-card-back{width:42px;height:60px;margin-right:-8px}
    .nn-ai-card{width:42px!important;height:60px!important;margin-right:-8px!important}
    .nn-ai-result{font-size:13px;min-height:17px;margin-top:4px}
    .nn-ai-change{font-size:14px;min-height:18px;margin-top:4px}
    .nn-ai-quote{font-size:11px;min-height:14px;margin-top:3px}
}
</style>
</head>
<body>

<!-- ====== 导航栏（DDZ 统一风格） ====== -->
<nav class="nn-nav" id="nnNav">
    <div class="nn-nav-inner">
        <div class="nn-nav-left">
            <span class="nn-nav-icon">🐂</span>
            <h1 class="nn-nav-title"><?= htmlspecialchars($config['title'] ?? 'H5斗牛') ?></h1>
        </div>
        <div class="nn-nav-right" id="navRight">
            <?php if ($current_user): ?>
            <div class="nav-user-info" id="navUserInfo">
                <?php $avatar = $current_user['avatar'] ?? ''; ?>
                <?php if ($avatar): ?>
                    <img class="nav-avatar" id="userAvatar" src="<?= $avatar ?>" alt="">
                <?php else: ?>
                    <span style="font-size:20px;line-height:1">👤</span>
                <?php endif; ?>
                <span class="nav-user-name" id="userName"><?= htmlspecialchars($current_user['nickname']) ?></span>
            </div>
            <div class="nav-score" id="navScoreBox" title="点击查看积分流水" onclick="showLogs()">
                <span class="nav-score-label">积分:</span>
                <span class="nav-score-value" id="navScoreVal"><?= $user_score_data ? $user_score_data['score'] : 0 ?></span>
            </div>
            <?php else: ?>
            <a href="<?= $login_url ?>" class="nav-btn nav-home-btn">登 录</a>
            <?php endif; ?>
            <button class="nav-btn" id="navBtnRanking" onclick="showRanking()">🏆 <span class="nav-btn-text">排行</span></button>
            <button class="nav-btn" id="navBtnMult" onclick="showMultiplierChart()">🔢 <span class="nav-btn-text">倍率</span></button>
            <a href="?plugin=wx_games" class="nav-home-btn">返回大厅</a>
        </div>
    </div>
</nav>

<!-- ====== 登录/欢迎界面（深色卡片，同DDZ截图） ====== -->
<div class="nn-login-screen" id="loginScreen">
    <div class="nn-login-container">
        <div class="nn-login-subtitle"><?php if ($current_user): ?>👋 欢迎回来，<?= htmlspecialchars($current_user['nickname']) ?><?php else: ?>🐂 欢迎来到斗牛<?php endif; ?></div>

        <div id="loggedInPanel" class="<?= $current_user ? '' : 'hidden' ?>">
            <div class="welcome-user" id="welcomeUserInfo">
                <?php $welcomeAvatar = $current_user['avatar'] ?? ''; ?>
                <?php if ($welcomeAvatar): ?>
                <div class="welcome-avatar-wrap">
                    <img class="welcome-avatar" id="welcomeAvatar" src="<?= $welcomeAvatar ?>" alt="" onerror="this.style.display='none'">
                </div>
                <?php else: ?>
                <div class="welcome-avatar-wrap">
                    <div class="welcome-avatar-placeholder">🐂</div>
                </div>
                <?php endif; ?>
                <span class="welcome-name" id="welcomeName"><?= $current_user ? htmlspecialchars($current_user['nickname']) : '游客' ?></span>
                <span class="welcome-score">积分: <strong id="welcomeScore"><?= $user_score_data ? $user_score_data['score'] : 0 ?></strong></span>
                <div class="welcome-buff-info" id="welcomeBuffInfo">🃏 目前没有应用积分卡，可在商城购买</div>
            </div>

            <button class="welcome-start-btn" id="btnStartGame" onclick="startGame()">🎮 开始游戏</button>

            <div class="welcome-actions" id="welcomeActions">
                <button class="welcome-action-btn" onclick="showShop()" style="background:linear-gradient(135deg,#f39c12,#e67e22);color:white;">🛒 商城</button>
                <button class="welcome-action-btn" onclick="showInventory()" style="background:linear-gradient(135deg,#e17055,#d63031);color:white;">🎒 背包</button>
                <button class="welcome-action-btn" onclick="showRecharge()" style="background:linear-gradient(135deg,#e74c3c,#c0392b);color:white;">💎 充值</button>
            </div>

            <!-- 公告与最近更新 -->
            <div class="welcome-modules">
                <div class="welcome-notice" id="welcomeNotice">
                    <div class="module-title">📢 公告</div>
                    <div class="module-body" id="noticeBody"><?= nl2br(htmlspecialchars($config['notice'] ?? '')) ?></div>
                </div>
                <div class="welcome-updates" id="welcomeUpdates" style="<?= empty($config['recent_updates']) ? 'display:none' : '' ?>">
                    <div class="module-title">🔄 最近更新</div>
                    <div class="module-body" id="updatesBody">
                        <?php if (!empty($config['recent_updates'])): ?>
                        <?php foreach (explode("\n", str_replace("\r\n", "\n", $config['recent_updates'])) as $line): $line = trim($line); if (empty($line)) continue; ?>
                        <div class="update-item"><?= htmlspecialchars($line) ?></div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="guestPanel" class="<?= $current_user ? 'hidden' : '' ?>">
            <div class="nn-login-tip">
                <strong>💡 登录说明</strong>
                登录后可保存积分到排行榜。
            </div>
            <a href="<?= $login_url ?>" class="btn-redirect-login">🔑 前往登录</a>
        </div>
    </div>
</div>

<!-- ====== 游戏容器 ====== -->
<div class="nn-container" id="gameScreen" style="display:none">
    <!-- 欢迎界面（游戏内，开始前） -->
    <div class="nn-welcome" id="welcomeScreen">
        <button class="nn-btn-start" id="btnStartGame2" onclick="startGame()">🎮 开始游戏</button>
        <button class="nn-btn-secondary" onclick="showRanking()">🏆 排行榜</button>
        <button class="nn-btn-secondary" onclick="showLogs()">📊 积分流水</button>
        <button class="nn-btn-secondary" onclick="showShop()">🛒 商城</button>
        <a class="nn-btn-secondary" href="?plugin=wx_games">← 返回大厅</a>
    </div>

    <!-- 游戏区 -->
    <div class="nn-game-area" id="gameArea">
        <div class="nn-table">
            <!-- AI区域（2行×3个） -->
            <div class="nn-ai-row" id="aiRow"></div>

            <!-- 中心 -->
            <div class="nn-center">
                <!-- 发牌前下注阶段 -->
                <div id="phasePreBet" style="display:none">
                    <div class="nn-vs-text">💰 请下注后再发牌</div>
                    <div id="preBetTip" style="font-size:13px;margin:6px 0;color:rgba(255,255,255,0.5)">底注 <?= intval($config['base_bet'] ?? 100) ?> 分 · 选择倍数后确认</div>
                    <div class="nn-bet-buttons" style="margin:12px 0">
                        <button class="nn-bet-btn nn-bet-btn-active" id="preBetBtn1" onclick="selectPreBet(1)" title="下注 <?= intval($config['base_bet'] ?? 100) ?> 分">1倍<br><small><?= intval($config['base_bet'] ?? 100) ?>分</small></button>
                        <button class="nn-bet-btn" id="preBetBtn2" onclick="selectPreBet(2)" title="下注 <?= intval($config['base_bet'] ?? 100) * 2 ?> 分">2倍<br><small><?= intval($config['base_bet'] ?? 100) * 2 ?>分</small></button>
                        <button class="nn-bet-btn" id="preBetBtn3" onclick="selectPreBet(3)" title="下注 <?= intval($config['base_bet'] ?? 100) * 3 ?> 分">3倍<br><small><?= intval($config['base_bet'] ?? 100) * 3 ?>分</small></button>
                        <button class="nn-bet-btn" id="preBetBtn5" onclick="selectPreBet(5)" title="下注 <?= intval($config['base_bet'] ?? 100) * 5 ?> 分">🔥 5倍<br><small><?= intval($config['base_bet'] ?? 100) * 5 ?>分</small></button>
                        <button class="nn-bet-btn" id="preBetBtn10" onclick="selectPreBet(10)" title="下注 <?= intval($config['base_bet'] ?? 100) * 10 ?> 分">🔥 10倍<br><small><?= intval($config['base_bet'] ?? 100) * 10 ?>分</small></button>
                    </div>
                    <button class="nn-btn-deal" id="btnConfirmBet" onclick="confirmBet()">确认下注 🎯</button>
                </div>

                <!-- 选牌+出牌阶段 -->
                <div id="phaseBet" style="display:none">
                    <div class="nn-vs-text" id="dealVs">👆 点击3张牌组成10的倍数</div>
                    <div id="niuTip" style="font-size:13px;margin:6px 0;color:rgba(255,255,255,0.5)">请选择3张牌组成10的倍数，或点「无牛」系统自动算</div>
                    <div style="display:flex;gap:8px;justify-content:center;margin-bottom:10px;flex-wrap:wrap">
                        <button class="nn-btn-deal nn-btn-hint" id="niuHintBtn" onclick="hintNiu()">💡 提示</button>
                        <button class="nn-btn-deal" id="niuPlayBtn" style="display:none" onclick="playNiu()">🃏 出牌</button>
                        <button class="nn-btn-deal" id="niuAutoBtn" style="background:linear-gradient(135deg,#95a5a6,#7f8c8d)" onclick="autoNiu()">❌ 无牛</button>
                    </div>
                </div>

                <!-- 亮牌阶段 -->
                <div id="phaseReveal" style="display:none">
                    <div class="nn-vs-text" id="revealStatus">✨ 亮牌中...</div>
                    <div class="nn-result-text" id="revealResult" style="font-size:15px;min-height:22px"></div>
                    <div style="font-size:14px;margin-top:6px" id="revealScore" style="min-height:20px"></div>
                    <div style="font-size:13px;margin-top:8px;color:rgba(255,255,255,0.7);max-width:400px;margin-left:auto;margin-right:auto;display:none" id="revealDetail"></div>
                </div>

                <!-- 最终结果 -->
                <div id="phaseResult" style="display:none">
                    <div class="nn-vs-text" id="finalResult"></div>
                    <div style="font-size:18px;margin:6px 0" id="finalScore"></div>
                    <div style="font-size:13px;color:rgba(255,255,255,0.6)" id="finalDetail"></div>
                    <div id="finalDetailList" style="margin-top:10px;max-height:180px;overflow-y:auto;padding:8px;background:rgba(0,0,0,0.2);border-radius:8px;border:1px solid rgba(255,255,255,0.06)"></div>
                    <button class="nn-btn-deal" style="margin-top:12px" onclick="backToWelcome()">返回大厅 🏠</button>
                    <button class="nn-btn-deal" style="margin-top:8px" onclick="startGame()">再来一局 🎯</button>
                </div>
            </div>
        </div>

        <!-- 我的手牌 -->
        <div class="nn-my-area">
            <div class="nn-my-label">我的手牌</div>
            <div class="nn-my-cards" id="myCards"></div>
        </div>
    </div>
</div>

<!-- 排行榜弹窗 -->
<div class="nn-modal" id="rankModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="nn-modal-content">
        <div class="nn-modal-title">🏆 排行榜</div>
        <div id="rankBody"><p style="color:#aaa">加载中...</p></div>
        <button class="nn-modal-close" onclick="document.getElementById('rankModal').style.display='none'">关闭</button>
    </div>
</div>

<!-- 流水弹窗 -->
<div class="nn-modal" id="logModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="nn-modal-content">
        <div class="nn-modal-title">📊 积分流水</div>
        <div class="score-log-list" id="scoreLogList">
            <div style="text-align:center;color:#aaa;padding:20px;">暂无记录</div>
        </div>
        <button class="nn-modal-close" onclick="document.getElementById('logModal').style.display='none'">关闭</button>
    </div>
</div>

<!-- 商城弹窗 -->
<div class="nn-modal" id="shopModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="nn-modal-content">
        <div class="nn-modal-title">🛒 道具商城</div>
        <div style="display:flex;gap:12px;justify-content:center;margin-bottom:15px;font-size:13px;color:#ccc;flex-wrap:wrap;">
            <span>斗牛积分: <strong id="shopNnScore" style="color:#ffd700">0</strong></span>
            <span>站点积分: <strong id="shopNnEmlog" style="color:#e17055">0</strong></span>
        </div>
        <div id="shopBody" style="max-height:350px;overflow-y:auto;"><p style="color:#aaa">加载中...</p></div>
        <button class="nn-modal-close" onclick="document.getElementById('shopModal').style.display='none'">关闭</button>
    </div>
</div>

<!-- 背包弹窗 -->
<div class="nn-modal" id="inventoryModal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="nn-modal-content">
        <div class="nn-modal-title">🎒 我的背包</div>
        <div id="inventoryBody" style="max-height:350px;overflow-y:auto;"><p style="color:#aaa">加载中...</p></div>
        <button class="nn-modal-close" onclick="document.getElementById('inventoryModal').style.display='none'">关闭</button>
    </div>
</div>

<script>
// ====== 全局配置 ======
const NN_API = '<?= $base_url ?>?plugin=wx_games&game=niuniu&niuniu_action=';
const CARD_URL = '<?= $plugin_url ?>../../games/ddz/assets/cards/';

console.log('[斗牛] 页面加载', {uid: <?= $current_user ? $current_user['uid'] : 'null' ?>, nickname: '<?= $current_user ? addslashes($current_user['nickname']) : '' ?>'});

// EMLOG配置 - 由PHP动态注入
window.EMLOG_CONFIG = {
    baseUrl: '<?php echo $base_url; ?>',
    baseBet: <?php echo intval($config['base_bet'] ?? 100); ?>,
    penaltyMultiplier: <?php echo isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0; ?>,
};
window.NN_USER = <?php echo $current_user ? json_encode($current_user) : 'null'; ?>;
window.NN_USER_SCORE = <?php echo $user_score_data ? json_encode($user_score_data) : 'null'; ?>;
window.WX_NN_EMLOG_CREDITS = <?php echo intval($emlog_credits); ?>;
let currentUser = window.NN_USER;
console.log('[斗牛] EMLOG_CONFIG:', window.EMLOG_CONFIG);

// 防逃跑提示
window.NN_PENALTY_MSG = <?php echo $penalty_message ? json_encode($penalty_message) : 'null'; ?>;
if (window.NN_PENALTY_MSG) {
    console.log('[斗牛] 检测到未完成惩罚:', window.NN_PENALTY_MSG);
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            alert(window.NN_PENALTY_MSG);
        }, 500);
    });
}

// ====== 道具效果加载 ======
window.NN_PLAYER_EFFECTS = {};
async function loadPlayerEffects() {
    if (!currentUser) return {};
    try {
        const res = await fetch(NN_API + 'get_active_effects', { credentials: 'include' });
        const data = await res.json();
        if (data.code !== 0 || !data.data) return {};
        const effects = {};
        data.data.forEach(item => {
            try {
                const effectData = JSON.parse(item.effect_data || '{}');
                if (item.item_type === 'title_colored' && effectData.color) {
                    effects.titleColor = effectData.color;
                }
                if (item.item_type === 'title_effect' && effectData.effect) {
                    effects.titleEffect = effectData.effect;
                    if (effectData.color) effects.titleEffectColor = effectData.color;
                }
                if (item.item_type === 'title_badge') {
                    effects.titleBadge = effectData.badge || item.name || '称号';
                }
            } catch(e) {}
        });
        window.NN_PLAYER_EFFECTS = effects;
        // 更新昵称显示
        const nameEls = ['welcomeName', 'userName'];
        nameEls.forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.innerHTML = renderPlayerName(el.textContent || el.innerText);
        });
        return effects;
    } catch(e) { return {}; }
}

function renderPlayerName(name) {
    const effects = window.NN_PLAYER_EFFECTS;
    let style = '';
    let suffix = '';
    if (effects.titleColor) {
        style += 'color:' + effects.titleColor + ';';
    }
    if (effects.titleEffect === 'glow') {
        const gc = effects.titleEffectColor || 'gold';
        style += 'text-shadow:0 0 10px ' + gc + ',0 0 20px ' + gc + ';';
    }
    if (effects.titleBadge) {
        suffix = ' <span style="font-size:10px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;padding:1px 6px;border-radius:8px;white-space:nowrap;">' + effects.titleBadge + '</span>';
    }
    if (style || suffix) {
        return '<span style="' + style + '">' + name + '</span>' + suffix;
    }
    return name;
}

// 页面加载完成后加载道具效果 + 积分加成卡信息
document.addEventListener('DOMContentLoaded', function() {
    if (currentUser) {
        loadPlayerEffects();
        loadBuffInfo();
    }
});

// 加载积分加成卡信息并更新欢迎页显示
async function loadBuffInfo() {
    try {
        const res = await fetch(NN_API + 'get_score_buff', {credentials:'include'});
        const data = await res.json();
        const el = document.getElementById('welcomeBuffInfo');
        if (data.code === 0 && data.buffs && data.buffs.length > 0) {
            const b = data.buffs[0];
            el.innerHTML = '⚡ 积分加成卡已激活：×' + b.multiplier + '（剩余 ' + b.remaining + ' 局）';
        } else {
            el.innerHTML = '🃏 目前没有应用积分卡，可在商城购买';
        }
    } catch(e) {}
}

function getCardUrl(card) {
    if (CARD_URL === '') return '';
    const suitMap = {'♠':'s','♥':'h','♣':'c','♦':'d'};
    return CARD_URL + 'card_' + (suitMap[card.suit] || 's') + '_' + card.value + '.png';
}

function renderCardImg(card, extraClass) {
    extraClass = extraClass || '';
    return '<div class="nn-card ' + extraClass + '"><img src="' + getCardUrl(card) + '" alt="" class="nn-card-img"></div>';
}

// ====== 游戏状态 ======
let gameData = null;
let currentBet = 1;
let selectedNiuCards = []; // 手动选中的3张牌索引
let playerNiuType = null;   // 手动配牛后的牌型
let gameInProgress = false; // 防逃跑：游戏进行中标记
let NN_ACTIVE_BUFF_MULT = 1; // 积分加成卡倍率（无加成=1）
const BASE_BET = <?= intval($config['base_bet'] ?? 100) ?>;
const AI_ASSETS_URL = '<?= WX_GAMES_URL ?>games/ddz/assets/';

// 算牛（前端）
function calcNiuFromCards(cards) {
    const pointMap = {'A':1,'J':10,'Q':10,'K':10};
    const values = cards.map(c => pointMap[c.value] || parseInt(c.value));
    const combos = [[0,1,2],[0,1,3],[0,1,4],[0,2,3],[0,2,4],[0,3,4],[1,2,3],[1,2,4],[1,3,4],[2,3,4]];
    for (const idx of combos) {
        if ((values[idx[0]] + values[idx[1]] + values[idx[2]]) % 10 === 0) {
            const remain = [0,1,2,3,4].filter(j => !idx.includes(j));
            const pt = (values[remain[0]] + values[remain[1]]) % 10;
            return pt === 0 ? 'niu_niu' : 'niu_' + pt;
        }
    }
    return 'no_niu';
}

function getNiuName(t) {
    const names = {
        'wu_xiao_niu':'五小牛','zha_dan':'炸弹','jin_niu':'金牛','yin_niu':'银牛',
        'niu_niu':'牛牛','niu_9':'牛9','niu_8':'牛8','niu_7':'牛7','niu_6':'牛6',
        'niu_5':'牛5','niu_4':'牛4','niu_3':'牛3','niu_2':'牛2','niu_1':'牛1','no_niu':'无牛'
    };
    return names[t] || t;
}

function getNiuMultiplier(t) {
    const m = {'wu_xiao_niu':20,'zha_dan':15,'jin_niu':10,'yin_niu':7,'niu_niu':5,'niu_9':4,'niu_8':3,'niu_7':2,'niu_6':1,'niu_5':1,'niu_4':1,'niu_3':1,'niu_2':1,'niu_1':1,'no_niu':1};
    return m[t] || 1;
}

function getNiuWeight(t) {
    const w = {'wu_xiao_niu':14,'zha_dan':13,'jin_niu':12,'yin_niu':11,'niu_niu':10,'niu_9':9,'niu_8':8,'niu_7':7,'niu_6':6,'niu_5':5,'niu_4':4,'niu_3':3,'niu_2':2,'niu_1':1,'no_niu':0};
    return w[t] || 0;
}

// ====== 进入游戏：先发牌前下注 ======
function startGame() {
    console.log('[斗牛] startGame() - 进入游戏, gameInProgress=' + gameInProgress);
    if (gameInProgress) {
        console.warn('[斗牛] startGame() 但 gameInProgress 仍为 true, 这不应发生');
    }
    document.getElementById('loginScreen').style.display = 'none';
    document.getElementById('gameScreen').style.display = 'flex';
    document.getElementById('welcomeScreen').style.display = 'none';
    document.getElementById('gameArea').style.display = 'flex';
    document.getElementById('phasePreBet').style.display = 'block';
    document.getElementById('phaseBet').style.display = 'none';
    document.getElementById('phaseReveal').style.display = 'none';
    document.getElementById('phaseResult').style.display = 'none';
    document.getElementById('aiRow').innerHTML = '';
    document.getElementById('myCards').innerHTML = '';
    document.querySelector('.nn-my-area').classList.remove('hidden');
    document.getElementById('finalDetailList').style.display = '';
    selectedNiuCards = [];
    playerNiuType = null;
    currentBet = 1;
    selectPreBet(1);
}

function selectPreBet(mult) {
    const score = gameData && gameData.current_score ? gameData.current_score : (<?= $user_score_data ? $user_score_data['score'] : 0 ?>);
    console.log('[斗牛] selectPreBet(' + mult + '), 当前积分=' + score + ', 底注=' + BASE_BET + ', 需扣=' + (BASE_BET * mult));
    currentBet = mult;
    [1, 2, 3, 5, 10].forEach(function(m) {
        const btn = document.getElementById('preBetBtn' + m);
        if (!btn) return;
        const cost = BASE_BET * m;
        const canBet = cost <= score;
        btn.className = 'nn-bet-btn' + (m === mult ? ' nn-bet-btn-active' : '') + (!canBet ? ' nn-bet-btn-disabled' : '');
        btn.disabled = !canBet;
    });
    const myCost = BASE_BET * mult;
    document.getElementById('preBetTip').textContent = '底注 ' + BASE_BET + ' 分 · 当前下注：×' + mult + '（共 ' + myCost + ' 分）' + (myCost > score ? ' · 积分不足，可能导致负分' : '');
}

function confirmBet() {
    console.log('[斗牛] confirmBet() - 下注倍率=' + currentBet + ', 底注=' + BASE_BET + ', 总押注=' + (BASE_BET * currentBet));
    // 加载并消耗积分加成卡
    NN_ACTIVE_BUFF_MULT = 1;
    try {
        fetch(NN_API + 'get_score_buff', {credentials:'include'})
            .then(r => r.json())
            .then(data => {
                if (data.code === 0 && data.buffs && data.buffs.length > 0) {
                    NN_ACTIVE_BUFF_MULT = data.buffs[0].multiplier || 1;
                    // 消耗1次
                    fetch(NN_API + 'consume_score_buff', {method:'POST', credentials:'include'})
                        .then(r => r.json())
                        .then(cd => {
                            if (cd.code === 0 && cd.multiplier > 1) {
                                NN_ACTIVE_BUFF_MULT = cd.multiplier;
                                console.log('[斗牛] 积分加成卡激活: ×' + cd.multiplier);
                            }
                        })
                        .catch(() => {});
                }
            })
            .catch(() => {});
    } catch(e) {}
    console.log('[斗牛] → 发送 start 信号, gameInProgress = true');
    document.getElementById('phasePreBet').style.display = 'none';
    document.getElementById('phaseBet').style.display = 'block';
    document.getElementById('dealVs').textContent = '🃏 发牌中...';

    new Image().src = '<?= $base_url ?>?plugin=wx_games&game=niuniu&wx_niuniu_signal=start&r=' + Math.random();
    gameInProgress = true;

    fetch(NN_API + 'deal', {method:'POST'})
        .then(r => r.json())
        .then(res => {
            if (res.code !== 0) { console.warn('[斗牛] deal 失败:', res.msg); alert(res.msg); backToWelcome(); return; }
            console.log('[斗牛] deal 成功, 当前积分=' + res.data.current_score);
            gameData = res.data;
            showDealResult(res.data);
        })
        .catch(() => { alert('发牌失败'); backToWelcome(); });
}

function showDealResult(d) {
    // AI分两行：每行3个
    const row = document.getElementById('aiRow');
    row.innerHTML = '<div class="nn-ai-row-inner" id="aiRow1"></div><div class="nn-ai-row-inner" id="aiRow2"></div>';
    for (let i = 0; i < 6; i++) {
        const ai = d.ai_players[i];
        const container = i < 3 ? 'aiRow1' : 'aiRow2';
        const avatarUrl = ai.avatar ? AI_ASSETS_URL + ai.avatar : '';
        document.getElementById(container).innerHTML +=
            '<div class="nn-ai-player" id="aiArea' + i + '">'
            + '<div class="nn-ai-player-inner">'
            + '<div class="nn-ai-header">'
            + (avatarUrl ? '<img class="nn-ai-avatar" src="' + avatarUrl + '" alt="" onerror="this.style.display=\'none\'">' : '<div class="nn-ai-avatar-placeholder">🤖</div>')
            + '<div class="nn-ai-name" id="aiName' + i + '">' + ai.name + '</div>'
            + '</div>'
            + '<div class="nn-ai-cards" id="aiCards' + i + '">'
            + '<div class="nn-ai-card-back"></div>'.repeat(5)
            + '</div>'
            + '<div class="nn-ai-result" id="aiResult' + i + '"></div>'
            + '<div class="nn-ai-change" id="aiChange' + i + '"></div>'
            + '<div class="nn-ai-quote" id="aiQuote' + i + '"></div>'
            + '</div>'
            + '</div>';
    }

    // 显示手牌（可选中的）
    renderSelectableCards(d.player_cards);
    document.getElementById('dealVs').textContent = '👆 点击3张牌组成10的倍数';
    // 显示选牌+押注区域（phaseBet 默认隐藏，发牌后才展示）
    document.getElementById('phaseBet').style.display = 'block';
    updateNiuActionBtns(); // 初始显示「无牛」按钮
}

function renderSelectableCards(cards) {
    const container = document.getElementById('myCards');
    container.innerHTML = '';
    cards.forEach((c, i) => {
        const div = document.createElement('div');
        div.className = 'nn-card nn-card-selectable';
        div.dataset.idx = i;
        div.innerHTML = '<img src="' + getCardUrl(c) + '" alt="" class="nn-card-img">';
        div.addEventListener('click', function() {
            toggleSelectCard(i);
        });
        container.appendChild(div);
    });
}

function toggleSelectCard(idx) {
    const cards = document.getElementById('myCards').children;
    const el = cards[idx];

    if (el.classList.contains('nn-card-selected')) {
        el.classList.remove('nn-card-selected');
        selectedNiuCards = selectedNiuCards.filter(i => i !== idx);
    } else {
        if (selectedNiuCards.length >= 3) {
            const firstIdx = selectedNiuCards.shift();
            cards[firstIdx].classList.remove('nn-card-selected');
        }
        selectedNiuCards.push(idx);
        el.classList.add('nn-card-selected');
    }
    updateNiuActionBtns();
}

function updateNiuActionBtns() {
    const btnPlay = document.getElementById('niuPlayBtn');
    const btnAuto = document.getElementById('niuAutoBtn');
    const tip = document.getElementById('niuTip');
    if (selectedNiuCards.length === 3) {
        btnPlay.style.display = 'inline-block';
        btnAuto.style.display = 'inline-block';
        // 预览所选3张组成的牌型
        const cards = gameData.player_cards;
        const selected = selectedNiuCards.map(i => cards[i]);
        const pointMap = {'A':1,'J':10,'Q':10,'K':10};
        const sum = selected.reduce((s, c) => s + (pointMap[c.value] || parseInt(c.value)), 0);
        if (sum % 10 === 0) {
            const remaining = [0,1,2,3,4].filter(i => !selectedNiuCards.includes(i)).map(i => cards[i]);
            const rSum = remaining.reduce((s, c) => s + (pointMap[c.value] || parseInt(c.value)), 0);
            const niuName = getNiuName(rSum % 10 === 0 ? 'niu_niu' : 'niu_' + (rSum % 10));
            tip.textContent = '✅ 所选3张和=' + sum + '（10的倍数），牌型：' + niuName + ' → 点击「出牌」';
            tip.style.color = '#2ecc71';
        } else {
            tip.textContent = '❌ 所选3张和=' + sum + '，不是10的倍数，请重选或点「无牛」自动算最优解';
            tip.style.color = '#e74c3c';
            btnPlay.style.display = 'none';
        }
    } else if (selectedNiuCards.length > 0) {
        btnPlay.style.display = 'none';
        btnAuto.style.display = 'inline-block';
        tip.textContent = '选 ' + selectedNiuCards.length + '/3 张，或直接点「无牛」系统自动算最优解';
        tip.style.color = 'rgba(255,255,255,0.5)';
    } else {
        btnPlay.style.display = 'none';
        btnAuto.style.display = 'inline-block';
        tip.textContent = '点击3张牌组成10的倍数，或点「无牛」自动算最优解';
        tip.style.color = 'rgba(255,255,255,0.5)';
    }
}

// 手动出牌：用选中的3张作为牛组
function playNiu() {
    console.log('[斗牛] playNiu() 选中牌索引:', selectedNiuCards);
    if (selectedNiuCards.length !== 3) { alert('请选择3张牌'); return; }
    const cards = gameData.player_cards;
    const selected = selectedNiuCards.map(i => cards[i]);
    const remaining = [0,1,2,3,4].filter(i => !selectedNiuCards.includes(i)).map(i => cards[i]);

    const pointMap = {'A':1,'J':10,'Q':10,'K':10};
    const sum = selected.reduce((s, c) => s + (pointMap[c.value] || parseInt(c.value)), 0);

    if (sum % 10 === 0) {
        // 有牛
        const rSum = remaining.reduce((s, c) => s + (pointMap[c.value] || parseInt(c.value)), 0);
        const niuPoint = rSum % 10;
        const typeKey = niuPoint === 0 ? 'niu_niu' : 'niu_' + niuPoint;
        console.log('[斗牛] playNiu 手动配牌成功: 3张和=' + sum + ', 剩余和=' + rSum + ', 牛点=' + niuPoint + ' -> ' + typeKey);
        processNiuResult(typeKey);
    } else {
        // 选的3张不能组成10点→无牛
        console.log('[斗牛] playNiu 手动配牌失败: 3张和=' + sum + ', 不能组成10点');
        document.getElementById('niuTip').textContent = '❌ 这3张和=' + sum + '，不能组成10点，请重选或点「无牛」自动算';
        document.getElementById('niuTip').style.color = '#e74c3c';
    }
}

// 无牛：系统自动找出最优解
function autoNiu() {
    console.log('[斗牛] autoNiu() - 系统自动配牌, 手牌:', gameData.player_cards.map(c => c.suit + c.value).join(' '));
    const cards = gameData.player_cards;
    const pointMap = {'A':1,'J':10,'Q':10,'K':10};
    const values = cards.map(c => pointMap[c.value] || parseInt(c.value));
    const ranks = cards.map(c => c.value);

    // 先检查特殊牌型
    const total = values.reduce((a, b) => a + b, 0);
    if (total <= 10) { processNiuResult('wu_xiao_niu'); return; }

    const counts = {};
    ranks.forEach(v => { counts[v] = (counts[v] || 0) + 1; });
    for (const v in counts) { if (counts[v] >= 4) { processNiuResult('zha_dan'); return; } }

    if (ranks.every(v => ['J','Q','K'].includes(v))) { processNiuResult('jin_niu'); return; }
    if (ranks.every(v => ['10','J','Q','K'].includes(v))) { processNiuResult('yin_niu'); return; }

    // 遍历10种组合找最优牛
    const combos = [[0,1,2],[0,1,3],[0,1,4],[0,2,3],[0,2,4],[0,3,4],[1,2,3],[1,2,4],[1,3,4],[2,3,4]];
    let bestWeight = -1;
    let bestType = 'no_niu';

    for (const idx of combos) {
        if ((values[idx[0]] + values[idx[1]] + values[idx[2]]) % 10 === 0) {
            const remain = [0,1,2,3,4].filter(j => !idx.includes(j));
            const pt = (values[remain[0]] + values[remain[1]]) % 10;
            const typeKey = pt === 0 ? 'niu_niu' : 'niu_' + pt;
            const w = getNiuWeight(typeKey);
            if (w > bestWeight) { bestWeight = w; bestType = typeKey; }
        }
    }

    // 自动选中最优组合的3张牌高亮
    if (bestType !== 'no_niu') {
        console.log('[斗牛] autoNiu 找到最优牌型=' + bestType + ' (weight=' + bestWeight + ')');
        // 找到对应的组合
        for (const idx of combos) {
            if ((values[idx[0]] + values[idx[1]] + values[idx[2]]) % 10 === 0) {
                const remain = [0,1,2,3,4].filter(j => !idx.includes(j));
                const pt = (values[remain[0]] + values[remain[1]]) % 10;
                const typeKey = pt === 0 ? 'niu_niu' : 'niu_' + pt;
                if (typeKey === bestType) {
                    // 高亮这3张
                    const myCards = document.getElementById('myCards').children;
                    for (let j = 0; j < 5; j++) myCards[j].classList.remove('nn-card-selected');
                    idx.forEach(j => myCards[j].classList.add('nn-card-selected'));
                    selectedNiuCards = idx;
                    break;
                }
            }
        }
    }

    processNiuResult(bestType);
}

// ====== 提示：高亮最佳组合但不操作 ======
function hintNiu() {
    const cards = gameData.player_cards;
    console.log('[斗牛] hintNiu() 手牌:', cards.map(c => c.suit + c.value).join(' '));
    const pointMap = {'A':1,'J':10,'Q':10,'K':10};
    const values = cards.map(c => pointMap[c.value] || parseInt(c.value));
    const ranks = cards.map(c => c.value);
    let hintType = 'no_niu';
    let hintCombo = [];

    // 特殊牌型
    const total = values.reduce((a, b) => a + b, 0);
    if (total <= 10) { hintType = 'wu_xiao_niu'; }
    const counts = {};
    ranks.forEach(v => { counts[v] = (counts[v] || 0) + 1; });
    for (const v in counts) { if (counts[v] >= 4) { hintType = 'zha_dan'; break; } }
    if (hintType === 'no_niu' && ranks.every(v => ['J','Q','K'].includes(v))) { hintType = 'jin_niu'; }
    if (hintType === 'no_niu' && ranks.every(v => ['10','J','Q','K'].includes(v))) { hintType = 'yin_niu'; }

    // 普通牛型：找最佳组合
    if (hintType === 'no_niu') {
        const combos = [[0,1,2],[0,1,3],[0,1,4],[0,2,3],[0,2,4],[0,3,4],[1,2,3],[1,2,4],[1,3,4],[2,3,4]];
        let bestWeight = -1;
        for (const idx of combos) {
            if ((values[idx[0]] + values[idx[1]] + values[idx[2]]) % 10 === 0) {
                const remain = [0,1,2,3,4].filter(j => !idx.includes(j));
                const pt = (values[remain[0]] + values[remain[1]]) % 10;
                const typeKey = pt === 0 ? 'niu_niu' : 'niu_' + pt;
                const w = getNiuWeight(typeKey);
                if (w > bestWeight) { bestWeight = w; hintType = typeKey; hintCombo = idx; }
            }
        }
    }

    // 高亮推荐组合
    const myCards = document.getElementById('myCards').children;
    for (let j = 0; j < 5; j++) myCards[j].classList.remove('nn-card-selected');
    if (hintCombo.length === 3) {
        hintCombo.forEach(j => myCards[j].classList.add('nn-card-selected'));
        selectedNiuCards = hintCombo;
        console.log('[斗牛] hintNiu 找到最佳组合: 牌型=' + hintType + ', 选中索引=', hintCombo);
    }

    const name = getNiuName(hintType);
    const mult = getNiuMultiplier(hintType);
    document.getElementById('niuTip').textContent = '💡 系统推荐：' + name + ' (×' + mult + ')，点击「出牌」确认';
    document.getElementById('niuTip').style.color = '#55efc4';

    updateNiuActionBtns();
}

function processNiuResult(typeKey) {
    console.log('[斗牛] processNiuResult(' + typeKey + ') -> name=' + getNiuName(typeKey) + ', weight=' + getNiuWeight(typeKey) + ', mult=' + getNiuMultiplier(typeKey));
    playerNiuType = {
        type: typeKey,
        name: getNiuName(typeKey),
        weight: getNiuWeight(typeKey),
        multiplier: getNiuMultiplier(typeKey)
    };

    document.getElementById('niuTip').textContent = '✅ 牌型：' + playerNiuType.name + ' (×' + playerNiuType.multiplier + ')';
    document.getElementById('niuTip').style.color = '#2ecc71';
    document.getElementById('niuPlayBtn').style.display = 'none';
    document.getElementById('niuAutoBtn').style.display = 'none';

    // 锁定卡牌
    document.querySelectorAll('.nn-card-selectable').forEach(el => el.classList.add('nn-card-locked'));

    // 下注已在发牌前完成，直接进入亮牌
    setTimeout(startReveal, 500);
}

function startReveal() {
    console.log('[斗牛] startReveal() - 开始亮牌, currentBet=' + currentBet + ', BASE_BET=' + BASE_BET + ', stake=' + (BASE_BET * currentBet));
    console.log('[斗牛] 玩家牌型:', playerNiuType);
    document.getElementById('phaseBet').style.display = 'none';
    document.getElementById('phaseReveal').style.display = 'block';
    document.getElementById('revealStatus').textContent = '✨ 亮牌开始！';
    document.getElementById('revealResult').textContent = '';
    document.getElementById('revealScore').textContent = '';
    document.getElementById('revealDetail').innerHTML = '';

    const d = gameData;
    const stake = BASE_BET * currentBet;
    let totalChange = 0;
    var aiChanges = []; // 记录每个AI的积分变化
    let aiIndex = 0;
    let detailHtml = '';

    async function revealNext() {
        if (aiIndex >= 6) {
            console.log('[斗牛] 全部AI亮牌完成, totalChange=' + totalChange + ', 调用 finishGame()');
            document.getElementById('revealStatus').textContent = '📊 结算完成！';
            await finishGame(totalChange, detailHtml, aiChanges);
            return;
        }

        const ai = d.ai_players[aiIndex];
        console.log('[斗牛] revealNext[' + aiIndex + '] name=' + ai.name + ', 牌型=' + ai.type.name + '(weight=' + ai.type.weight + ', mult=' + ai.type.multiplier + '), 玩家牌型=' + playerNiuType.name + '(weight=' + playerNiuType.weight + ', mult=' + playerNiuType.multiplier + ')');
        document.getElementById('aiCards' + aiIndex).innerHTML = ai.cards.map(c => renderCardImg(c, 'nn-ai-card')).join('');
        document.getElementById('aiQuote' + aiIndex).textContent = ai.quote && ai.quote !== '看看谁大' ? ai.quote : '';

        const result = playerNiuType.weight > ai.type.weight ? 1 : (playerNiuType.weight < ai.type.weight ? -1 : 0);
        const change = result === 1 ? stake * playerNiuType.multiplier : (result === -1 ? -(stake * ai.type.multiplier) : 0);
        console.log('[斗牛] 对比AI[' + aiIndex + '] result=' + result + ', change=' + change + ', stake=' + stake + ', 累计=' + (totalChange + change));
        totalChange += change;
        aiChanges[aiIndex] = -change; // AI的积分变化（与玩家相反）
        const emoji = result === 1 ? '✅' : (result === -1 ? '❌' : '➖');
        document.getElementById('aiResult' + aiIndex).textContent = '🐂 ' + ai.type.name + ' ×' + ai.type.multiplier;
        document.getElementById('aiChange' + aiIndex).textContent = emoji + ' ' + (change > 0 ? '+' : '') + change;
        document.getElementById('aiChange' + aiIndex).className = 'nn-ai-change' + (change > 0 ? ' nn-ai-win' : (change < 0 ? ' nn-ai-lose' : ''));
        detailHtml += '<div class="nn-detail-item">' + emoji + ' vs ' + ai.name + ' (' + ai.type.name + ') ' + (change > 0 ? '+' : '') + change + '</div>';

        document.getElementById('revealStatus').textContent = '👤 第 ' + (aiIndex + 1) + '/6 家 · ' + ai.name;
        document.getElementById('revealResult').textContent = emoji + ' 玩家「' + playerNiuType.name + '」 vs 「' + ai.type.name + '」' + (result === 1 ? ' 🎉 胜' : (result === -1 ? ' 😅 负' : ' ➖ 平'));
        document.getElementById('revealScore').textContent = '当前累计: ' + (totalChange > 0 ? '+' : '') + totalChange + ' 分';
        document.getElementById('revealScore').style.color = totalChange > 0 ? '#2ecc71' : (totalChange < 0 ? '#e74c3c' : '#aaa');
        // 中央累计明细已不需要，结果已显示在各 AI 面板
        // document.getElementById('revealDetail').innerHTML = detailHtml;

        aiIndex++;
        setTimeout(revealNext, 1200);
    }

    revealNext();
}

async function finishGame(totalChange, detailHtml, aiChanges) {
    // 应用积分加成卡倍率
    var originalChangeBeforeBuff = 0;
    var buffApplied = false;
    if (NN_ACTIVE_BUFF_MULT > 1 && totalChange > 0) {
        originalChangeBeforeBuff = totalChange;
        totalChange = Math.round(totalChange * NN_ACTIVE_BUFF_MULT);
        buffApplied = true;
        console.log('[斗牛] 积分加成卡: ' + originalChangeBeforeBuff + ' × ' + NN_ACTIVE_BUFF_MULT + ' = ' + totalChange);
    }
    console.log('[斗牛] finishGame() totalChange=' + totalChange + ', aiChanges=' + JSON.stringify(aiChanges) + ', gameInProgress=' + gameInProgress + ' → false');
    gameInProgress = false;
    // 等待 showdown 完成并关闭本局游戏记录，避免返回大厅后刷新被误判为逃跑
    console.log('[斗牛] → 等待 saveShowdown() 完成...');
    await saveShowdown(totalChange);
    console.log('[斗牛] → saveShowdown() 完成, 保存AI分数');
    // 保存AI分数
    if (aiChanges && gameData && gameData.ai_players) {
        for (let aiIdx = 0; aiIdx < gameData.ai_players.length; aiIdx++) {
            let aiMember = gameData.ai_players[aiIdx];
            if (aiMember && aiChanges[aiIdx] !== undefined) {
                var fd = new FormData();
                fd.append('score', aiChanges[aiIdx]);
                fd.append('result', aiChanges[aiIdx] > 0 ? 'win' : (aiChanges[aiIdx] < 0 ? 'lose' : 'draw'));
                fd.append('nickname', aiMember.name);
                fd.append('avatar', aiMember.avatar || '');
                console.log('[斗牛] 发送 save_ai_score: ' + aiMember.name + ' score=' + aiChanges[aiIdx] + ' type=' + (aiMember.type ? aiMember.type.name : '?') + '×' + (aiMember.type ? aiMember.type.multiplier : '?'));
                fetch(NN_API + 'save_ai_score', { method: 'POST', body: fd })
                .then(function(rsp){ return rsp.json(); })
                .then((function(member, idx){ return function(d){ console.log('[斗牛] save_ai_score', member.name, 'idx=' + idx, d); }; })(aiMember, aiIdx))
                .catch(function(e){ console.warn('[斗牛] save_ai_score error', aiMember.name, e); });
            }
        }
    }
    console.log('[斗牛] → saveShowdown() 完成, 发送 end 信号');
    // 二次保险：发送 end 信号
    new Image().src = '<?= $base_url ?>?plugin=wx_games&game=niuniu&wx_niuniu_signal=end&r=' + Math.random();
    document.getElementById('phaseReveal').style.display = 'none';
    document.getElementById('phaseResult').style.display = 'block';
    document.querySelector('.nn-my-area').classList.add('hidden');
    document.getElementById('finalResult').textContent = totalChange > 0 ? '🎉 胜利啦！' : (totalChange < 0 ? '😅 输了' : '🤝 保本');
    document.getElementById('finalScore').textContent = (totalChange > 0 ? '+' : '') + totalChange + ' 分';
    document.getElementById('finalScore').style.color = totalChange > 0 ? '#2ecc71' : (totalChange < 0 ? '#e74c3c' : '#aaa');

    // 构建结算明细：庄家牌型 + 积分卡倍率计算过程
    var detailHtml2 = '庄家牌型：' + playerNiuType.name + ' ×' + playerNiuType.multiplier;
    if (buffApplied) {
        detailHtml2 += '<div style="margin-top:6px;color:#fdcb6e">⚡ 基础分 ' + originalChangeBeforeBuff + ' × 积分卡倍率 ' + NN_ACTIVE_BUFF_MULT + ' = <b>' + totalChange + ' 分</b></div>';
    }
    document.getElementById('finalDetail').innerHTML = detailHtml2;

    document.getElementById('finalDetailList').style.display = 'none';
    const navScore = (gameData.current_score || 0) + totalChange;
    document.getElementById('navScoreVal').textContent = navScore;
    document.getElementById('welcomeScore').textContent = navScore;
}

function saveShowdown(totalChange) {
    console.log('[斗牛] saveShowdown() totalChange=' + totalChange);
    return fetch(NN_API + 'showdown', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({total_change: totalChange})
    }).then(r => r.json()).then(res => {
        if (res.code !== 0) {
            console.warn('[斗牛] saveShowdown 失败:', res.msg);
        } else {
            console.log('[斗牛] saveShowdown 成功:', res.data);
        }
    }).catch(function(err) {
        console.error('[斗牛] saveShowdown 网络错误:', err);
    });
}

function backToWelcome() {
    console.log('[斗牛] backToWelcome() gameInProgress=' + gameInProgress + ' → false');
    gameInProgress = false;
    document.getElementById('gameArea').style.display = 'none';
    document.getElementById('welcomeScreen').style.display = 'flex';
    if (gameData && gameData.current_score) {
        document.getElementById('welcomeScore').textContent = gameData.current_score;
        document.getElementById('navScoreVal').textContent = gameData.current_score;
    }
    // 刷新积分卡剩余局数（后端每次结算已 used+1，需重新拉取）
    if (currentUser) loadBuffInfo();
}

function returnToLobby() {
    console.log('[斗牛] returnToLobby() gameInProgress=' + gameInProgress + ' → false');
    gameInProgress = false;
    document.getElementById('gameArea').style.display = 'none';
    document.getElementById('gameScreen').style.display = 'none';
    document.getElementById('loginScreen').style.display = 'flex';
    document.getElementById('welcomeScreen').style.display = 'flex';
    if (gameData && gameData.current_score) {
        document.getElementById('welcomeScore').textContent = gameData.current_score;
        document.getElementById('navScoreVal').textContent = gameData.current_score;
    }
    // 刷新积分卡剩余局数
    if (currentUser) loadBuffInfo();
}

function getTypeEmoji(type) {
    const map = {'wu_xiao_niu':'🖐️','zha_dan':'💣','jin_niu':'👑','yin_niu':'🥈','niu_niu':'🐂','niu_9':'9️⃣','niu_8':'8️⃣','niu_7':'7️⃣','niu_6':'6️⃣','no_niu':'❌'};
    return map[type] || '🐂';
}

// ====== 倍率表 ======
function showMultiplierChart() {
    document.querySelectorAll('.nn-modal').forEach(function(el){ el.style.display = 'none'; });
    var modal = document.createElement('div');
    modal.className = 'nn-modal';
    modal.style.display = 'flex';
    modal.onclick = function(e) { if (e.target === this) this.remove(); };
    modal.innerHTML = '<div class="nn-modal-content" onclick="event.stopPropagation()">'
        + '<div class="nn-modal-title">🐂 牌型倍率表</div>'
        + '<table class="mult-table">'
        + '<thead><tr><th>牌型</th><th>说明</th><th>倍率</th></tr></thead>'
        + '<tbody>'
        + '<tr><td><span class="mult-icon">🖐️</span> 五小牛</td><td>5张牌总点数≤10</td><td class="mult-val">×20</td></tr>'
        + '<tr><td><span class="mult-icon">💣</span> 炸弹</td><td>4张牌点数相同</td><td class="mult-val">×15</td></tr>'
        + '<tr><td><span class="mult-icon">👑</span> 金牛</td><td>5张牌全是J/Q/K</td><td class="mult-val">×10</td></tr>'
        + '<tr><td><span class="mult-icon">🥈</span> 银牛</td><td>5张牌全是10/J/Q/K</td><td class="mult-val">×7</td></tr>'
        + '<tr><td><span class="mult-icon">🐂</span> 牛牛</td><td>3张10倍，剩2张和=10倍</td><td class="mult-val">×5</td></tr>'
        + '<tr><td><span class="mult-icon">9️⃣</span> 牛9</td><td>3张10倍，剩2张和=9点</td><td class="mult-val">×4</td></tr>'
        + '<tr><td><span class="mult-icon">8️⃣</span> 牛8</td><td>3张10倍，剩2张和=8点</td><td class="mult-val">×3</td></tr>'
        + '<tr><td><span class="mult-icon">7️⃣</span> 牛7</td><td>3张10倍，剩2张和=7点</td><td class="mult-val">×2</td></tr>'
        + '<tr><td><span class="mult-icon">6️⃣</span> 牛6 ~ 牛1</td><td>3张10倍，剩2张和=6~1点</td><td class="mult-val">×1</td></tr>'
        + '<tr><td><span class="mult-icon">❌</span> 无牛</td><td>没有3张和是10倍</td><td class="mult-val">×1</td></tr>'
        + '</tbody>'
        + '</table>'
        + '<div style="margin-top:12px;font-size:12px;color:rgba(255,255,255,0.5)">你赢 = 底注 × 你的倍率 | 你输 = 底注 × 对方倍率</div>'
        + '<button class="nn-modal-close" onclick="this.closest(\'.nn-modal\').remove()">关闭</button>'
        + '</div>';
    document.body.appendChild(modal);
}

// ====== 排行榜 ======
function showRanking() {
    const modal = document.getElementById('rankModal');
    modal.style.display = 'flex';
    document.getElementById('rankBody').innerHTML = '<p style="color:#aaa">加载中...</p>';

    fetch(NN_API + 'get_ranking', {method:'POST'})
        .then(r => r.json())
        .then(res => {
            if (res.code !== 0 || !res.data || res.data.length === 0) {
                document.getElementById('rankBody').innerHTML = '<p style="color:#aaa">暂无数据</p>';
                return;
            }
            const html = res.data.map((item, i) => {
                const medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : (i+1)+'.'));
                const name = item.is_ai ? (item.nickname + '🤖') : item.nickname;
                return '<div class="nn-modal-list-item"><span>' + medal + ' ' + name + '</span><span style="color:#ffd700">' + item.score + '分</span></div>';
            }).join('');
            document.getElementById('rankBody').innerHTML = '<div class="nn-modal-list">' + html + '</div>';
        })
        .catch(() => {
            document.getElementById('rankBody').innerHTML = '<p style="color:#e74c3c">加载失败</p>';
        });
}

// ====== 积分流水（统一三栏布局） ======
function showLogs() {
    const modal = document.getElementById('logModal');
    modal.style.display = 'flex';
    const list = document.getElementById('scoreLogList');
    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;">加载中...</div>';

    fetch(NN_API + 'get_user_logs', {method:'POST'})
        .then(r => r.json())
        .then(res => {
            if (res.code !== 0 || !res.data || res.data.length === 0) {
                list.innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;">暂无记录</div>';
                return;
            }
            list.innerHTML = res.data.slice(0, 20).map(item => {
                const d = new Date(item.created_at.replace(' ', 'T'));
                const dateStr = d.getFullYear() + '-' + (d.getMonth()+1) + '-' + d.getDate() + ' ' + d.getHours() + ':' + String(d.getMinutes()).padStart(2,'0');
                const change = parseInt(item.score_change);
                const sign = change >= 0 ? '+' : '';
                const color = change >= 0 ? '#2ecc71' : '#e74c3c';
                return '<div class="score-log-item">' +
                    '<span class="log-reason">' + (item.reason || '游戏结算') + '</span>' +
                    '<span class="log-time">' + dateStr + '</span>' +
                    '<span class="log-change" style="color:' + color + ';font-weight:bold;">' + sign + change + '</span>' +
                '</div>';
            }).join('');
        })
        .catch(() => {
            list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:20px;">加载失败</div>';
        });
}

// ====== 商城 ======
const SHOP_TYPE_NAMES = {
    'title_colored': '昵称变色',
    'title_effect': '昵称特效',
    'card_back': '牌背皮肤',
    'emoticon': '专属表情',
    'score_buff': '积分加成卡',
    'title_badge': '称号徽章'
};
const SHOP_TYPE_ICONS = {
    'title_colored': '🎨',
    'title_effect': '✨',
    'card_back': '🃏',
    'emoticon': '😎',
    'score_buff': '⚡',
    'title_badge': '👑'
};

const ShopManager = {
    currentFilter: 'all',
    allItems: [],
    async show() {
        const modal = document.getElementById('shopModal');
        modal.style.display = 'flex';
        const body = document.getElementById('shopBody');
        body.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
        this.updateScoreDisplay();
        try {
            const res = await fetch(NN_API + 'get_shop_items', {method:'POST', credentials:'include'});
            const data = await res.json();
            if (data.code !== 0 || !data.data || data.data.length === 0) {
                body.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">商城暂无商品</div>';
                return;
            }
            this.allItems = data.data;
            this.currentFilter = 'all';
            this.renderFilterBar();
            this.renderItems();
        } catch(e) {
            body.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">网络错误，请重试</div>';
        }
    },
    renderFilterBar() {
        const body = document.getElementById('shopBody');
        const oldBar = document.getElementById('shopFilterBarNN');
        if (oldBar) oldBar.remove();
        const bar = document.createElement('div');
        bar.id = 'shopFilterBarNN';
        bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
        const types = ['all', ...new Set(this.allItems.map(i => i.item_type))];
        types.forEach(key => {
            const btn = document.createElement('button');
            btn.style.cssText = 'font-size:10px;padding:3px 8px;border-radius:12px;border:none;cursor:pointer;transition:all 0.2s;white-space:nowrap;background:rgba(255,255,255,0.1);color:#ccc;';
            if (key === 'all') {
                btn.textContent = '全部';
                btn.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                btn.style.color = '#fff';
            } else {
                const icon = SHOP_TYPE_ICONS[key] || '🎁';
                const name = SHOP_TYPE_NAMES[key] || key;
                btn.textContent = icon + ' ' + name;
            }
            btn.dataset.filter = key;
            btn.onclick = () => {
                this.currentFilter = key;
                bar.querySelectorAll('button').forEach(b => { b.style.background = 'rgba(255,255,255,0.1)'; b.style.color = '#ccc'; });
                btn.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                btn.style.color = '#fff';
                this.renderItems();
            };
            bar.appendChild(btn);
        });
        body.parentNode.insertBefore(bar, body);
    },
    renderItems() {
        const body = document.getElementById('shopBody');
        const filtered = this.currentFilter === 'all' ? this.allItems : this.allItems.filter(i => i.item_type === this.currentFilter);
        if (filtered.length === 0) {
            body.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">该分类暂无商品</div>';
            return;
        }
        body.innerHTML = filtered.map(item => {
            const hasEmlog = item.price_emlog > 0;
            const hasGame = item.price_niuniu > 0;
            let priceHtml = '';
            if (hasEmlog && hasGame) {
                priceHtml = '站点积分 ' + item.price_emlog + ' + 斗牛 ' + item.price_niuniu;
            } else if (hasEmlog) {
                priceHtml = '站点积分 ' + item.price_emlog;
            } else if (hasGame) {
                priceHtml = '斗牛 ' + item.price_niuniu;
            }
            return '<div class="shop-item" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;margin-bottom:6px;background:rgba(255,255,255,0.06);">' +
                '<span style="font-size:22px;">' + (item.icon || '🎁') + '</span>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:bold;font-size:13px;">' + item.name + (item.is_global ? ' <span style="font-size:9px;color:#fdcb6e;border:1px solid #fdcb6e;border-radius:4px;padding:0 4px;vertical-align:middle;">通用</span>' : '') + '</div>' +
                    '<div style="font-size:10px;color:#aaa;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + (item.description || '') + '</div>' +
                '</div>' +
                '<div style="text-align:right;flex-shrink:0;">' +
                    '<div style="font-size:11px;margin-bottom:3px;">' + priceHtml + '</div>' +
                    (item.owned
                        ? '<span style="display:inline-block;font-size:10px;padding:2px 8px;background:rgba(46,204,113,0.15);color:#2ecc71;border-radius:8px;border:1px solid #2ecc71;">✓ 已拥有</span>'
                        : '<button class="nn-modal-close" style="margin-top:4px;padding:4px 12px;font-size:12px" onclick="ShopManager.buyItem(' + item.id + ')">购买</button>'
                    ) +
                '</div></div>';
        }).join('');
    },
    updateScoreDisplay() {
        const score = window.NN_USER_SCORE ? (window.NN_USER_SCORE.score || 0) : 0;
        const emlogCredits = window.WX_NN_EMLOG_CREDITS || 0;
        const scoreEl = document.getElementById('shopNnScore');
        const emlogEl = document.getElementById('shopNnEmlog');
        if (scoreEl) scoreEl.textContent = score;
        if (emlogEl) emlogEl.textContent = emlogCredits;
    },
    async buyItem(itemId) {
        if (!currentUser) { alert('请先登录'); return; }
        const item = this.allItems.find(i => i.id === itemId);
        if (!item) { alert('商品数据未找到'); return; }
        const priceGame = item.price_niuniu || 0;
        const priceEmlog = item.price_emlog || 0;
        let currency = '';
        if (priceGame > 0 && priceEmlog > 0) {
            const choice = prompt('选择支付方式：\n1 - 斗牛积分（' + priceGame + '分）\n2 - 站点积分（' + priceEmlog + '分）\n请输入 1 或 2：', '1');
            if (choice === null) return;
            currency = choice.trim() === '2' ? 'emlog' : 'niuniu';
        } else if (priceEmlog > 0) {
            if (!confirm('确认使用 站点积分 ' + priceEmlog + ' 购买？')) return;
            currency = 'emlog';
        } else if (priceGame > 0) {
            if (!confirm('确认使用 斗牛积分 ' + priceGame + ' 购买？')) return;
            currency = 'niuniu';
        } else {
            alert('该商品暂无有效价格');
            return;
        }
        try {
            const res = await fetch(NN_API + 'purchase_item', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({item_id: itemId, currency: currency})
            });
            const data = await res.json();
            if (data.code === 0) {
                alert('购买成功！');
                this.show(); // 刷新商城
            } else {
                alert(data.msg || '购买失败');
            }
        } catch(e) {
            alert('网络错误');
        }
    }
};

// ====== 背包（以麻将风格为基准） ======
const InventoryManager = {
    currentFilter: 'all',
    allItems: [],
    async show() {
        if (!currentUser) { alert('请先登录'); return; }
        const modal = document.getElementById('inventoryModal');
        modal.style.display = 'flex';
        const body = document.getElementById('inventoryBody');
        body.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
        try {
            const res = await fetch(NN_API + 'get_inventory', {method:'POST', credentials:'include'});
            const data = await res.json();
            if (data.code !== 0 || !data.data || data.data.length === 0) {
                body.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">背包暂无道具</div>';
                return;
            }
            this.allItems = data.data.map(item => ({
                inv_id: item.inv_id || item.id,
                item_id: item.item_id,
                name: item.name,
                icon: item.icon,
                item_type: item.item_type,
                quantity: item.quantity || 1,
                is_active: item.is_active
            }));
            this.currentFilter = 'all';
            this.renderFilterBar();
            this.renderItems();
        } catch(e) {
            body.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">网络错误</div>';
        }
    },
    renderFilterBar() {
        const body = document.getElementById('inventoryBody');
        const oldBar = document.getElementById('invFilterBarNN');
        if (oldBar) oldBar.remove();
        const bar = document.createElement('div');
        bar.id = 'invFilterBarNN';
        bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
        const types = ['all', ...new Set(this.allItems.map(i => i.item_type || 'unknown'))];
        types.forEach(key => {
            const btn = document.createElement('button');
            btn.style.cssText = 'padding:4px 12px;font-size:11px;border:none;border-radius:20px;cursor:pointer;transition:all 0.2s;white-space:nowrap;font-weight:600;';
            if (key === 'all') {
                btn.textContent = '全部';
                btn.style.background = 'linear-gradient(135deg,#4a7cf7,#3b82f6)';
                btn.style.color = '#fff';
            } else {
                btn.textContent = (SHOP_TYPE_ICONS[key] || '🎁') + ' ' + (SHOP_TYPE_NAMES[key] || key);
                btn.style.background = 'rgba(255,255,255,0.08)';
                btn.style.color = '#ccc';
                btn.style.border = '1px solid rgba(255,255,255,0.15)';
            }
            btn.dataset.filter = key;
            btn.onclick = () => {
                this.currentFilter = key;
                bar.querySelectorAll('button').forEach(b => { b.style.background = 'rgba(255,255,255,0.08)'; b.style.color = '#ccc'; b.style.border = '1px solid rgba(255,255,255,0.15)'; });
                btn.style.background = 'linear-gradient(135deg,#4a7cf7,#3b82f6)';
                btn.style.color = '#fff';
                btn.style.border = 'none';
                this.renderItems();
            };
            bar.appendChild(btn);
        });
        body.parentNode.insertBefore(bar, body);
    },
    renderItems() {
        const body = document.getElementById('inventoryBody');
        const filtered = this.currentFilter === 'all' ? this.allItems : this.allItems.filter(i => (i.item_type || 'unknown') === this.currentFilter);
        if (filtered.length === 0) {
            body.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">该分类暂无道具</div>';
            return;
        }
        const cosmeticTypes = ['title_colored', 'title_effect', 'title_badge', 'card_back', 'emoticon'];
        body.innerHTML = filtered.map(item => {
            const isCosmetic = cosmeticTypes.indexOf(item.item_type) !== -1;
            let btnHtml = '';
            if (item.is_active == 1) {
                btnHtml = '<span style="font-size:10px;padding:2px 8px;background:rgba(34,197,94,0.2);color:#22c55e;border-radius:8px;border:1px solid #22c55e;white-space:nowrap;">✓ 已激活</span>';
            } else if (isCosmetic) {
                btnHtml = '<button style="padding:4px 12px;font-size:11px;border:none;border-radius:20px;cursor:pointer;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-weight:600;" onclick="InventoryManager.useItem(' + item.inv_id + ')">🎯 激活</button>';
            } else {
                btnHtml = '<button style="padding:4px 12px;font-size:11px;border:none;border-radius:20px;cursor:pointer;background:linear-gradient(135deg,#4a7cf7,#3b82f6);color:#fff;font-weight:600;" onclick="InventoryManager.useItem(' + item.inv_id + ')">使用</button>';
            }
            return '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;margin-bottom:6px;background:rgba(255,255,255,0.06);">' +
                '<span style="font-size:22px;">' + (item.icon || '🎁') + '</span>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:bold;font-size:13px;">' + item.name + '</div>' +
                    '<div style="font-size:10px;color:#aaa;">剩余 x' + (item.quantity || 1) + '</div>' +
                '</div>' + btnHtml + '</div>';
        }).join('');
    },
    async useItem(invId) {
        if (!confirm('确认使用此道具？')) return;
        try {
            const res = await fetch(NN_API + 'use_item', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({inv_id: invId})
            });
            const data = await res.json();
            if (data.code === 0) {
                alert(data.msg || '✅ 使用成功');
                loadPlayerEffects();
                this.refreshItems();
            } else {
                alert(data.msg || '使用失败');
            }
        } catch(e) {
            alert('网络错误');
        }
    },
    async refreshItems() {
        try {
            const res = await fetch(NN_API + 'get_inventory', {method:'POST', credentials:'include'});
            const data = await res.json();
            if (data.code === 0 && data.data) {
                this.allItems = data.data.map(item => ({
                    inv_id: item.inv_id || item.id,
                    item_id: item.item_id,
                    name: item.name,
                    icon: item.icon,
                    item_type: item.item_type,
                    quantity: item.quantity || 1,
                    is_active: item.is_active
                }));
                this.renderItems();
            }
        } catch(e) {}
    }
};

// 兼容旧函数名（按钮仍用 onclick 调用）
function showShop() { ShopManager.show(); }
function showInventory() { InventoryManager.show(); }

// ====== 充值 ======
function showRecharge() {
    const rechargeUrl = <?= json_encode($config['recharge_link'] ?? '') ?>;
    if (rechargeUrl) {
        window.open(rechargeUrl, '_blank');
    } else {
        alert('充值功能暂未开放，请联系管理员。');
    }
}

// ====== 防逃跑：离开页面提示 + 惩罚信号 ======
window.addEventListener('beforeunload', function(e) {
    console.log('[斗牛] beforeunload 触发, gameInProgress=' + gameInProgress + ', currentBet=' + currentBet + ', penaltyMultiplier=' + (EMLOG_CONFIG.penaltyMultiplier || 1));
    if (!gameInProgress) {
        console.log('[斗牛] beforeunload → 游戏未进行中, 不发送惩罚信号');
        return;
    }

    var baseBet = EMLOG_CONFIG.baseBet || 100;
    var multi = currentBet || 1;
    var penalty = baseBet * multi * (EMLOG_CONFIG.penaltyMultiplier || 1);
    console.log('[斗牛] 惩罚计算: 底分' + baseBet + ' × ' + multi + ' × ' + (EMLOG_CONFIG.penaltyMultiplier || 1) + ' = ' + penalty);

    // sendBeacon 发送惩罚信号（浏览器不拦截）
    console.log('[斗牛] → sendBeacon 发送 penalty 信号, points=' + penalty);
    navigator.sendBeacon('<?= $base_url ?>?plugin=wx_games&game=niuniu&wx_niuniu_signal=penalty&points=' + penalty);

    // 浏览器原生确认框
    e.preventDefault();
    e.returnValue = '游戏进行中，离开将被扣除 ' + penalty + ' 积分（底分' + baseBet + '×倍率' + multi + '×惩罚' + (EMLOG_CONFIG.penaltyMultiplier || 1).toFixed(1) + '）！';
});
</script>
<script>
(function(){if(localStorage.getItem("wx_games_player_on")!=="1"||document.getElementById("myhk"))return;
var s1=document.createElement("script");s1.type="text/javascript";s1.id="myhk";s1.src="https://myhkw.cn/api/player/1733906404100";s1.setAttribute("key","1733906404100");s1.setAttribute("m","1");document.body.appendChild(s1);
if(!document.querySelector("script[src*=\"myhkw.cn/player/js/jquery\"]")){var s2=document.createElement("script");s2.type="text/javascript";s2.src="https://myhkw.cn/player/js/jquery.min.js";document.body.appendChild(s2)}
})();
</script>
</body>
</html>
