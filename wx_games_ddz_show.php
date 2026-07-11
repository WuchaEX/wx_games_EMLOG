<?php
!defined('EMLOG_ROOT') && exit('access denied!');

// 函数定义已在 wx_games_show.php 中加载，此处不再 require
// AJAX 请求已在 wx_games.php 中被拦截处理并 exit

$plugin_url = wx_ddz_get_plugin_url();
$config = wx_ddz_get_config();
$current_user = wx_ddz_check_user();

// 获取当前用户的积分数据（从数据库）
$user_score_data = null;
if ($current_user) {
    $user_score_data = wx_ddz_get_user_score($current_user['uid']);
}

// 获取当前emlog站点信息供JS使用
$base_url = BLOG_URL;
$login_url = $base_url . 'admin/account.php?action=signin';

// ========== 防逃跑：检查未完成游戏，发现即惩罚 ==========
$pending_game_warning = null;
$penalty_message = null;
$db_check = null;
if ($current_user) {
    $db_check = Database::getInstance();
    $table_games = DB_PREFIX . 'wx_ddz_games';
    $check_uid = intval($current_user['uid']);
    $pending_row = $db_check->once_fetch_array(
        "SELECT `id`, `created_at` FROM `$table_games` 
         WHERE `uid` = $check_uid AND `status` = 1 
         ORDER BY `id` DESC LIMIT 1"
    );

    if ($pending_row) {
        // 有未完成的游戏 → 立即惩罚
        $penalty_mul = isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0;
        $penalty = intval(-100 * $penalty_mul);
        $now = time();
        $db_check->query("UPDATE `$table_games` SET 
            `result` = 'lose', `score_change` = $penalty, `status` = 0, `finished_at` = $now
            WHERE `id` = " . intval($pending_row['id']));
        wx_ddz_apply_penalty($current_user['uid'], $penalty);
        $penalty_message = '检测到你上一局中途退出，已扣除 ' . abs($penalty) . ' 积分';
        $user_score_data = wx_ddz_get_user_score($current_user['uid']);
    }

    // 注意：不再在页面加载时创建 pending 记录
    // 改为在 JS 中用户点击"开始游戏"时通过 wxddz_signal=start 创建
}

// 获取积分流水日志（用于弹窗显示）
$score_logs = [];
$emlog_credits = 0;
if ($current_user) {
    $table_logs = DB_PREFIX . 'wx_ddz_logs';
    $log_uid = intval($current_user['uid']);
    $log_result = $db_check->query(
        "SELECT `score_change`, `score_before`, `score_after`, `reason`, `created_at`
         FROM `$table_logs` WHERE `uid` = $log_uid
         ORDER BY `created_at` DESC LIMIT 50"
    );
    if ($log_result) {
        while ($log_row = $db_check->fetch_array($log_result)) {
            $score_logs[] = $log_row;
        }
    }
    // 获取 Emlog 积分
    try {
        $userModel = new User_Model();
        $emlog_user = $userModel->getOneUser($current_user['uid']);
        if ($emlog_user && isset($emlog_user['credits'])) {
            $emlog_credits = intval($emlog_user['credits']);
        }
    } catch (Throwable $e) {}
}
// ========== 防逃跑检查结束 ==========
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($config['title']); ?></title>
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>css/style.css?v=2.0.4">
<body>
    <!-- 粘性导航栏（仿 wx_medal / wx_lottery 统一风格） -->
    <nav class="ddz-nav" id="ddzNav">
        <div class="ddz-nav-inner">
            <div class="ddz-nav-left">
                <span class="ddz-nav-icon">🃏</span>
                <h1 class="ddz-nav-title"><?php echo htmlspecialchars($config['title']); ?></h1>
            </div>
            <div class="ddz-nav-right" id="navRight">
                <div class="nav-user-info hidden" id="navUserInfo">
                    <img class="nav-avatar" id="userAvatar" src="" alt="">
                    <span class="nav-user-name" id="userName"></span>
                </div>
                <div class="nav-score hidden" id="navScoreBox" title="点击查看积分流水">
                    <span class="nav-score-label">积分:</span>
                    <span class="nav-score-value" id="playerScore">0</span>
                </div>
                <button class="nav-btn hidden" id="btnLeaderboard">
                    <span class="nav-btn-icon">🏆</span>
                    <span class="nav-btn-text">排行</span>
                </button>
                <button class="nav-btn hidden" id="btnNewGame">
                    <span class="nav-btn-icon">🔄</span>
                    <span class="nav-btn-text">新游戏</span>
                </button>
                <button class="nav-btn hidden" id="btnShop">
                    <span class="nav-btn-icon">🛒</span>
                    <span class="nav-btn-text">商城</span>
                </button>
                <button class="nav-btn hidden" id="btnInventory">
                    <span class="nav-btn-icon">🎒</span>
                    <span class="nav-btn-text">背包</span>
                </button>
                <a href="<?php echo BLOG_URL; ?>" class="nav-home-btn" id="navHomeBtn">返回首页</a>
            </div>
        </div>
    </nav>

    <!-- 登录/欢迎界面 -->
    <div class="login-screen" id="loginScreen">
        <div class="login-container">
            <div class="login-subtitle" id="welcomeSubtitle">🃏 欢迎来到斗地主</div>

            <div id="loggedInPanel" class="hidden">
                <div class="welcome-user" id="welcomeUserInfo">
                    <img class="welcome-avatar" id="welcomeAvatar" src="" alt="">
                    <span class="welcome-name" id="welcomeName"></span>
                    <span class="welcome-score">积分: <strong id="welcomeScore">0</strong></span>
                </div>
                <div id="welcomeBuffInfo" style="margin:6px 0;font-size:12px;min-height:18px;text-align:center;"></div>
                <button class="btn btn-primary welcome-start-btn" id="btnStartGame">🎮 开始游戏</button>
                <div class="welcome-actions" id="welcomeActions">
                    <button class="btn welcome-action-btn" id="btnWelcomeShop" style="background:linear-gradient(135deg,#f39c12,#e67e22);color:white;">商城</button>
                    <button class="btn welcome-action-btn" id="btnWelcomeInventory" style="background:linear-gradient(135deg,#e17055,#d63031);color:white;">背包</button>
                    <button class="btn welcome-action-btn" id="btnWelcomeRecharge" style="background:linear-gradient(135deg,#e74c3c,#c0392b);color:white;">充值</button>
                </div>

                <!-- 公告与最近更新模块 -->
                <div class="welcome-modules">
                    <div class="welcome-notice" id="welcomeNotice">
                        <div class="module-title">📢 公告</div>
                        <div class="module-body" id="noticeBody"></div>
                    </div>
                    <div class="welcome-updates" id="welcomeUpdates">
                        <div class="module-title">🔄 最近更新</div>
                        <div class="module-body" id="updatesBody"></div>
                    </div>
                </div>
            </div>

            <div id="loginFormContainer">
                <div class="login-error" id="loginError"></div>

                <div class="login-tip">
                    <strong>💡 登录说明：</strong>
                    登录后可保存积分到排行榜，游客模式仅限本地体验。
                </div>

                <a href="<?php echo $login_url; ?>" class="btn-redirect-login" id="btnRedirectLogin">
                    🔑 前往登录（推荐）
                </a>

                <?php if ($config['guest_play'] === '1') : ?>
                <div style="text-align: center; margin: 15px 0; color: #999;">— 或者 —</div>

                <button class="btn-guest" id="btnGuest">🎮 游客模式</button>
                <?php endif; ?>
            </div>

            <div id="loadingContainer" class="hidden">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div class="loading-text" id="loadingText">正在检查登录状态...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 游戏容器 -->
    <div class="game-container hidden" id="gameContainer">
        <!-- 记牌器（顶部水平栏） -->
        <div class="card-tracker-bar collapsed" id="cardTrackerBar">
            <button class="tracker-toggle" id="trackerToggle">
                📊 <span class="arrow">▼</span>
            </button>
            <div class="tracker-content" id="trackerContent"></div>
        </div>

        <!-- 底牌区域 -->
        <div class="landlord-cards" id="landlordCards"></div>

        <!-- 倍数显示 -->
        <div class="multiplier">
            倍数: <span class="multiplier-value" id="multiplierValue">1</span>
        </div>

        <!-- 左边AI玩家 -->
        <div class="player-area player-left" id="playerLeft">
            <div class="player-info">
                <img class="player-avatar" id="avatarLeft" src="" alt="">
                <div>
                    <div class="player-name" id="nameLeft"></div>
                    <div class="player-role farmer" id="roleLeft">农民</div>
                </div>
                <div class="card-count" id="countLeft">17</div>
            </div>
            <div class="speech-bubble hidden" id="speechBubbleLeft">
                <span class="speech-text" id="speechBubbleLeftText"></span>
                <div class="speech-arrow"></div>
            </div>
            <div class="ai-cards" id="cardsLeft"></div>
            <div class="played-cards" id="playedLeft">
                <div class="played-cards-label"></div>
                <div class="played-cards-list" id="playedCardsLeft"></div>
            </div>
        </div>

        <!-- 右边AI玩家 -->
        <div class="player-area player-right" id="playerRight">
            <div class="player-info">
                <img class="player-avatar" id="avatarRight" src="" alt="">
                <div>
                    <div class="player-name" id="nameRight"></div>
                    <div class="player-role farmer" id="roleRight">农民</div>
                </div>
                <div class="card-count" id="countRight">17</div>
            </div>
            <div class="speech-bubble hidden" id="speechBubbleRight">
                <span class="speech-text" id="speechBubbleRightText"></span>
                <div class="speech-arrow"></div>
            </div>
            <div class="ai-cards" id="cardsRight"></div>
            <div class="played-cards" id="playedRight">
                <div class="played-cards-label"></div>
                <div class="played-cards-list" id="playedCardsRight"></div>
            </div>
        </div>

        <!-- 中央出牌区域 -->
        <div class="play-area">
            <div class="played-cards-container">
                <div class="played-cards" id="playedCenter">
                    <div class="played-cards-label" id="playedLabel"></div>
                    <div class="played-cards-list" id="playedCardsCenter"></div>
                </div>
            </div>
        </div>

        <!-- 玩家区域 -->
        <div class="player-area player-bottom" id="playerBottom">
            <div class="action-buttons hidden" id="actionButtons">
                <button class="btn-action" id="btnEmote" style="background:linear-gradient(135deg,#f39c12,#e67e22);color:white;display:none;">😎</button>
                <button class="btn-action btn-pass" id="btnPass">不出</button>
                <button class="btn-action btn-play" id="btnPlay">出牌</button>
            </div>
            <div class="bid-buttons hidden" id="bidButtons">
                <button class="btn-bid btn-nobid" id="btnNoBid">不叫</button>
                <button class="btn-bid" id="btnBid1">1分</button>
                <button class="btn-bid" id="btnBid2">2分</button>
                <button class="btn-bid" id="btnBid3">3分</button>
            </div>
            <div class="player-info" style="position:relative;">
                <img class="player-avatar" id="avatarPlayer" src="" alt="">
                <div>
                    <div class="player-name" id="namePlayer"></div>
                    <div class="player-role farmer" id="rolePlayer">农民</div>
                </div>
                <!-- 玩家弹幕气泡 -->
                <div class="speech-bubble hidden" id="speechBubblePlayer" style="position:absolute;bottom:60px;left:50%;transform:translateX(-50%);">
                    <span class="speech-text" id="speechBubblePlayerText"></span>
                    <div class="speech-arrow" style="position:absolute;bottom:-16px;left:50%;transform:translateX(-50%);width:0;height:0;border:8px solid transparent;border-top-color:#fff;"></div>
                </div>
            </div>
            <div class="hand-cards" id="handCards"></div>
        </div>

        <!-- 积分加成卡状态 -->
        <div class="buff-indicator hidden" id="buffIndicator">
            <span id="buffIndicatorText">⚡ 加成卡 x2</span>
        </div>        <!-- 炸弹特效遮罩 -->
        <div class="bomb-overlay hidden" id="bombOverlay">
            <div class="bomb-overlay-inner" id="bombOverlayInner">💥</div>
        </div>        <!-- Toast消息容器（左下角） -->
        <div class="toast-container" id="toastContainer"></div>
    </div>

    <!-- 结果弹窗 -->
    <div class="result-modal hidden" id="resultModal">
        <div class="result-content">
            <div class="result-title" id="resultTitle"></div>
            <div class="result-score" id="resultScore"></div>
            <div class="result-detail" id="resultDetail"></div>
            <div class="result-buttons">
                <button class="btn btn-primary" id="btnPlayAgain">再来一局</button>
                <button class="btn btn-secondary" id="btnStopGame">停止对局</button>
            </div>
        </div>
    </div>

    <!-- 排行榜弹窗 -->
    <div class="leaderboard-modal hidden" id="leaderboardModal">
        <div class="leaderboard-content">
            <div class="leaderboard-title">🏆 排行榜 - <span id="lbModeName">经典模式</span></div>
            <div class="leaderboard-list" id="leaderboardList"></div>
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-primary" id="btnCloseLeaderboard">关闭</button>
            </div>
        </div>
    </div>

    <!-- 积分流水弹窗 -->
    <div class="leaderboard-modal hidden" id="scoreLogModal">
        <div class="leaderboard-content">
            <div class="leaderboard-title">📊 积分流水</div>
            <div class="score-log-list" id="scoreLogList">
                <div style="text-align: center; color: #aaa; padding: 20px;">暂无记录</div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-primary" id="btnCloseScoreLog">关闭</button>
            </div>
        </div>
    </div>

    <!-- 商城弹窗 -->
    <div class="leaderboard-modal hidden" id="shopModal">
        <div class="leaderboard-content" style="max-width: 500px;">
            <div class="leaderboard-title">🛒 道具商城</div>
            <div style="display: flex; gap: 12px; justify-content: center; margin-bottom: 15px; font-size: 13px; color: #ccc; flex-wrap: wrap;">
                <span>斗地主积分: <strong style="color: #ffd700;" id="shopDdzScore">0</strong></span>
                <span>站点积分: <strong style="color: #e17055;" id="shopEmlogCredits">0</strong></span>
            </div>
            <div class="leaderboard-list" id="shopItemsList">
                <div style="text-align: center; color: #aaa; padding: 30px;">加载中...</div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-primary" id="btnCloseShop">关闭</button>
            </div>
        </div>
    </div>

    <!-- 背包弹窗 -->
    <div class="leaderboard-modal hidden" id="inventoryModal">
        <div class="leaderboard-content" style="max-width: 500px;">
            <div class="leaderboard-title">🎒 我的背包</div>
            <div class="leaderboard-list" id="inventoryList">
                <div style="text-align: center; color: #aaa; padding: 30px;">加载中...</div>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-primary" id="btnCloseInventory">关闭</button>
            </div>
        </div>
    </div>

    <!-- 购买反馈弹窗 -->
    <div class="result-modal hidden" id="shopFeedbackModal">
        <div class="result-content" style="max-width: 360px;">
            <div class="result-title" id="shopFeedbackIcon" style="font-size: 48px; margin-bottom: 5px;">🎉</div>
            <div class="result-title" id="shopFeedbackTitle" style="font-size: 24px; margin-bottom: 10px;">购买成功</div>
            <div class="result-detail" id="shopFeedbackMsg" style="font-size: 14px; color: #ccc; margin-bottom: 15px;">积分已扣除，道具已发放到背包</div>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <button class="btn btn-primary" id="btnShopFeedbackClose">确定</button>
            </div>
        </div>
    </div>

    <!-- JS模块 -->
    <script>
        // EMLOG配置 - 由PHP动态注入
        window.EMLOG_CONFIG = {
            baseUrl: '<?php echo $base_url; ?>',
            loginUrl: '<?php echo $login_url; ?>',
            leaderboardApi: '<?php echo $base_url; ?>?plugin=wx_games&game=ddz',
            cardUrl: '<?php echo $plugin_url; ?>assets/cards/',
            penaltyMultiplier: <?php echo isset($config['penalty_multiplier']) ? floatval($config['penalty_multiplier']) : 1.0; ?>,
            maxEntries: <?php echo isset($config['max_entries']) ? intval($config['max_entries']) : 100; ?>
        };

        window.WX_DDZ_MAX_ENTRIES = EMLOG_CONFIG.maxEntries;

        // 当前登录用户信息（由PHP注入，未登录为null）
        window.WX_DDZ_USER = <?php echo $current_user ? json_encode($current_user) : 'null'; ?>;

        // 当前用户积分数据（从数据库读取）
        window.WX_DDZ_USER_SCORE = <?php echo $user_score_data ? json_encode($user_score_data) : 'null'; ?>;

        // 防逃跑机制
        window.WX_DDZ_PENALTY = <?php echo $penalty_message ? json_encode($penalty_message) : 'null'; ?>;

        // 积分流水日志
        window.WX_DDZ_SCORE_LOGS = <?php echo json_encode($score_logs, JSON_UNESCAPED_UNICODE); ?>;

        // 用户 Emlog 积分
        window.WX_DDZ_EMLOG_CREDITS = <?php echo $current_user ? $emlog_credits : 0; ?>;

        // 游客模式配置（0=关闭，1=开启）
        window.WX_DDZ_GUEST_PLAY = '<?php echo $config['guest_play']; ?>';

        // 积分充值链接
        window.WX_DDZ_RECHARGE_LINK = <?php echo json_encode(isset($config['recharge_link']) ? $config['recharge_link'] : '', JSON_UNESCAPED_UNICODE); ?>;

        // 公告与更新内容
        window.WX_DDZ_NOTICE = <?php echo json_encode(isset($config['notice']) ? $config['notice'] : '', JSON_UNESCAPED_UNICODE); ?>;
        window.WX_DDZ_UPDATES = <?php echo json_encode(isset($config['recent_updates']) ? $config['recent_updates'] : '', JSON_UNESCAPED_UNICODE); ?>;

        // AI玩家配置（从后台设置同步，使用新的 ai_players 格式）
        window.MEMBERS = <?php
            $ai_players = wx_ddz_get_ai_players();
            $ai_members = [];
            $assets_url = $plugin_url . 'assets/';
            foreach ($ai_players as $index => $ai) {
                $name = isset($ai['name']) ? trim($ai['name']) : 'AI玩家' . ($index + 1);
                if (empty($name)) $name = 'AI玩家' . ($index + 1);
                $avatar = isset($ai['avatar']) ? $ai['avatar'] : 'boram.jpg';
                $member = [
                'id'     => 'ai' . ($index + 1),
                'name'   => $name,
                'avatar' => $assets_url . $avatar
            ];
            if (isset($ai['quotes']) && is_array($ai['quotes'])) {
                $member['quotes'] = $ai['quotes'];
            }
            $ai_members[] = $member;
            }
            echo json_encode($ai_members, JSON_UNESCAPED_UNICODE);
        ?>;
    </script>
    <script src="<?php echo $plugin_url; ?>js/config.js?v=2.0.4"></script>
    <script src="<?php echo $plugin_url; ?>js/card-patterns.js?v=2.0.4"></script>
    <script src="<?php echo $plugin_url; ?>js/ai-strategy.js?v=2.0.4"></script>
    <script src="<?php echo $plugin_url; ?>js/ai-quotes.js?v=2.0.4"></script>
    <script src="<?php echo $plugin_url; ?>js/audio.js?v=2.0.4"></script>
    <script src="<?php echo $plugin_url; ?>js/emlog-api.js?v=2.0.4"></script>
    <script src="<?php echo $plugin_url; ?>js/leaderboard.js?v=2.0.4"></script>
    <script>
        // ==================== 用户状态 ====================
        let currentUser = window.WX_DDZ_USER;
        let isGuest = false;
        let gameInProgress = false; // 游戏是否进行中（防逃跑）

        // ==================== 游戏状态 ====================
        let gameState = {
            phase: 'start',
            deck: [],
            players: [
                { id: 'player', cards: [], isAI: false, member: null, isLandlord: false },
                { id: 'left', cards: [], isAI: true, member: null, isLandlord: false },
                { id: 'right', cards: [], isAI: true, member: null, isLandlord: false }
            ],
            landlordCards: [],
            currentPlayer: 0,
            lastPlay: null,
            lastPlayer: -1,
            passCount: 0,
            multiplier: 1,
            bombCount: 0,
            selectedCards: [],
            playerScore: 0,
            usedCards: {},
            bidScore: 0,
            currentBidder: 0,
        };

        // 从数据库加载用户积分数据
        if (window.WX_DDZ_USER_SCORE) {
            gameState.playerScore = window.WX_DDZ_USER_SCORE.score || 0;
        }

        // ==================== Toast 消息系统 ====================
        function showToast(text, duration = 1800) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = text;
            container.appendChild(toast);

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, duration + 300);
        }

        // ==================== 防逃跑：离开页面提示 ====================
        window.addEventListener('beforeunload', function(e) {
            if (!gameInProgress || !currentUser) return;

            // 用本地游戏进度计算实际惩罚
            var multi = gameState.multiplier || 1;
            var penalty = 100 * multi * (EMLOG_CONFIG.penaltyMultiplier || 1);

            // sendBeacon 会携带 cookie 发送（浏览器不拦截）
            navigator.sendBeacon('?plugin=wx_games&game=ddz&wxddz_signal=penalty&points=' + penalty);

            // 浏览器原生确认框
            e.preventDefault();
            e.returnValue = '游戏进行中，离开将被扣除 ' + penalty + ' 积分（100×倍率' + multi + '×惩罚' + (EMLOG_CONFIG.penaltyMultiplier || 1).toFixed(1) + '）！';
        });

        // ==================== 记牌器 ====================
        function initTracker() {
            const toggle = document.getElementById('trackerToggle');
            const bar = document.getElementById('cardTrackerBar');

            toggle.addEventListener('click', () => {
                if (bar.classList.contains('collapsed')) {
                    bar.classList.remove('collapsed');
                    bar.classList.add('expanded');
                } else {
                    bar.classList.remove('expanded');
                    bar.classList.add('collapsed');
                }
            });
        }

        function renderCardTracker() {
            const container = document.getElementById('trackerContent');
            const allCards = ['3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A', '2', '小王', '大王'];

            container.innerHTML = allCards.map((value, index) => {
                const total = index < 13 ? 4 : 1;
                const used = gameState.usedCards[value] || 0;
                const isUsed = used >= total;

                return `
                    <div class="tracker-item ${isUsed ? 'used' : ''}">
                        <div class="tracker-value">${value}</div>
                        <div class="tracker-count">${total - used}张</div>
                    </div>
                `;
            }).join('');
        }

        // ==================== 导航栏游戏UI控制 ====================
        function toggleNavGameUI(show) {
            const ids = ['navUserInfo', 'navScoreBox', 'btnLeaderboard', 'btnNewGame', 'btnShop', 'btnInventory'];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    if (show) el.classList.remove('hidden');
                    else el.classList.add('hidden');
                }
            });
        }

        // 更新导航栏用户信息（头像、昵称、积分）
        function updateNavUserInfo() {
            const userInfoEl = document.getElementById('navUserInfo');
            const scoreBoxEl = document.getElementById('navScoreBox');
            const avatarEl = document.getElementById('userAvatar');
            const nameEl = document.getElementById('userName');
            const scoreEl = document.getElementById('playerScore');

            if (currentUser) {
                var avatarSrc = currentUser.avatar || generateAvatarSvg(currentUser.nickname, '#e17055');
                avatarEl.src = avatarSrc;
                nameEl.innerHTML = renderPlayerName(currentUser.nickname);
                scoreEl.textContent = gameState.playerScore;

                userInfoEl.classList.remove('hidden');
                scoreBoxEl.classList.remove('hidden');
            } else if (isGuest) {
                avatarEl.src = generateAvatarSvg('游', '#95a5a6');
                nameEl.textContent = '游客';
                scoreEl.textContent = gameState.playerScore;

                userInfoEl.classList.remove('hidden');
                scoreBoxEl.classList.remove('hidden');
            } else {
                userInfoEl.classList.add('hidden');
                scoreBoxEl.classList.add('hidden');
            }
        }

        // ==================== UI 渲染 ====================
        // 获取用户激活的牌背皮肤样式
        function getCardBackStyle() {
            const effects = window.WX_DDZ_PLAYER_EFFECTS || {};
            const skin = effects.cardBack;
            const base = (window.EMLOG_CONFIG && window.EMLOG_CONFIG.cardUrl) ? window.EMLOG_CONFIG.cardUrl : 'games/ddz/assets/cards/';
            if (!skin) {
                return 'background:url(' + base + 'back_default.png) center/cover no-repeat; box-shadow:0 2px 10px rgba(0,0,0,0.3);';
            }
            // 新格式：直接使用 effect_data 中的 bg 作为背景
            if (skin.bg) {
                var cardBackStyle = 'background:' + skin.bg + ';';
                if (skin.border) cardBackStyle += 'border:2px solid ' + skin.border + ';';
                return cardBackStyle;
            }
            // 旧格式兼容
            if (skin.skin === 'custom' && skin.url) {
                return 'background:url(' + skin.url + ') center/cover no-repeat;';
            }
            var gradient = 'linear-gradient(135deg, #e17055 0%, #d63031 100%)';
            if (skin.colors && skin.colors.length >= 2) {
                gradient = 'linear-gradient(135deg, ' + skin.colors[0] + ' 0%, ' + skin.colors[1] + ' 100%)';
            } else {
                var SKIN_GRADIENTS = {
                    'gradient1': 'linear-gradient(135deg, #ff6b6b 0%, #c0392b 100%)',
                    'gradient2': 'linear-gradient(135deg, #e17055 0%, #fdcb6e 100%)',
                    'gradient3': 'linear-gradient(135deg, #55efc4 0%, #00b894 100%)',
                    'gradient4': 'linear-gradient(135deg, #2d3436 0%, #636e72 100%)',
                    'gradient5': 'linear-gradient(135deg, #fd79a8 0%, #e84393 100%)',
                    'gradient6': 'linear-gradient(135deg, #fdcb6e 0%, #e17055 100%)',
                };
                gradient = SKIN_GRADIENTS[skin.skin] || gradient;
            }
            return 'background:' + gradient + ';';
        }

        /** 获取牌背装饰花纹 HTML */
        function getCardBackIconHtml() {
            const effects = window.WX_DDZ_PLAYER_EFFECTS || {};
            const skin = effects.cardBack;
            if (skin && skin.icon && skin.bg) {
                var iconColor = skin.icon_color || 'rgba(255,255,255,0.3)';
                return '<span style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-size:14px;color:' + iconColor + ';pointer-events:none;">' + skin.icon + '</span>';
            }
            return '';
        }

        function getCardImagePath(card) {
            const base = (window.EMLOG_CONFIG && window.EMLOG_CONFIG.cardUrl) ? window.EMLOG_CONFIG.cardUrl : 'games/ddz/assets/cards/';
            if (card.isJoker) {
                return base + (card.isSmall ? 'joker_small.png' : 'joker_big.png');
            }
            const suitMap = { '♠': 's', '♥': 'h', '♣': 'c', '♦': 'd' };
            const suit = suitMap[card.suit] || 's';
            return base + 'card_' + suit + '_' + card.value + '.png';
        }

        function renderCard(card, isBack = false) {
            if (isBack) {
                return '<div class="card-back" style="' + getCardBackStyle() + '"></div>';
            }

            const colorClass = card.isJoker ? (card.isSmall ? 'joker small' : 'joker') : SUIT_COLORS[card.suit];
            const imgPath = getCardImagePath(card);

            return `
                <div class="card ${colorClass}" data-id="${card.id}">
                    <img src="${imgPath}" alt="" class="card-img">
                    <span class="card-value">${card.isJoker ? (card.isSmall ? '小' : '大') : card.value}</span>
                    <span class="card-suit">${card.isJoker ? '王' : card.suit}</span>
                    <span class="card-center-suit">${card.isJoker ? '🃏' : card.suit}</span>
                </div>
            `;
        }

        function renderPlayedCard(card) {
            const colorClass = card.isJoker ? (card.isSmall ? 'joker small' : 'joker') : SUIT_COLORS[card.suit];
            const imgPath = getCardImagePath(card);

            return `
                <div class="played-card ${colorClass}">
                    <img src="${imgPath}" alt="" class="card-img">
                    <span>${card.isJoker ? (card.isSmall ? '小' : '大') : card.value}</span>
                    <span>${card.isJoker ? '王' : card.suit}</span>
                </div>
            `;
        }

        function renderHandCards() {
            const container = document.getElementById('handCards');
            const cards = gameState.players[0].cards;
            container.innerHTML = cards.map(card => renderCard(card)).join('');

            // 绑定点击和触摸事件（防止touch+click双触发）
            container.querySelectorAll('.card').forEach(cardEl => {
                let lastTouchTime = 0;
                
                const handleSelect = (e) => {
                    // 防止touchend后300ms内的click重复触发
                    const now = Date.now();
                    if (e.type === 'click' && now - lastTouchTime < 400) return;
                    if (e.type === 'touchend') lastTouchTime = now;
                    
                    e.preventDefault();
                    const cardId = cardEl.dataset.id;
                    const card = cards.find(c => c.id === cardId);

                    if (cardEl.classList.contains('selected')) {
                        cardEl.classList.remove('selected');
                        cardEl.style.zIndex = '';
                        gameState.selectedCards = gameState.selectedCards.filter(c => c.id !== cardId);
                    } else {
                        cardEl.classList.add('selected');
                        cardEl.style.zIndex = '100';
                        gameState.selectedCards.push(card);
                    }
                };

                cardEl.addEventListener('touchend', handleSelect, { passive: false });
                cardEl.addEventListener('click', handleSelect);
            });
        }

        function renderAICards() {
            const iconHtml = getCardBackIconHtml();
            const cardHtml = '<div class="card-back" style="' + getCardBackStyle() + ';position:relative;">' + iconHtml + '</div>';
            const leftContainer = document.getElementById('cardsLeft');
            leftContainer.innerHTML = gameState.players[1].cards.map(() => cardHtml).join('');
            document.getElementById('countLeft').textContent = gameState.players[1].cards.length;

            const rightContainer = document.getElementById('cardsRight');
            rightContainer.innerHTML = gameState.players[2].cards.map(() => cardHtml).join('');
            document.getElementById('countRight').textContent = gameState.players[2].cards.length;
        }

        function renderLandlordCards(revealed = false) {
            const container = document.getElementById('landlordCards');
            if (revealed) {
                // 使用独立模板，不生成 .card / data-id 等可交互属性，防止被误选中
                container.innerHTML = gameState.landlordCards.map(card => {
                    const colorClass = card.isJoker ? (card.isSmall ? 'joker small' : 'joker') : SUIT_COLORS[card.suit];
                    const displayValue = card.isJoker ? (card.isSmall ? '小' : '大') : card.value;
                    const displaySuit = card.isJoker ? '王' : card.suit;
                    return `<div class="landlord-card revealed ${colorClass}"><span>${displayValue}${displaySuit}</span></div>`;
                }).join('');
            } else {
                container.innerHTML = gameState.landlordCards.map(() => `<div class="landlord-card">?</div>`).join('');
            }
        }

        function updateMultiplier() {
            document.getElementById('multiplierValue').textContent = gameState.multiplier;
        }

        function renderPlayedCards(playerId, cards, label = '') {
            const containerId = playerId === 0 ? 'playedCardsCenter' :
                               playerId === 1 ? 'playedCardsLeft' : 'playedCardsRight';
            const container = document.getElementById(containerId);

            if (cards === null || cards.length === 0) {
                container.innerHTML = '<div style="color: white; font-size: 14px;">不出</div>';
            } else {
                container.innerHTML = cards.map(card => renderPlayedCard(card)).join('');
            }
        }

        function clearPlayedCards() {
            document.getElementById('playedCardsCenter').innerHTML = '';
            document.getElementById('playedCardsLeft').innerHTML = '';
            document.getElementById('playedCardsRight').innerHTML = '';
        }

        // ==================== 用户界面更新 ====================
        function updateUserUI() {
            if (currentUser) {
                const avatarSrc = currentUser.avatar || generateAvatarSvg(currentUser.nickname, '#e17055');
                document.getElementById('userAvatar').src = avatarSrc;
                document.getElementById('userName').innerHTML = renderPlayerName(currentUser.nickname);

                document.getElementById('avatarPlayer').src = avatarSrc;
                // 道具效果渲染玩家昵称
                document.getElementById('namePlayer').innerHTML = renderPlayerName(currentUser.nickname);

                gameState.players[0].member = {
                    id: 'emlog_user_' + currentUser.uid,
                    name: currentUser.nickname,
                    avatar: currentUser.avatar
                };
            } else if (isGuest) {
                document.getElementById('userAvatar').src = generateAvatarSvg('游', '#95a5a6');
                document.getElementById('userName').textContent = '游客';

                document.getElementById('avatarPlayer').src = generateAvatarSvg('游', '#95a5a6');
                document.getElementById('namePlayer').textContent = '游客';
            }
        }

        function generateAvatarSvg(text, color) {
            return `data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="50" fill="${encodeURIComponent(color)}"/><text x="50" y="60" text-anchor="middle" fill="white" font-size="40">${text.charAt(0)}</text></svg>`;
        }

        // ==================== 游戏流程 ====================
        function initGame() {
            gameState.deck = shuffleDeck(createDeck());
            gameState.players.forEach(p => {
                p.cards = [];
                p.isLandlord = false;
            });
            gameState.landlordCards = [];
            gameState.currentPlayer = 0;
            gameState.lastPlay = null;
            gameState.lastPlayer = -1;
            gameState.passCount = 0;
            gameState.multiplier = 1;
            gameState.bombCount = 0;
            gameState.selectedCards = [];
            gameState.usedCards = {};
            gameState.phase = 'bidding';
            gameState.bidScore = 0;
            gameState.currentBidder = Math.floor(Math.random() * 3);

            for (let i = 0; i < 51; i++) {
                gameState.players[i % 3].cards.push(gameState.deck[i]);
            }
            gameState.landlordCards = gameState.deck.slice(51, 54);

            gameState.players.forEach(p => {
                p.cards = sortCards(p.cards);
            });

            const aiMembers = [...MEMBERS];
            shuffleArray(aiMembers);
            gameState.players[1].member = aiMembers[0];
            gameState.players[2].member = aiMembers[1];

            document.getElementById('avatarLeft').src = gameState.players[1].member.avatar;
            document.getElementById('nameLeft').textContent = gameState.players[1].member.name;

            document.getElementById('avatarRight').src = gameState.players[2].member.avatar;
            document.getElementById('nameRight').textContent = gameState.players[2].member.name;

            renderHandCards();
            renderAICards();
            renderLandlordCards(false);
            renderCardTracker();
            updateMultiplier();
            clearPlayedCards();

            updateUserUI();

            gameInProgress = true; // 防逃跑：标记游戏进行中
            startBidding();
        }

        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
        }

        function startBidding() {
            gameState.phase = 'bidding';

            if (gameState.currentBidder === 0) {
                document.getElementById('bidButtons').classList.remove('hidden');
                document.getElementById('actionButtons').classList.add('hidden');
            } else {
                setTimeout(() => aiBid(), 1000);
            }
        }

        function aiBid() {
            const player = gameState.players[gameState.currentBidder];
            const bid = AI.decideBid(player.cards);

            if (bid > gameState.bidScore) {
                gameState.bidScore = bid;
                showToast(`${player.member.name}: ${bid}分`);

                if (bid === 3) {
                    becomeLandlord(gameState.currentBidder);
                    return;
                }
            } else {
                showToast(`${player.member.name}: 不叫`);
            }

            nextBidder();
        }

        function nextBidder() {
            gameState.currentBidder = (gameState.currentBidder + 1) % 3;

            if (gameState.bidScore > 0) {
                becomeLandlord(gameState.currentBidder);
                return;
            }

            if (gameState.currentBidder === 0) {
                document.getElementById('bidButtons').classList.remove('hidden');
            } else {
                setTimeout(() => aiBid(), 1000);
            }
        }

        function becomeLandlord(playerIndex) {
            gameState.players[playerIndex].isLandlord = true;
            gameState.multiplier = gameState.bidScore || 1;

            gameState.players[playerIndex].cards.push(...gameState.landlordCards);
            gameState.players[playerIndex].cards = sortCards(gameState.players[playerIndex].cards);

            renderLandlordCards(true);
            updateMultiplier();

            // 正确映射: playerIndex 0=玩家, 1=左AI, 2=右AI
            const roleIds = ['rolePlayer', 'roleLeft', 'roleRight'];
            roleIds.forEach((id, index) => {
                const el = document.getElementById(id);
                if (index === playerIndex) {
                    el.textContent = '地主';
                    el.classList.remove('farmer');
                } else {
                    el.textContent = '农民';
                    el.classList.add('farmer');
                }
            });

            if (playerIndex === 0) {
                renderHandCards();
            }
            renderAICards();

            gameState.phase = 'playing';
            gameState.currentPlayer = playerIndex;
            document.getElementById('bidButtons').classList.add('hidden');

            const landlordName = playerIndex === 0 ?
                (currentUser ? currentUser.nickname : '你') :
                gameState.players[playerIndex].member.name;

            // AI 成为地主时触发台词
            if (playerIndex !== 0) {
                setTimeout(function() {
                    QuoteSystem.speak(playerIndex, 'bid');
                }, 500);
            }
            showToast(`${landlordName} 是地主！`);

            setTimeout(() => {
                startTurn();
            }, 2000);
        }

        function startTurn() {
            if (gameState.currentPlayer === 0) {
                document.getElementById('actionButtons').classList.remove('hidden');
                document.getElementById('btnPass').disabled = gameState.lastPlayer === 0 || gameState.lastPlayer === -1;
                // 检查是否有激活的弹幕
                var emoteBtn = document.getElementById('btnEmote');
                var effects = window.WX_DDZ_PLAYER_EFFECTS || {};
                emoteBtn.style.display = effects.emoticon && effects.emoticon.text ? '' : 'none';
            } else {
                document.getElementById('actionButtons').classList.add('hidden');
                setTimeout(() => aiPlay(), 1000);
            }
        }

        function aiPlay() {
            const player = gameState.players[gameState.currentPlayer];
            const lastPlay = gameState.lastPlayer !== gameState.currentPlayer ? gameState.lastPlay : null;
            const playerIndex = gameState.currentPlayer;

            const play = AI.findPlayableCards(player.cards, lastPlay, playerIndex, gameState);

            if (play) {
                play.cards.forEach(card => {
                    const index = player.cards.findIndex(c => c.id === card.id);
                    if (index > -1) player.cards.splice(index, 1);

                    const value = card.isJoker ? (card.isSmall ? '小王' : '大王') : card.value;
                    gameState.usedCards[value] = (gameState.usedCards[value] || 0) + 1;
                });

                gameState.lastPlay = play.pattern;
                gameState.lastPlayer = gameState.currentPlayer;
                gameState.passCount = 0;

                if (play.pattern.type === 'bomb' || play.pattern.type === 'rocket') {
                    gameState.multiplier *= 2;
                    gameState.bombCount++;
                    AudioSystem.play('bomb');
                    updateMultiplier();
                    triggerBombEffect();
                } else {
                    AudioSystem.play('play');
                }

                renderPlayedCards(gameState.currentPlayer, play.cards);
                renderAICards();
                renderCardTracker();

                // AI 出牌后根据牌型触发台词
                var playedValues = play.cards.map(function(c) { return c.isJoker ? (c.isSmall ? 16 : 17) : c.value; });
                QuoteSystem.speakByPlay(gameState.currentPlayer, playedValues);

                showToast(`${player.member.name} 出牌`);

                if (player.cards.length === 0) {
                    endGame(gameState.currentPlayer);
                    return;
                }
            } else {
                gameState.passCount++;
                renderPlayedCards(gameState.currentPlayer, null);
                AudioSystem.play('pass');
                showToast(`${player.member.name} 不出`);
                QuoteSystem.speak(gameState.currentPlayer, 'pass');
            }

            nextPlayer();
        }

        function nextPlayer() {
            if (gameState.passCount >= 2) {
                gameState.lastPlay = null;
                gameState.lastPlayer = -1;
                gameState.passCount = 0;
                clearPlayedCards();
            }

            gameState.currentPlayer = (gameState.currentPlayer + 1) % 3;

            setTimeout(() => startTurn(), 500);
        }

        // 炸弹粒子爆炸特效
        function triggerBombEffect() {
            const effects = window.WX_DDZ_PLAYER_EFFECTS || {};
            if (!effects.bombEffect) return;
            var color = effects.bombEffect.color || '#ff4500';
            triggerParticleExplosion(color);
        }

        function triggerParticleExplosion(color) {
            // 创建临时 canvas
            var canvas = document.createElement('canvas');
            canvas.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:5000;pointer-events:none;';
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            document.body.appendChild(canvas);
            var ctx = canvas.getContext('2d');

            var cx = canvas.width / 2;
            var cy = canvas.height / 2;
            var particles = [];
            var count = 80;
            var colors = [color, '#fff', color, '#ffd700'];

            for (var i = 0; i < count; i++) {
                var angle = Math.random() * Math.PI * 2;
                var speed = Math.random() * 12 + 4;
                particles.push({
                    x: cx, y: cy,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed - 3,
                    size: Math.random() * 6 + 3,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    life: 1,
                    decay: Math.random() * 0.02 + 0.008,
                    gravity: 0.15
                });
            }

            var startTime = Date.now();
            var duration = 2000;

            function frame() {
                var elapsed = Date.now() - startTime;
                if (elapsed > duration) {
                    canvas.remove();
                    return;
                }
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (var i = particles.length - 1; i >= 0; i--) {
                    var p = particles[i];
                    p.x += p.vx;
                    p.y += p.vy;
                    p.vy += p.gravity;
                    p.life -= p.decay;
                    if (p.life <= 0) { particles.splice(i, 1); continue; }
                    ctx.globalAlpha = p.life;
                    ctx.fillStyle = p.color;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2);
                    ctx.fill();
                }
                // 中心闪光
                var flashLife = Math.max(0, 1 - elapsed / 300);
                ctx.globalAlpha = flashLife * 0.6;
                var gradient = ctx.createRadialGradient(cx, cy, 0, cx, cy, 80);
                gradient.addColorStop(0, '#fff');
                gradient.addColorStop(1, color);
                ctx.fillStyle = gradient;
                ctx.beginPath();
                ctx.arc(cx, cy, 80, 0, Math.PI * 2);
                ctx.fill();

                ctx.globalAlpha = 1;
                requestAnimationFrame(frame);
            }
            frame();
        }

        function playerPlay() {
            const selectedCards = gameState.selectedCards;

            if (selectedCards.length === 0) {
                showToast('请选择要出的牌');
                return;
            }

            const pattern = CardPatterns.getPattern(selectedCards);

            if (!pattern) {
                showToast('无效的牌型');
                return;
            }

            const lastPlay = gameState.lastPlayer !== 0 ? gameState.lastPlay : null;

            if (lastPlay && !CardPatterns.canBeat(selectedCards, lastPlay)) {
                showToast('打不过上家');
                return;
            }

            selectedCards.forEach(card => {
                const index = gameState.players[0].cards.findIndex(c => c.id === card.id);
                if (index > -1) gameState.players[0].cards.splice(index, 1);

                const value = card.isJoker ? (card.isSmall ? '小王' : '大王') : card.value;
                gameState.usedCards[value] = (gameState.usedCards[value] || 0) + 1;
            });

            gameState.lastPlay = pattern;
            gameState.lastPlayer = 0;
            gameState.passCount = 0;
            gameState.selectedCards = [];

            if (pattern.type === 'bomb' || pattern.type === 'rocket') {
                gameState.multiplier *= 2;
                gameState.bombCount++;
                AudioSystem.play('bomb');
                updateMultiplier();
                triggerBombEffect();
            } else {
                AudioSystem.play('play');
            }

            renderHandCards();
            renderPlayedCards(0, selectedCards);
            renderCardTracker();

            if (gameState.players[0].cards.length === 0) {
                endGame(0);
                return;
            }

            document.getElementById('actionButtons').classList.add('hidden');
            nextPlayer();
        }

        function playerPass() {
            if (gameState.lastPlayer === 0 || gameState.lastPlayer === -1) {
                showToast('你必须出牌');
                return;
            }

            gameState.passCount++;
            gameState.selectedCards = [];
            renderHandCards();
            renderPlayedCards(0, null);
            AudioSystem.play('pass');

            document.getElementById('actionButtons').classList.add('hidden');
            nextPlayer();
        }
        function endGame(winnerIndex) {
            gameState.phase = 'end';

            const winner = gameState.players[winnerIndex];
            const isLandlordWin = winner.isLandlord;
            const playerIsLandlord = gameState.players[0].isLandlord;
            const playerWin = (playerIsLandlord && isLandlordWin) || (!playerIsLandlord && !isLandlordWin);

            let baseScore = 100 * gameState.multiplier;

            let isSpring = false;
            if (isLandlordWin) {
                const farmer1Cards = gameState.players[1].cards.length;
                const farmer2Cards = gameState.players[2].cards.length;
                const farmer1Played = gameState.players[1].isLandlord ? true : farmer1Cards === 17;
                const farmer2Played = gameState.players[2].isLandlord ? true : farmer2Cards === 17;

                if (farmer1Played && farmer2Played) {
                    isSpring = true;
                    baseScore *= 2;
                }
            } else {
                const landlordIndex = gameState.players.findIndex(p => p.isLandlord);
                if (landlordIndex !== -1 && gameState.players[landlordIndex].cards.length === 20) {
                    isSpring = true;
                    baseScore *= 2;
                }
            }

            // 计算每个玩家的得分（地主赢拿双倍底分，农民赢各拿一份底分）
            const playerScores = [0, 0, 0];
            for (let i = 0; i < 3; i++) {
                const pIsLandlord = gameState.players[i].isLandlord;
                const pWin = (pIsLandlord && isLandlordWin) || (!pIsLandlord && !isLandlordWin);
                if (isLandlordWin) {
                    // 地主赢：地主得 2×底分，农民各输 1×底分
                    playerScores[i] = pWin ? baseScore * 2 : -baseScore;
                } else {
                    // 农民赢：农民各得 1×底分，地主输 2×底分
                    playerScores[i] = pWin ? baseScore : -baseScore * 2;
                }
            }

            // 积分加成卡倍率（仅玩家胜利时自己的得分翻倍，不影响AI）
            var buffMultiplier = 1;
            var buffValue = 0;
            var buffDisplay = '';
            if (currentUser && window.WX_DDZ_ACTIVE_BUFFS && window.WX_DDZ_ACTIVE_BUFFS.length > 0 && playerWin) {
                buffMultiplier = window.WX_DDZ_ACTIVE_BUFFS[0].multiplier || 1;
                buffValue = buffMultiplier;
                buffDisplay = '加成卡 x' + buffMultiplier;
                playerScores[0] = Math.round(playerScores[0] * buffMultiplier);
                // 注意：AI得分不乘以加成倍率，积分卡只对玩家生效
            }

            // 构建结算明细（新格式）
            var parts = ['100（基础分）'];
            var bidVal = gameState.bidScore || 1;
            parts.push('×' + bidVal + '（地主分）');
            if (isSpring) {
                parts.push('×2（春天）');
            }
            if (gameState.bombCount > 0) {
                var bombFactor = Math.pow(2, gameState.bombCount);
                parts.push('×' + bombFactor + '（炸弹分）');
            }
            var totalBeforeBuff = 100 * (gameState.bidScore || 1) * (isSpring ? 2 : 1) * Math.pow(2, gameState.bombCount);
            if (buffMultiplier > 1) {
                parts.push('×' + buffMultiplier + '（积分卡）');
            }
            var finalTotal = buffMultiplier > 1 ? Math.round(totalBeforeBuff * buffMultiplier) : totalBeforeBuff;
            var scoreBreakdown = parts.join('') + '=' + finalTotal;

            var detailText = scoreBreakdown;
            if (buffDisplay) {
                detailText += '<br>【' + buffDisplay + '🎯】生效中…';
            }

            // 更新玩家积分
            gameState.playerScore += playerScores[0];
            document.getElementById('playerScore').textContent = gameState.playerScore;

            if (playerWin) {
                AudioSystem.play('win');
            } else {
                AudioSystem.play('lose');
            }

            // 保存玩家积分
            const resultType = playerWin ? 'win' : 'lose';
            const roundScore = playerScores[0]; // 本轮实际得分（正或负）

            if (currentUser) {
                // 已登录用户，通过 Leaderboard.saveScore 保存到服务器（旧的稳定方式）
                Leaderboard.saveScore(roundScore, resultType).then(async success => {
                    if (success) {
                        // 保存成功后从服务器拉取最新积分
                        const rank = await Leaderboard.getMyRank();
                        if (rank && rank.score !== undefined) {
                            gameState.playerScore = rank.score;
                            document.getElementById('playerScore').textContent = gameState.playerScore;
                        }
                    } else {
                        // 保存失败时本地更新（降级）
                        gameState.playerScore += roundScore;
                        document.getElementById('playerScore').textContent = gameState.playerScore;
                    }
                });
            } else {
                // 游客模式，仅本地更新
                gameState.playerScore += roundScore;
                document.getElementById('playerScore').textContent = gameState.playerScore;
            }

            // AI玩家也记录到排行榜，并触发台词
            for (let i = 1; i <= 2; i++) {
                const aiMember = gameState.players[i].member;
                if (aiMember) {
                    const aiWin = playerScores[i] > 0;
                    Leaderboard.saveAIScore(aiMember, playerScores[i], aiWin ? 'win' : 'lose');
                    setTimeout((function(idx, win) {
                        return function() { QuoteSystem.speak(idx, win ? 'win' : 'lose'); };
                    })(i, playerScores[i] > 0), 800);
                }
            }

            // 消耗已在开局时扣减，这里不再重复调用
            // 只更新指示器
            updateBuffIndicator();

            const modal = document.getElementById('resultModal');
            const title = document.getElementById('resultTitle');
            const score = document.getElementById('resultScore');
            const detail = document.getElementById('resultDetail');

            const actualScore = Math.abs(playerScores[0]); // 含加成倍率的实际得分
            title.textContent = playerWin ? '🎉 胜利！' : '😢 失败';
            title.className = 'result-title ' + (playerWin ? 'win' : 'lose');
            score.textContent = `${playerWin ? '+' : '-'}${actualScore} 分`;

            detail.innerHTML = detailText;

            modal.classList.remove('hidden');

            gameInProgress = false; // 防逃跑：游戏已结束
        }

        // ==================== 登录流程 ====================
        // 玩家道具效果
        window.WX_DDZ_PLAYER_EFFECTS = {};

        async function loadPlayerEffects() {
            if (!currentUser) return {};
            try {
                const res = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_active_effects', { credentials: 'include' });
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
                        if (item.item_type === 'title_badge' && effectData.badge) {
                            effects.titleBadge = effectData.badge;
                        }
                        if (item.item_type === 'card_back') {
                            effects.cardBack = effectData;
                        }
                        if (item.item_type === 'bomb_effect') {
                            effects.bombEffect = effectData;
                        }
                        if (item.item_type === 'emoticon') {
                            effects.emoticon = effectData;
                        }
                    } catch(e) {}
                });
                window.WX_DDZ_PLAYER_EFFECTS = effects;
                // 效果加载完成后重新渲染所有昵称位置
                if (currentUser) {
                    document.getElementById('welcomeName').innerHTML = renderPlayerName(currentUser.nickname);
                    document.getElementById('userName').innerHTML = renderPlayerName(currentUser.nickname);
                    document.getElementById('namePlayer').innerHTML = renderPlayerName(currentUser.nickname);
                }
                // 如果游戏进行中，刷新 AI 牌背
                if (gameInProgress) {
                    var leftCards = document.getElementById('cardsLeft');
                    var rightCards = document.getElementById('cardsRight');
                    if (leftCards && gameState.players[1]) {
                        leftCards.innerHTML = gameState.players[1].cards.map(function(){ return '<div class="card-back" style="' + getCardBackStyle() + '"></div>'; }).join('');
                    }
                    if (rightCards && gameState.players[2]) {
                        rightCards.innerHTML = gameState.players[2].cards.map(function(){ return '<div class="card-back" style="' + getCardBackStyle() + '"></div>'; }).join('');
                    }
                    // 刷新弹幕按钮显示
                    var emoteBtn = document.getElementById('btnEmote');
                    if (emoteBtn) {
                        emoteBtn.style.display = effects.emoticon && effects.emoticon.text ? '' : 'none';
                    }
                }
                updateBuffIndicator();
                return effects;
            } catch(e) { return {}; }
        }

        // 带道具效果渲染玩家名称
        function renderPlayerName(name) {
            const effects = window.WX_DDZ_PLAYER_EFFECTS;
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

        async function checkLoginAndStart() {
            showLoading('正在检查登录状态...');

            // 防逃跑：显示惩罚提示
            if (window.WX_DDZ_PENALTY) {
                showToast(window.WX_DDZ_PENALTY, 3000);
            }

            // 如果PHP已经注入了用户信息
            if (window.WX_DDZ_USER) {
                currentUser = window.WX_DDZ_USER;
                hideLoading();
                showWelcomeScreen();
                loadPlayerEffects(); // 异步加载道具效果
                return;
            }

            // 否则尝试通过API检查
            const result = await EmlogAPI.checkLogin();

            if (result.loggedIn) {
                currentUser = result.user;
                hideLoading();
                showWelcomeScreen();
            } else {
                hideLoading();
                showLoginForm();
            }
        }

        function showWelcomeScreen() {
            // 显示已登录用户的欢迎界面（非强制开始游戏）
            document.getElementById('loginError').classList.add('hidden');

            if (currentUser) {
                // 填充用户信息
                var avatarSrc = currentUser.avatar || generateAvatarSvg(currentUser.nickname, '#e17055');
                document.getElementById('welcomeAvatar').src = avatarSrc;
                document.getElementById('welcomeName').innerHTML = renderPlayerName(currentUser.nickname);
                document.getElementById('welcomeScore').textContent = gameState.playerScore;

                document.getElementById('loggedInPanel').classList.remove('hidden');
                document.getElementById('loginFormContainer').classList.add('hidden');
                document.getElementById('welcomeSubtitle').textContent = '🃏 欢迎回来，' + currentUser.nickname;

                // 导航栏展示完整用户信息（欢迎界面不显示"新游戏"，因为已有"开始游戏"按钮）
                updateNavUserInfo();
                document.getElementById('btnLeaderboard').classList.remove('hidden');
            } else if (isGuest) {
                updateNavUserInfo();
                document.getElementById('btnLeaderboard').classList.remove('hidden');
            }

            // 充值按钮：无配置链接时隐藏
            var rechargeBtn = document.getElementById('btnWelcomeRecharge');
            if (rechargeBtn) {
                rechargeBtn.style.display = (window.WX_DDZ_RECHARGE_LINK) ? '' : 'none';
            }

            // 填充公告和最近更新（支持换行）
            var noticeEl = document.getElementById('noticeBody');
            if (noticeEl && window.WX_DDZ_NOTICE) {
                noticeEl.innerHTML = window.WX_DDZ_NOTICE.split('\n').map(function(l) { return l.trim() ? l.trim() + '<br>' : ''; }).join('');
                document.getElementById('welcomeNotice').style.display = 'block';
            }

            var updatesEl = document.getElementById('updatesBody');
            if (updatesEl && window.WX_DDZ_UPDATES) {
                var lines = window.WX_DDZ_UPDATES.split('\n');
                updatesEl.innerHTML = lines.map(function(l) { return l.trim() ? '<div class="update-item">' + l.trim() + '</div>' : ''; }).join('');
                document.getElementById('welcomeUpdates').style.display = 'block';
            }

            document.getElementById('loginScreen').classList.remove('hidden');
            document.getElementById('gameContainer').classList.add('hidden');
            document.getElementById('loadingContainer').classList.add('hidden');
        }

        function showLoginForm() {
            document.getElementById('loginScreen').classList.remove('hidden');
            document.getElementById('gameContainer').classList.add('hidden');
            document.getElementById('loggedInPanel').classList.add('hidden');
            document.getElementById('loginFormContainer').classList.remove('hidden');
            document.getElementById('welcomeSubtitle').textContent = '🃏 欢迎来到斗地主';
            toggleNavGameUI(false);
            updateNavUserInfo(); // 确保隐藏用户info
            document.getElementById('loadingContainer').classList.add('hidden');
        }

        function hideLoginForm() {
            document.getElementById('loginScreen').classList.add('hidden');
        }

        function showLoading(text) {
            document.getElementById('loadingText').textContent = text;
            document.getElementById('loginFormContainer').classList.add('hidden');
            document.getElementById('loadingContainer').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingContainer').classList.add('hidden');
            document.getElementById('loginFormContainer').classList.remove('hidden');
        }

        async function startGame() {
            hideLoginForm();
            document.getElementById('gameContainer').classList.remove('hidden');
            AudioSystem.init();
            initTracker();
            toggleNavGameUI(true);
            updateNavUserInfo();

            // 通知服务端游戏开始（创建 pending 记录，防逃跑用）
            if (currentUser) {
                fetch('?plugin=wx_games&game=ddz&wxddz_signal=start', {
                    credentials: 'include'
                }).catch(function(e) {
                    console.warn('[AntiEscape] 开始信号发送失败:', e);
                });
            }

            // 从数据库同步用户积分
            if (currentUser) {
                const rank = await Leaderboard.getMyRank();
                if (rank && rank.score !== undefined) {
                    gameState.playerScore = rank.score;
                }
                // 加载积分加成卡
                try {
                    const buffRes = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_score_buff', { credentials: 'include' });
                    const buffData = await buffRes.json();
                    if (buffData.code === 0 && buffData.buffs && buffData.buffs.length > 0) {
                        window.WX_DDZ_ACTIVE_BUFFS = buffData.buffs;
                        // 开局即消耗 1 次，防止刷牌
                        const consumeRes = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=consume_score_buff', { credentials: 'include' });
                        const consumeData = await consumeRes.json();
                        if (consumeData.code === 0) {
                            window.WX_DDZ_ACTIVE_BUFFS = consumeData.remaining_buffs || [];
                        }
                    } else {
                        window.WX_DDZ_ACTIVE_BUFFS = [];
                    }
                } catch(e) {
                    window.WX_DDZ_ACTIVE_BUFFS = [];
                }
            }

            // 更新积分显示
            document.getElementById('playerScore').textContent = gameState.playerScore;

            // 更新加成卡指示器
            updateBuffIndicator();

            initGame();
        }

        // 更新加成卡指示器（游戏内 + 欢迎界面）
        function updateBuffIndicator() {
            var buffs = window.WX_DDZ_ACTIVE_BUFFS || [];
            var indicator = document.getElementById('buffIndicator');
            var welcomeBuff = document.getElementById('welcomeBuffInfo');
            var navBuff = document.getElementById('navBuffInfo');

            if (buffs.length > 0) {
                var b = buffs[0];
                var text = '⚡ 加成卡 x' + b.multiplier + ' (剩' + b.remaining + '局)';
                if (indicator) {
                    indicator.classList.remove('hidden');
                    document.getElementById('buffIndicatorText').textContent = text;
                }
                if (welcomeBuff) {
                    welcomeBuff.textContent = text;
                    welcomeBuff.style.color = '#f39c12';
                    welcomeBuff.style.fontWeight = 'bold';
                }
                if (navBuff) navBuff.textContent = text;
            } else {
                if (indicator) indicator.classList.add('hidden');
                if (welcomeBuff) {
                    welcomeBuff.textContent = '👋🏻 目前没有应用积分卡，可在商城购买';
                    welcomeBuff.style.color = '#aaa';
                    welcomeBuff.style.fontWeight = 'normal';
                }
            }
        }

        // ==================== 事件绑定 ====================
        // 开始游戏按钮（欢迎界面）
        document.getElementById('btnStartGame').addEventListener('click', () => {
            hideLoginForm();
            startGame();
        });

        // 游客模式按钮
        const btnGuestEl = document.getElementById('btnGuest');
        if (btnGuestEl && window.WX_DDZ_GUEST_PLAY === '1') {
            btnGuestEl.addEventListener('click', () => {
                isGuest = true;
                currentUser = null;
                hideLoginForm();
                startGame();
            });
        }

        document.getElementById('btnPlay').addEventListener('click', playerPlay);
        document.getElementById('btnPass').addEventListener('click', playerPass);

        // 专属弹幕按钮
        document.getElementById('btnEmote').addEventListener('click', function() {
            const effects = window.WX_DDZ_PLAYER_EFFECTS || {};
            const emote = effects.emoticon;
            if (!emote || !emote.text) return;
            const bubble = document.getElementById('speechBubblePlayer');
            const textEl = document.getElementById('speechBubblePlayerText');
            if (!bubble || !textEl) return;
            textEl.textContent = emote.text;
            bubble.classList.remove('hidden');
            bubble.classList.remove('speech-animate');
            void bubble.offsetWidth;
            bubble.classList.add('speech-animate');
            if (bubble._hideTimer) clearTimeout(bubble._hideTimer);
            bubble._hideTimer = setTimeout(function() {
                bubble.classList.add('hidden');
                bubble.classList.remove('speech-animate');
            }, 3000);
        });

        document.getElementById('btnNoBid').addEventListener('click', () => {
            document.getElementById('bidButtons').classList.add('hidden');
            showToast('不叫');
            nextBidder();
        });

        document.getElementById('btnBid1').addEventListener('click', () => {
            if (1 > gameState.bidScore) {
                gameState.bidScore = 1;
                document.getElementById('bidButtons').classList.add('hidden');
                showToast('1分');
                becomeLandlord(0);
            }
        });

        document.getElementById('btnBid2').addEventListener('click', () => {
            if (2 > gameState.bidScore) {
                gameState.bidScore = 2;
                document.getElementById('bidButtons').classList.add('hidden');
                showToast('2分');
                becomeLandlord(0);
            }
        });

        document.getElementById('btnBid3').addEventListener('click', () => {
            if (3 > gameState.bidScore) {
                gameState.bidScore = 3;
                document.getElementById('bidButtons').classList.add('hidden');
                showToast('3分');
                becomeLandlord(0);
            }
        });

        document.getElementById('btnNewGame').addEventListener('click', () => {
            // 如果游戏进行中，先确认惩罚
            if (gameInProgress && currentUser) {
                var multi = gameState.multiplier || 1;
                var penalty = 100 * multi * (EMLOG_CONFIG.penaltyMultiplier || 1);
                var msg = '⚠️ 放弃当前对局\n\n';
                msg += '开启新游戏、刷新页面、退出页面时，本局即算失败。\n';
                msg += '计算公式：100 × 游戏倍率(' + multi + ') × 惩罚系数(' + (EMLOG_CONFIG.penaltyMultiplier || 1).toFixed(1) + ')\n';
                msg += '将扣除 ' + penalty + ' 积分。\n\n';
                msg += '确定要放弃吗？';

                if (!confirm(msg)) return;

                // 发送惩罚信号
                navigator.sendBeacon('?plugin=wx_games&game=ddz&wxddz_signal=penalty&points=' + penalty);

                // 本地更新积分显示
                gameState.playerScore -= penalty;
                document.getElementById('playerScore').textContent = gameState.playerScore;

                // 标记游戏结束
                gameInProgress = false;
            }

            document.getElementById('resultModal').classList.add('hidden');
            startGame();
        });

        document.getElementById('btnPlayAgain').addEventListener('click', () => {
            document.getElementById('resultModal').classList.add('hidden');
            initGame();
        });

        // 停止对局按钮：返回欢迎界面，需手动确认才可开始新游戏
        document.getElementById('btnStopGame').addEventListener('click', () => {
            document.getElementById('resultModal').classList.add('hidden');
            toggleNavGameUI(false);
            if (currentUser) {
                showWelcomeScreen();
            } else {
                showLoginForm();
            }
        });

        document.getElementById('btnLeaderboard').addEventListener('click', () => {
            document.getElementById('lbModeName').textContent = GAME_MODES[currentGameMode].name;
            Leaderboard.show();
        });

        document.getElementById('btnCloseLeaderboard').addEventListener('click', () => {
            document.getElementById('leaderboardModal').classList.add('hidden');
        });

        // ==================== 积分流水 ====================
        const ScoreLog = {
            async show() {
                const list = document.getElementById('scoreLogList');
                list.innerHTML = '<div style="text-align: center; color: #aaa; padding: 20px;">加载中...</div>';
                document.getElementById('scoreLogModal').classList.remove('hidden');

                // 从服务端获取最新流水记录
                try {
                    const uid = currentUser ? currentUser.uid : 0;
                    if (!uid) {
                        list.innerHTML = '<div style="text-align: center; color: #aaa; padding: 20px;">请先登录</div>';
                        return;
                    }
                    const res = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_user_logs&uid=' + uid, { credentials: 'include' });
                    const data = await res.json();

                    if (data.code !== 0 || !data.data || data.data.length === 0) {
                        list.innerHTML = '<div style="text-align: center; color: #aaa; padding: 20px;">暂无流水记录<br><small style="color:#666;">游戏结束后会自动记录积分变动</small></div>';
                        return;
                    }

                    const logs = data.data;
                    list.innerHTML = logs.map(function(item) {
                        const change = parseInt(item.score_change);
                        const sign = change >= 0 ? '+' : '';
                        const color = change >= 0 ? '#2ecc71' : '#e74c3c';
                        const timeStr = item.time || '';
                        return '<div class="score-log-item">' +
                            '<span class="log-reason">' + (item.reason || '游戏结算') + '</span>' +
                            '<span class="log-time">' + timeStr + '</span>' +
                            '<span class="log-change" style="color:' + color + ';font-weight:bold;">' + sign + change + '</span>' +
                            '</div>';
                    }).join('');
                } catch (e) {
                    list.innerHTML = '<div style="text-align: center; color: #e74c3c; padding: 20px;">加载失败，请重试</div>';
                }
            }
        };

        // 积分点击弹出流水（游戏内积分 + 导航栏积分）
        document.getElementById('playerScore').addEventListener('click', function() {
            if (currentUser) ScoreLog.show();
        });
        document.getElementById('navScoreBox').addEventListener('click', function() {
            if (currentUser) ScoreLog.show();
        });

        document.getElementById('btnCloseScoreLog').addEventListener('click', () => {
            document.getElementById('scoreLogModal').classList.add('hidden');
        });

        // ==================== 商城 ====================
        const SHOP_TYPE_NAMES = {
            'title_colored': '昵称变色',
            'title_effect': '昵称特效',
            'card_back': '牌背皮肤',
            'emoticon': '专属表情',
            'bomb_effect': '炸弹特效',
            'score_buff': '积分加成卡',
            'title_badge': '称号徽章'
        };
        const SHOP_TYPE_ICONS = {
            'title_colored': '🎨',
            'title_effect': '✨',
            'card_back': '🃏',
            'emoticon': '😎',
            'bomb_effect': '💥',
            'score_buff': '⚡',
            'title_badge': '👑'
        };

        const ShopManager = {
            currentFilter: 'all',
            allItems: [],
            async show() {
                const modal = document.getElementById('shopModal');
                modal.classList.remove('hidden');
                const list = document.getElementById('shopItemsList');
                list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';

                // 更新积分显示
                this.updateScoreDisplay();

                try {
                    const res = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_shop_items', { credentials: 'include' });
                    const data = await res.json();
                    if (data.code !== 0 || !data.data || !data.data.items) {
                        list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">加载商品失败</div>';
                        return;
                    }
                    this.allItems = data.data.items;
                    this.currentFilter = 'all';
                    this.renderFilterBar();
                    this.renderItems();
                } catch (e) {
                    list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">网络错误，请重试</div>';
                }
            },
            renderFilterBar() {
                const list = document.getElementById('shopItemsList');
                const bar = document.createElement('div');
                bar.id = 'shopFilterBar';
                bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
                var self = this;
                // 收集所有出现的道具类型
                var types = ['all'];
                this.allItems.forEach(function(item) {
                    if (types.indexOf(item.item_type) === -1) types.push(item.item_type);
                });
                types.forEach(function(key) {
                    const btn = document.createElement('button');
                    btn.style.cssText = 'font-size:10px;padding:3px 8px;border-radius:12px;border:none;cursor:pointer;transition:all 0.2s;white-space:nowrap;';
                    if (key === 'all') {
                        btn.textContent = '全部';
                        btn.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                        btn.style.color = '#fff';
                    } else {
                        var icon = SHOP_TYPE_ICONS[key] || '🎁';
                        var name = SHOP_TYPE_NAMES[key] || key;
                        btn.textContent = icon + ' ' + name;
                        btn.style.background = 'rgba(255,255,255,0.1)';
                        btn.style.color = '#ccc';
                    }
                    btn.dataset.filter = key;
                    btn.addEventListener('click', function() {
                        self.currentFilter = this.dataset.filter;
                        // 重置所有按钮样式
                        bar.querySelectorAll('button').forEach(function(b) {
                            if (b.dataset.filter === 'all') {
                                b.style.background = 'rgba(255,255,255,0.1)';
                                b.style.color = '#ccc';
                            } else {
                                b.style.background = 'rgba(255,255,255,0.1)';
                                b.style.color = '#ccc';
                            }
                        });
                        // 高亮当前选中
                        this.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                        this.style.color = '#fff';
                        // "全部"也要高亮
                        if (this.dataset.filter === 'all') {
                            bar.querySelectorAll('button').forEach(function(b) {
                                if (b.dataset.filter === 'all') {
                                    b.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                                    b.style.color = '#fff';
                                }
                            });
                        }
                        self.renderItems();
                    });
                    bar.appendChild(btn);
                });
                // 移除旧的筛选栏
                var oldBar = document.getElementById('shopFilterBar');
                if (oldBar) oldBar.remove();
                list.parentNode.insertBefore(bar, list);
            },
            renderItems() {
                const list = document.getElementById('shopItemsList');
                const filtered = this.currentFilter === 'all'
                    ? this.allItems
                    : this.allItems.filter(function(item) { return item.item_type === ShopManager.currentFilter; });
                if (filtered.length === 0) {
                    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">该分类暂无商品</div>';
                    return;
                }
                list.innerHTML = filtered.map(function(item) {
                    const hasEmlog = item.price_emlog > 0;
                    const hasDdz = item.price_ddz > 0;
                    let priceHtml = '';
                    if (hasEmlog && hasDdz) {
                        priceHtml = '站点积分 ' + item.price_emlog + ' + 斗地主 ' + item.price_ddz;
                    } else if (hasEmlog) {
                        priceHtml = '站点积分 ' + item.price_emlog;
                    } else if (hasDdz) {
                        priceHtml = '斗地主 ' + item.price_ddz;
                    }
                    return '<div class="shop-item">' +
                        '<span style="font-size:22px;">' + (item.icon || '🎁') + '</span>' +
                        '<div style="flex:1;min-width:0;">' +
                            '<div style="font-weight:bold;font-size:13px;">' + item.name + '</div>' +
                            '<div style="font-size:10px;color:#aaa;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">' + (item.description || '') + '</div>' +
                        '</div>' +
                        '<div style="text-align:right;flex-shrink:0;">' +
                            '<div style="font-size:11px;margin-bottom:3px;">' + priceHtml + '</div>' +
                            '<button class="btn btn-primary shop-buy-btn" style="font-size:10px;padding:2px 8px;" data-id="' + item.id + '" data-emlog="' + item.price_emlog + '" data-ddz="' + item.price_ddz + '">购买</button>' +
                        '</div>' +
                    '</div>';
                }).join('');

                // 绑定购买按钮
                list.querySelectorAll('.shop-buy-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        ShopManager.buyItem(parseInt(this.dataset.id), parseInt(this.dataset.emlog), parseInt(this.dataset.ddz));
                    });
                });
            },
            updateScoreDisplay() {
                const ddzScore = window.WX_DDZ_USER_SCORE ? window.WX_DDZ_USER_SCORE.score : 0;
                const emlogCredits = window.WX_DDZ_EMLOG_CREDITS || 0;
                document.getElementById('shopDdzScore').textContent = ddzScore;
                document.getElementById('shopEmlogCredits').textContent = emlogCredits;
                // 同步更新导航栏积分
                const scoreEl = document.getElementById('playerScore');
                if (scoreEl) scoreEl.textContent = ddzScore;
            },
            async buyItem(itemId, priceEmlog, priceDdz) {
                if (!currentUser) {
                    showToast('请先登录');
                    return;
                }
                // 双货币商品需要同时扣除两种积分
                let payType = '';
                if (priceEmlog > 0 && priceDdz > 0) {
                    if (!confirm('此商品需同时消耗 站点积分 ' + priceEmlog + ' 和 斗地主积分 ' + priceDdz + '，确认购买？')) return;
                    payType = 'both';
                } else if (priceEmlog > 0) {
                    if (!confirm('确认使用 站点积分 ' + priceEmlog + ' 购买此商品？')) return;
                    payType = 'emlog';
                } else if (priceDdz > 0) {
                    if (!confirm('确认使用 斗地主积分 ' + priceDdz + ' 购买此商品？')) return;
                    payType = 'ddz';
                } else {
                    showShopFeedback('⚠️', '定价异常', '该商品未设置价格');
                    return;
                }

                const formData = new FormData();
                formData.append('item_id', itemId);
                formData.append('pay_type', payType);

                try {
                    const res = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=purchase_item', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    });
                    const data = await res.json();
                    if (data.code === 0) {
                        // 重新拉取用户积分信息
                        try {
                            const scoreRes = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_my_rank', { credentials: 'include' });
                            const scoreData = await scoreRes.json();
                            if (scoreData.code === 0 && scoreData.data) {
                                window.WX_DDZ_USER_SCORE = scoreData.data;
                                const newScore = scoreData.data.score || 0;
                                document.getElementById('playerScore').textContent = newScore;
                                document.getElementById('welcomeScore').textContent = newScore;
                            }
                        } catch(e) {}
                        try {
                            const emlogRes = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_my_emlog_credits', { credentials: 'include' });
                            const emlogData = await emlogRes.json();
                            if (emlogData.code === 0) {
                                window.WX_DDZ_EMLOG_CREDITS = emlogData.credits;
                            }
                        } catch(e) {}
                        this.updateScoreDisplay();
                        showShopFeedback('🎉', '购买成功', data.msg || '积分已扣除，道具已发放到背包');
                    } else {
                        showShopFeedback('❌', '购买失败', data.msg || '未知错误');
                    }
                } catch (e) {
                    showShopFeedback('❌', '网络错误', '请检查网络连接后重试');
                }
            }
        };

        document.getElementById('btnShop').addEventListener('click', () => {
            ShopManager.show();
        });
        document.getElementById('btnCloseShop').addEventListener('click', () => {
            document.getElementById('shopModal').classList.add('hidden');
        });

        // ==================== 背包 ====================
        const InventoryManager = {
            currentFilter: 'all',
            allItems: [],
            async show() {
                if (!currentUser) {
                    showToast('请先登录');
                    return;
                }
                const modal = document.getElementById('inventoryModal');
                modal.classList.remove('hidden');
                const list = document.getElementById('inventoryList');
                list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';

                try {
                    const res = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=get_inventory', { credentials: 'include' });
                    const data = await res.json();
                    if (data.code !== 0 || !data.data || !data.data.items) {
                        list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">加载失败</div>';
                        return;
                    }
                    this.allItems = data.data.items;
                    this.currentFilter = 'all';
                    this.renderFilterBar();
                    this.renderItems();
                } catch (e) {
                    list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:30px;">网络错误</div>';
                }
            },
            renderFilterBar() {
                const list = document.getElementById('inventoryList');
                var oldBar = document.getElementById('invFilterBar');
                if (oldBar) oldBar.remove();
                const bar = document.createElement('div');
                bar.id = 'invFilterBar';
                bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
                var self = this;
                var types = ['all'];
                this.allItems.forEach(function(item) {
                    if (types.indexOf(item.item_type) === -1) types.push(item.item_type);
                });
                types.forEach(function(key) {
                    const btn = document.createElement('button');
                    btn.style.cssText = 'font-size:10px;padding:3px 8px;border-radius:12px;border:none;cursor:pointer;transition:all 0.2s;white-space:nowrap;';
                    if (key === 'all') {
                        btn.textContent = '全部';
                        btn.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                        btn.style.color = '#fff';
                    } else {
                        var icon = SHOP_TYPE_ICONS[key] || '🎁';
                        var name = SHOP_TYPE_NAMES[key] || key;
                        btn.textContent = icon + ' ' + name;
                        btn.style.background = 'rgba(255,255,255,0.1)';
                        btn.style.color = '#ccc';
                    }
                    btn.dataset.filter = key;
                    btn.addEventListener('click', function() {
                        self.currentFilter = this.dataset.filter;
                        bar.querySelectorAll('button').forEach(function(b) {
                            if (b.dataset.filter === 'all') {
                                b.style.background = 'rgba(255,255,255,0.1)'; b.style.color = '#ccc';
                            } else {
                                b.style.background = 'rgba(255,255,255,0.1)'; b.style.color = '#ccc';
                            }
                        });
                        this.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)';
                        this.style.color = '#fff';
                        if (this.dataset.filter === 'all') {
                            bar.querySelectorAll('button').forEach(function(b) {
                                if (b.dataset.filter === 'all') { b.style.background = 'linear-gradient(135deg,#e17055,#fdcb6e)'; b.style.color = '#fff'; }
                            });
                        }
                        self.renderItems();
                    });
                    bar.appendChild(btn);
                });
                list.parentNode.insertBefore(bar, list);
            },
            renderItems() {
                const list = document.getElementById('inventoryList');
                const filtered = this.currentFilter === 'all'
                    ? this.allItems
                    : this.allItems.filter(function(item) { return item.item_type === InventoryManager.currentFilter; });
                if (filtered.length === 0) {
                    list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">该分类暂无道具</div>';
                    return;
                }
                list.innerHTML = filtered.map(function(item) {
                    const cosmeticTypes = ['title_colored', 'title_effect', 'card_back', 'emoticon', 'bomb_effect', 'title_badge'];
                    const isCosmetic = cosmeticTypes.indexOf(item.item_type) !== -1;
                    let btnHtml = '';
                    if (item.is_active) {
                        btnHtml = '<span style="font-size:10px;padding:2px 8px;background:rgba(46,204,113,0.2);color:#2ecc71;border-radius:8px;border:1px solid #2ecc71;white-space:nowrap;">✓ 已激活</span>';
                    } else if (isCosmetic) {
                        btnHtml = '<button class="btn btn-primary use-item-btn" style="font-size:10px;padding:2px 8px;" data-inv_id="' + item.inv_id + '">🎯 激活</button>';
                    } else {
                        btnHtml = '<button class="btn btn-primary use-item-btn" style="font-size:10px;padding:2px 8px;" data-inv_id="' + item.inv_id + '">使用</button>';
                    }
                    return '<div class="shop-item">' +
                        '<span style="font-size:22px;">' + (item.icon || '🎁') + '</span>' +
                        '<div style="flex:1;min-width:0;">' +
                            '<div style="font-weight:bold;font-size:13px;">' + item.name + '</div>' +
                            '<div style="font-size:10px;color:#aaa;">剩余 x' + item.quantity + '</div>' +
                        '</div>' +
                        btnHtml +
                    '</div>';
                }).join('');

                list.querySelectorAll('.use-item-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        InventoryManager.useItem(parseInt(this.dataset.inv_id), this);
                    });
                });
            },
            async useItem(invId, btnEl) {
                if (!confirm('确认使用此道具？')) return;
                const formData = new FormData();
                formData.append('inv_id', invId);
                try {
                    const res = await fetch(EMLOG_CONFIG.leaderboardApi + '&ddz_action=use_item', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
                    });
                    const data = await res.json();
                    if (data.code === 0) {
                        showToast(data.msg || '✅ 使用成功');
                        // 刷新背包
                        this.show();
                        // 重新加载道具效果
                        await loadPlayerEffects();
                        // 再次刷新背包确保激活状态显示正确
                        this.show();
                    } else {
                        showToast(data.msg || '使用失败');
                    }
                } catch (e) {
                    showToast('网络错误');
                }
            }
        };

        document.getElementById('btnInventory').addEventListener('click', () => {
            InventoryManager.show();
        });
        document.getElementById('btnCloseInventory').addEventListener('click', () => {
            document.getElementById('inventoryModal').classList.add('hidden');
        });

        // 购买反馈弹窗
        function showShopFeedback(icon, title, msg) {
            document.getElementById('shopFeedbackIcon').textContent = icon;
            document.getElementById('shopFeedbackTitle').textContent = title;
            document.getElementById('shopFeedbackMsg').textContent = msg;
            document.getElementById('shopFeedbackModal').classList.remove('hidden');
        }
        document.getElementById('btnShopFeedbackClose').addEventListener('click', () => {
            document.getElementById('shopFeedbackModal').classList.add('hidden');
        });

        // 欢迎界面商城/背包/充值
        document.getElementById('btnWelcomeShop').addEventListener('click', () => {
            ShopManager.show();
        });
        document.getElementById('btnWelcomeInventory').addEventListener('click', () => {
            InventoryManager.show();
        });
        document.getElementById('btnWelcomeRecharge').addEventListener('click', () => {
            var link = window.WX_DDZ_RECHARGE_LINK;
            if (link) {
                window.open(link, '_blank');
            } else {
                showToast('暂未配置充值链接');
            }
        });

        // 初始化：检查登录状态
        checkLoginAndStart();
    </script>

    <script>
    // 导航栏滚动效果
    (function() {
        var nav = document.getElementById('ddzNav');
        if (!nav) return;
        var scrollThreshold = 10;
        var ticking = false;
        function updateNav() {
            if (window.scrollY > scrollThreshold) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
            ticking = false;
        }
        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(updateNav);
                ticking = true;
            }
        }, { passive: true });
    })();
    </script>
</body>
</html>
