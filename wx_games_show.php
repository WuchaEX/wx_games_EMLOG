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

// ===== 游戏大厅页 =====
$user = wx_games_check_user();
$list = wx_games_get_list();
$base_url = BLOG_URL;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>棋牌大厅</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
     background:linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);min-height:100vh;color:#fff}
.hub-header{text-align:center;padding:40px 20px 20px}
.hub-header h1{font-size:2rem;background:linear-gradient(135deg,#e17055,#fdcb6e);
               -webkit-background-clip:text;-webkit-text-fill-color:transparent;
               background-clip:text;margin-bottom:8px}
.hub-header p{color:rgba(255,255,255,0.6);font-size:0.95rem}
.hub-user{text-align:center;margin:8px 0 24px;font-size:0.9rem;color:rgba(255,255,255,0.7)}
.hub-user a{color:#fdcb6e;text-decoration:none}
.hub-user a:hover{text-decoration:underline}
.game-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
           gap:20px;max-width:800px;margin:0 auto;padding:0 20px 60px}
.game-card{background:rgba(255,255,255,0.08);backdrop-filter:blur(10px);
           border-radius:16px;padding:28px;cursor:pointer;
           transition:transform 0.2s,box-shadow 0.2s;border:1px solid rgba(255,255,255,0.1)}
.game-card:hover{transform:translateY(-4px);box-shadow:0 8px 30px rgba(0,0,0,0.3);
                 border-color:#e17055}
.game-card .icon{font-size:2.5rem;margin-bottom:12px}
.game-card h3{font-size:1.2rem;margin-bottom:6px;color:#fdcb6e}
.game-card p{color:rgba(255,255,255,0.6);font-size:0.85rem;line-height:1.5;margin-bottom:16px}
.game-card .btn-play{display:inline-block;padding:8px 28px;border-radius:8px;
                      background:linear-gradient(135deg,#e17055,#fdcb6e);color:#1a1a2e;
                      font-weight:600;text-decoration:none;font-size:0.9rem;
                      transition:opacity 0.2s}
.game-card .btn-play:hover{opacity:0.9}
.hub-footer{text-align:center;padding:20px;color:rgba(255,255,255,0.3);font-size:0.8rem}
.hub-footer a{color:#e17055;text-decoration:none}
.hub-footer a:hover{text-decoration:underline}
@media(max-width:600px){.hub-header h1{font-size:1.5rem}.game-grid{grid-template-columns:1fr;padding:0 16px 40px}.game-card{padding:20px}}
</style>
</head>
<body>
<div class="hub-header">
    <h1>🎮 棋牌大厅</h1>
    <p>选择一款游戏开始对战</p>
</div>
<div class="hub-user">
    <?php if ($user): ?>
        👋 欢迎回来，<?= htmlspecialchars($user['nickname']) ?>
    <?php else: ?>
        <a href="<?= $base_url ?>admin/account.php?action=signin">登录</a>后可保存战绩
    <?php endif; ?>
</div>
<div class="game-grid">
<?php foreach ($list as $key => $g): ?>
    <div class="game-card" onclick="location.href='?plugin=wx_games&game=<?= $key ?>'">
        <div class="icon"><?= $g['icon'] ?></div>
        <h3><?= htmlspecialchars($g['name']) ?></h3>
        <p><?= htmlspecialchars($g['desc']) ?></p>
        <a class="btn-play" href="?plugin=wx_games&game=<?= $key ?>">开始游戏</a>
    </div>
<?php endforeach; ?>
</div>
<div class="hub-footer">
    <a href="<?= $base_url ?>">返回首页</a> · 棋牌大厅 v1.0.0
</div>
</body>
</html>
