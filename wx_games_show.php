<?php
/**
 * wx_games 前台页面 - 游戏大厅入口
 * ?plugin=wx_games → 选择游戏
 * ?plugin=wx_games&game=ddz → 斗地主
 * ?plugin=wx_games&game=mj → 麻将
 */
!defined('EMLOG_ROOT') && exit('access denied!');

require_once __DIR__ . '/wx_games.php';

$game = isset($_GET['game']) ? preg_replace('/[^a-z_]/', '', $_GET['game']) : '';

// 检查游戏是否启用
if (!empty($game) && !wx_games_is_enabled($game)) {
    header('Location: ' . BLOG_URL . '?plugin=wx_games');
    exit;
}

// ===== 具体游戏页 =====
if ($game === 'ddz') {
    require_once __DIR__ . '/wx_games_ddz_fn.php';
    require_once __DIR__ . '/wx_games_ddz_show.php';
    exit;
}

if ($game === 'mj') {
    require_once __DIR__ . '/wx_games_mojang_fn.php';
    require_once __DIR__ . '/wx_games_mojang_show.php';
    exit;
}

if ($game === 'niuniu') {
    require_once __DIR__ . '/wx_games_niuniu_fn.php';
    require_once __DIR__ . '/wx_games_niuniu_show.php';
    exit;
}

if ($game === 'plinko') {
    require_once __DIR__ . '/wx_games_plinko_fn.php';
    require_once __DIR__ . '/wx_games_plinko_show.php';
    exit;
}

// ===== 游戏大厅页 =====
$user = wx_games_check_user();
$list = wx_games_get_list();
$base_url = BLOG_URL;

// 读取各游戏后台公告，优先作为首页卡片描述
$game_desc = [];
$storage_map = ['ddz' => 'wx_ddz', 'mj' => 'wx_mojang', 'niuniu' => 'wx_niuniu'];
foreach ($list as $key => $g) {
    $storage_key = $storage_map[$key] ?? 'wx_' . $key;
    $storage = Storage::getInstance($storage_key);
    // 公告优先从 config 数组中读取，兼容旧版单字段
    $config = $storage->getValue('config');
    $notice = '';
    if (is_array($config) && !empty($config['notice'])) {
        $notice = $config['notice'];
    } elseif ($storage->getValue('notice')) {
        $notice = $storage->getValue('notice');
    }
    $game_desc[$key] = !empty($notice) ? $notice : $g['desc'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>棋牌大厅 - <?php echo defined('BLOG_NAME') ? BLOG_NAME : '20090729.CN'; ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
     background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);min-height:100vh;color:#fff}
.hub-header{text-align:center;padding:30px 20px 12px}
.hub-header h1{font-size:2rem;margin-bottom:8px;font-weight:800}
.hub-header h1 .emoji{display:inline-block;font-size:2rem;vertical-align:middle;margin-right:6px;-webkit-text-fill-color:initial;background:none}
.hub-header h1 .title{background:linear-gradient(135deg,#e17055,#fdcb6e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.hub-header p{color:rgba(255,255,255,0.6);font-size:0.95rem}
.hub-nav{display:flex;align-items:center;justify-content:space-between;max-width:800px;margin:0 auto 16px;padding:10px 20px;background:rgba(255,255,255,0.05);border-radius:12px;font-size:0.9rem}
.hub-nav-left{display:flex;align-items:center;gap:10px}
.hub-nav-avatar{width:34px;height:34px;border-radius:50%;border:2px solid #fdcb6e;object-fit:cover}
.hub-nav-user{color:#fdcb6e;font-weight:600}
.hub-nav-right{display:flex;align-items:center;gap:14px}
.hub-nav-link{color:rgba(255,255,255,0.7);text-decoration:none;font-size:0.85rem;padding:4px 10px;border-radius:8px;transition:background .2s}
.hub-nav-link:hover{background:rgba(255,255,255,0.1);color:#fff}
.hub-nav-stat{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:rgba(225,112,85,0.15);border-radius:8px;color:#fdcb6e;font-size:0.8rem;font-weight:600}
.game-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
           gap:20px;max-width:800px;margin:0 auto;padding:0 20px 60px}
.game-card{background:rgba(255,255,255,0.08);backdrop-filter:blur(10px);
           border-radius:16px;padding:28px;cursor:pointer;
           transition:transform 0.2s,box-shadow 0.2s;border:1px solid rgba(255,255,255,0.1)}
.game-card:hover{transform:translateY(-4px);box-shadow:0 8px 30px rgba(0,0,0,0.3);
                 border-color:#e17055}
.game-card .icon{font-size:2.5rem;margin-bottom:12px;color:#fdcb6e;font-family:"Apple Color Emoji","Segoe UI Emoji","Noto Color Emoji","Twemoji Mozilla","EmojiOne Color",sans-serif;line-height:1}
.game-card h3{font-size:1.2rem;margin-bottom:6px;color:#fdcb6e}
.game-card p{color:rgba(255,255,255,0.6);font-size:0.85rem;line-height:1.5;margin-bottom:14px;max-height:60px;overflow:hidden}
.game-card .stats{display:flex;gap:12px;margin-bottom:14px;padding:8px 0;border-top:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08)}
.game-card .stat-item{flex:1;text-align:center}
.game-card .stat-item .num{font-size:1rem;font-weight:700;color:#fdcb6e;display:block}
.game-card .stat-item .lbl{font-size:0.7rem;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:.5px}
.game-card .stats-none{font-size:0.8rem;color:rgba(255,255,255,0.4);text-align:center;padding:10px 0;margin-bottom:14px;border-top:1px solid rgba(255,255,255,0.08);border-bottom:1px solid rgba(255,255,255,0.08)}
.game-card .btn-play{display:inline-block;padding:8px 28px;border-radius:8px;
                      background:linear-gradient(135deg,#e17055,#fdcb6e);color:#1a1a2e;
                      font-weight:600;text-decoration:none;font-size:0.9rem;
                      transition:opacity 0.2s}
.game-card .btn-play:hover{opacity:0.9}
.hub-footer{text-align:center;padding:20px;color:rgba(255,255,255,0.3);font-size:0.8rem}
.hub-footer a{color:#e17055;text-decoration:none}
.hub-footer a:hover{text-decoration:underline}
@media(max-width:600px){.hub-header h1{font-size:1.5rem}.game-grid{grid-template-columns:1fr;padding:0 16px 40px}.game-card{padding:20px}.hub-nav{flex-direction:column;gap:8px}}

/* ====== 播放器开关 ====== */
.player-toggle-wrap{display:inline-flex;align-items:center;gap:6px;margin-left:12px;cursor:pointer;vertical-align:middle;user-select:none}
.player-toggle-label{font-size:0.8rem;color:rgba(255,255,255,0.4);transition:color 0.3s}
.player-toggle-label.on{color:#55efc4}
.player-switch{position:relative;display:inline-block;width:36px;height:20px;flex-shrink:0}
.player-switch input{opacity:0;width:0;height:0}
.player-slider{position:absolute;cursor:pointer;inset:0;background:rgba(255,255,255,0.15);border-radius:20px;transition:0.3s}
.player-slider::before{content:"";position:absolute;width:16px;height:16px;left:2px;top:2px;background:#fff;border-radius:50%;transition:0.3s}
.player-switch input:checked+.player-slider{background:linear-gradient(135deg,#00b894,#55efc4)}
.player-switch input:checked+.player-slider::before{transform:translateX(16px)}
@media(max-width:600px){.hub-header h1{font-size:1.5rem}.game-grid{grid-template-columns:1fr;padding:0 16px 40px}.game-card{padding:20px}}
</style>
</head>
<body>
<div class="hub-header">
    <h1><span class="emoji">🎮</span><span class="title">棋牌大厅</span></h1>
    <p>选择一款游戏开始对战</p>
</div>
<?php if ($user):
    // 计算各游戏个人数据
    $db = Database::getInstance();
    $table_scores = DB_PREFIX . 'wx_games_scores';
    $table_plinko = DB_PREFIX . 'wx_plinko_accounts';
    $user_stats = [];
    foreach (array_keys($list) as $k) {
        if ($k === 'plinko') {
            $row = $db->once_fetch_array("SELECT `balance` FROM `$table_plinko` WHERE `uid` = " . intval($user['uid']) . " LIMIT 1");
            $user_stats[$k] = $row ? ['score' => floatval($row['balance']), 'win' => 0, 'total' => 0] : null;
        } else {
            $row = $db->once_fetch_array("SELECT * FROM `$table_scores` WHERE `uid` = " . intval($user['uid']) . " AND `game` = '$k' LIMIT 1");
            $user_stats[$k] = $row ? ['score' => intval($row['score']), 'wins' => intval($row['wins']), 'total' => intval($row['total_games'])] : null;
        }
    }
    // 站点积分
    $um = new User_Model();
    $um_row = $um->getOneUser(intval($user['uid']));
    $site_credits = $um_row ? intval($um_row['credits']) : 0;
?>
<div class="hub-nav">
    <div class="hub-nav-left">
        <?php $uavatar = wx_games_resolve_avatar($user) ?: ''; ?>
        <?php if ($uavatar): ?>
            <img class="hub-nav-avatar" src="<?= htmlspecialchars($uavatar) ?>" alt="">
        <?php else: ?>
            <span class="hub-nav-avatar" style="display:flex;align-items:center;justify-content:center;background:rgba(253,203,110,0.2);font-size:18px">👤</span>
        <?php endif; ?>
        <span class="hub-nav-user"><?= htmlspecialchars($user['nickname']) ?></span>
        <span class="hub-nav-stat">💎 <?= $site_credits ?></span>
    </div>
    <div class="hub-nav-right">
        <a class="hub-nav-link" href="<?= $base_url ?>admin/account.php?action=signin">👤 个人中心</a>
        <a class="hub-nav-link" href="<?= $base_url ?>">🏠 返回首页</a>
    </div>
</div>
<?php endif; ?>
<div class="game-grid">
<?php foreach ($list as $key => $g):
    $stat = $user ? ($user_stats[$key] ?? null) : null;
?>
    <div class="game-card" onclick="location.href='?plugin=wx_games&game=<?= $key ?>'">
        <div class="icon"><?= $g['icon'] ?></div>
        <h3><?= htmlspecialchars($g['name']) ?></h3>
        <p><?= nl2br(htmlspecialchars($game_desc[$key])) ?></p>
        <?php if ($stat): ?>
        <div class="stats">
            <div class="stat-item"><span class="num"><?= $stat['score'] ?></span><span class="lbl">积分</span></div>
            <?php if ($key === 'plinko'): ?>
            <div class="stat-item"><span class="num">💎</span><span class="lbl">弹珠币</span></div>
            <?php else: ?>
            <div class="stat-item"><span class="num"><?= $stat['wins'] ?></span><span class="lbl">胜</span></div>
            <div class="stat-item"><span class="num"><?= $stat['total'] ?></span><span class="lbl">总</span></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="stats-none">登录后查看战绩</div>
        <?php endif; ?>
        <a class="btn-play" href="?plugin=wx_games&game=<?= $key ?>">开始游戏</a>
    </div>
<?php endforeach; ?>
</div>
<div class="hub-footer">
    <a href="<?= $base_url ?>">返回首页</a> · 棋牌大厅 v1.0.0
    <label class="player-toggle-wrap" id="playerToggleWrap">
        <span class="player-toggle-label" id="playerToggleLabel">🎵 播放器</span>
        <span class="player-switch">
            <input type="checkbox" id="playerCheckbox" onchange="togglePlayer()">
            <span class="player-slider"></span>
        </span>
    </label>
</div>
<script>
// ====== 播放器管理 ======
(function(){
    const STORAGE_KEY = 'wx_games_player_on';
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved === '1') {
        document.getElementById('playerCheckbox').checked = true;
        loadPlayer();
    }
})();
function loadPlayer() {
    if (document.getElementById('myhk')) return;
    var s1 = document.createElement('script');
    s1.type = 'text/javascript';
    s1.id = 'myhk';
    s1.src = 'https://myhkw.cn/api/player/1733906404100';
    s1.setAttribute('key','1733906404100');
    s1.setAttribute('m','1');
    document.body.appendChild(s1);
    if (!document.querySelector('script[src*="myhkw.cn/player/js/jquery"]')) {
        var s2 = document.createElement('script');
        s2.type = 'text/javascript';
        s2.src = 'https://myhkw.cn/player/js/jquery.min.js';
        document.body.appendChild(s2);
    }
    document.getElementById('playerToggleLabel').textContent = '🎵 已开启';
    document.getElementById('playerToggleLabel').className = 'player-toggle-label on';
}
function unloadPlayer() {
    var el = document.getElementById('myhk');
    if (el) el.remove();
    document.getElementById('playerToggleLabel').textContent = '🎵 播放器';
    document.getElementById('playerToggleLabel').className = 'player-toggle-label';
}
function togglePlayer() {
    var cb = document.getElementById('playerCheckbox');
    if (cb.checked) {
        loadPlayer();
        localStorage.setItem('wx_games_player_on', '1');
    } else {
        unloadPlayer();
        localStorage.setItem('wx_games_player_on', '0');
    }
}
</script>
</body>
</html>
