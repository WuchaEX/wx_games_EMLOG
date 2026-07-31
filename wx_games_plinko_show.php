<?php
!defined('EMLOG_ROOT') && exit('access denied!');

$current_user = wx_plinko_check_user();
$game_url = WX_GAMES_URL . 'games/plinko/';
$base_url = BLOG_URL;
$avatar = $current_user && isset($current_user['avatar']) ? $current_user['avatar'] : (BLOG_URL . 'admin/views/images/avatar.png');

// 加载存档（用于初始余额显示）
$saved_balance = 200;
$has_save = false;
$save_data = [];
if ($current_user) {
    $row = wx_plinko_get_account(intval($current_user['uid']));
    if ($row) {
        $saved_balance = floatval($row['balance']);
        $has_save = true;
    }
}

// 加载配置（公告 + 最近更新 + 充值链接）
$_plinko_cfg = wx_plinko_get_config();
$plinko_notice = isset($_plinko_cfg['notice']) ? $_plinko_cfg['notice'] : '欢迎来到H5弹珠台！选择风险等级，投球赢取奖励！';
$plinko_updates = isset($_plinko_cfg['recent_updates']) ? $_plinko_cfg['recent_updates'] : "v1.0.0 - H5弹珠台正式上线\nv1.0.0 - 真实物理引擎\nv1.0.0 - 余额同步、多球连发、深色主题";
$login_url = (is_array($_plinko_cfg) && !empty($_plinko_cfg['recharge_link'])) ? $_plinko_cfg['recharge_link'] : (defined('LOGIN_URL') ? LOGIN_URL : (BLOG_URL . 'admin'));
$emlog_credits = 0;
if ($current_user) { try { $um = new User_Model(); $eu = $um->getOneUser(intval($current_user['uid'])); $emlog_credits = ($eu && isset($eu['credits'])) ? intval($eu['credits']) : 0; } catch(Throwable $e) {} }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo htmlspecialchars($_plinko_cfg['title']); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html,body{height:100%;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Noto Sans SC',sans-serif;
  background:linear-gradient(135deg,#2D1A12 0%,#1A0E08 100%);color:#fff}

/* ---- 导航栏（完全复刻 ddz）---- */
.ddz-nav{width:100%;margin:0;height:60px;
  background:linear-gradient(135deg,#e17055 0%,#d94a2e 100%);
  display:flex;align-items:center;position:fixed;top:0;left:0;z-index:5000;
  box-shadow:0 2px 20px rgba(225,112,85,0.3);
  transition:background .3s ease,box-shadow .3s ease;
  backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.ddz-nav-inner{width:100%;padding:0 24px;display:flex;align-items:center;justify-content:space-between;box-sizing:border-box}
.ddz-nav-left{display:flex;align-items:center;gap:10px;flex-shrink:0}
.ddz-nav-icon{font-size:24px;line-height:1}
.ddz-nav-title{color:#fff;font-size:20px;font-weight:600;letter-spacing:2px;margin:0}
.ddz-nav-right{display:flex;gap:12px;align-items:center;margin-left:auto}

/* ddz nav 按钮组 */
.nav-user-info,.nav-score,.nav-btn,.nav-home-btn{display:flex;align-items:center;justify-content:center;height:34px;border-radius:17px;box-sizing:border-box;flex-shrink:0}
.nav-user-info{gap:6px;padding:0 10px;background:rgba(0,0,0,.25);max-width:220px}
.nav-avatar{width:24px;height:24px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,215,0,.7);flex-shrink:0}
.nav-user-name{font-size:13px;color:#fff;font-weight:500;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.nav-score{gap:4px;padding:0 10px;background:rgba(0,0,0,.25);cursor:pointer}
.nav-score:hover{background:rgba(0,0,0,.4)}
.nav-score-label{font-size:12px;color:rgba(255,255,255,.8)}
.nav-score-value{font-size:14px;font-weight:bold;color:#ffd700}
.nav-btn{display:flex;align-items:center;justify-content:center;gap:4px;padding:0 12px;
  background:rgba(255,255,255,.18);color:#fff;border:none;
  font-size:12px;cursor:pointer;transition:all .2s ease;white-space:nowrap;
  -webkit-appearance:none;appearance:none}
.nav-btn:hover{background:rgba(255,255,255,.3)}
.nav-btn:active{transform:scale(.95)}
.nav-home-btn{background:rgba(255,255,255,.2);color:#fff;font-weight:500;
  border:1px solid rgba(255,255,255,.3);padding:0 14px;text-decoration:none;font-size:14px}
.nav-home-btn:hover{background:rgba(255,255,255,.3);border-color:rgba(255,255,255,.5)}

/* ---- 通用 Leaderboard Modal (ddz 同款) ---- */
.leaderboard-modal{position:fixed;top:0;left:0;right:0;bottom:0;
  background:rgba(0,0,0,0.85);display:flex;justify-content:center;align-items:center;z-index:4000}
.leaderboard-modal.hidden{display:none}
.leaderboard-content{background:linear-gradient(135deg,#2D1A12 0%,#1E0F08 100%);
  border-radius:20px;padding:25px;color:#fff;border:2px solid #ffd700;
  width:90vw;max-width:500px;max-height:80vh;overflow-y:auto;-webkit-overflow-scrolling:touch;box-sizing:border-box}
.leaderboard-title{font-size:22px;font-weight:bold;text-align:center;margin-bottom:15px;color:#ffd700}
.leaderboard-list{max-height:350px;overflow-y:auto;-webkit-overflow-scrolling:touch}
.leaderboard-close{position:absolute;top:12px;right:15px;background:none;border:none;font-size:26px;cursor:pointer;color:rgba(255,255,255,0.5);line-height:1;z-index:1}
.leaderboard-close:hover{color:#ffd700}
.leaderboard-header-info{display:flex;justify-content:space-around;margin-bottom:12px;font-size:13px;color:rgba(255,255,255,0.8);gap:10px;flex-wrap:wrap}

/* 商城 / 背包道具卡片 */
.shop-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;margin-bottom:6px;background:rgba(255,255,255,0.06)}

/* 反馈弹窗 (ddz 同款) */
.result-modal{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);
  display:flex;justify-content:center;align-items:center;z-index:5000}
.result-modal.hidden{display:none}
.result-content{background:linear-gradient(135deg,#2D1A12 0%,#1E0F08 100%);
  border-radius:20px;padding:30px;text-align:center;color:#fff;border:2px solid #ffd700;width:320px;max-width:90vw;box-sizing:border-box}
.result-title{font-size:32px;font-weight:bold;margin-bottom:15px}
.result-title.win{color:#2ecc71}
.result-title.lose{color:#e74c3c}
.result-detail{font-size:13px;color:#aaa;margin-bottom:15px}
.btn-close-fb{background:linear-gradient(135deg,#e17055,#d63031);color:#fff;border:none;border-radius:20px;padding:8px 30px;font-size:14px;font-weight:600;cursor:pointer}

/* ---- 欢迎页（严格对齐 ddz 深色风格）---- */
.login-screen{position:fixed;top:60px;left:0;right:0;bottom:0;
  display:flex;flex-direction:column;justify-content:center;align-items:center;z-index:3000;padding:20px;overflow-y:auto;
  background:linear-gradient(135deg,#2D1A12 0%,#1A0E08 100%)}
.login-container{background:linear-gradient(135deg,#2D1A12 0%,#1E0F08 100%);border-radius:20px;padding:35px;min-width:340px;max-width:400px;
  box-shadow:0 10px 40px rgba(0,0,0,0.5),0 0 0 1px rgba(225,112,85,0.25);color:#f0e6dc;box-sizing:border-box}
.login-subtitle{font-size:15px;text-align:center;margin-bottom:25px;color:rgba(240,230,220,0.7);font-weight:500}
.welcome-user{display:flex;flex-direction:column;align-items:center;gap:6px;margin-bottom:8px}
.welcome-avatar{width:72px;height:72px;border-radius:50%;border:3px solid #d63031;object-fit:cover;background:#fff}
.welcome-name{font-size:20px;font-weight:700;color:#fff}
.welcome-score{font-size:14px;color:#bbb}
.welcome-score strong{color:#f1c40f;font-size:18px}
.welcome-start-btn{display:block;width:100%;margin:16px 0;font-size:18px;padding:12px 40px;box-sizing:border-box;
  background:linear-gradient(135deg,#e74c3c,#c0392b);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;
  box-shadow:0 4px 15px rgba(231,76,60,0.4);-webkit-appearance:none;appearance:none}
.welcome-start-btn:active{transform:scale(.98)}
.welcome-actions{display:flex;gap:10px;margin-top:10px}
.welcome-action-btn{flex:1;min-width:0;padding:10px 8px;font-size:13px;font-weight:600;border-radius:10px;border:none;cursor:pointer;transition:all .2s ease;color:#fff;white-space:nowrap;-webkit-appearance:none;appearance:none}
.welcome-action-btn:active{transform:scale(.97)}
.welcome-modules{margin-top:24px;display:flex;flex-direction:column;gap:12px;text-align:left}
.welcome-notice,.welcome-updates{background:rgba(255,255,255,0.08);border-radius:10px;padding:14px 16px;color:rgba(255,255,255,0.85)}
.welcome-notice .module-title,.welcome-updates .module-title{font-size:13px;font-weight:600;color:#f1c40f;margin-bottom:8px}
.welcome-notice .module-body{font-size:13px;color:#ddd;line-height:1.6;white-space:pre-wrap}
.welcome-updates .module-body{font-size:12px;color:#bbb;line-height:1.8;max-height:calc(1.8em*5);overflow-y:auto;padding-right:4px;scrollbar-width:thin}
.welcome-updates .module-body::-webkit-scrollbar{width:4px}
.welcome-updates .module-body::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.2);border-radius:2px}

/* 游客态容器 */
.guest-container{background:linear-gradient(135deg,#2D1A12 0%,#1E0F08 100%);border-radius:20px;padding:35px;min-width:340px;max-width:400px;
  box-shadow:0 10px 40px rgba(0,0,0,0.5),0 0 0 1px rgba(225,112,85,0.25);color:#f0e6dc;text-align:center;box-sizing:border-box}
.guest-tip{background:rgba(255,255,255,0.08);color:#fdcb6e;padding:12px;border-radius:8px;font-size:13px;margin-bottom:20px;line-height:1.5;border:1px solid rgba(255,255,255,0.06)}
.guest-tip strong{display:block;margin-bottom:5px}
.btn-redirect-login{padding:14px;background:linear-gradient(135deg,#27ae60,#2ecc71);color:#fff;border:none;border-radius:10px;font-size:15px;cursor:pointer;display:block;text-align:center;min-height:44px;text-decoration:none;box-shadow:0 4px 15px rgba(39,174,96,0.4);-webkit-appearance:none;appearance:none}
.btn-redirect-login:active{transform:scale(.98)}
.btn-guest{padding:12px;background:transparent;color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.2);border-radius:10px;font-size:14px;cursor:pointer;margin-top:10px;min-height:44px;width:100%;-webkit-appearance:none;appearance:none}
.btn-guest:hover{background:rgba(255,255,255,0.08);color:#fff}

/* ---- 游戏区（初始隐藏） ---- */
.game-wrap{display:none;position:fixed;top:60px;left:0;right:0;bottom:0}
.game-wrap.active{display:block}
.game-wrap iframe{width:100%;height:100%;border:none;display:block}
</style>
</head>
<body>
<!-- 导航栏（复刻 ddz） -->
<nav class="ddz-nav">
  <div class="ddz-nav-inner">
    <div class="ddz-nav-left">
      <span class="ddz-nav-icon">👑</span>
      <h1 class="ddz-nav-title"><?php echo htmlspecialchars($_plinko_cfg['title']); ?></h1>
    </div>
    <div class="ddz-nav-right">
      <?php if ($current_user): ?>
        <div class="nav-user-info">
          <img class="nav-avatar" src="<?php echo $avatar; ?>" alt="" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%23e17055%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 font-size=%2240%22>👑</text></svg>'">
          <span class="nav-user-name" id="navUserName"><?php echo htmlspecialchars($current_user['nickname']); ?></span>
        </div>
        <div class="nav-score" id="navScoreBox" title="点击查看弹珠流水" onclick="showScoreLog()">
          <span class="nav-score-label">弹珠数量:</span>
          <span class="nav-score-value" id="navBalance"><?php echo $saved_balance; ?></span>
        </div>
        <button class="nav-btn" onclick="showRanking()">
          <span class="nav-btn-icon">🏆</span>
          <span class="nav-btn-text">排行</span>
        </button>
        <button class="nav-btn" onclick="showScoreLog()">
          <span class="nav-btn-icon">📜</span>
          <span class="nav-btn-text">流水</span>
        </button>
      <?php else: ?>
        <span class="nav-user-name" style="color:#fff">游客模式</span>
      <?php endif; ?>
      <a href="<?php echo $base_url; ?>?plugin=wx_games" class="nav-home-btn">返回大厅</a>
    </div>
  </div>
</nav>

<!-- 欢迎页（移植自 ddz） -->
<div class="login-screen" id="loginScreen">
  <?php if ($current_user): ?>
    <div class="login-container">
      <div class="login-subtitle" id="welcomeSubtitle">🎱 欢迎来到<?php echo htmlspecialchars($_plinko_cfg['title']); ?></div>

      <div id="loggedInPanel">
        <div class="welcome-user">
      <?php $avatar = isset($current_user['avatar']) ? $current_user['avatar'] : (BLOG_URL . 'admin/views/images/avatar.png'); ?>
          <img class="welcome-avatar" src="<?php echo $avatar; ?>" alt="" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2250%22 fill=%22%23e17055%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 font-size=%2240%22>👑</text></svg>'">
          <div class="welcome-name" id="welcomeName"><?php echo htmlspecialchars($current_user['nickname']); ?></div>
          <div class="welcome-score">弹珠数量: <strong id="welcomeScore"><?php echo $saved_balance; ?></strong></div>
        </div>
        <div id="welcomeBuffInfo" style="margin:6px 0;font-size:12px;min-height:18px;text-align:center;color:#aaa;">🎴 当前没有应用积分卡，可在商城购买</div>
        <button class="welcome-start-btn" onclick="startGame()">🎱 开始游戏</button>
        <div class="welcome-actions">
          <button class="welcome-action-btn" onclick="ShopManager.show()" style="background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;">🛒 商城</button>
          <button class="welcome-action-btn" onclick="InventoryManager.show()" style="background:linear-gradient(135deg,#e17055,#d63031);color:#fff;">🎒 背包</button>
          <a class="welcome-action-btn" href="<?php echo $login_url; ?>" style="background:linear-gradient(135deg,#e74c3c,#c0392b);color:#fff;text-decoration:none;line-height:1;display:flex;align-items:center;justify-content:center;">💎 充值</a>
        </div>

        <div class="welcome-modules">
          <div class="welcome-notice">
            <div class="module-title">📢 公告</div>
            <div class="module-body" id="noticeBody"></div>
          </div>
          <div class="welcome-updates">
            <div class="module-title">🔄 最近更新</div>
            <div class="module-body" id="updatesBody"></div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="guest-container">
      <div class="login-subtitle">👑 <?php echo htmlspecialchars($_plinko_cfg['title']); ?></div>
      <div class="guest-tip">
        <strong>💡 登录说明：</strong>
        登录后可保存弹珠到云存档，并参与排行榜。游客模式仅限本地体验。
      </div>
      <a href="<?php echo $login_url; ?>" class="btn-redirect-login">🔑 前往登录（推荐）</a>
      <div style="text-align:center;margin:15px 0;color:#999;">— 或者 —</div>
      <button class="btn-guest" onclick="startGame()">🎮 游客试玩</button>
    </div>
  <?php endif; ?>
</div>

<!-- 游戏区（初始隐藏） -->
<div class="game-wrap" id="gameView">
  <iframe id="plinkoFrame" src="<?php echo $game_url; ?>index.html?v=11" allow="autoplay"></iframe>
</div>

<!-- 商城弹窗 (ddz 风格) -->
<div class="leaderboard-modal hidden" id="shopModal">
  <div class="leaderboard-content" style="position:relative;">
    <button class="leaderboard-close" id="btnCloseShop">&times;</button>
    <div class="leaderboard-title">🛒 道具商城</div>
    <div class="leaderboard-header-info">
      <span>弹珠币: <strong style="color:#ffd700;" id="shopPlinkoScore">0</strong></span>
      <span>站点积分: <strong style="color:#e17055;" id="shopEmlogCredits">0</strong></span>
    </div>
    <div class="leaderboard-list" id="shopItemsList"></div>
  </div>
</div>

<!-- 背包弹窗 (ddz 风格) -->
<div class="leaderboard-modal hidden" id="inventoryModal">
  <div class="leaderboard-content" style="position:relative;">
    <button class="leaderboard-close" id="btnCloseInventory">&times;</button>
    <div class="leaderboard-title">🎒 我的背包</div>
    <div class="leaderboard-list" id="inventoryList"></div>
  </div>
</div>

<!-- 购买反馈弹窗 -->
<div class="result-modal hidden" id="shopFeedbackModal">
  <div class="result-content">
    <div class="result-title" id="shopFeedbackIcon" style="font-size:48px;margin-bottom:5px;">🎉</div>
    <div class="result-title" id="shopFeedbackTitle" style="font-size:24px;margin-bottom:10px;">购买成功</div>
    <div class="result-detail" id="shopFeedbackMsg" style="font-size:14px;color:#ccc;margin-bottom:15px;">积分已扣除，道具已发放到背包</div>
    <button class="btn-close-fb" id="btnShopFeedbackClose">确 定</button>
  </div>
</div>

<!-- 排行榜弹窗 -->
<div class="leaderboard-modal hidden" id="rankingModal">
  <div class="leaderboard-content" style="position:relative;">
    <button class="leaderboard-close" id="btnCloseRanking">&times;</button>
    <div class="leaderboard-title">🏆 弹珠排行榜</div>
    <div class="leaderboard-list" id="rankingList">
      <div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>
    </div>
  </div>
</div>

<!-- 弹珠流水弹窗 -->
<div class="leaderboard-modal hidden" id="scoreLogModal">
  <div class="leaderboard-content" style="position:relative;">
    <button class="leaderboard-close" id="btnCloseScoreLog">&times;</button>
    <div class="leaderboard-title">📜 弹珠流水</div>
    <div class="leaderboard-list" id="scoreLogList" style="max-height:350px;overflow-y:auto;">
      <div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>
    </div>
  </div>
</div>

<script>
window.PLINKO_NOTICE = <?php echo json_encode($plinko_notice, JSON_UNESCAPED_UNICODE); ?>;
window.PLINKO_UPDATES = <?php echo json_encode($plinko_updates, JSON_UNESCAPED_UNICODE); ?>;

// 公告/更新渲染
(function(){
  function renderLines(txt){return txt.split('\n').map(function(l){return l.trim()?l.trim()+'<br>':'';}).join('');}
  var n = document.getElementById('noticeBody');
  if (n && window.PLINKO_NOTICE) n.innerHTML = renderLines(window.PLINKO_NOTICE);
  var u = document.getElementById('updatesBody');
  if (u && window.PLINKO_UPDATES) u.innerHTML = renderLines(window.PLINKO_UPDATES);
})();
</script>

<script>
// ============ 存档同步 IIFE ============
(function(){
  var uid = <?php echo $current_user ? intval($current_user['uid']) : 0; ?>;
  var lastSaveTime = 0;
  var saveMinInterval = 2000;
  var autoSaveInterval = 3000;
  var frame = document.getElementById('plinkoFrame');
  var navBalance = document.getElementById('navBalance');
  var welcomeScore = document.getElementById('welcomeScore');
  var plinkoRestored = false;
  var plinkoSaveTimer = null;

  function getGameStats() {
    try {
      var ls = frame.contentWindow.localStorage;
      return {
        balance: parseFloat(ls.getItem('plinko_balance')||'200')||200,
        total_bet: parseInt(ls.getItem('plinko_total_bet')||'0',10)||0,
        total_payout: parseInt(ls.getItem('plinko_total_payout')||'0',10)||0,
        play_count: parseInt(ls.getItem('plinko_play_count')||'0',10)||0
      };
    } catch(e) { return null; }
  }
  function saveToServer() {
    if (uid <= 0) return; var now = Date.now();
    if (now - lastSaveTime < saveMinInterval) return; lastSaveTime = now;
    var stats = getGameStats(); if (!stats) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?plugin=wx_games&game=plinko&plinko_action=save', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function(){
      if (xhr.readyState===4 && xhr.status===200) {
        try { JSON.parse(xhr.responseText); } catch(e) {}
      }
    };
    xhr.send(JSON.stringify(stats));
  }
  function loadFromServer() {
    if (uid <= 0) return;
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '?plugin=wx_games&game=plinko&plinko_action=load', true);
    xhr.onreadystatechange = function(){
      if (xhr.readyState===4 && xhr.status===200) {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.code===0 && res.data && res.data.found && res.data.data && res.data.data.balance>0) {
            try { frame.contentWindow.localStorage.setItem('plinko_restore', JSON.stringify({balance: res.data.data.balance})); } catch(e) {}
            // reload iframe 让 restore 逻辑生效（只在 iframe 加载时执行一次）
            try { frame.contentWindow.location.reload(); } catch(e) {}
          } else {
            // 数据库无存档：清除浏览器缓存，iframe 重启时用默认余额
            try {
              frame.contentWindow.localStorage.removeItem('plinko_balance');
              frame.contentWindow.localStorage.removeItem('plinko_restore');
              frame.contentWindow.localStorage.removeItem('plinko_total_bet');
              frame.contentWindow.localStorage.removeItem('plinko_total_payout');
              frame.contentWindow.localStorage.removeItem('plinko_play_count');
            } catch(e) {}
            // 强制 iframe 重新加载（清缓存后从头开始）
            try { frame.contentWindow.location.reload(); } catch(e) {}
          }
        } catch(e) {}
      }
    };
    xhr.send();
  }

  // 唯一余额显示入口：1位小数格式化
  function updateBalance(bal) {
    bal = Math.round(Number(bal) * 10) / 10;
    var txt = bal % 1 === 0 ? String(bal) : bal.toFixed(1);
    if (navBalance) navBalance.textContent = txt;
    if (welcomeScore) welcomeScore.textContent = txt;
  }
  // 暴露到全局，让 ShopManager/InventoryManager 可调用
  window._plinkoUpdateBalance = updateBalance;

  // iframe 加载后：恢复存档 + 定时存档
  frame.addEventListener('load', function(){
    if (!plinkoRestored) { plinkoRestored = true; loadFromServer(); }
    if (!plinkoSaveTimer) plinkoSaveTimer = setInterval(saveToServer, autoSaveInterval);
  });

  // startGame
  window.startGame = function(){
    document.getElementById('loginScreen').style.display = 'none';
    document.getElementById('gameView').classList.add('active');
    if (!plinkoSaveTimer) plinkoSaveTimer = setInterval(saveToServer, autoSaveInterval);
  };

  // 唯一实时更新源：iframe postMessage
  window.addEventListener('message', function(e){
    if (!e.data) return;
    if (e.data.type==='plinko_ball' && uid > 0) {
      var d = e.data;
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '?plugin=wx_games&game=plinko&plinko_action=log_ball', true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.send(JSON.stringify({betAmount:d.betAmount,multiplier:d.multiplier,payout:d.payout,profit:d.profit,risk:d.risk,rowCount:d.rowCount,binIndex:d.binIndex}));
    }
    if (e.data.type==='plinko_balance') {
      if (uid > 0) updateBalance(e.data.balance);
    }
  });

  // beforeunload + visibilitychange 同步保存
  function forceSave() {
    if (uid <= 0) return; var stats = getGameStats(); if (!stats) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '?plugin=wx_games&game=plinko&plinko_action=save', false);
    xhr.setRequestHeader('Content-Type', 'application/json');
    try { xhr.send(JSON.stringify(stats)); } catch(e) {}
  }
  window.addEventListener('beforeunload', forceSave);
  document.addEventListener('visibilitychange', function(){ if (document.hidden) forceSave(); });
})();
</script>

<!-- 商城/背包逻辑（完全移植 ddz） -->
<script>
// ========== EMLOG 配置 ==========
window.EMLOG_CONFIG = {
    leaderboardApi: '<?php echo $base_url; ?>?plugin=wx_games&game=plinko'
};
window.WX_PLINKO_USER_SCORE = <?php echo ($current_user ? json_encode(['score'=>$saved_balance]) : 'null'); ?>;
window.WX_PLINKO_EMLOG_CREDITS = <?php echo $current_user ? $emlog_credits : 0; ?>;

// ========== 道具类型映射 ==========
var SHOP_TYPE_NAMES = {
    'title_colored': '昵称变色', 'title_effect': '昵称特效', 'card_back': '牌背皮肤',
    'emoticon': '专属表情', 'bomb_effect': '炸弹特效', 'score_buff': '积分加成卡',
    'title_badge': '称号徽章', 'plinko_skin': '弹珠皮肤', 'plinko_theme': '钉阵主题',
    'plinko_coin_pack': 'H5弹珠币包'
};
var SHOP_TYPE_ICONS = {
    'title_colored': '🎨', 'title_effect': '✨', 'card_back': '🃏', 'emoticon': '😎',
    'bomb_effect': '💥', 'score_buff': '⚡', 'title_badge': '👑',
    'plinko_skin': '🎱', 'plinko_theme': '🌈', 'plinko_coin_pack': '👑'
};

// ========== 反馈弹窗 ==========
function showShopFeedback(icon, title, msg) {
    document.getElementById('shopFeedbackIcon').textContent = icon;
    document.getElementById('shopFeedbackTitle').textContent = title;
    document.getElementById('shopFeedbackMsg').textContent = msg;
    document.getElementById('shopFeedbackModal').classList.remove('hidden');
}

// ========== 商城管理器（移植自 ddz） ==========
var ShopManager = {
    currentFilter: 'all',
    allItems: [],
    show: function() {
        if (!<?php echo $current_user?'true':'false' ?>) { alert('请先登录'); return; }
        document.getElementById('shopModal').classList.remove('hidden');
        var list = document.getElementById('shopItemsList');
        list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
        // 先更新商城顶部余额显示（站点积分 + 弹珠币）
        var ecEl = document.getElementById('shopEmlogCredits');
        var psEl = document.getElementById('shopPlinkoScore');
        if (ecEl) ecEl.textContent = (window.WX_PLINKO_EMLOG_CREDITS || 0);
        if (psEl) psEl.textContent = (window.WX_PLINKO_USER_SCORE && window.WX_PLINKO_USER_SCORE.score != null) ? window.WX_PLINKO_USER_SCORE.score : 0;
        // 拉取最新站点积分（避免后台手动改分后前端不一致）
        var xhr2 = new XMLHttpRequest();
        xhr2.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_my_emlog_credits', true);
        xhr2.onreadystatechange = function() {
            if (xhr2.readyState===4 && xhr2.status===200) {
                try {
                    var r = JSON.parse(xhr2.responseText);
                    if (r.code===0 && r.data && r.data.credits != null) {
                        window.WX_PLINKO_EMLOG_CREDITS = r.data.credits;
                        if (ecEl) ecEl.textContent = r.data.credits;
                    }
                } catch(e) {}
            }
        };
        xhr2.send();
        // 拉商品列表
        var xhr = new XMLHttpRequest();
        xhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_shop_items', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState===4 && xhr.status===200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.code!==0 || !res.data || !res.data.items) {
                        list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">加载商品失败</div>';
                        return;
                    }
                    ShopManager.allItems = res.data.items;
                    ShopManager.currentFilter = 'all';
                    ShopManager.renderFilterBar();
                    ShopManager.renderItems();
                } catch(e) { list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">网络错误，请重试</div>'; }
            }
        };
        xhr.send();
    },
    renderFilterBar: function() {
        var list = document.getElementById('shopItemsList');
        var bar = document.createElement('div');
        bar.id = 'shopFilterBar';
        bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
        var self = this;
        var types = ['all'];
        this.allItems.forEach(function(item) {
            if (types.indexOf(item.item_type)===-1) types.push(item.item_type);
        });
        types.forEach(function(key) {
            var btn = document.createElement('button');
            btn.style.cssText = 'font-size:10px;padding:3px 8px;border-radius:12px;border:none;cursor:pointer;transition:all 0.2s;white-space:nowrap;';
            if (key==='all') {
                btn.textContent = '全部';
                btn.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                btn.style.color = '#fff';
            } else {
                btn.textContent = (SHOP_TYPE_ICONS[key]||'🎁') + ' ' + (SHOP_TYPE_NAMES[key]||key);
                btn.style.background = 'rgba(255,255,255,0.1)';
                btn.style.color = '#ccc';
            }
            btn.dataset.filter = key;
            btn.addEventListener('click', function() {
                self.currentFilter = this.dataset.filter;
                bar.querySelectorAll('button').forEach(function(b) {
                    b.style.background = 'rgba(255,255,255,0.1)';
                    b.style.color = '#ccc';
                });
                this.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                this.style.color = '#fff';
                self.renderItems();
            });
            bar.appendChild(btn);
        });
        var oldBar = document.getElementById('shopFilterBar');
        if (oldBar) oldBar.remove();
        list.parentNode.insertBefore(bar, list);
    },
    renderItems: function() {
        var list = document.getElementById('shopItemsList');
        var filtered = this.currentFilter==='all' ? this.allItems :
            this.allItems.filter(function(item) { return item.item_type===ShopManager.currentFilter; });
        if (filtered.length===0) {
            list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">该分类暂无商品</div>';
            return;
        }
        list.innerHTML = filtered.map(function(item) {
            var hasEmlog = item.price_emlog > 0;
            var hasPlinko = item.price_ddz > 0;
            var priceHtml = '';
            if (hasEmlog && hasPlinko) priceHtml = '站点积分 ' + item.price_emlog + ' + 弹珠币 ' + item.price_ddz;
            else if (hasEmlog) priceHtml = '站点积分 ' + item.price_emlog;
            else if (hasPlinko) priceHtml = '弹珠币 ' + item.price_ddz;
            return '<div class="shop-item">'
                + '<span style="font-size:22px;">' + (item.icon||'🎁') + '</span>'
                + '<div style="flex:1;min-width:0;">'
                + '<div style="font-weight:bold;font-size:13px;">' + item.name
                + (item.is_global ? ' <span style="font-size:9px;color:#fdcb6e;border:1px solid #fdcb6e;border-radius:4px;padding:0 4px;vertical-align:middle;">通用</span>' : '')
                + '</div>'
                + '<div style="font-size:10px;color:#aaa;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + (item.description||item.effect_desc||'') + '</div>'
                + '</div>'
                + '<div style="text-align:right;flex-shrink:0;">'
                + '<div style="font-size:11px;margin-bottom:3px;">' + priceHtml + '</div>'
                + (item.owned
                    ? '<span style="display:inline-block;font-size:10px;padding:2px 8px;background:rgba(46,204,113,0.15);color:#2ecc71;border-radius:8px;border:1px solid #2ecc71;">✓ 已拥有</span>'
                    : '<button class="btn btn-primary shop-buy-btn" style="font-size:10px;padding:2px 8px;" data-id="' + item.id + '" data-emlog="' + item.price_emlog + '" data-plinko="' + item.price_ddz + '">购买</button>')
                + '</div></div>';
        }).join('');
        list.querySelectorAll('.shop-buy-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                ShopManager.buyItem(parseInt(this.dataset.id), parseInt(this.dataset.emlog), parseInt(this.dataset.plinko));
            });
        });
    },
    buyItem: function(itemId, priceEmlog, pricePlinko) {
        if (!<?php echo $current_user?'true':'false' ?>) { alert('请先登录'); return; }
        var payType = '';
        if (priceEmlog > 0 && pricePlinko > 0) {
            if (!confirm('此商品需同时消耗 站点积分 ' + priceEmlog + ' 和 弹珠币 ' + pricePlinko + '，确认购买？')) return;
            payType = 'both';
        } else if (priceEmlog > 0) {
            if (!confirm('确认使用 站点积分 ' + priceEmlog + ' 购买此商品？')) return;
            payType = 'emlog';
        } else if (pricePlinko > 0) {
            if (!confirm('确认使用 弹珠币 ' + pricePlinko + ' 购买此商品？')) return;
            payType = 'plinko';
        } else {
            showShopFeedback('⚠️', '定价异常', '该商品未设置价格');
            return;
        }
        var fd = new FormData();
        fd.append('item_id', itemId);
        fd.append('pay_type', payType);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', EMLOG_CONFIG.leaderboardApi + '&plinko_action=purchase_item', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState===4 && xhr.status===200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.code===0) {
                        // 刷新弹珠币显示
                        var balXhr = new XMLHttpRequest();
                        balXhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_my_rank', true);
                        balXhr.onreadystatechange = function() {
                            if (balXhr.readyState===4 && balXhr.status===200) {
                                try { var r = JSON.parse(balXhr.responseText);
                                    if (r.code===0 && r.data) {
                                        if (typeof _plinkoUpdateBalance === 'function') _plinkoUpdateBalance(r.data.score);
                                        else { var v = Math.round(Number(r.data.score)*10)/10; var t = v%1===0?String(v):v.toFixed(1); var ne=document.getElementById('navBalance'); var ws=document.getElementById('welcomeScore'); if(ne)ne.textContent=t; if(ws)ws.textContent=t; }
                                    }
                                } catch(e) {}
                            }
                        };
                        balXhr.send();
                        // 若是站点积分支付，刷新站点积分显示
                        if (payType === 'emlog' || payType === 'both') {
                            var ecXhr = new XMLHttpRequest();
                            ecXhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_my_emlog_credits', true);
                            ecXhr.onreadystatechange = function() {
                                if (ecXhr.readyState===4 && ecXhr.status===200) {
                                    try {
                                        var rr = JSON.parse(ecXhr.responseText);
                                        if (rr.code===0 && rr.data && rr.data.credits != null) {
                                            window.WX_PLINKO_EMLOG_CREDITS = rr.data.credits;
                                            var ecEl = document.getElementById('shopEmlogCredits');
                                            if (ecEl) ecEl.textContent = rr.data.credits;
                                        }
                                    } catch(e) {}
                                }
                            };
                            ecXhr.send();
                        }
                        showShopFeedback('🎉', '购买成功', data.msg || '积分已扣除，道具已发放到背包');
                    } else {
                        showShopFeedback('❌', '购买失败', data.msg || '未知错误');
                    }
                } catch(e) { showShopFeedback('❌', '网络错误', '请检查网络连接后重试'); }
            }
        };
        xhr.send(fd);
    }
};

// ========== 背包管理器（移植自 ddz） ==========
var InventoryManager = {
    currentFilter: 'all',
    allItems: [],
    show: function() {
        if (!<?php echo $current_user?'true':'false' ?>) { alert('请先登录'); return; }
        document.getElementById('inventoryModal').classList.remove('hidden');
        var list = document.getElementById('inventoryList');
        list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
        var xhr = new XMLHttpRequest();
        xhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_inventory', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState===4 && xhr.status===200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.code!==0 || !res.data || !res.data.items) {
                        list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">加载失败</div>';
                        return;
                    }
                    InventoryManager.allItems = res.data.items;
                    InventoryManager.currentFilter = 'all';
                    InventoryManager.renderFilterBar();
                    InventoryManager.renderItems();
                } catch(e) { list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">网络错误</div>'; }
            }
        };
        xhr.send();
    },
    renderFilterBar: function() {
        var list = document.getElementById('inventoryList');
        var existing = document.getElementById('invFilterBar');
        if (existing) existing.remove();
        var bar = document.createElement('div');
        bar.id = 'invFilterBar';
        bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
        var types = ['all'];
        this.allItems.forEach(function(i) { if (types.indexOf(i.item_type)===-1) types.push(i.item_type); });
        var self = this;
        types.forEach(function(key) {
            var btn = document.createElement('button');
            btn.style.cssText = 'padding:4px 12px;font-size:11px;border:none;border-radius:20px;cursor:pointer;transition:all 0.2s;white-space:nowrap;font-weight:600;';
            if (key==='all') {
                btn.textContent = '全部';
                btn.style.background = 'linear-gradient(135deg,#4a7cf7,#3b82f6)';
                btn.style.color = '#fff';
            } else {
                btn.textContent = (SHOP_TYPE_ICONS[key]||'🎁') + ' ' + (SHOP_TYPE_NAMES[key]||key);
                btn.style.background = 'rgba(255,255,255,0.08)';
                btn.style.color = '#ccc';
                btn.style.border = '1px solid rgba(255,255,255,0.15)';
            }
            btn.dataset.filter = key;
            btn.addEventListener('click', function() {
                self.currentFilter = this.dataset.filter;
                bar.querySelectorAll('button').forEach(function(b) {
                    b.style.background = 'rgba(255,255,255,0.08)';
                    b.style.color = '#ccc';
                    b.style.border = '1px solid rgba(255,255,255,0.15)';
                });
                this.style.background = 'linear-gradient(135deg,#4a7cf7,#3b82f6)';
                this.style.color = '#fff';
                this.style.border = 'none';
                self.renderItems();
            });
            bar.appendChild(btn);
        });
        list.parentNode.insertBefore(bar, list);
    },
    renderItems: function() {
        var list = document.getElementById('inventoryList');
        var filtered = this.currentFilter==='all' ? this.allItems :
            this.allItems.filter(function(i) { return i.item_type===InventoryManager.currentFilter; });
        if (filtered.length===0) {
            list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">该分类暂无道具</div>';
            return;
        }
        var cosmeticTypes = ['title_colored','title_effect','title_badge','card_back','bomb_effect','emoticon','plinko_skin','plinko_theme'];
        list.innerHTML = filtered.map(function(item) {
            var isCosmetic = cosmeticTypes.indexOf(item.item_type)!==-1;
            var btnHtml = '';
            if (item.is_active==1) {
                btnHtml = '<span style="font-size:10px;padding:2px 8px;background:rgba(34,197,94,0.2);color:#22c55e;border-radius:8px;border:1px solid #22c55e;white-space:nowrap;">✓ 已激活</span>';
            } else if (isCosmetic) {
                btnHtml = '<button class="btn btn-primary" style="font-size:11px;padding:4px 12px;" onclick="InventoryManager.useItem(' + (item.inv_id||item.id) + ',this)">🎯 激活</button>';
            } else {
                btnHtml = '<button class="btn btn-primary" style="font-size:11px;padding:4px 12px;" onclick="InventoryManager.useItem(' + (item.inv_id||item.id) + ',this)">使用</button>';
            }
            var globalTag = item.is_global ? ' <span style="font-size:9px;color:#fdcb6e;border:1px solid #fdcb6e;border-radius:4px;padding:0 3px;vertical-align:middle;">通用</span>' : '';
            return '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;margin-bottom:6px;background:rgba(255,255,255,0.06);">'
                + '<span style="font-size:22px;">' + (item.icon||'🎁') + '</span>'
                + '<div style="flex:1;min-width:0;">'
                + '<div style="font-weight:bold;font-size:13px;">' + item.name + globalTag + '</div>'
                + '<div style="font-size:10px;color:#aaa;">剩余 x' + (item.quantity||1) + '</div>'
                + '</div>' + btnHtml + '</div>';
        }).join('');
    },
    useItem: function(invId, btnEl) {
        if (!confirm('确认使用此道具？')) return;
        var fd = new FormData();
        fd.append('inv_id', invId);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', EMLOG_CONFIG.leaderboardApi + '&plinko_action=use_item', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState===4 && xhr.status===200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.code===0) {
                    var payload = data.data || data; // 兼容嵌套/扁平
                    var isCoinPack = payload.item_type === 'plinko_coin_pack';
                        showShopFeedback('✅', isCoinPack ? '兑换成功' : '已激活', payload.msg || '操作成功');
                        InventoryManager.refreshItems();
                        if (typeof loadPlayerEffects === 'function') loadPlayerEffects();
                        if (isCoinPack && payload.new_balance != null) {
                            // 写 plinko_restore + 写 balance（双保险），然后 reload iframe 让游戏重读
                            try {
                                var f = document.getElementById('plinkoFrame');
                                if (f && f.contentWindow) {
                                    f.contentWindow.localStorage.setItem('plinko_restore', JSON.stringify({balance: payload.new_balance}));
                                    f.contentWindow.localStorage.setItem('plinko_balance', String(payload.new_balance));
                                    f.contentWindow.location.reload();
                                }
                            } catch(e) { console.error('[plinko] reload failed:', e); }
                            localStorage.setItem('plinko_balance', String(payload.new_balance));
                            window.WX_PLINKO_USER_SCORE = {score: payload.new_balance};
                            // 更新导航栏 + 欢迎页
                            var navEl = document.getElementById('navBalance');
                            var welEl = document.getElementById('welcomeScore');
                            var val = Math.round(Number(payload.new_balance) * 10) / 10;
                            var txt = val % 1 === 0 ? String(val) : val.toFixed(1);
                            if (navEl) navEl.textContent = txt;
                            if (welEl) welEl.textContent = txt;
                            // 也调全局函数（IIFE 内部版）
                            if (typeof _plinkoUpdateBalance === 'function') {
                                _plinkoUpdateBalance(payload.new_balance);
                            } else {
                            }
                        }
                    } else {
                        console.error('[plinko] useItem failed:', data);
                        showShopFeedback('❌', '使用失败', data.message||'未知错误');
                    }
                } catch(e) {
                    console.error('[plinko] useItem parse error:', e, 'responseText:', xhr.responseText.slice(0,200));
                    showShopFeedback('❌', '网络错误', '请重试');
                }
            } else if (xhr.readyState===4) {
                console.error('[plinko] useItem HTTP error:', xhr.status, xhr.responseText.slice(0,200));
                showShopFeedback('❌', '网络错误', 'HTTP ' + xhr.status);
            }
        };
        xhr.send(fd);
    },
    refreshItems: function() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_inventory', true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState===4 && xhr.status===200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.code===0 && res.data && res.data.items) {
                        InventoryManager.allItems = res.data.items;
                        InventoryManager.renderItems();
                    }
                } catch(e) {}
            }
        };
        xhr.send();
    }
};

// 弹窗按钮绑定
document.getElementById('btnCloseShop').addEventListener('click', function() {
    document.getElementById('shopModal').classList.add('hidden');
});
document.getElementById('btnCloseInventory').addEventListener('click', function() {
    document.getElementById('inventoryModal').classList.add('hidden');
});
document.getElementById('btnShopFeedbackClose').addEventListener('click', function() {
    document.getElementById('shopFeedbackModal').classList.add('hidden');
});
document.getElementById('btnCloseRanking').addEventListener('click', function() {
    document.getElementById('rankingModal').classList.add('hidden');
});
document.getElementById('btnCloseScoreLog').addEventListener('click', function() {
    document.getElementById('scoreLogModal').classList.add('hidden');
});

// ========== 排行榜 ==========
function showRanking() {
    if (!<?php echo $current_user?'true':'false' ?>) { alert('请先登录'); return; }
    document.getElementById('rankingModal').classList.remove('hidden');
    var list = document.getElementById('rankingList');
    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_ranking', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState===4 && xhr.status===200) {
            try {
                var data = JSON.parse(xhr.responseText);
                var ranking = (data.code===0 && data.data && data.data.ranking) ? data.data.ranking : [];
                if (ranking.length===0) {
                    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">暂无排行数据</div>';
                    return;
                }
                function fmtB(n){n=Number(n)||0; return n%1===0?n:n.toFixed(1);}
  list.innerHTML = ranking.map(function(r, i) {
      var rank = i + 1;
      var badge = rank===1 ? '🥇' : rank===2 ? '🥈' : rank===3 ? '🥉' : rank;
      return '<div class="leaderboard-item">'
          + '<span class="rank">' + badge + '</span>'
          + '<span style="flex:1;margin-left:10px;">' + (r.nickname||'未知') + '</span>'
          + '<span style="font-weight:bold;color:#ffd700;">👑 ' + fmtB(r.balance) + '</span>'
          + '</div>';
  }).join('');
            } catch(e) { list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">加载失败</div>'; }
        }
    };
    xhr.send();
}

// ========== 弹珠流水 ==========
function showScoreLog() {
    if (!<?php echo $current_user?'true':'false' ?>) { alert('请先登录'); return; }
    document.getElementById('scoreLogModal').classList.remove('hidden');
    var list = document.getElementById('scoreLogList');
    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_score_log', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState===4 && xhr.status===200) {
            try {
                var data = JSON.parse(xhr.responseText);
                var logs = (data.code===0 && data.data && data.data.logs) ? data.data.logs : [];
                if (logs.length===0) {
                    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">暂无弹珠记录</div>';
                    return;
                }
                list.innerHTML = logs.map(function(l) {
                    var cls = l.profit>=0 ? 'color:#2ecc71;' : 'color:#e74c3c;';
                    var fmtB = function(n){n=Number(n)||0; return n%1===0?n:n.toFixed(1);};
                    return '<div class="leaderboard-item" style="justify-content:space-between;gap:10px;">'
                        + '<span style="font-size:11px;color:#aaa;">' + l.time + '</span>'
                        + '<span style="font-size:13px;">' + l.risk + '·' + l.rows + '行</span>'
                        + '<span style="font-size:11px;">投' + fmtB(l.bet) + '</span>'
                        + '<span style="font-size:12px;">×' + fmtB(l.multiplier) + '</span>'
                        + '<span style="font-weight:bold;' + cls + '">' + (l.profit>=0?'+':'') + fmtB(l.profit) + '</span>'
                        + '</div>';
                }).join('');
            } catch(e) { list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">加载失败</div>'; }
        }
    };
    xhr.send();
}

// ========== 玩家外观效果加载（移植自 ddz） ==========
window.WX_PLINKO_PLAYER_EFFECTS = {};
async function loadPlayerEffects() {
    if (!<?php echo $current_user?'true':'false' ?>) return {};
    try {
        var xhr = new XMLHttpRequest();
        await new Promise(function(resolve, reject) {
            xhr.open('GET', EMLOG_CONFIG.leaderboardApi + '&plinko_action=get_active_effects', true);
            xhr.onreadystatechange = function() { if (xhr.readyState===4) resolve(); };
            xhr.send();
        });
        if (xhr.status!==200) return {};
        var data = JSON.parse(xhr.responseText);
        if (data.code!==0 || !data.data) return {};
        var effects = {};
        data.data.forEach(function(item) {
            try {
                var eff = (typeof item.effect_data==='string') ? JSON.parse(item.effect_data) : item.effect_data;
                if (item.item_type==='title_colored' && eff.color) effects.titleColor = eff.color;
                if (item.item_type==='title_effect' && eff.effect) {
                    effects.titleEffect = eff.effect;
                    if (eff.color) effects.titleEffectColor = eff.color;
                }
                if (item.item_type==='title_badge' && eff.badge) effects.titleBadge = eff.badge;
            } catch(e) {}
        });
        window.WX_PLINKO_PLAYER_EFFECTS = effects;
        // 刷新欢迎页与导航栏昵称
        var nm = '<?php echo $current_user ? addslashes(htmlspecialchars($current_user['nickname'])) : ''; ?>';
        if (nm) {
            document.getElementById('welcomeName').innerHTML = renderPlayerName(nm);
            document.getElementById('navUserName').innerHTML = renderPlayerName(nm);
        }
        return effects;
    } catch(e) { return {}; }
}

function renderPlayerName(name) {
    var e = window.WX_PLINKO_PLAYER_EFFECTS || {};
    var s = '', sfx = '';
    if (e.titleColor) {
        s += 'color:' + e.titleColor + ';';
    } else {
        // 默认暖色渐变
        s += 'background:linear-gradient(135deg,#fdcb6e,#e17055);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;';
    }
    if (e.titleEffect==='glow') {
        var gc = e.titleEffectColor||'gold';
        s += 'text-shadow:0 0 10px ' + gc + ',0 0 20px ' + gc + ';';
    }
    if (e.titleBadge) {
        sfx = ' <span style="font-size:10px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;padding:1px 6px;border-radius:8px;white-space:nowrap;">' + e.titleBadge + '</span>';
    }
    return (s||sfx) ? '<span style="' + s + '">' + name + '</span>' + sfx : name;
}

// 页面加载时获取效果
loadPlayerEffects();
</script>
</body>
</html>
