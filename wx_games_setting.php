<?php
/**
 * wx_games 后台设置页
 * 关键：admin 文件在顶层作用域加载，确保 global 变量可访问
 */
!defined('EMLOG_ROOT') && exit('access denied!');

require_once __DIR__ . '/wx_games.php';

global $wx_games_list;

$wxg_game = isset($_GET['game']) ? preg_replace('/[^a-z_]/', '', $_GET['game']) : '';

// ============================================================
// 顶层加载 admin 文件（在 plugin_setting_view() 之前）
// ============================================================
if ($wxg_game === 'shop') {
    require_once __DIR__ . '/wx_games_shop_admin.php';
} elseif ($wxg_game === 'ddz') {
    require_once __DIR__ . '/wx_games_ddz_fn.php';
    require_once __DIR__ . '/wx_games_ddz_admin.php';
} elseif ($wxg_game === 'mj') {
    require_once __DIR__ . '/wx_games_mojang_fn.php';
    require_once __DIR__ . '/wx_games_mojang_admin.php';
} elseif ($wxg_game === 'niuniu') {
    require_once __DIR__ . '/wx_games_niuniu_fn.php';
    require_once __DIR__ . '/wx_games_niuniu_admin.php';
} elseif ($wxg_game === 'plinko') {
    require_once __DIR__ . '/wx_games_plinko_fn.php';
    require_once __DIR__ . '/wx_games_plinko_admin.php';
}

// ============================================================
// 后台视图
// ============================================================
function plugin_setting_view() {
    global $wx_games_list;
    $game = isset($_GET['game']) ? preg_replace('/[^a-z_]/', '', $_GET['game']) : '';
    $base_url = BLOG_URL . 'admin/plugin.php?plugin=wx_games';
    echo '<!-- [PLUGIN_VIEW] game=' . htmlspecialchars($game) . ' -->';
?>
<link rel="stylesheet" href="<?= BLOG_URL ?>content/plugins/wx_games/css/admin.css">
<style>
.wxg-nav{margin-bottom:20px;display:flex;gap:0;border-bottom:2px solid #eee;padding:0}
.wxg-nav a{display:inline-block;padding:10px 22px;text-decoration:none;font-size:0.95rem;color:#666;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s}
.wxg-nav a:hover{color:#e17055}
.wxg-nav a.active{color:#e17055;border-bottom-color:#e17055;font-weight:600}
/* 统一下拉框样式（覆盖 Emlog 主题默认 padding） */
.wx-card select.form-control,
.wx-card input.form-control,
.wx-card textarea.form-control,
select.form-control{padding:5px 8px;min-width:0}
</style>
<div class="wxg-nav">
    <a href="<?= $base_url ?>" class="<?= empty($game) ? 'active' : '' ?>">🏠 大厅管理</a>
    <a href="<?= $base_url ?>&game=shop" class="<?= $game === 'shop' ? 'active' : '' ?>">🛒 商城</a>
    <?php foreach ($wx_games_list as $key => $g): ?>
    <a href="<?= $base_url ?>&game=<?= $key ?>" class="<?= $game === $key ? 'active' : '' ?>">
        <?= htmlspecialchars($g['icon'] . ' ' . $g['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php
    if ($game === 'shop') {
        if (function_exists('wx_shop_admin_render')) {
            wx_shop_admin_render();
        } else {
            echo '<div class="alert alert-danger">shop admin 加载失败</div>';
        }
    } elseif ($game === 'ddz') {
        // admin 文件已在顶层加载（fn.php + admin.php）
        // 函数和 global 变量已就绪
        if (function_exists('wx_ddz_admin_render')) {
            wx_ddz_admin_render();
        } else {
            echo '<div class="alert alert-danger">ddz admin 加载失败</div>';
        }
    } elseif ($game === 'mj') {
        if (function_exists('wx_mojang_admin_render')) {
            wx_mojang_admin_render();
        } else {
            echo '<div class="alert alert-danger">mojang admin 加载失败</div>';
        }
    } elseif ($game === 'niuniu') {
        if (function_exists('wx_niuniu_admin_render')) {
            wx_niuniu_admin_render();
        } else {
            echo '<div class="alert alert-danger">niuniu admin 加载失败</div>';
        }
    } elseif ($game === 'plinko') {
        // 确保辅助文件已加载（兼容 emlog 未执行顶层 require_once 的情况）
        if (!function_exists('wx_admin_score_tab_html')) {
            require_once __DIR__ . '/wx_games_plinko_fn.php';
            require_once __DIR__ . '/wx_games_admin_helper.php';
        }
        if (function_exists('wx_plinko_admin_render')) {
            wx_plinko_admin_render();
        } else {
            // 兜底加载
            require_once __DIR__ . '/wx_games_plinko_fn.php';
            require_once __DIR__ . '/wx_games_plinko_admin.php';
            if (function_exists('wx_plinko_admin_render')) {
                wx_plinko_admin_render();
            } else {
                echo '<div class="alert alert-danger">plinko admin 加载失败（render 函数不存在）</div>';
            }
        }
    } else {
        wxg_hub_view();
    }
}

// ============================================================
// 大厅管理页（带游戏开关 + 数据看板）
// ============================================================
function wxg_hub_view() {
    global $wx_games_list;
    $list = $wx_games_list;
    $storage = Storage::getInstance('wx_games');
    $game_status = $storage->getValue('game_status');
    if (!is_array($game_status)) {
        $game_status = ['ddz' => '1', 'mj' => '1', 'niuniu' => '1'];
    }

    // 处理开关 POST
    if (Input::postStrVar('wxg_action') === 'toggle_game') {
        $gk = addslashes(trim(Input::postStrVar('game_key', '')));
        if (isset($list[$gk])) {
            $game_status[$gk] = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? '1' : '0';
            $storage->setValue('game_status', $game_status, 'array');
            emMsg('设置已保存', './plugin.php?plugin=wx_games');
        }
    }

    $db = Database::getInstance();
    $now = time();
    $yesterday = $now - 86400;
    $week_ago = $now - 604800;
?>
<style>
.wxg-admin{max-width:960px;margin:0 auto}
.wxg-admin .card{border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.08);margin-bottom:22px;overflow:hidden}
.wxg-admin .card-header{background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;padding:14px 20px;font-weight:600;font-size:1rem}
.wxg-admin .card-body{padding:20px}
.game-entry{padding:16px 0;border-bottom:1px solid #eee}
.game-entry:last-child{border-bottom:none}
.game-top{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.game-info{display:flex;align-items:center;gap:14px}
.game-icon{font-size:2rem;width:48px;text-align:center}
.game-name{font-weight:700;font-size:1.05rem}
.game-desc{color:#888;font-size:0.82rem;margin-top:2px}
.game-actions{display:flex;gap:8px;align-items:center}
.game-actions a{padding:6px 16px;border-radius:6px;text-decoration:none;font-size:0.82rem;color:#fff;transition:opacity .2s}
.game-actions a:hover{opacity:.85}
.btn-settings{background:#e17055}
.btn-play{background:#fdcb6e;color:#333!important}

/* 开关 */
.switch-wrap{display:flex;align-items:center;gap:6px;font-size:0.82rem;color:#666}
.switch{position:relative;display:inline-block;width:40px;height:22px;margin:0}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:22px;transition:.3s}
.slider:before{position:absolute;content:"";height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.switch input:checked + .slider{background:#e17055}
.switch input:checked + .slider:before{transform:translateX(18px)}
.switch input:disabled + .slider{opacity:.4;cursor:not-allowed}

/* 数据指标 */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-top:14px}
.stat-item{background:#f8f9fa;border-radius:8px;padding:12px;text-align:center}
.stat-value{font-size:1.3rem;font-weight:700;color:#e17055}
.stat-label{font-size:0.75rem;color:#888;margin-top:2px}
.stat-off .stat-value{color:#aaa}

/* 总览条 */
.overview-bar{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:18px}
.ov-item{flex:1;min-width:140px;background:linear-gradient(135deg,#e17055,#fdcb6e);border-radius:10px;padding:14px 18px;color:#fff;text-align:center}
.ov-value{font-size:1.5rem;font-weight:700}
.ov-label{font-size:0.78rem;opacity:.85;margin-top:2px}
</style>

<div class="wxg-admin">
    <!-- 总览条 -->
    <div class="overview-bar">
        <?php
        $total_players = 0; $total_games_all = 0;
        foreach ($list as $key => $g):
            $scores_table = DB_PREFIX . 'wx_games_scores';
            $games_table = ($key === 'ddz') ? DB_PREFIX . 'wx_ddz_games' : (($key === 'mj') ? DB_PREFIX . 'wx_mojang_games' : DB_PREFIX . 'wx_niuniu_games');
            try {
                $r = $db->once_fetch_array("SELECT COUNT(*) AS c, COALESCE(SUM(total_games),0) AS tg FROM `$scores_table` WHERE `is_ai` = 0");
                $total_players += (int)($r['c'] ?? 0);
                $total_games_all += (int)($r['tg'] ?? 0);
            } catch (\Throwable $e) {}
        endforeach;
        ?>
        <div class="ov-item"><div class="ov-value"><?= $total_players ?></div><div class="ov-label">注册玩家</div></div>
        <div class="ov-item"><div class="ov-value"><?= $total_games_all ?></div><div class="ov-label">总对局数</div></div>
        <div class="ov-item"><div class="ov-value"><?= count($list) ?></div><div class="ov-label">已集成游戏</div></div>
    </div>

    <!-- 游戏管理 -->
    <div class="card">
        <div class="card-header">🎮 游戏管理</div>
        <div class="card-body">
            <p style="color:#999;font-size:0.82rem;margin-bottom:12px">关闭的游戏将不在前台大厅显示，已有数据不受影响</p>
            <?php foreach ($list as $key => $g):
                $enabled = isset($game_status[$key]) ? $game_status[$key] : '1';
            $scores_table = DB_PREFIX . 'wx_games_scores';
            $games_table = ($key === 'ddz') ? DB_PREFIX . 'wx_ddz_games' : (($key === 'mj') ? DB_PREFIX . 'wx_mojang_games' : DB_PREFIX . 'wx_niuniu_games');

                // 查基础数据
                $players = 0; $total_games = 0; $max_score = 0; $today_active = 0; $week_active = 0;
                try {
                    $r = $db->once_fetch_array("SELECT COUNT(*) AS c, COALESCE(SUM(total_games),0) AS tg, COALESCE(MAX(score),0) AS ms FROM `$scores_table` WHERE `game` = '$key' AND `is_ai` = 0");
                    $players = (int)($r['c'] ?? 0);
                    $total_games = (int)($r['tg'] ?? 0);
                    $max_score = (int)($r['ms'] ?? 0);
                } catch (\Throwable $e) {}
                try {
                    if ($key === 'ddz' || $key === 'niuniu' || $key === 'plinko') {
                        $r = $db->once_fetch_array("SELECT COUNT(*) AS c FROM `$games_table` WHERE `created_at` > $yesterday");
                        $today_active = (int)($r['c'] ?? 0);
                        $r = $db->once_fetch_array("SELECT COUNT(*) AS c FROM `$games_table` WHERE `created_at` > $week_ago");
                        $week_active = (int)($r['c'] ?? 0);
                    } else {
                        $r = $db->once_fetch_array("SELECT COUNT(*) AS c FROM `$games_table` WHERE `created` > FROM_UNIXTIME($yesterday)");
                        $today_active = (int)($r['c'] ?? 0);
                        $r = $db->once_fetch_array("SELECT COUNT(*) AS c FROM `$games_table` WHERE `created` > FROM_UNIXTIME($week_ago)");
                        $week_active = (int)($r['c'] ?? 0);
                    }
                } catch (\Throwable $e) {}
            ?>
            <div class="game-entry">
                <div class="game-top">
                    <div class="game-info">
                        <div class="game-icon"><?= $g['icon'] ?></div>
                        <div>
                            <div class="game-name"><?= htmlspecialchars($g['name']) ?></div>
                            <div class="game-desc"><?= htmlspecialchars($g['desc']) ?></div>
                        </div>
                    </div>
                    <div class="game-actions">
                        <form method="post" style="display:inline-flex;align-items:center;gap:8px">
                            <input type="hidden" name="wxg_action" value="toggle_game">
                            <input type="hidden" name="game_key" value="<?= $key ?>">
                            <label class="switch-wrap">
                                <label class="switch">
                                    <input type="checkbox" name="enabled" value="1" <?= $enabled === '1' ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="slider"></span>
                                </label>
                                <span><?= $enabled === '1' ? '已开启' : '已关闭' ?></span>
                            </label>
                        </form>
                        <a href="<?= BLOG_URL ?>admin/plugin.php?plugin=wx_games&game=<?= $key ?>" class="btn-settings">设置</a>
                    </div>
                </div>
                <!-- 数据指标 -->
                <div class="stats-grid <?= $enabled !== '1' ? 'stat-off' : '' ?>">
                    <div class="stat-item"><div class="stat-value"><?= $players ?></div><div class="stat-label">玩家数</div></div>
                    <div class="stat-item"><div class="stat-value"><?= $total_games ?></div><div class="stat-label">总对局</div></div>
                    <div class="stat-item"><div class="stat-value"><?= $max_score ?></div><div class="stat-label">最高分</div></div>
                    <div class="stat-item"><div class="stat-value"><?= $week_active ?></div><div class="stat-label">本周对局</div></div>
                    <div class="stat-item"><div class="stat-value"><?= $today_active ?></div><div class="stat-label">今日对局</div></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
}
