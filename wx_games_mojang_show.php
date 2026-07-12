<?php
/**
 * wx_mojang - 前台游戏页面
 * 国标麻将 H5 游戏界面
 */
!defined('EMLOG_ROOT') && exit('access denied!');

// 获取配置和数据
$config = wx_mojang_get_config();
$userData = wx_mojang_check_user();
$uid = $userData['uid'];
// 解析头像完整URL
$userAvatar = '';
if ($uid > 0 && !empty($userData['avatar'])) {
    if (strpos($userData['avatar'], 'http') === 0) {
        $userAvatar = $userData['avatar'];
    } else {
        $userAvatar = BLOG_URL . ltrim($userData['avatar'], './');
    }
}
$userScore = ($uid > 0) ? (wx_mojang_get_user_score($uid) ?: ['score' => 0, 'total_games' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'best_score' => 0, 'max_fan' => 0]) : ['score' => 0, 'total_games' => 0, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'best_score' => 0, 'max_fan' => 0];
$aiPlayers = wx_mojang_get_ai_players();
$pluginUrl = WX_MOJANG_URL;
$assetsUrl = $pluginUrl . 'assets/';
// 转为 player1/player2 键名格式（前端 _selectRandomAI 通过 player1~player6 键名访问）
// 同时补全头像路径为完整URL
if (!empty($aiPlayers) && isset($aiPlayers[0])) {
    $keyed = [];
    foreach ($aiPlayers as $i => $player) {
        $player['avatar'] = $player['avatar'] ? $assetsUrl . $player['avatar'] : '';
        $keyed['player' . ($i + 1)] = $player;
    }
    $aiPlayers = $keyed;
}

// 检查未完成的游戏（防逃跑检测）
$penaltyMsg = '';
if ($uid > 0) {
    $db = Database::getInstance();
    $table = DB_PREFIX . 'wx_mojang_games';
    $pending = $db->once_fetch_array(
        "SELECT * FROM `{$table}` WHERE uid={$uid} AND status=1 AND created > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY created DESC LIMIT 1"
    );
    if ($pending) {
        $penalty = -(int)$config['base_score'] * $config['penalty_multiplier'];
        $db->query("UPDATE `{$table}` SET status=0 WHERE uid={$uid} AND status=1");
        wx_mojang_apply_penalty($uid);
        $penaltyMsg = "检测到未完成的游戏，已自动惩罚：{$penalty} 积分";
    }
}

// 获取Emlog站点积分
$emlogCredits = 0;
if ($uid > 0) {
    $userModel = new User_Model();
    $user = $userModel->getOneUser($uid);
    $emlogCredits = (int)($user['credits'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= $config['title'] ?></title>
    <link rel="stylesheet" href="<?= $pluginUrl ?>css/style.css?v=1.3.1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
</head>
<body class="mj-page">
<!-- 数据注入 -->
<script>
window.MJ_CONFIG = <?= json_encode([
    'title' => $config['title'],
    'baseScore' => (int)$config['base_score'],
    'penaltyMultiplier' => (float)$config['penalty_multiplier'],
    'minFanToWin' => (int)$config['min_fan_to_win'],
    'pluginUrl' => $pluginUrl,
    'tilesUrl' => $pluginUrl . 'assets/Mahjong/2/',
]) ?>;
window.MJ_USER = <?= json_encode([
    'uid' => $uid,
    'nickname' => $userData['nickname'] ?: '游客',
    'avatar' => $userAvatar,
]) ?>;
window.MJ_USER_SCORE = <?= json_encode($userScore) ?>;
window.MJ_EMLOG_CREDITS = <?= $emlogCredits ?>;
window.MJ_PENALTY_MSG = <?= json_encode($penaltyMsg) ?>;
window.MJ_NOTICE = <?= json_encode($config['notice'] ?? '') ?>;
window.MJ_UPDATES = <?= json_encode($config['recent_updates'] ?? '') ?>;
window.MJ_RECHARGE_LINK = <?= json_encode($config['recharge_link'] ?? '') ?>;
window.MJ_AI_PLAYERS = <?= json_encode($aiPlayers) ?>;
window.MJ_GUEST_PLAY = <?= $config['guest_play'] == '1' ? 'true' : 'false' ?>;
</script>

<!-- 顶部导航（ddz 风格） -->
<nav class="mj-nav" id="mjNav">
    <div class="mj-nav-inner">
        <div class="mj-nav-left">
            <span class="mj-nav-icon">🀄</span>
            <h1 class="mj-nav-title"><?= htmlspecialchars($config['title']) ?></h1>
        </div>
        <div class="mj-nav-right" id="mjNavRight">
            <?php if ($uid > 0): ?>
            <div class="nav-user-info" id="navUserInfo">
                <?php if ($userAvatar): ?>
                    <img src="<?= $userAvatar ?>" class="nav-avatar" alt="">
                <?php else: ?>
                    <span class="nav-avatar-placeholder">👤</span>
                <?php endif; ?>
                <span class="nav-user-name"><?= htmlspecialchars($userData['nickname']) ?></span>
            </div>
            <div class="nav-score" id="navScoreBox" title="点击查看积分流水">
                <span class="nav-score-label">积分:</span>
                <span class="nav-score-value" id="navUserScore"><?= (int)($userScore['score'] ?? 0) ?></span>
            </div>
            <?php else: ?>
            <a href="<?= BLOG_URL ?>admin/account.php?action=signin" class="nav-btn nav-home-btn">登 录</a>
            <?php endif; ?>
            <button class="nav-btn" id="btnLeaderboard" onclick="Leaderboard.show()">🏆 <span class="nav-btn-text">排行</span></button>
            <button class="nav-btn" onclick="showFanTypes()">🀄 <span class="nav-btn-text">番型</span></button>
            <button class="nav-btn nav-home-btn" id="btnReturnHome" onclick="return confirm('确定要返回首页吗？') &amp;&amp; (window.location.href='<?php echo BLOG_URL; ?>')">返回首页</button>
        </div>
    </div>
</nav>

<!-- 惩罚消息 -->
<?php if ($penaltyMsg): ?>
<script>alert(<?= json_encode($penaltyMsg) ?>);</script>
<?php endif; ?>

<!-- ====== 主内容 ====== -->
<div id="mjApp">
    <!-- 由JS动态渲染 -->
</div>

<!-- ====== 商城弹窗（ddz 风格） ====== -->
<div class="mj-list-modal hidden" id="shopModal">
    <div class="mj-list-content" onclick="event.stopPropagation()" style="max-width:500px;">
        <div class="list-title">🛒 道具商城</div>
        <div style="display:flex;gap:12px;justify-content:center;margin-bottom:15px;font-size:13px;color:#ccc;flex-wrap:wrap;">
            <span>麻将积分: <strong id="shopMjScore" style="color:#ffd700">0</strong></span>
            <span>站点积分: <strong id="shopEmlogCredits" style="color:#4fc3f7">0</strong></span>
        </div>
        <div class="list-body" id="shopItemsList">
            <div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>
        </div>
        <div style="text-align:center;margin-top:20px;">
            <button class="btn btn-primary" id="btnCloseShop">关闭</button>
        </div>
    </div>
</div>

<!-- ====== 背包弹窗（ddz 风格） ====== -->
<div class="mj-list-modal hidden" id="inventoryModal">
    <div class="mj-list-content" onclick="event.stopPropagation()" style="width:90vw;max-width:500px;">
        <div class="list-title">🎒 我的背包</div>
        <div class="list-body" id="inventoryList">
            <div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>
        </div>
        <div style="text-align:center;margin-top:20px;">
            <button class="btn btn-primary" id="btnCloseInventory">关闭</button>
        </div>
    </div>
</div>

<!-- ====== 积分流水弹窗（ddz 风格） ====== -->
<div class="mj-list-modal hidden" id="scoreLogModal">
    <div class="mj-list-content" onclick="event.stopPropagation()" style="width:90vw;max-width:500px;">
        <div class="list-title">📊 积分流水</div>
        <div class="score-log-list" id="scoreLogList">
            <div style="text-align:center;color:#aaa;padding:20px;">暂无记录</div>
        </div>
        <div style="text-align:center;margin-top:20px;">
            <button class="btn btn-primary" id="btnCloseScoreLog">关闭</button>
        </div>
    </div>
</div>

<!-- ====== 购买反馈弹窗（ddz 风格） ====== -->
<div class="mj-result-modal hidden" id="shopFeedbackModal">
    <div class="mj-result-content" onclick="event.stopPropagation()" style="max-width:360px;">
        <div style="font-size:48px;margin-bottom:5px;" id="feedbackIcon">🎉</div>
        <h3 id="feedbackTitle" style="margin:8px 0;color:#ffd700;">购买成功</h3>
        <p id="feedbackMsg" style="color:rgba(255,255,255,0.7);font-size:14px;">积分已扣除，道具已发放到背包</p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <button class="btn btn-primary" onclick="document.getElementById('shopFeedbackModal').classList.add('hidden')">确 定</button>
        </div>
    </div>
</div>

<!-- ====== JS文件 ====== -->
<script src="<?= $pluginUrl ?>js/config.js?v=1.3.1"></script>
<script src="<?= $pluginUrl ?>js/game-engine.js?v=1.3.1"></script>
<script src="<?= $pluginUrl ?>js/ai-quotes.js?v=1.3.1"></script>
<script src="<?= $pluginUrl ?>js/leaderboard.js?v=1.3.1"></script>
<script src="<?= $pluginUrl ?>js/audio.js?v=1.3.1"></script>
<script src="<?= $pluginUrl ?>js/emlog-api.js?v=1.3.1"></script>

<script>
// ====== 道具类型名称与图标 ======
const SHOP_TYPE_NAMES = {
    'title_colored': '昵称变色',
    'title_effect': '昵称特效',
    'title_badge': '称号徽章',
    'card_back': '牌背皮肤',
    'win_effect': '胡牌特效',
    'emoticon': '专属表情',
    'score_buff': '积分加成卡'
};

const SHOP_TYPE_ICONS = {
    'title_colored': '🎨',
    'title_effect': '✨',
    'title_badge': '👑',
    'card_back': '🀄',
    'win_effect': '💥',
    'emoticon': '😎',
    'score_buff': '⚡'
};

// ====== 当前用户状态 ======
let currentUser = null;
if (MJ_USER && MJ_USER.uid > 0) {
    currentUser = { uid: MJ_USER.uid, nickname: MJ_USER.nickname, avatar: MJ_USER.avatar };
}

// ====== 全局Toast函数 ======
function showToast(msg, duration) {
    const existing = document.querySelector('.mj-toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'mj-toast';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), duration || 2000);
}

// ====== 商店管理器 ======
const ShopManager = {
    currentFilter: 'all',
    allItems: [],

    async show() {
        if (!currentUser) {
            showToast('请先登录');
            return;
        }
        closeModal();
        document.getElementById('shopModal').classList.remove('hidden');
        const list = document.getElementById('shopItemsList');
        list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
        this.updateScoreDisplay();

        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=get_shop_items', { credentials: 'include' });
            const data = await res.json();
            if (data.code !== 0 || !data.data || !data.data.items) {
                list.innerHTML = '<div style="text-align:center;color:var(--accent-red);padding:30px;">加载商品失败</div>';
                return;
            }
            this.allItems = data.data.items;
            this.currentFilter = 'all';
            this.renderFilterBar();
            this.renderItems();
        } catch (e) {
            list.innerHTML = '<div style="text-align:center;color:var(--accent-red);padding:30px;">网络错误，请重试</div>';
        }
    },

    renderFilterBar() {
        const list = document.getElementById('shopItemsList');
        const existing = document.getElementById('shopFilterBar');
        if (existing) existing.remove();
        const bar = document.createElement('div');
        bar.id = 'shopFilterBar';
        bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
        const types = ['all', ...new Set(this.allItems.map(i => i.item_type))];
        types.forEach(key => {
            const btn = document.createElement('button');
            btn.className = 'mj-btn mj-btn-sm';
            if (key === 'all') {
                btn.textContent = '全部';
                btn.className += ' mj-btn-primary';
            } else {
                btn.textContent = (SHOP_TYPE_ICONS[key] || '🎁') + ' ' + (SHOP_TYPE_NAMES[key] || key);
                btn.className += ' mj-btn-outline';
            }
            btn.dataset.filter = key;
            btn.onclick = () => {
                this.currentFilter = key;
                bar.querySelectorAll('button').forEach(b => { b.className = 'mj-btn mj-btn-sm mj-btn-outline'; });
                btn.className = 'mj-btn mj-btn-sm mj-btn-primary';
                this.renderItems();
            };
            bar.appendChild(btn);
        });
        list.parentNode.insertBefore(bar, list);
    },

    renderItems() {
        const list = document.getElementById('shopItemsList');
        const filtered = this.currentFilter === 'all' ? this.allItems : this.allItems.filter(i => i.item_type === this.currentFilter);
        if (filtered.length === 0) {
            list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;">该分类暂无商品</div>';
            return;
        }
        list.innerHTML = filtered.map(item => {
            let priceParts = [];
            if (parseInt(item.price_majiang) > 0) priceParts.push('麻将 ' + item.price_majiang);
            if (parseInt(item.price_emlog) > 0) priceParts.push('站点 ' + item.price_emlog);
            const priceHtml = priceParts.join(' + ') || '免费';
            let limitHtml = '';
            if (item.stock >= 0) limitHtml += '<span style="margin-left:4px;">库存' + item.stock + '</span>';
            if (item.max_per_user > 0) limitHtml += '<span style="margin-left:4px;">限' + item.max_per_user + '</span>';
            const itemId = item.id;
            return '<div class="shop-item" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;margin-bottom:6px;background:rgba(255,255,255,0.06);">' +
                '<span style="font-size:22px;">' + (item.icon || '🎁') + '</span>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:bold;font-size:13px;">' + item.name + (item.is_global ? ' <span style="font-size:9px;color:#fdcb6e;border:1px solid #fdcb6e;border-radius:4px;padding:0 4px;vertical-align:middle;">通用</span>' : '') + '</div>' +
                    '<div style="font-size:10px;color:var(--text-muted);">' +
                        '<span class="shop-desc" id="shopDesc_' + itemId + '" data-expanded="false">' + (item.description || '') + '</span>' +
                        (item.description && item.description.length > 50 ? '<span class="shop-desc-toggle" id="shopDescToggle_' + itemId + '" onclick="toggleShopDesc(' + itemId + ')" style="color:var(--accent-blue);cursor:pointer;margin-left:2px;">展开</span>' : '') +
                        limitHtml +
                    '</div>' +
                '</div>' +
                '<div style="text-align:right;flex-shrink:0;">' +
                    '<div style="font-size:11px;margin-bottom:3px;color:var(--text-secondary);">' + priceHtml + '</div>' +
                    (item.owned
                        ? '<span style="display:inline-block;font-size:10px;padding:2px 8px;background:rgba(46,204,113,0.15);color:#2ecc71;border-radius:8px;border:1px solid #2ecc71;">✓ 已拥有</span>'
                        : '<button class="mj-btn mj-btn-primary mj-btn-sm" onclick="ShopManager.buyItem(' + item.id + ',' + item.price_majiang + ',' + item.price_emlog + ')">购买</button>'
                    ) +
                '</div></div>';
        }).join('');
    },

    updateScoreDisplay() {
        const mjScore = window.MJ_USER_SCORE ? (window.MJ_USER_SCORE.score || 0) : 0;
        const emlogCredits = window.MJ_EMLOG_CREDITS || 0;
        document.getElementById('shopMjScore').textContent = mjScore;
        document.getElementById('shopEmlogCredits').textContent = emlogCredits;
    },

    async buyItem(itemId, priceMj, priceEmlog) {
        if (!currentUser) { showToast('请先登录'); return; }
        
        // 确定支付方式
        let payType = 'both';
        let msg = '';
        if (priceMj > 0 && priceEmlog > 0) {
            // 两种价格都有，让用户选择支付方式
            payType = prompt('选择支付方式：\n1 - 仅麻将积分（' + priceMj + '分）\n2 - 仅站点积分（' + priceEmlog + '分）\n请输入 1 或 2：', '1');
            if (payType === null) return; // 取消
            payType = payType.trim();
            if (payType === '1') {
                payType = 'mj';
                msg = '确认使用 麻将积分 ' + priceMj + ' 购买？';
            } else if (payType === '2') {
                payType = 'emlog';
                msg = '确认使用 站点积分 ' + priceEmlog + ' 购买？';
            } else {
                showToast('请输入 1 或 2');
                return;
            }
        } else if (priceMj > 0) {
            payType = 'mj';
            msg = '确认使用 麻将积分 ' + priceMj + ' 购买？';
        } else if (priceEmlog > 0) {
            payType = 'emlog';
            msg = '确认使用 站点积分 ' + priceEmlog + ' 购买？';
        } else {
            msg = '确认免费领取？';
        }
        if (!confirm(msg)) return;

        const fd = new FormData();
        fd.append('item_id', itemId);
        fd.append('pay_type', payType);
        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=purchase_item', { method: 'POST', body: fd, credentials: 'include' });
            const data = await res.json();
            if (data.code === 0) {
                // 刷新积分
                try {
                    const sr = await fetch('?plugin=wx_games&game=mj&mj_action=get_my_rank', { credentials: 'include' });
                    const sd = await sr.json();
                    if (sd.code === 0 && sd.data) {
                        window.MJ_USER_SCORE = sd.data;
                        document.getElementById('shopMjScore').textContent = sd.data.score || 0;
                    }
                } catch(e) {}
                try {
                    const er = await fetch('?plugin=wx_games&game=mj&mj_action=get_my_emlog_credits', { credentials: 'include' });
                    const ed = await er.json();
                    if (ed.code === 0) window.MJ_EMLOG_CREDITS = ed.data.credits || 0;
                } catch(e) {}
                this.updateScoreDisplay();
                showShopFeedback('🎉', '购买成功', '积分已扣除，道具已发放到背包');
                // 刷新商城列表（更新库存/限购状态）
                this.refreshItems();
            } else {
                showShopFeedback('❌', '购买失败', data.message || '未知错误');
            }
        } catch(e) {
            showShopFeedback('❌', '网络错误', '请检查网络连接后重试');
        }
    },

    async refreshItems() {
        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=get_shop_items', { credentials: 'include' });
            const data = await res.json();
            if (data.code === 0 && data.data && data.data.items) {
                this.allItems = data.data.items;
                this.renderItems();
            }
        } catch(e) {}
    }
};

// ====== 背包管理器 ======
const InventoryManager = {
    currentFilter: 'all',
    allItems: [],

    async show() {
        if (!currentUser) { showToast('请先登录'); return; }
        closeModal();
        document.getElementById('inventoryModal').classList.remove('hidden');
        const list = document.getElementById('inventoryList');
        list.innerHTML = '<div style="text-align:center;color:#aaa;padding:30px;">加载中...</div>';
        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=get_inventory', { credentials: 'include' });
            const data = await res.json();
            if (data.code !== 0 || !data.data || !data.data.items) {
                list.innerHTML = '<div style="text-align:center;color:var(--accent-red);padding:30px;">加载失败</div>';
                return;
            }
            this.allItems = data.data.items;
            this.currentFilter = 'all';
            this.renderFilterBar();
            this.renderItems();
        } catch(e) {
            list.innerHTML = '<div style="text-align:center;color:var(--accent-red);padding:30px;">网络错误</div>';
        }
    },

    renderFilterBar() {
        const list = document.getElementById('inventoryList');
        const existing = document.getElementById('invFilterBar');
        if (existing) existing.remove();
        const bar = document.createElement('div');
        bar.id = 'invFilterBar';
        bar.style.cssText = 'display:flex;gap:6px;justify-content:center;margin-bottom:12px;flex-wrap:wrap;';
        const types = ['all', ...new Set(this.allItems.map(i => i.item_type))];
        types.forEach(key => {
            const btn = document.createElement('button');
            btn.className = 'mj-btn mj-btn-sm';
            if (key === 'all') {
                btn.textContent = '全部';
                btn.className += ' mj-btn-primary';
            } else {
                btn.textContent = (SHOP_TYPE_ICONS[key] || '🎁') + ' ' + (SHOP_TYPE_NAMES[key] || key);
                btn.className += ' mj-btn-outline';
            }
            btn.dataset.filter = key;
            btn.onclick = () => {
                this.currentFilter = key;
                bar.querySelectorAll('button').forEach(b => { b.className = 'mj-btn mj-btn-sm mj-btn-outline'; });
                btn.className = 'mj-btn mj-btn-sm mj-btn-primary';
                this.renderItems();
            };
            bar.appendChild(btn);
        });
        list.parentNode.insertBefore(bar, list);
    },

    renderItems() {
        const list = document.getElementById('inventoryList');
        const filtered = this.currentFilter === 'all' ? this.allItems : this.allItems.filter(i => i.item_type === this.currentFilter);
        if (filtered.length === 0) {
            list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;">该分类暂无道具</div>';
            return;
        }
        const cosmeticTypes = ['title_colored', 'title_effect', 'title_badge', 'card_back', 'win_effect', 'emoticon'];
        list.innerHTML = filtered.map(item => {
            const isCosmetic = cosmeticTypes.indexOf(item.item_type) !== -1;
            let btnHtml = '';
            if (item.is_active == 1) {
                btnHtml = '<span style="font-size:10px;padding:2px 8px;background:rgba(34,197,94,0.2);color:var(--accent-green);border-radius:8px;border:1px solid var(--accent-green);white-space:nowrap;">✓ 已激活</span>';
            } else if (isCosmetic) {
                btnHtml = '<button class="mj-btn mj-btn-success mj-btn-sm" onclick="InventoryManager.useItem(' + item.inv_id + ',this)">🎯 激活</button>';
            } else {
                btnHtml = '<button class="mj-btn mj-btn-primary mj-btn-sm" onclick="InventoryManager.useItem(' + item.inv_id + ',this)">使用</button>';
            }
            return '<div class="shop-item" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;margin-bottom:6px;background:rgba(255,255,255,0.06);">' +
                '<span style="font-size:22px;">' + (item.icon || '🎁') + '</span>' +
                '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:bold;font-size:13px;">' + item.name + '</div>' +
                    '<div style="font-size:10px;color:var(--text-muted);">剩余 x' + (item.quantity || 1) + '</div>' +
                '</div>' + btnHtml + '</div>';
        }).join('');
    },

    async useItem(invId, btnEl) {
        if (!confirm('确认使用此道具？')) return;
        const fd = new FormData();
        fd.append('user_item_id', invId);
        fd.append('action', 'activate');
        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=use_item', { method: 'POST', body: fd, credentials: 'include' });
            const data = await res.json();
            if (data.code === 0) {
                showShopFeedback('✅', '已激活', '道具已成功激活');
                await loadPlayerEffects();
                this.refreshItems();
            } else {
                showShopFeedback('❌', '使用失败', data.message || '未知错误');
            }
        } catch(e) {
            showShopFeedback('❌', '网络错误', '请重试');
        }
    },

    async refreshItems() {
        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=get_inventory', { credentials: 'include' });
            const data = await res.json();
            if (data.code === 0 && data.data && data.data.items) {
                // 格式转换：后端返回 {id, item_id, name, icon, item_type, quantity, is_active}
                const raw = data.data.items;
                this.allItems = raw.map(item => ({
                    inv_id: item.id || item.inv_id,
                    item_id: item.item_id,
                    name: item.name,
                    icon: item.icon,
                    item_type: item.item_type,
                    quantity: item.quantity || 1,
                    is_active: item.is_active || 0,
                }));
                this.renderItems();
            }
        } catch(e) {}
    }
};

// ====== 牌面图片辅助 ======
// ====== 牌面图片辅助 ======
// 素材命名规则：11~19=筒  21~29=万  31~39=条  41~47=东南西北中发白  00=牌背
// 目录：/1=左横置(吃碰杠来牌)  /2=正常(handmah手牌+mingmah牌河/副露)  /3=右横置
function tileToImageName(tile) {
    const suitMap = { tong: '1', wan: '2', tiao: '3' };
    if (suitMap[tile.suit]) return 'handmah_' + suitMap[tile.suit] + tile.num + '.png';
    const honorMap = { feng_0: '41', feng_1: '42', feng_2: '43', feng_3: '44', jian_0: '45', jian_1: '46', jian_2: '47' };
    return 'handmah_' + honorMap[tile.suit + '_' + tile.num] + '.png';
}
function mingmahName(tile) {
    return tileToImageName(tile).replace('handmah_', 'mingmah_');
}
// 玩家手牌（用 /2/handmah）
function tileImg(tile) {
    return MJ_CONFIG.tilesUrl + tileToImageName(tile);
}
// 牌河 / AI副露 / 玩家副露（用 /2/mingmah）
function meldTileImg(tile) {
    return MJ_CONFIG.tilesUrl + mingmahName(tile);
}
// 牌背（支持皮肤）
function tileBackImg() {
    const effects = window.MJ_PLAYER_EFFECTS || {};
    const skin = effects.tileSkin;
    if (!skin) return MJ_CONFIG.tilesUrl + 'mingmah_00.png';
    // 皮肤有自定义URL时使用
    if (skin.url) return skin.url;
    // 无自定义URL时返回默认牌背
    return MJ_CONFIG.tilesUrl + 'mingmah_00.png';
}

// ====== buff 指示器（欢迎页+导航） ======
function updateBuffDisplay() {
    const welcomeEl = document.getElementById('welcomeBuffInfo');
    if (!welcomeEl) return;
    const buffs = window.MJ_ACTIVE_BUFFS || [];
    if (buffs.length > 0) {
        var b = buffs[0];
        welcomeEl.innerHTML = '⚡ 加成卡 x' + b.multiplier + ' (剩' + b.remaining + '局)';
        welcomeEl.style.color = '#f39c12';
        welcomeEl.style.fontWeight = 'bold';
        welcomeEl.style.display = '';
    } else {
        welcomeEl.innerHTML = '👋🏻 目前没有应用积分卡，可在商城购买';
        welcomeEl.style.color = '#aaa';
        welcomeEl.style.fontWeight = 'normal';
        welcomeEl.style.display = '';
    }
}

// ====== 道具效果系统 ======
window.MJ_PLAYER_EFFECTS = {};

async function loadPlayerEffects() {
    if (!currentUser) return {};
    try {
        const res = await fetch('?plugin=wx_games&game=mj&mj_action=get_active_effects', { credentials: 'include' });
        const data = await res.json();
        if (data.code !== 0 || !data.data) return {};
        const effects = {};
        if (Array.isArray(data.data)) {
            data.data.forEach(item => {
                // effect_data 可能为 JSON 字符串或对象
                let ed = item.effect_data || {};
                if (typeof ed === 'string') { try { ed = JSON.parse(ed); } catch(e) { ed = {}; } }
                if (item.item_type === 'title_colored' && ed.color) effects.titleColor = ed.color;
                if (item.item_type === 'title_effect' && ed.effect) {
                    effects.titleEffect = ed.effect;
                    if (ed.color) effects.titleEffectColor = ed.color;
                }
                if (item.item_type === 'title_badge' && ed.badge) effects.titleBadge = ed.badge;
                if (item.item_type === 'card_back') effects.tileSkin = ed;
                if (item.item_type === 'win_effect') effects.winEffect = ed;
                if (item.item_type === 'emoticon') effects.emoticon = ed;
            });
        }
        window.MJ_PLAYER_EFFECTS = effects;

        // 刷新所有人类玩家名称位置
        if (currentUser) {
            const navName = document.querySelector('.nav-user-name');
            if (navName) navName.innerHTML = renderPlayerName(currentUser.nickname);
            // 注意: welcomeName 在 renderStartPage 中通过 setWelcomeUserInfo 设置，无需重复更新
        }

        // 刷新游戏中的 UI
        if (window.game) {
            if (window.game.state === 'playing') {
                try { window.game.renderAiHands(); } catch(e) {}
                try { window.game._updateBuffDisplay(); } catch(e) {}
                try { window.game._updateEmoteBtn(); } catch(e) {}
            }
        }
        updateBuffDisplay();
        return effects;
    } catch(e) { return {}; }
}

function renderPlayerName(name) {
    const effects = window.MJ_PLAYER_EFFECTS || {};
    let style = '';
    let suffix = '';
    if (effects.titleColor) style += 'color:' + effects.titleColor + ';';
    if (effects.titleEffect === 'glow') {
        const gc = effects.titleEffectColor || 'gold';
        style += 'text-shadow:0 0 10px ' + gc + ',0 0 20px ' + gc + ';';
    }
    if (effects.titleBadge) {
        suffix = ' <span style="font-size:10px;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;padding:1px 6px;border-radius:8px;white-space:nowrap;">' + effects.titleBadge + '</span>';
    }
    return (style || suffix) ? '<span style="' + style + '">' + name + '</span>' + suffix : name;
}

// ====== 商城描述展开/收起 ======
function toggleShopDesc(itemId) {
    const desc = document.getElementById('shopDesc_' + itemId);
    const toggle = document.getElementById('shopDescToggle_' + itemId);
    if (!desc || !toggle) return;
    const expanded = desc.dataset.expanded === 'true';
    desc.dataset.expanded = expanded ? 'false' : 'true';
    toggle.textContent = expanded ? '展开' : '收起';
}
window.toggleShopDesc = toggleShopDesc;

// ====== 全局弹窗关闭函数 ======
function closeModal() {
    // 关闭所有 ddz 风格弹窗
    document.querySelectorAll('.mj-list-modal, .mj-result-modal').forEach(o => o.classList.add('hidden'));
    // 关闭结算弹窗（动态创建的 .mj-modal-overlay）
    document.querySelectorAll('.mj-modal-overlay').forEach(o => {
        o.style.display = 'none';
        o.remove();
    });
}
window.closeModal = closeModal;

// ====== 番型图鉴（从GitHub算法同步） ======
function showFanTypes() {
    closeModal();
    const tileImgUrl = MJ_CONFIG.tilesUrl;
    // 完整国标番型（88番~1番）含牌型示例
    const fans = [
        { fan: 88, items: [
            { name: '大四喜', desc: '东南西北四副风刻', tiles: ['feng_0','feng_0','feng_0','feng_1','feng_1','feng_1','feng_2','feng_2','feng_2','feng_3','feng_3','feng_3','wan_1','wan_1'] },
            { name: '大三元', desc: '中发白三副刻子', tiles: ['jian_0','jian_0','jian_0','jian_1','jian_1','jian_1','jian_2','jian_2','jian_2','wan_1','wan_2','wan_3','tiao_5','tiao_5'] },
            { name: '绿一色', desc: '全部是绿色牌（条2/3/4/6/8+发财）', tiles: ['tiao_2','tiao_2','tiao_2','tiao_3','tiao_3','tiao_3','tiao_4','tiao_4','tiao_4','tiao_6','tiao_6','tiao_6','jian_1','jian_1'] },
            { name: '九莲宝灯', desc: '1112345678999+同花色', tiles: ['wan_1','wan_1','wan_1','wan_2','wan_3','wan_4','wan_5','wan_6','wan_7','wan_8','wan_9','wan_9','wan_9','wan_1'] },
            { name: '四杠', desc: '四个杠加一对将牌', tiles: ['wan_1','wan_1','wan_1','wan_1','tiao_2','tiao_2','tiao_2','tiao_2','tong_3','tong_3','tong_3','tong_3','feng_0','feng_0'] },
            { name: '连七对', desc: '同花色七对且序数相连', tiles: ['wan_2','wan_2','wan_3','wan_3','wan_4','wan_4','wan_5','wan_5','wan_6','wan_6','wan_7','wan_7','wan_8','wan_8'] },
            { name: '十三幺', desc: '19万/19条/19筒+东南西北中发白各一+任一张', tiles: ['wan_1','wan_9','tiao_1','tiao_9','tong_1','tong_9','feng_0','feng_1','feng_2','feng_3','jian_0','jian_1','jian_2','wan_1'] },
        ] },
        { fan: 64, items: [
            { name: '清幺九', desc: '只有1和9的序数牌组成的碰碰胡', tiles: ['wan_1','wan_1','wan_1','wan_9','wan_9','wan_9','tiao_1','tiao_1','tiao_1','tiao_9','tiao_9','tiao_9','tong_1','tong_1'] },
            { name: '小四喜', desc: '3个风刻+风雀头', tiles: ['feng_0','feng_0','feng_0','feng_1','feng_1','feng_1','feng_2','feng_2','feng_2','feng_3','feng_3','wan_1','wan_2','wan_3'] },
            { name: '小三元', desc: '2个箭刻+箭雀头', tiles: ['jian_0','jian_0','jian_0','jian_1','jian_1','jian_1','jian_2','jian_2','wan_1','wan_2','wan_3','wan_7','wan_8','wan_9'] },
            { name: '字一色', desc: '全部是字牌', tiles: ['feng_0','feng_0','feng_0','feng_1','feng_1','feng_1','feng_2','feng_2','feng_2','jian_0','jian_0','jian_1','jian_1','jian_2'] },
            { name: '四暗刻', desc: '4组暗刻（含暗杠）', tiles: ['wan_1','wan_1','wan_1','wan_5','wan_5','wan_5','tiao_3','tiao_3','tiao_3','tong_7','tong_7','tong_7','feng_0','feng_0'] },
            { name: '一色双龙会', desc: '清一色123+789+5做将', tiles: ['wan_1','wan_2','wan_3','wan_1','wan_2','wan_3','wan_7','wan_8','wan_9','wan_7','wan_8','wan_9','wan_5','wan_5'] },
        ] },
        { fan: 48, items: [
            { name: '一色四同顺', desc: '同花色4副相同顺子', tiles: ['wan_2','wan_3','wan_4','wan_2','wan_3','wan_4','wan_2','wan_3','wan_4','wan_2','wan_3','wan_4','wan_5','wan_5'] },
            { name: '一色四节高', desc: '同花色4副递增1的刻子', tiles: ['wan_2','wan_2','wan_2','wan_3','wan_3','wan_3','wan_4','wan_4','wan_4','wan_5','wan_5','wan_5','wan_1','wan_1'] },
        ] },
        { fan: 32, items: [
            { name: '一色四步高', desc: '同花色4副递进1~2的顺子', tiles: ['wan_1','wan_2','wan_3','wan_2','wan_3','wan_4','wan_3','wan_4','wan_5','wan_4','wan_5','wan_6','wan_8','wan_8'] },
            { name: '三杠', desc: '3个杠', tiles: ['wan_1','wan_1','wan_1','wan_1','tiao_2','tiao_2','tiao_2','tiao_2','tong_3','tong_3','tong_3','tong_3','feng_0','feng_0'] },
            { name: '混幺九', desc: '幺九的碰碰胡+字牌', tiles: ['wan_1','wan_1','wan_1','wan_9','wan_9','wan_9','tiao_1','tiao_1','tiao_1','feng_0','feng_0','feng_0','jian_1','jian_1'] },
        ] },
        { fan: 24, items: [
            { name: '七对', desc: '7个对子', tiles: ['wan_1','wan_1','wan_2','wan_2','wan_3','wan_3','tiao_5','tiao_5','tiao_6','tiao_6','tong_8','tong_8','feng_0','feng_0'] },
            { name: '七星不靠', desc: '7种字牌+147/258/369', tiles: ['feng_0','feng_1','feng_2','feng_3','jian_0','jian_1','jian_2','wan_1','wan_4','wan_7','tiao_2','tiao_5','tiao_8','tong_3'] },
            { name: '全双刻', desc: '全部是偶数牌的刻子', tiles: ['wan_2','wan_2','wan_2','wan_4','wan_4','wan_4','tiao_6','tiao_6','tiao_6','tong_8','tong_8','tong_8','tiao_2','tiao_2'] },
            { name: '清一色', desc: '全部同一花色', tiles: ['wan_2','wan_2','wan_2','wan_3','wan_4','wan_5','wan_5','wan_5','wan_6','wan_7','wan_8','wan_9','wan_9','wan_9'] },
            { name: '一色三同顺', desc: '同花色3副相同顺子', tiles: ['wan_3','wan_4','wan_5','wan_3','wan_4','wan_5','wan_3','wan_4','wan_5','wan_7','wan_8','wan_9','wan_2','wan_2'] },
            { name: '一色三节高', desc: '同花色3副递增1的刻子', tiles: ['wan_3','wan_3','wan_3','wan_4','wan_4','wan_4','wan_5','wan_5','wan_5','tiao_2','tiao_3','tiao_4','tiao_7','tiao_7'] },
            { name: '全大', desc: '全部序数牌&#x2265;7', tiles: ['wan_7','wan_7','wan_7','wan_8','wan_8','wan_8','tiao_7','tiao_8','tiao_9','tong_7','tong_8','tong_9','tiao_9','tiao_9'] },
            { name: '全中', desc: '全部序数牌=456', tiles: ['wan_4','wan_4','wan_4','wan_5','wan_5','wan_5','tiao_4','tiao_5','tiao_6','tong_4','tong_5','tong_6','wan_6','wan_6'] },
            { name: '全小', desc: '全部序数牌&#x2264;3', tiles: ['wan_1','wan_1','wan_1','wan_2','wan_2','wan_2','tiao_3','tiao_3','tiao_3','tong_1','tong_2','tong_3','wan_3','wan_3'] },
        ] },
        { fan: 16, items: [
            { name: '清龙', desc: '同花色123+456+789', tiles: ['wan_1','wan_2','wan_3','wan_4','wan_5','wan_6','wan_7','wan_8','wan_9','tiao_5','tiao_5','tiao_5','tong_5','tong_5'] },
            { name: '三色双龙会', desc: '二种花色123+789+另一花色5做将', tiles: ['wan_1','wan_2','wan_3','wan_1','wan_2','wan_3','tiao_7','tiao_8','tiao_9','tiao_7','tiao_8','tiao_9','tong_5','tong_5'] },
            { name: '一色三步高', desc: '同花色3副递进1~2的顺子', tiles: ['wan_1','wan_2','wan_3','wan_2','wan_3','wan_4','wan_3','wan_4','wan_5','tiao_2','tiao_2','tiao_2','tiao_7','tiao_7'] },
            { name: '全带五', desc: '每组+将都含5', tiles: ['wan_5','wan_5','wan_5','tiao_5','tiao_5','tiao_5','tong_5','tong_5','tong_5','wan_3','wan_4','wan_5','tiao_5','tiao_5'] },
            { name: '三同刻', desc: '三花色相同数字的刻子', tiles: ['wan_5','wan_5','wan_5','tiao_5','tiao_5','tiao_5','tong_5','tong_5','tong_5','wan_1','wan_2','wan_3','wan_7','wan_7'] },
            { name: '三暗刻', desc: '3组暗刻（含暗杠）', tiles: ['wan_1','wan_1','wan_1','wan_5','wan_5','wan_5','tiao_3','tiao_3','tiao_3','wan_2','wan_3','wan_4','wan_7','wan_8'] },
        ] },
        { fan: 12, items: [
            { name: '全不靠', desc: '147/258/369各取一门+字牌', tiles: ['wan_1','wan_4','wan_7','tiao_2','tiao_5','tiao_8','tong_3','tong_6','tong_9','feng_0','feng_1','feng_2','jian_0','jian_1'] },
            { name: '组合龙', desc: '三花色147/258/369各一副', tiles: ['wan_1','wan_4','wan_7','tiao_2','tiao_5','tiao_8','tong_3','tong_6','tong_9','wan_2','wan_2','wan_2','tiao_1','tiao_1'] },
            { name: '大于五', desc: '全部序数牌&#x2265;6', tiles: ['wan_6','wan_6','wan_6','wan_7','wan_8','wan_9','tiao_6','tiao_7','tiao_8','tong_6','tong_7','tong_8','tiao_9','tiao_9'] },
            { name: '小于五', desc: '全部序数牌&#x2264;4', tiles: ['wan_1','wan_1','wan_1','wan_2','wan_2','wan_2','tiao_3','tiao_3','tiao_3','tong_4','tong_4','tong_4','tiao_4','tiao_4'] },
            { name: '三风刻', desc: '3个风刻', tiles: ['feng_0','feng_0','feng_0','feng_1','feng_1','feng_1','feng_2','feng_2','feng_2','wan_1','wan_2','wan_3','wan_5','wan_5'] },
        ] },
        { fan: 8, items: [
            { name: '花龙', desc: '三花色123+456+789各一副', tiles: ['wan_1','wan_2','wan_3','tiao_4','tiao_5','tiao_6','tong_7','tong_8','tong_9','tiao_1','tiao_1','tiao_1','tong_1','tong_1'] },
            { name: '推不倒', desc: '所有牌可翻转', tiles: ['tiao_2','tiao_2','tiao_2','tiao_3','tiao_3','tiao_3','tiao_8','tiao_8','tiao_8','tong_5','tong_5','tong_5','jian_1','jian_1'] },
            { name: '三色三同顺', desc: '三花色相同数字顺子', tiles: ['wan_4','wan_5','wan_6','tiao_4','tiao_5','tiao_6','tong_4','tong_5','tong_6','wan_2','wan_2','wan_2','tiao_8','tiao_8'] },
            { name: '三色三节高', desc: '三花色递进1的刻子', tiles: ['wan_3','wan_3','wan_3','tiao_4','tiao_4','tiao_4','tong_5','tong_5','tong_5','wan_7','wan_8','wan_9','tiao_9','tiao_9'] },
            { name: '无番和', desc: '没有任何番种的胡牌' },
            { name: '海底捞月', desc: '自摸最后一张牌' },
            { name: '海底胡', desc: '和最后一张打出的牌' },
            { name: '杠上开花', desc: '杠后补牌和牌' },
            { name: '抢杠和', desc: '抢他人补杠胡牌' },
        ] },
        { fan: 6, items: [
            { name: '碰碰胡', desc: '4刻+1将', tiles: ['wan_1','wan_1','wan_1','wan_5','wan_5','wan_5','tiao_3','tiao_3','tiao_3','tong_7','tong_7','tong_7','feng_0','feng_0'] },
            { name: '混一色', desc: '一种花色+字牌', tiles: ['wan_1','wan_1','wan_1','wan_2','wan_3','wan_4','wan_6','wan_7','wan_8','feng_0','feng_0','feng_0','jian_1','jian_1'] },
            { name: '三色三步高', desc: '三花色递进1的顺子', tiles: ['wan_2','wan_3','wan_4','tiao_3','tiao_4','tiao_5','tong_4','tong_5','tong_6','wan_7','wan_7','wan_7','tiao_8','tiao_8'] },
            { name: '五门齐', desc: '万条筒+风+箭', tiles: ['wan_1','wan_2','wan_3','tiao_4','tiao_5','tiao_6','tong_7','tong_7','tong_7','feng_0','feng_0','feng_0','jian_1','jian_1'] },
            { name: '全求人', desc: '4组副露+单钓', tiles: ['wan_1','wan_2','wan_3','tiao_4','tiao_5','tiao_6','tong_7','tong_7','tong_7','feng_0','feng_0','feng_0','wan_5'] },
            { name: '双暗杠', desc: '2个暗杠', tiles: ['wan_1','wan_1','wan_1','wan_1','tiao_2','tiao_2','tiao_2','tiao_2','wan_3','wan_4','wan_5','wan_6','wan_7','wan_8'] },
            { name: '双箭刻', desc: '2个箭刻', tiles: ['jian_0','jian_0','jian_0','jian_1','jian_1','jian_1','wan_1','wan_2','wan_3','wan_4','wan_5','wan_6','wan_7','wan_8'] },
        ] },
        { fan: 4, items: [
            { name: '全带幺', desc: '每组+将都含1/9/字牌', tiles: ['wan_1','wan_1','wan_1','wan_9','wan_9','wan_9','tiao_1','tiao_2','tiao_3','tong_7','tong_8','tong_9','feng_0','feng_0'] },
            { name: '不求人', desc: '门清自摸' },
            { name: '双明杠', desc: '2个明杠', tiles: ['wan_1','wan_1','wan_1','wan_1','tiao_2','tiao_2','tiao_2','tiao_2','wan_3','wan_4','wan_5','wan_6','wan_7','wan_8'] },
            { name: '和绝张', desc: '胡最后一枚该牌' },
        ] },
        { fan: 2, items: [
            { name: '箭刻', desc: '中/发/白的刻子', tiles: ['jian_0','jian_0','jian_0','wan_1','wan_2','wan_3','tiao_4','tiao_5','tiao_6','tong_7','tong_8','tong_9','tiao_1','tiao_1'] },
            { name: '门风刻', desc: '刻子与门风相同' },
            { name: '门前清', desc: '无吃碰明杠' },
            { name: '平和', desc: '4顺+非字雀头', tiles: ['wan_1','wan_2','wan_3','tiao_4','tiao_5','tiao_6','tong_2','tong_3','tong_4','wan_6','wan_7','wan_8','tiao_8','tiao_8'] },
            { name: '四归一', desc: '手中有4张同牌（非杠）', tiles: ['wan_1','wan_1','wan_1','wan_1','wan_2','wan_2','wan_3','wan_3','tiao_5','tiao_5','tiao_6','tiao_6','tong_8','tong_8'] },
            { name: '双同刻', desc: '二花色相同数字刻子', tiles: ['wan_5','wan_5','wan_5','tiao_5','tiao_5','tiao_5','wan_1','wan_2','wan_3','wan_7','wan_8','wan_9','tiao_9','tiao_9'] },
            { name: '双暗刻', desc: '2组暗刻', tiles: ['wan_1','wan_1','wan_1','tiao_3','tiao_3','tiao_3','tiao_4','tiao_5','tiao_6','tong_7','tong_8','tong_9','wan_5','wan_5'] },
            { name: '暗杠', desc: '暗杠' },
            { name: '断幺', desc: '无1/9/字牌', tiles: ['wan_2','wan_2','wan_2','wan_3','wan_4','wan_5','tiao_4','tiao_5','tiao_6','tong_6','tong_7','tong_8','tiao_2','tiao_2'] },
        ] },
        { fan: 1, items: [
            { name: '一般高', desc: '2副相同顺子', tiles: ['wan_3','wan_4','wan_5','wan_3','wan_4','wan_5','tiao_2','tiao_2','tiao_2','tong_6','tong_7','tong_8','tiao_8','tiao_8'] },
            { name: '喜相逢', desc: '二花色相同数字顺子', tiles: ['wan_4','wan_5','wan_6','tiao_4','tiao_5','tiao_6','tong_2','tong_2','tong_2','tong_7','tong_8','tong_9','tiao_9','tiao_9'] },
            { name: '连六', desc: '同花色2副差3的顺子', tiles: ['wan_1','wan_2','wan_3','wan_4','wan_5','wan_6','tiao_2','tiao_2','tiao_2','tong_7','tong_8','tong_9','tiao_8','tiao_8'] },
            { name: '老少副', desc: '同花色123+789', tiles: ['wan_1','wan_2','wan_3','wan_7','wan_8','wan_9','tiao_3','tiao_3','tiao_3','tong_5','tong_5','tong_5','tiao_7','tiao_7'] },
            { name: '明杠', desc: '明杠' },
            { name: '缺一门', desc: '缺少一种花色', tiles: ['wan_1','wan_1','wan_1','wan_2','wan_3','wan_4','wan_5','wan_6','wan_7','tiao_2','tiao_2','tiao_2','tiao_8','tiao_8'] },
            { name: '无字', desc: '没有字牌', tiles: ['wan_2','wan_2','wan_2','wan_3','wan_4','wan_5','tiao_4','tiao_5','tiao_6','tong_6','tong_7','tong_8','tiao_2','tiao_2'] },
            { name: '边张', desc: '12胡3或89胡7' },
            { name: '嵌张', desc: '胡顺子中间牌' },
            { name: '单钓将', desc: '钓单张做将' },
            { name: '自摸', desc: '自摸成和' },
        ] },
        { fan: 5, items: [
            { name: '明暗杠', desc: '1个明杠+1个暗杠', tiles: ['wan_1','wan_1','wan_1','wan_1','tiao_2','tiao_2','tiao_2','tiao_2','wan_3','wan_4','wan_5','wan_6','wan_7','wan_8'] },
        ] },
    ];
    const allFans = fans.flatMap(g => g.items);
    let activeTab = -1; // 默认全部

    function renderFanModal() {
        let html = '<div class="mj-modal-overlay" onclick="closeModal()"><div class="mj-modal" onclick="event.stopPropagation()" style="max-height:85vh;display:flex;flex-direction:column;">';
        html += '<div class="mj-modal-header" style="background:linear-gradient(135deg,#2d3436,#636e72);color:#fff;padding:14px 20px;border-radius:14px 14px 0 0;flex-shrink:0;"><h3 style="margin:0;font-size:16px;">🀄 国标番型一览</h3></div>';
        html += '<div style="display:flex;flex-wrap:wrap;gap:4px;padding:10px 16px;border-bottom:1px solid rgba(255,255,255,0.1);flex-shrink:0;overflow-x:auto;">';
        html += `<span class="fan-tab" data-idx="-1"${activeTab===-1?' style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent;"':''}>全部</span>`;
        fans.forEach((g, gi) => {
            const a = gi === activeTab;
            html += `<span class="fan-tab" data-idx="${gi}"${a?' style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-color:transparent;"':''}>${g.fan}番</span>`;
        });
        html += '</div>';
        html += '<div style="overflow-y:auto;padding:12px 16px;flex:1;" id="fanListBody">';
        const items = activeTab === -1 ? allFans : fans[activeTab].items;
        items.forEach(item => {
            html += '<div style="margin-bottom:8px;background:rgba(255,255,255,0.04);border-radius:10px;padding:8px 12px;">';
            html += `<div style="font-size:13px;font-weight:600;color:#f1c40f;margin-bottom:4px;">${item.name} <span style="font-size:11px;color:#999;font-weight:400;">${item.desc}</span></div>`;
            if (item.tiles) {
                html += '<div style="display:flex;flex-wrap:wrap;gap:1px;">';
                item.tiles.forEach(tId => {
                    const parts = tId.split('_');
                    const suit = parts[0], num = parseInt(parts[1]);
                    const imgSrc = tileImgUrl + 'handmah_' + ({tong:'1',wan:'2',tiao:'3'}[suit]) + num + '.png';
                    if (suit === 'feng') {
                        const n = ['feng_0','feng_1','feng_2','feng_3'].indexOf(tId) + 1;
                        html += `<img src="${tileImgUrl}handmah_4${n}.png" style="width:18px;height:26px;object-fit:contain;">`;
                    } else if (suit === 'jian') {
                        const n = ['jian_0','jian_1','jian_2'].indexOf(tId) + 5;
                        html += `<img src="${tileImgUrl}handmah_4${n}.png" style="width:18px;height:26px;object-fit:contain;">`;
                    } else {
                        html += `<img src="${imgSrc}" style="width:18px;height:26px;object-fit:contain;">`;
                    }
                });
                html += '</div>';
            }
            html += '</div>';
        });
        html += '</div></div></div>';
        const temp = document.createElement('div');
        temp.innerHTML = html;
        temp.querySelectorAll('.fan-tab').forEach(el => {
            el.addEventListener('click', function() {
                activeTab = parseInt(this.dataset.idx);
                renderFanModal();
            });
        });
        document.body.appendChild(temp);
    }
    renderFanModal();
}

// ====== 导航栏游戏UI开关（nav 按钮已移除，保留空函数避免调用报错） ======
function toggleNavGameUI(show) {
    // 无操作 —— 商城/背包按钮已从导航栏移除
}

// ====== 积分流水 ======
const ScoreLog = {
    async show() {
        const list = document.getElementById('scoreLogList');
        list.innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;">加载中...</div>';
        document.getElementById('scoreLogModal').classList.remove('hidden');

        const uid = window.MJ_USER?.uid || 0;
        if (uid <= 0) { showToast('请先登录'); return; }

        try {
            const data = await Leaderboard.getUserLogs(20, 0, uid);
            const logs = data.logs || [];
            if (logs.length === 0) {
                list.innerHTML = '<div style="text-align:center;color:#aaa;padding:20px;">暂无记录</div>';
                return;
            }
            list.innerHTML = logs.map(function(item) {
                const change = parseInt(item.score_change);
                const sign = change >= 0 ? '+' : '';
                const color = change >= 0 ? '#2ecc71' : '#e74c3c';
                return '<div class="score-log-item">' +
                    '<span class="log-reason">' + (item.reason || '游戏结算') + '</span>' +
                    '<span class="log-time">' + (item.time || '') + '</span>' +
                    '<span class="log-change" style="color:' + color + ';font-weight:bold;">' + sign + change + '</span>' +
                '</div>';
            }).join('');
        } catch(e) {
            list.innerHTML = '<div style="text-align:center;color:#e74c3c;padding:20px;">加载失败</div>';
        }
    }
};

// ====== 购买反馈 ======
function showShopFeedback(icon, title, msg) {
    document.getElementById('feedbackIcon').textContent = icon;
    document.getElementById('feedbackTitle').textContent = title;
    document.getElementById('feedbackMsg').textContent = msg;
    document.getElementById('shopFeedbackModal').classList.remove('hidden');
}

// ====== 弹窗关闭按钮绑定 ======
document.addEventListener('DOMContentLoaded', function() {
    // 积分流水
    document.getElementById('navScoreBox').addEventListener('click', function() {
        if (MJ_USER?.uid > 0) ScoreLog.show();
    });
    // 关闭按钮
    document.getElementById('btnCloseScoreLog').addEventListener('click', function() {
        document.getElementById('scoreLogModal').classList.add('hidden');
    });
    document.getElementById('btnCloseShop').addEventListener('click', function() {
        document.getElementById('shopModal').classList.add('hidden');
    });
    document.getElementById('btnCloseInventory').addEventListener('click', function() {
        document.getElementById('inventoryModal').classList.add('hidden');
    });
});

// ====== 菜单管理器 ======
const MenuManager = {
    showLeaderboard() {
        Leaderboard.show();
    }
};

/**
 * 麻将游戏管理器 - 主控逻辑
 */
class MJGame {
    constructor() {
        this.state = 'idle'; // idle, playing, settled
        this.phase = 'idle';
        this.gameState = null;
        this.playerHand = [];
        this.aiHands = [[], [], []];
        this.discardPile = [];
        this.wall = [];
        this.dealerIndex = 0;
        this.currentPlayer = 0;
        this.turnCount = 0;
        this.myMelds = []; // 我的吃碰杠
        this.aiMelds = [[], [], []]; // AI的吃碰杠
        this.lastDiscard = null;
        this.gameToken = '';
        this.buffMultiplier = 1;
        this.selectedTiles = new Set();
        this.pendingClaims = null; // 待处理的吃碰杠胡
        this.isProcessing = false;
        this.players = [];
        this.tingTiles = [];
        this.roundScore = 0;

        this.init();
    }

    async init() {
        this.renderStartPage(true);
        if (MJ_USER.uid > 0) {
            // 显示loading（ddz 风格）
            document.getElementById('loadingContainer').classList.remove('hidden');
            try {
                const d = await Leaderboard.checkPending();
                if (d && d.cleaned) {
                    console.log('[Init] 已清理未完成的对局记录');
                }
            } catch(e) {}
            // 加载道具效果
            await loadPlayerEffects();
            // 设置欢迎页用户信息
            this.setWelcomeUserInfo();
            // 加载buff信息
            this.loadBuffInfo();
        }
        // 隐藏loading
        const lc = document.getElementById('loadingContainer');
        if (lc) lc.classList.add('hidden');
    }

    // ====== 设置欢迎页用户信息 ======
    setWelcomeUserInfo() {
        if (!MJ_USER || MJ_USER.uid <= 0) return;
        const avatarEl = document.getElementById('welcomeAvatar');
        const nameEl = document.getElementById('welcomeName');
        const scoreEl = document.getElementById('welcomeScore');
        const subtitleEl = document.getElementById('welcomeSubtitle');
        const loggedInPanel = document.getElementById('loggedInPanel');
        const loginForm = document.getElementById('loginFormContainer');

        if (MJ_USER.avatar) {
            avatarEl.src = MJ_USER.avatar;
        } else {
            avatarEl.src = 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#667eea"/><text x="50" y="55" text-anchor="middle" fill="white" font-size="40" font-family="sans-serif">' + (MJ_USER.nickname ? MJ_USER.nickname.charAt(0) : '?') + '</text></svg>');
        }
        nameEl.innerHTML = renderPlayerName(MJ_USER.nickname);
        scoreEl.textContent = window.MJ_USER_SCORE ? window.MJ_USER_SCORE.score : 0;
        if (subtitleEl) subtitleEl.textContent = '🀄️ 欢迎回来，' + MJ_USER.nickname;

        // 显示已登录面板，隐藏登录表单
        if (loggedInPanel) loggedInPanel.style.display = '';
        if (loginForm) loginForm.style.display = 'none';

        // 设置充值按钮
        this.setupRechargeBtn();
    }

    // ====== 设置充值按钮 ======
    setupRechargeBtn() {
        const btn = document.getElementById('btnRecharge');
        if (!btn) return;
        if (window.MJ_RECHARGE_LINK) {
            btn.style.display = '';
            btn.onclick = function() { window.open(window.MJ_RECHARGE_LINK, '_blank'); };
        } else {
            btn.style.display = 'none';
        }
    }

    // ====== 加载加成卡buff信息 ======
    async loadBuffInfo() {
        if (!currentUser || currentUser.uid <= 0) return;
        try {
            const res = await fetch('?plugin=wx_games&game=mj&mj_action=get_score_buff', { credentials: 'include' });
            const data = await res.json();
            if (data.code === 0 && data.data && data.data.buffs && data.data.buffs.length > 0) {
                window.MJ_ACTIVE_BUFFS = data.data.buffs.map(b => ({
                    multiplier: b.multiplier,
                    remaining: b.remaining
                }));
            } else {
                window.MJ_ACTIVE_BUFFS = [];
            }
        } catch(e) {
            window.MJ_ACTIVE_BUFFS = [];
        }
        updateBuffDisplay();
    }

    // ====== 渲染开始页（ddz 风格 login-container） ======
    renderStartPage(showUserInfo) {
        toggleNavGameUI(false);
        const app = document.getElementById('mjApp');
        const isLoggedIn = MJ_USER && MJ_USER.uid > 0;
        const showInfo = showUserInfo !== false && isLoggedIn;
        const loginPanelStyle = isLoggedIn ? '' : ' style="display:none;"';
        const guestPanelStyle = (!isLoggedIn) ? '' : ' style="display:none;"';
        const userInfoHtml = showInfo ? `
                        <div class="welcome-user" id="welcomeUserInfo">
                            <img class="welcome-avatar" id="welcomeAvatar" src="" alt="">
                            <span class="welcome-name" id="welcomeName"></span>
                            <span class="welcome-score">积分: <strong id="welcomeScore">0</strong></span>
                        </div>
                        <div id="welcomeBuffInfo" style="margin:6px 0;font-size:12px;min-height:18px;text-align:center;"></div>` : '';
        app.innerHTML = `
            <div class="login-screen" id="loginScreen">
                <div class="login-container">
                    <div class="login-subtitle" id="welcomeSubtitle">${isLoggedIn ? '🀄️ 欢迎回来，' + MJ_USER.nickname : '🀄️ 欢迎来到麻将'}</div>
                    <div class="game-subtitle">国标麻将 · 四人局 · AI对战</div>

                    <div id="loggedInPanel"${loginPanelStyle}>
                        ${userInfoHtml}
                        <button class="start-btn" id="btnStartGame" onclick="game.startGame()">🎮 开始游戏</button>
                        <div class="welcome-actions" id="welcomeActions">
                            <button class="btn welcome-action-btn" style="background:linear-gradient(135deg,#f39c12,#e67e22);color:white;" onclick="ShopManager.show()">🛒 商城</button>
                            <button class="btn welcome-action-btn" style="background:linear-gradient(135deg,#3498db,#2980b9);color:white;" onclick="InventoryManager.show()">🎒 背包</button>
                            <button class="btn welcome-action-btn" id="btnRecharge" style="background:linear-gradient(135deg,#e74c3c,#c0392b);color:white;">💰 充值</button>
                        </div>
                        ${(MJ_NOTICE || MJ_UPDATES) ? `
                        <div class="welcome-modules">
                            ${MJ_NOTICE ? `
                            <div class="welcome-notice">
                                <div class="module-title">📢 公告</div>
                                <div class="module-body">${MJ_NOTICE.replace(/\n/g, '<br>')}</div>
                            </div>` : ''}
                            ${MJ_UPDATES ? `
                            <div class="welcome-updates">
                                <div class="module-title">🔄 最近更新</div>
                                <div class="module-body">${MJ_UPDATES.replace(/\n/g, '<br>')}</div>
                            </div>` : ''}
                        </div>` : ''}
                    </div>

                    <div id="loginFormContainer"${guestPanelStyle}>
                        <div class="login-error" id="loginError"></div>
                        <div class="login-tip">
                            <strong>💡 登录说明：</strong>
                            登录后可保存积分到排行榜，游客模式仅限本地体验。
                        </div>
                        <a href="?plugin=wx_games&game=mj&mj_action=login" class="btn-redirect-login" id="btnRedirectLogin">
                            🔑 前往登录（推荐）
                        </a>
                        ${MJ_GUEST_PLAY ? '<div style="text-align:center;margin:15px 0;color:#999;">— 或者 —</div><button class="btn-guest" id="btnGuest">🎮 游客模式</button>' : ''}
                    </div>

                    <div id="loadingContainer" class="hidden">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                            <div class="loading-text" id="loadingText">正在检查登录状态...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // ====== 开始游戏 ======
    async startGame() {
        // 游客模式检查
        if (MJ_USER.uid <= 0 && MJ_GUEST_PLAY !== true) {
            this.showToast('请先登录', 2000);
            return;
        }
        try {
            AudioEngine.click();
        } catch(e) { console.warn('Audio:', e); }
        console.log('[MJ] 开始新游戏');
        this.state = 'playing';
        this.myMelds = [];
        this.aiMelds = [[], [], []];
        this.discardPile = [];
        this.selectedTiles = new Set();
        this.lastDiscard = null;
        this.lastDiscarder = 0;
        this.buffMultiplier = 1;
        this.roundScore = 0;
        this.pendingClaims = null;
        this.isProcessing = false;

        // 获取游戏token
        if (MJ_USER.uid > 0) {
            try {
                const d = await Leaderboard.startGame();
                this.gameToken = d.game_token || '';
            } catch(e) { this.gameToken = ''; }
        }

        // 发牌
        const deck = TileEngine.shuffle(TileEngine.createDeck());
        const dealt = TileEngine.deal(deck);
        this.playerHand = dealt.hands[0];
        // 从6个AI池中随机选3个
        const aiPool = this._selectRandomAI(3);
        // 防止AI池不足导致崩溃
        while (aiPool.length < 3) {
            aiPool.push({ key: 'fill', name: 'AI玩家', avatar: '', quotes: { good: [], bad: [], win: [], lose: [] } });
        }
        this.aiHands = [
            dealt.hands[1],
            dealt.hands[2],
            dealt.hands[3]
        ];
        this.wall = dealt.wall;
        this.dealerIndex = 0;
        this.currentPlayer = 0;
        this.turnCount = 0;

        // 排序手牌
        this.playerHand = TileEngine.sortTiles(this.playerHand);
        for (let i = 0; i < 3; i++) {
            this.aiHands[i] = TileEngine.sortTiles(this.aiHands[i]);
        }

        // 设置AI玩家信息（从随机选的3个AI池中取）
        this.currentAIKeys = aiPool.map(a => a.key);
        this.players = [
            { index: 0, name: MJ_USER.nickname || '我', isHuman: true },
            { index: 1, name: aiPool[0].name, avatar: aiPool[0].avatar, isHuman: false, quotes: aiPool[0].quotes, key: aiPool[0].key },
            { index: 2, name: aiPool[1].name, avatar: aiPool[1].avatar, isHuman: false, quotes: aiPool[1].quotes, key: aiPool[1].key },
            { index: 3, name: aiPool[2].name, avatar: aiPool[2].avatar, isHuman: false, quotes: aiPool[2].quotes, key: aiPool[2].key },
        ];

        // 检查积分加成
        if (MJ_USER.uid > 0) {
            try {
                const buff = await fetch(`?plugin=wx_games&game=mj&mj_action=get_score_buff`).then(r => r.json()).then(d => d.data);
                if (buff && buff.has_buff && buff.buffs && buff.buffs[0]) {
                    this.buffMultiplier = buff.buffs[0].multiplier || 2;
                    // 开局即消耗一次（无论输赢，消耗完则不加成）
                    await fetch(`?plugin=wx_games&game=mj&mj_action=consume_score_buff`, { method: 'POST' });
                }
            } catch(e) {}
        }

        this.renderGameTable();
        this.renderPlayerHand();
        toggleNavGameUI(true);
        this._updateEmoteBtn();
        this._updateBuffDisplay();
        updateBuffDisplay();

        // 庄家起手14张（多摸一张）
        if (this.dealerIndex === 0) {
            const firstTile = TileEngine.drawTile(this.wall);
            if (firstTile) {
                this.playerHand.push(firstTile);
                this.playerHand = TileEngine.sortTiles(this.playerHand);
                this.renderPlayerHand();
            }
        }
        this.updateWallCount();

        // 庄家先出牌
        if (this.currentPlayer === 0) {
            this.waitForPlayerDiscard();
        } else {
            setTimeout(() => this.aiTurn(this.currentPlayer - 1), 1000);
        }
    }

    /**
     * 从6个AI池中随机选择N个
     */
    _selectRandomAI(count) {
        const aiKeys = ['player1', 'player2', 'player3', 'player4', 'player5', 'player6'];
        const pool = [];
        // 收集所有可用的AI
        for (const key of aiKeys) {
            if (MJ_AI_PLAYERS[key]) {
                pool.push({
                    key: key,
                    name: MJ_AI_PLAYERS[key].name || key,
                    avatar: MJ_AI_PLAYERS[key].avatar || '',
                    quotes: MJ_AI_PLAYERS[key].quotes
                });
            }
        }
        // 洗牌取前N个
        for (let i = pool.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [pool[i], pool[j]] = [pool[j], pool[i]];
        }
        return pool.slice(0, count);
    }

    /**
     * 获取AI的MJ_AI_PLAYERS key（从players数组索引取）
     */
    _getAIKey(aiIdx) {
        // aiIdx是0,1,2对应players[1],players[2],players[3]
        return this.players[aiIdx + 1]?.key || 'player' + (aiIdx + 1);
    }

    /**
     * 渲染AI头像（有图显示图，无图显示首字母占位）
     */
    _renderAiAvatar(player) {
        if (!player) return '<div class="avatar-placeholder">AI</div>';
        if (player.avatar) {
            return `<img src="${player.avatar}" class="avatar" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><div class="avatar-placeholder" style="display:none">${player.name ? player.name[0] : 'AI'}</div>`;
        }
        return `<div class="avatar-placeholder">${player.name ? player.name[0] : 'AI'}</div>`;
    }

    // ====== 渲染牌桌（雀魂风格） ======
    renderGameTable() {
        const app = document.getElementById('mjApp');
        const playerScore = (MJ_USER_SCORE && MJ_USER_SCORE.score) || 0;
        const playerAvatarHtml = MJ_USER.avatar
            ? `<img src="${MJ_USER.avatar}" class="avatar" alt="">`
            : `<div class="avatar-placeholder">${MJ_USER.nickname ? MJ_USER.nickname[0] : '?'}</div>`;

        app.innerHTML = `
            <div class="mj-game-area">
                <!-- 顶部信息栏 -->
                <div class="mj-top-bar">
                    <div class="stat-item">🀄 牌墙 <span class="stat-value" id="wallCount">${this.wall.length}</span></div>
                    <div class="stat-item">🔄 巡目 <span class="stat-value" id="turnCount">${this.turnCount}</span></div>
                    <div class="stat-item" id="buffDisplay" style="display:none">⚡ <span class="stat-value">x${this.buffMultiplier}</span></div>
                </div>

                <!-- 对面AI -->
                <div class="mj-player-opponent" id="aiTop">
                    <div class="player-card">
                        ${this._renderAiAvatar(this.players[2])}
                        <span class="name">${this.players[2].name}</span>
                        <div class="mj-melds" id="aiTopMelds"></div>
                    </div>
                    <div class="player-hand" id="aiTopHand"></div>
                    <div class="mj-bubble top" id="bubbleTop" style="display:none"></div>
                </div>

                <!-- 左侧AI（下家） -->
                <div class="mj-player-left" id="aiLeft">
                    <div class="player-card">
                        ${this._renderAiAvatar(this.players[1])}
                        <span class="name">${this.players[1].name}</span>
                        <div class="mj-melds" id="aiLeftMelds"></div>
                    </div>
                    <div class="player-hand" id="aiLeftHand"></div>
                    <div class="mj-bubble left" id="bubbleLeft" style="display:none"></div>
                </div>

                <!-- 中央出牌区（河） -->
                <div class="mj-center-zone">
                    <div class="mj-river" id="discardArea"></div>
                </div>

                <!-- 右侧AI（上家） -->
                <div class="mj-player-right" id="aiRight">
                    <div class="player-card">
                        ${this._renderAiAvatar(this.players[3])}
                        <span class="name">${this.players[3].name}</span>
                        <div class="mj-melds" id="aiRightMelds"></div>
                    </div>
                    <div class="player-hand" id="aiRightHand"></div>
                    <div class="mj-bubble right" id="bubbleRight" style="display:none"></div>
                </div>

                <!-- 底部玩家（自己） -->
                <div class="mj-player-self" id="playerBottom">
                    <div class="player-top-bar">
                        <div class="player-card">
                            ${playerAvatarHtml}
                            <span class="name">${renderPlayerName(this.players[0].name)}</span>
                        </div>
                        <div class="mj-game-actions" id="actionArea">
                            <button class="mj-action-btn" id="btnEmote" style="display:none;background:linear-gradient(135deg,#f39c12,#e67e22);color:#fff;" onclick="game.sendEmote()">😎</button>
                            <button class="mj-action-btn peng" id="btnDiscard" style="display:none" onclick="game.playerDiscard()">出牌</button>
                        </div>
                        <div class="mj-melds" id="playerMelds"></div>
                    </div>
                    <div class="hand-wrapper">
                        <div class="player-hand" id="playerHand"></div>
                    </div>
                    <!-- 表情弹窗气泡 -->
                    <div class="speech-bubble" id="speechBubblePlayer" style="display:none;">
                        <span id="speechBubblePlayerText"></span>
                    </div>
                </div>

                <!-- 吃碰杠胡操作栏（浮动） -->
                <div class="mj-action-bar" id="claimArea" style="display:none"></div>

                <!-- 听牌提示区 -->
                <div class="mj-ting-indicator" id="tingIndicator" style="display:none"></div>
            </div>
        `;

        // 屏蔽右键菜单
        app.addEventListener('contextmenu', e => e.preventDefault());
    }

    // ====== 渲染手牌 ======
    renderPlayerHand() {
        const container = document.getElementById('playerHand');
        if (!container) return;
        const fragment = document.createDocumentFragment();
        this.playerHand.forEach((tile, index) => {
            const div = document.createElement('div');
            div.className = `mj-tile${this.selectedTiles.has(index) ? ' selected' : ''}`;
            div.dataset.index = index;
            div.innerHTML = `<img src="${tileImg(tile)}" class="tile-img" alt="" draggable="false">`;
            div.onclick = () => this.toggleTileSelection(index);
            // 选中时显示剩余牌数
            if (this.selectedTiles.has(index)) {
                const handCount = this.playerHand.filter(t => t.id === tile.id).length;
                const discCount = this.discardPile.filter(t => t.id === tile.id).length;
                const remaining = 4 - handCount - discCount;
                const badge = document.createElement('span');
                badge.className = 'tile-remain';
                badge.textContent = `剩${Math.max(0, remaining)}张`;
                div.appendChild(badge);
            }
            fragment.appendChild(div);
        });
        // 单次 DOM 替换，减少重排
        container.replaceChildren(fragment);
    }

    // ====== 渲染AI手牌（牌背 - 竖着显示） ======
    renderAiHands() {
        const positions = ['aiLeftHand', 'aiTopHand', 'aiRightHand'];
        const sizes = ['mj-tile-back-sm', 'mj-tile-back-xs', 'mj-tile-back-sm'];
        for (let i = 0; i < 3; i++) {
            const container = document.getElementById(positions[i]);
            if (!container) continue;
            const count = this.aiHands[i].length;
            // 获取当前牌背图片URL
            const skin = (window.MJ_PLAYER_EFFECTS || {}).tileSkin;
            const backSrc = (skin && skin.url) ? skin.url : tileBackImg();
            const existingBack = container.querySelector('.mj-tile-back');
            if (existingBack) {
                const badge = existingBack.querySelector('.ai-hand-badge');
                if (badge) badge.textContent = count;
                else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'ai-hand-badge';
                    newBadge.textContent = count;
                    existingBack.appendChild(newBadge);
                }
                // 更新牌背图片
                const img = existingBack.querySelector('img');
                if (img) { img.src = backSrc; }
                else { existingBack.innerHTML = `<img src="${backSrc}" class="tile-img-back" alt="" draggable="false">`; }
                continue;
            }
            // 首次渲染：创建牌背
            const sizeClass = sizes[i];
            const div = document.createElement('div');
            div.className = 'mj-tile-back ' + sizeClass;
            div.innerHTML = `<img src="${backSrc}" class="tile-img-back" alt="" draggable="false">`;
            const badge = document.createElement('span');
            badge.className = 'ai-hand-badge';
            badge.textContent = count;
            div.appendChild(badge);
            container.replaceChildren(div);
        }
    }

    // ====== 获取牌面显示 ======
    getTileDisplay(tile) {
        const key = `${tile.suit}_${tile.num}`;
        return TILE_SHORT[key] || tile.id;
    }

    /**
     * 从 tileId 解析牌对象（用于副露渲染）
     */
    _parseTileId(tileId) {
        const parts = tileId.split('_');
        return { id: tileId, suit: parts[0], num: parseInt(parts[1]) };
    }

    getTileColor(tile) {
        return TILE_COLORS[tile.suit] || '#fff';
    }

    // ====== 牌选择切换（单张选择） ======
    toggleTileSelection(index) {
        if (this.phase !== 'discarding' || this.currentPlayer !== 0) return;
        AudioEngine.click();
        const container = document.getElementById('playerHand');
        if (!container) return;

        // 清除所有已选中的样式和 badge
        container.querySelectorAll('.mj-tile.selected').forEach(el => {
            el.classList.remove('selected');
            const badge = el.querySelector('.tile-remain');
            if (badge) badge.remove();
        });
        this.selectedTiles.clear();

        // 选中新牌
        this.selectedTiles.add(index);
        const tileDiv = container.children[index];
        if (tileDiv) {
            tileDiv.classList.add('selected');
            // 显示剩余牌数 badge
            const tile = this.playerHand[index];
            const handCount = this.playerHand.filter(t => t.id === tile.id).length;
            const discCount = this.discardPile.filter(t => t.id === tile.id).length;
            const remaining = 4 - handCount - discCount;
            const badge = document.createElement('span');
            badge.className = 'tile-remain';
            badge.textContent = `剩${Math.max(0, remaining)}张`;
            tileDiv.appendChild(badge);
        }

        this.updateActionButtons();
        this._updateTingOnSelection();
    }

    // ====== 更新操作按钮 ======
    updateActionButtons() {
        const btnDiscard = document.getElementById('btnDiscard');
        if (!btnDiscard) return;
        btnDiscard.style.display = this.selectedTiles.size > 0 ? 'inline-flex' : 'none';
    }

    // ====== 更新表情按钮可见性 ======
    _updateEmoteBtn() {
        const btn = document.getElementById('btnEmote');
        if (!btn) return;
        const effects = window.MJ_PLAYER_EFFECTS || {};
        btn.style.display = effects.emoticon && effects.emoticon.text ? 'inline-flex' : 'none';
    }

    // ====== 发送表情弹幕 ======
    sendEmote() {
        const effects = window.MJ_PLAYER_EFFECTS || {};
        const emote = effects.emoticon;
        if (!emote || !emote.text) return;
        const bubble = document.getElementById('speechBubblePlayer');
        const textEl = document.getElementById('speechBubblePlayerText');
        if (!bubble || !textEl) return;
        textEl.textContent = emote.text;
        bubble.style.display = 'block';
        bubble.classList.remove('speech-animate');
        void bubble.offsetWidth;
        bubble.classList.add('speech-animate');
        if (bubble._hideTimer) clearTimeout(bubble._hideTimer);
        bubble._hideTimer = setTimeout(function() {
            bubble.style.display = 'none';
            bubble.classList.remove('speech-animate');
        }, 3000);
    }

    // ====== 更新 buff 显示 ======
    _updateBuffDisplay() {
        const el = document.getElementById('buffDisplay');
        if (!el) return;
        if (this.buffMultiplier > 1) {
            el.style.display = 'flex';
            el.querySelector('.stat-value').textContent = 'x' + this.buffMultiplier;
            // 添加剩余局数提示（从API获取）
            fetch('?plugin=wx_games&game=mj&mj_action=get_score_buff').then(r => r.json()).then(d => {
                const buffs = d.data && d.data.buffs;
                if (buffs && buffs.length > 0) {
                    el.querySelector('.stat-value').textContent = 'x' + this.buffMultiplier + ' 剩' + buffs[0].remaining + '局';
                }
            }).catch(() => {});
        } else {
            el.style.display = 'none';
        }
    }

    // ====== 高亮当前出牌人 ======
    highlightCurrentPlayer() {
        // 移除所有玩家卡片的 active-turn
        document.querySelectorAll('.player-card').forEach(el => el.classList.remove('active-turn'));

        if (this.currentPlayer === 0) {
            // 高亮自己
            const selfCard = document.querySelector('#playerBottom .player-card');
            if (selfCard) selfCard.classList.add('active-turn');
        } else {
            // 高亮对应AI
            const positions = ['aiLeft', 'aiTop', 'aiRight'];
            const idx = this.currentPlayer - 1; // 0,1,2 → aiLeft, aiTop, aiRight
            const container = document.getElementById(positions[idx]);
            if (container) {
                const card = container.querySelector('.player-card');
                if (card) card.classList.add('active-turn');
            }
        }
    }

    // ====== 等待玩家出牌 ======
    waitForPlayerDiscard() {
        this.phase = 'discarding';
        this.isProcessing = false;
        document.getElementById('btnDiscard').style.display = 'none';
        this.updateActionButtons();
        this.highlightCurrentPlayer();
        // 清除之前的听牌提示（等选中牌后重新计算）
        const tingEl = document.getElementById('tingIndicator');
        if (tingEl) tingEl.style.display = 'none';
    }

    // ====== 玩家摸牌 ======
    playerDraw() {
        if (this.phase !== 'drawing' || this.currentPlayer !== 0) return;
        AudioEngine.draw();

        const tile = TileEngine.drawTile(this.wall);
        if (!tile) {
            // 流局
            this.settleDraw();
            return;
        }

        this.playerHand.push(tile);
        this.playerHand = TileEngine.sortTiles(this.playerHand);
        this.renderPlayerHand();
        this.updateWallCount();
        this.turnCount++;
        document.getElementById('turnCount').textContent = this.turnCount;

        // 检查自摸胡
        const canHu = HuChecker.isHu(this.playerHand);
        if (canHu) {
            this.phase = 'claiming';
            this.showClaimActions([{ type: 'hu', label: '🀄 胡！', action: () => this.playerWin(true) }], true);
            return;
        }

        // 检查暗杠
        const anGangTiles = HuChecker.canAnGang(this.playerHand);
        if (anGangTiles.length > 0) {
            // 先出牌
            this.waitForPlayerDiscard();
            return;
        }

        this.waitForPlayerDiscard();
    }

    // ====== 玩家出牌 ======
    playerDiscard() {
        if (this.phase !== 'discarding' || this.currentPlayer !== 0) return;
        const selIdx = [...this.selectedTiles][0];
        const selectedTile = this.playerHand[selIdx];
        console.log('[MJ] 玩家出牌:', selectedTile ? selectedTile.id : 'unknown');
        if (this.selectedTiles.size !== 1) {
            this.showToast('请选择一张牌打出', 1000);
            return;
        }

        AudioEngine.discard();
        const index = [...this.selectedTiles][0];
        const tile = this.playerHand[index];

        // 移除手牌
        this.playerHand.splice(index, 1);
        this.selectedTiles.clear();
        this.renderPlayerHand();
        this.phase = 'idle';

        // 记录出牌
        this.lastDiscard = tile;
        this.discardPile.push(tile);
        this.renderDiscardArea();

        // 检查其他玩家是否能吃碰杠胡
        this.checkClaims(0, tile);
    }

    // ====== 检查玩家操作（吃碰杠胡） ======
    checkClaims(playerIndex, tile) {
        console.log('[MJ] checkClaims, 点炮者:', playerIndex, '牌:', tile.id);
        // 记录点炮者索引，供 playerWin 使用
        this.lastDiscarder = playerIndex;
        const aiIndex = playerIndex - 1; // AI索引(0,1,2)

        // 收集所有可用的claim操作
        const claims = [];

        // 检查胡（所有AI + 玩家自己）
        for (let i = 1; i <= 3; i++) {
            const pIdx = (playerIndex + i) % 4;
            if (pIdx === 0) continue; // 玩家是0，等JS端处理

            const aiIdx = pIdx - 1;
            const aiHand = this.aiHands[aiIdx];
            const testHand = [...aiHand, { ...tile }];
            if (HuChecker.isHu(testHand)) {
                return setTimeout(() => this.aiWin(aiIdx, tile, false, playerIndex), 800);
            }
        }

        // 检查玩家是否能吃碰杠胡
        this.checkPlayerClaims(tile, () => {
            // 如果玩家没有操作，检查AI的碰/杠/吃
            for (let i = 1; i <= 3; i++) {
                const pIdx = (playerIndex + i) % 4;
                if (pIdx === 0) continue;

                const aiIdx = pIdx - 1;
                const aiHand = this.aiHands[aiIdx];

                // 明杠（优先级高于碰）
                if (HuChecker.canGang(aiHand, tile)) {
                    return setTimeout(() => {
                        this.aiGang(aiIdx, tile);
                    }, 600);
                }

                // 碰
                if (HuChecker.canPeng(aiHand, tile)) {
                    return setTimeout(() => {
                        this.aiPeng(aiIdx, tile);
                    }, 600);
                }

                // 吃（只有出牌者的下家才能吃，即 i === 1）
                if (i === 1 && HuChecker.canChi(aiHand, tile).length > 0) {
                    return setTimeout(() => {
                        this.aiChi(aiIdx, tile);
                    }, 600);
                }
            }

            // 没人要，下家摸牌
            this.nextTurn();
        });
    }

    // ====== 检查玩家是否可以吃碰杠胡 ======
    checkPlayerClaims(tile, onNoClaim) {
        const claims = [];

        // 胡
        const testHand = [...this.playerHand, { ...tile }];
        if (HuChecker.isHu(testHand)) {
            claims.push({ type: 'hu', label: '🀄 胡！', style: 'hu', action: () => this.playerWin(false, tile) });
        }

        // 碰
        if (HuChecker.canPeng(this.playerHand, tile)) {
            claims.push({ type: 'peng', label: '碰', style: 'peng', action: () => this.playerPeng(tile) });
        }

        // 吃（只有上家出牌时才能吃）
        const prevPlayer = (this.dealerIndex - 1 + 4) % 4; // 玩家的上家
        if (this.currentPlayer === prevPlayer) {
            const chiOptions = HuChecker.canChi(this.playerHand, tile);
            if (chiOptions.length > 0) {
                if (chiOptions.length === 1) {
                    // 只有一种吃法，一个按钮
                    claims.push({ type: 'chi', label: '吃', style: 'chi', action: () => this.playerChi(tile, chiOptions) });
                } else {
                    // 多种吃法，每种一个按钮
                    chiOptions.forEach((opt, idx) => {
                        const tilesDisp = opt.tiles.map(tId => {
                            const p = this._parseTileId(tId);
                            return this.getTileDisplay(p);
                        }).join('');
                        claims.push({
                            type: 'chi',
                            label: `吃 ${tilesDisp}`,
                            style: 'chi',
                            action: () => this.playerChi(tile, chiOptions, idx)
                        });
                    });
                }
            }
        }

        // 明杠
        if (HuChecker.canGang(this.playerHand, tile)) {
            claims.push({ type: 'gang', label: '杠', style: 'gang', action: () => this.playerGang(tile) });
        }

        if (claims.length > 0) {
            this.phase = 'claiming';
            this.showClaimActions(claims, false, () => {
                // 过
                this.phase = 'idle';
                if (onNoClaim) onNoClaim();
            });
        } else {
            if (onNoClaim) onNoClaim();
        }
    }

    // ====== 显示吃碰杠胡操作 ======
    showClaimActions(actions, isUrgent, onPass) {
        const area = document.getElementById('claimArea');
        area.style.display = 'flex';
        area.innerHTML = '';

        actions.forEach(a => {
            const btn = document.createElement('button');
            btn.className = `mj-action-btn ${a.style || 'hu'}`;
            btn.textContent = a.label;
            btn.onclick = () => {
                AudioEngine.click();
                area.style.display = 'none';
                this.phase = 'idle';
                a.action();
            };
            area.appendChild(btn);
        });

        if (onPass) {
            const passBtn = document.createElement('button');
            passBtn.className = 'mj-action-btn pass';
            passBtn.textContent = '过';
            passBtn.onclick = () => {
                AudioEngine.click();
                area.style.display = 'none';
                this.phase = 'idle';
                onPass();
            };
            area.appendChild(passBtn);
        }
    }

    // ====== 自动摸牌（替代手动点击） ======
    autoDraw() {
        if (this.currentPlayer !== 0) return;
        console.log('[MJ] 玩家摸牌, 牌墙剩余:', this.wall.length);
        this.phase = 'drawing';
        AudioEngine.draw();

        const tile = TileEngine.drawTile(this.wall);
        if (!tile) {
            this.settleDraw();
            return;
        }

        this.playerHand.push(tile);
        this.playerHand = TileEngine.sortTiles(this.playerHand);
        this.renderPlayerHand();
        // 为新摸的牌添加动画
        setTimeout(() => {
            const handEl = document.getElementById('playerHand');
            if (handEl) {
                const tiles = handEl.querySelectorAll('.mj-tile');
                tiles.forEach(el => {
                    const idx = parseInt(el.dataset.index);
                    if (!isNaN(idx) && this.playerHand[idx] && this.playerHand[idx].id === tile.id) {
                        el.classList.add('just-drawn');
                        setTimeout(() => el.classList.remove('just-drawn'), 800);
                    }
                });
            }
        }, 50);
        this.updateWallCount();
        this.turnCount++;
        const turnEl = document.getElementById('turnCount');
        if (turnEl) turnEl.textContent = this.turnCount;

        // 检查自摸胡
        const canHu = HuChecker.isHu(this.playerHand);
        if (canHu) {
            this.phase = 'claiming';
            this.showClaimActions([{ type: 'hu', label: '🀄 胡！', action: () => this.playerWin(true) }], true);
            return;
        }

        // 检查暗杠
        const anGangTiles = HuChecker.canAnGang(this.playerHand);
        if (anGangTiles.length > 0) {
            this.phase = 'claiming';
            const gangActions = anGangTiles.map(id => ({
                type: 'gang',
                label: `暗杠 ${this.getTileDisplay(this._parseTileId(id))}`,
                style: 'gang',
                action: () => this.playerAnGang(id)
            }));
            // 加一个"取消"选项继续出牌
            gangActions.push({
                type: 'pass',
                label: '取消',
                style: 'pass',
                action: () => {
                    this.phase = 'idle';
                    this.waitForPlayerDiscard();
                }
            });
            this.showClaimActions(gangActions, true);
            return;
        }

        // 检查补杠（已碰的牌摸进第4张）
        const buGangResults = HuChecker.canBuGang(this.playerHand, this.myMelds || []);
        if (buGangResults.length > 0) {
            this.phase = 'claiming';
            const gangActions = buGangResults.map(bg => ({
                type: 'gang',
                label: `补杠 ${this.getTileDisplay(this._parseTileId(bg.tileId))}`,
                style: 'gang',
                action: () => this.playerBuGang(bg.meldIndex, bg.tileId)
            }));
            gangActions.push({
                type: 'pass',
                label: '取消',
                style: 'pass',
                action: () => {
                    this.phase = 'idle';
                    this.waitForPlayerDiscard();
                }
            });
            this.showClaimActions(gangActions, true);
            return;
        }

        // 检查听牌（选中牌时会自动显示听牌提示）
        this.waitForPlayerDiscard();
    }

    // ====== 下家摸牌 ======
    nextTurn() {
        console.log('[MJ] nextTurn, 新玩家:', (this.currentPlayer + 1) % 4, '回合:', this.turnCount + 1);
        this.phase = 'idle';
        this.currentPlayer = (this.currentPlayer + 1) % 4;
        this.turnCount++;

        if (this.currentPlayer === 0) {
            // 玩家回合 — 自动摸牌
            setTimeout(() => this.autoDraw(), 500);
        } else {
            this.highlightCurrentPlayer();
            // AI摸牌+出牌
            const aiIdx = this.currentPlayer - 1;
            setTimeout(() => this.aiTurn(aiIdx), 800);
        }
    }

    // ====== AI回合 ======
    aiTurn(aiIdx) {
        if (this.state !== 'playing') return;
        console.log('[MJ] AI回合:', aiIdx, '牌墙:', this.wall.length);

        this.highlightCurrentPlayer();

        // AI摸牌
        const tile = TileEngine.drawTile(this.wall);
        if (!tile) {
            this.settleDraw();
            return;
        }
        this.aiHands[aiIdx].push(tile);
        this.aiHands[aiIdx] = TileEngine.sortTiles(this.aiHands[aiIdx]);
        this.renderAiHands();
        this.updateWallCount();

        // 检查AI是否胡牌
        if (HuChecker.isHu(this.aiHands[aiIdx])) {
            this.aiWin(aiIdx, tile, true);
            return;
        }

        // 检查AI是否可以暗杠
        const anGangTiles = HuChecker.canAnGang(this.aiHands[aiIdx]);
        if (anGangTiles.length > 0) {
            // 简化：用第一个暗杠
            const gangId = anGangTiles[0];
            this._doAnGang(aiIdx, gangId);
            return;
        }

        // AI出牌
        setTimeout(() => {
            const aiGameState = {
                myMelds: this.aiMelds[aiIdx] || []
            };
            const discard = AIEngine.decideDiscard(this.aiHands[aiIdx], this.discardPile, aiGameState);
            if (!discard) { this.nextTurn(); return; }

            AudioEngine.discard();

            // 移除AI手牌
            const idx = this.aiHands[aiIdx].findIndex(t => t.id === discard.id);
            if (idx !== -1) this.aiHands[aiIdx].splice(idx, 1);
            this.renderAiHands();

            // AI台词
            const aiKey = game._getAIKey(aiIdx);
            const aiQuote = AIQuotes.onDiscard(aiIdx, MJ_AI_PLAYERS[aiKey]?.quotes);
            if (aiQuote) this.showAiBubble(aiIdx, aiQuote);

            // 记录出牌
            this.lastDiscard = discard;
            this.discardPile.push(discard);
            this.renderDiscardArea();

            // 检查其他玩家能否吃碰杠胡
            this.checkPostDiscard(aiIdx, discard);
        }, AI_THINK_DELAY.NORMAL);
    }

    // ====== 出牌后的全局检查 ======
    checkPostDiscard(aiIdx, tile) {
        console.log('[MJ] checkPostDiscard AI:', aiIdx, '出牌:', tile.id);
        const playerIdx = aiIdx + 1;

        // 先检查所有玩家能否胡
        const playerTest = [...this.playerHand, { ...tile }];
        if (HuChecker.isHu(playerTest)) {
            // 重要：必须在 playerWin 前设置点炮者索引
            this.lastDiscarder = playerIdx;
            this.phase = 'claiming';
            this.showClaimActions([
                { type: 'hu', label: '🀄 胡！', style: 'hu', action: () => this.playerWin(false, tile) }
            ], true, () => {
                // 过 — 继续检查其他人
                this.checkClaims(playerIdx, tile);
            });
            return;
        }

        // 检查吃碰杠胡（完整检查玩家+AI）
        this.checkClaims(playerIdx, tile);
    }

    nextTurnAfterCheck(aiIdx, tile) {
        // 检查AI能否碰/杠(简化：只检查玩家后面的AI)
        for (let i = 1; i <= 2; i++) {
            const checkAiIdx = (aiIdx + i) % 3;
            const checkHand = this.aiHands[checkAiIdx];

            if (HuChecker.canPeng(checkHand, tile)) {
                return setTimeout(() => {
                    this.aiPeng(checkAiIdx, tile);
                }, 600);
            }
            if (HuChecker.canGang(checkHand, tile)) {
                return setTimeout(() => {
                    this.aiGang(checkAiIdx, tile);
                }, 600);
            }
        }

        // 没人要，下家摸牌
        this.currentPlayer = (aiIdx + 2) % 4; // AI的下家
        if (this.currentPlayer === 0) {
            // 玩家回合 — 自动摸牌
            setTimeout(() => this.autoDraw(), 500);
        } else {
            this.highlightCurrentPlayer();
            setTimeout(() => this.aiTurn(this.currentPlayer - 1), 800);
        }
    }

    // ====== 玩家碰 ======
    playerPeng(tile) {
        console.log('[MJ] 玩家碰:', tile.id);
        AudioEngine.peng();
        // 从手牌移除2张
        let count = 0;
        this.playerHand = this.playerHand.filter(t => {
            if (t.id === tile.id && count < 2) { count++; return false; }
            return true;
        });
        this.myMelds.push({ type: 'peng', tiles: [tile.id, tile.id, tile.id], from: 'discard' });
        this.renderPlayerHand();
        this.renderPlayerMelds();

        this.currentPlayer = 0;
        this.phase = 'discarding';
        this.showToast('碰！', 1000);
        this.waitForPlayerDiscard();
    }

    // ====== 玩家吃 ======
    playerChi(tile, options, optionIdx) {
        console.log('[MJ] 玩家吃:', tile.id, '选项:', optionIdx !== undefined ? optionIdx : 0);
        AudioEngine.chi();
        const idx = (optionIdx !== undefined && optionIdx !== null) ? optionIdx : 0;
        const option = options[idx];
        if (!option) { this.showToast('选择无效', 1000); return; }
        const tilesToRemove = option.tiles.filter(id => id !== tile.id);
        const removeIds = new Set(tilesToRemove);
        let count = 0;
        this.playerHand = this.playerHand.filter(t => {
            if (removeIds.has(t.id) && count < 2) { count++; return false; }
            return true;
        });
        this.myMelds.push({ type: 'chi', tiles: option.tiles });
        this.renderPlayerHand();
        this.renderPlayerMelds();

        this.currentPlayer = 0;
        this.phase = 'discarding';
        this.showToast('吃！', 1000);
        this.waitForPlayerDiscard();
    }

    // ====== AI暗杠 ======
    _doAnGang(aiIdx, gangId) {
        console.log('[MJ] AI暗杠, AI:', aiIdx, '牌:', gangId);
        AudioEngine.gang();
        let count = 0;
        this.aiHands[aiIdx] = this.aiHands[aiIdx].filter(t => {
            if (t.id === gangId && count < 4) { count++; return false; }
            return true;
        });
        this.aiMelds[aiIdx].push({ type: 'gang', tiles: [gangId, gangId, gangId, gangId], isHidden: true });

        // 补牌
        const newTile = TileEngine.drawTile(this.wall);
        if (!newTile) { this.settleDraw(); return; }
        this.aiHands[aiIdx].push(newTile);
        this.aiHands[aiIdx] = TileEngine.sortTiles(this.aiHands[aiIdx]);
        this.renderAiHands();
        this.renderAiMelds();
        this.updateWallCount();

        // 检查杠上花
        if (HuChecker.isHu(this.aiHands[aiIdx])) {
            this.aiWin(aiIdx, newTile, true);
            return;
        }

        // 出牌
        setTimeout(() => {
            const discard = AIEngine.decideDiscard(this.aiHands[aiIdx], this.discardPile, { myMelds: this.aiMelds[aiIdx] });
            if (!discard) { this.nextTurn(); return; }
            AudioEngine.discard();
            const idx = this.aiHands[aiIdx].findIndex(t => t.id === discard.id);
            if (idx !== -1) this.aiHands[aiIdx].splice(idx, 1);
            this.renderAiHands();
            this.lastDiscard = discard;
            this.discardPile.push(discard);
            this.renderDiscardArea();
            this.checkPostDiscard(aiIdx, discard);
        }, AI_THINK_DELAY.NORMAL);
    }

    // ====== 玩家暗杠 ======
    playerAnGang(gangId) {
        console.log('[MJ] 玩家暗杠:', gangId);
        AudioEngine.gang();
        let count = 0;
        this.playerHand = this.playerHand.filter(t => {
            if (t.id === gangId && count < 4) { count++; return false; }
            return true;
        });
        this.myMelds.push({ type: 'gang', tiles: [gangId, gangId, gangId, gangId], isHidden: true });

        // 杠后补牌
        const newTile = TileEngine.drawTile(this.wall);
        if (!newTile) { this.settleDraw(); return; }
        this.playerHand.push(newTile);
        this.playerHand = TileEngine.sortTiles(this.playerHand);
        this.renderPlayerHand();
        // 新牌动画
        setTimeout(() => {
            const handEl = document.getElementById('playerHand');
            if (handEl) {
                handEl.querySelectorAll('.mj-tile').forEach(el => {
                    const idx = parseInt(el.dataset.index);
                    if (!isNaN(idx) && this.playerHand[idx] && this.playerHand[idx].id === newTile.id) {
                        el.classList.add('just-drawn');
                        setTimeout(() => el.classList.remove('just-drawn'), 800);
                    }
                });
            }
        }, 50);
        this.renderPlayerMelds();
        this.updateWallCount();

        // 检查杠上花
        if (HuChecker.isHu(this.playerHand)) {
            this.playerWin(true, null, true);
            return;
        }

        this.currentPlayer = 0;
        this.phase = 'discarding';
        this.showToast('暗杠！', 1000);
        this.waitForPlayerDiscard();
    }

    // ====== 玩家补杠（已碰后摸进第4张） ======
    playerBuGang(meldIndex, tileId) {
        console.log('[MJ] 玩家补杠, meld:', meldIndex, '牌:', tileId);
        AudioEngine.gang();
        // 从手牌中移除1张该牌
        let removed = false;
        this.playerHand = this.playerHand.filter(t => {
            if (!removed && t.id === tileId) { removed = true; return false; }
            return true;
        });
        // 将碰的3张升级为杠4张
        const meld = this.myMelds[meldIndex];
        if (meld) {
            meld.type = 'gang';
            meld.tiles = [tileId, tileId, tileId, tileId];
            meld.isHidden = false;
        }

        // 杠后补牌
        const newTile = TileEngine.drawTile(this.wall);
        if (!newTile) { this.settleDraw(); return; }
        this.playerHand.push(newTile);
        this.playerHand = TileEngine.sortTiles(this.playerHand);
        this.renderPlayerHand();
        setTimeout(() => {
            const handEl = document.getElementById('playerHand');
            if (handEl) {
                handEl.querySelectorAll('.mj-tile').forEach(el => {
                    const idx = parseInt(el.dataset.index);
                    if (!isNaN(idx) && this.playerHand[idx] && this.playerHand[idx].id === newTile.id) {
                        el.classList.add('just-drawn');
                        setTimeout(() => el.classList.remove('just-drawn'), 800);
                    }
                });
            }
        }, 50);
        this.renderPlayerMelds();
        this.updateWallCount();

        // 检查杠上花
        if (HuChecker.isHu(this.playerHand)) {
            this.playerWin(true, null, true);
            return;
        }

        this.currentPlayer = 0;
        this.phase = 'discarding';
        this.showToast('补杠！', 1000);
        this.waitForPlayerDiscard();
    }

    // ====== 玩家明杠 ======
    playerGang(tile) {
        console.log('[MJ] 玩家明杠:', tile.id);
        AudioEngine.gang();
        let count = 0;
        this.playerHand = this.playerHand.filter(t => {
            if (t.id === tile.id && count < 3) { count++; return false; }
            return true;
        });
        this.myMelds.push({ type: 'gang', tiles: [tile.id, tile.id, tile.id, tile.id], from: 'discard' });

        // 杠后补牌
        const newTile = TileEngine.drawTile(this.wall);
        if (!newTile) { this.settleDraw(); return; }
        this.playerHand.push(newTile);
        this.playerHand = TileEngine.sortTiles(this.playerHand);
        this.renderPlayerHand();
        // 新牌动画
        setTimeout(() => {
            const handEl = document.getElementById('playerHand');
            if (handEl) {
                handEl.querySelectorAll('.mj-tile').forEach(el => {
                    const idx = parseInt(el.dataset.index);
                    if (!isNaN(idx) && this.playerHand[idx] && this.playerHand[idx].id === newTile.id) {
                        el.classList.add('just-drawn');
                    }
                });
            }
        }, 50);
        this.renderPlayerMelds();
        this.updateWallCount();

        // 检查杠上花
        if (HuChecker.isHu(this.playerHand)) {
            this.playerWin(true, null, true);
            return;
        }

        this.currentPlayer = 0;
        this.phase = 'discarding';
        this.showToast('杠！', 1000);
        this.waitForPlayerDiscard();
    }

    // ====== AI碰 ======
    aiPeng(aiIdx, tile) {
        console.log('[MJ] AI碰, AI:', aiIdx, '牌:', tile.id);
        AudioEngine.peng();
        let count = 0;
        this.aiHands[aiIdx] = this.aiHands[aiIdx].filter(t => {
            if (t.id === tile.id && count < 2) { count++; return false; }
            return true;
        });
        this.aiMelds[aiIdx].push({ type: 'peng', tiles: [tile.id, tile.id, tile.id] });
        this.renderAiHands();
        this.renderAiMelds();

        const aiKey = game._getAIKey(aiIdx);
        const quote = AIQuotes.onMeld(aiIdx, MJ_AI_PLAYERS[aiKey]?.quotes);
        if (quote) this.showAiBubble(aiIdx, quote);

        // AI碰后出牌
        setTimeout(() => {
            const discard = AIEngine.decideDiscard(this.aiHands[aiIdx], this.discardPile, { myMelds: this.aiMelds[aiIdx] });
            if (!discard) { this.nextTurn(); return; }
            AudioEngine.discard();
            const idx = this.aiHands[aiIdx].findIndex(t => t.id === discard.id);
            if (idx !== -1) this.aiHands[aiIdx].splice(idx, 1);
            this.renderAiHands();
            this.lastDiscard = discard;
            this.discardPile.push(discard);
            this.renderDiscardArea();

            this.currentPlayer = aiIdx + 1;
            // 碰后继续检查
            this.checkPostDiscard(aiIdx, discard);
        }, AI_THINK_DELAY.QUICK);
    }

    // ====== AI吃 ======
    aiChi(aiIdx, tile) {
        console.log('[MJ] AI吃, AI:', aiIdx, '牌:', tile.id);
        AudioEngine.chi();
        const options = HuChecker.canChi(this.aiHands[aiIdx], tile);
        if (options.length === 0) { this.nextTurn(); return; }
        const option = options[0];
        const tilesToRemove = option.tiles.filter(id => id !== tile.id);
        const removeIds = new Set(tilesToRemove);
        let count = 0;
        this.aiHands[aiIdx] = this.aiHands[aiIdx].filter(t => {
            if (removeIds.has(t.id) && count < 2) { count++; return false; }
            return true;
        });
        this.aiMelds[aiIdx].push({ type: 'chi', tiles: option.tiles, from: 'discard' });
        this.renderAiHands();
        this.renderAiMelds();

        const aiKey = game._getAIKey(aiIdx);
        const quote = AIQuotes.onMeld(aiIdx, MJ_AI_PLAYERS[aiKey]?.quotes);
        if (quote) this.showAiBubble(aiIdx, quote);

        // AI吃后出牌
        setTimeout(() => {
            const discard = AIEngine.decideDiscard(this.aiHands[aiIdx], this.discardPile, { myMelds: this.aiMelds[aiIdx] });
            if (!discard) { this.nextTurn(); return; }
            AudioEngine.discard();
            const idx = this.aiHands[aiIdx].findIndex(t => t.id === discard.id);
            if (idx !== -1) this.aiHands[aiIdx].splice(idx, 1);
            this.renderAiHands();
            this.lastDiscard = discard;
            this.discardPile.push(discard);
            this.renderDiscardArea();
            this.checkPostDiscard(aiIdx, discard);
        }, AI_THINK_DELAY.NORMAL);
    }

    // ====== AI明杠 ======
    aiGang(aiIdx, tile) {
        console.log('[MJ] AI明杠, AI:', aiIdx, '牌:', tile.id);
        AudioEngine.gang();
        let count = 0;
        this.aiHands[aiIdx] = this.aiHands[aiIdx].filter(t => {
            if (t.id === tile.id && count < 3) { count++; return false; }
            return true;
        });
        this.aiMelds[aiIdx].push({ type: 'gang', tiles: [tile.id, tile.id, tile.id, tile.id], from: 'discard' });
        this.renderAiHands();
        this.renderAiMelds();

        // 补牌
        const newTile = TileEngine.drawTile(this.wall);
        if (!newTile) { this.settleDraw(); return; }
        this.aiHands[aiIdx].push(newTile);
        this.aiHands[aiIdx] = TileEngine.sortTiles(this.aiHands[aiIdx]);
        this.renderAiHands();
        this.updateWallCount();

        setTimeout(() => {
            const discard = AIEngine.decideDiscard(this.aiHands[aiIdx], this.discardPile, { myMelds: this.aiMelds[aiIdx] });
            if (!discard) { this.nextTurn(); return; }
            const idx = this.aiHands[aiIdx].findIndex(t => t.id === discard.id);
            if (idx !== -1) this.aiHands[aiIdx].splice(idx, 1);
            this.renderAiHands();
            this.lastDiscard = discard;
            this.discardPile.push(discard);
            this.renderDiscardArea();
            this.checkPostDiscard(aiIdx, discard);
        }, AI_THINK_DELAY.NORMAL);
    }

    // ====== AI胡 ======
    async aiWin(aiIdx, tile, isSelfDraw, discarderIdx) {
        console.log('[MJ] AI胡! AI:', aiIdx, '自摸:', isSelfDraw, '点炮:', discarderIdx);
        AudioEngine.hu();
        this.state = 'settled';

        const aiKey = game._getAIKey(aiIdx);
        const quote = AIQuotes.onWin(aiIdx, MJ_AI_PLAYERS[aiKey]?.quotes);
        if (quote) this.showAiBubble(aiIdx, quote);

        // 计算番数
        let fanResult = { total: 0, fans: [] };
        const winHand = [...this.aiHands[aiIdx]];
        try {
            const melds = this.aiMelds[aiIdx] || [];
            fanResult = FanCalculator.calculate({
                hand: winHand,
                melds: melds,
                winTile: tile,
                isSelfDraw: isSelfDraw,
                isDealer: false,
                wall: this.wall,
                discards: this.discardPile
            });
        } catch(e) {
            console.warn('AI FanCalc error:', e);
            fanResult = { total: 1, fans: [{ name: '胡牌', fan: 1 }] };
        }

        const baseScore = MJ_CONFIG.baseScore || 50;
        const fanTotal = fanResult.total;
        const winnerRealIdx = aiIdx + 1; // AI的实际玩家索引(1,2,3)

        // 计算4人分数变化（不含buff倍率，AI赢时buff不生效）
        const playerScoreChanges = [0, 0, 0, 0];
        const payPerPerson = fanTotal * baseScore;

        if (isSelfDraw) {
            // 自摸：其他3人各付1份
            for (let i = 0; i < 4; i++) {
                if (i === winnerRealIdx) {
                    playerScoreChanges[i] = payPerPerson * 3; // 胡牌者收3份
                } else {
                    playerScoreChanges[i] = -payPerPerson; // 其他3人各付1份
                }
            }
        } else {
            // 点炮
            const discarder = (discarderIdx !== undefined && discarderIdx !== null) ? discarderIdx : 0;
            for (let i = 0; i < 4; i++) {
                if (i === winnerRealIdx) {
                    playerScoreChanges[i] = payPerPerson * 3; // 胡牌者收3份
                } else if (i === discarder) {
                    playerScoreChanges[i] = -payPerPerson * 3; // 点炮者付3份
                } else {
                    playerScoreChanges[i] = 0; // 其他人不变
                }
            }
        }

        // 玩家(索引0)的分数变化
        this.roundScore = playerScoreChanges[0];

        const fanStr = fanResult.fans.map(f => `${f.name}(${f.fan}番)`).join('、');

        this.showSettlement({
            winner: 'ai',
            winnerName: this.players[winnerRealIdx].name,
            isSelfDraw: isSelfDraw,
            fanCount: fanTotal,
            fans: fanStr,
            fanArray: fanResult.fans,
            scoreChange: this.roundScore,
            handTiles: winHand,
            melds: this.aiMelds[aiIdx] || [],
            buffMultiplier: (winnerRealIdx === 0 && this.buffMultiplier > 1) ? this.buffMultiplier : 0,
            players: [
                { name: this.players[0].name, scoreChange: playerScoreChanges[0] },
                { name: this.players[1].name, scoreChange: playerScoreChanges[1] },
                { name: this.players[2].name, scoreChange: playerScoreChanges[2] },
                { name: this.players[3].name, scoreChange: playerScoreChanges[3] }
            ]
        });

        // 保存分数 — 对所有有分数变化的玩家进行操作
        if (playerScoreChanges[0] !== 0) {
            await this.saveGameResult(playerScoreChanges[0] > 0 ? 'win' : 'lose', fanResult.total, fanStr, 
                isSelfDraw ? 'self_draw' : 'discard');
        }

        // 发送结束信号关闭游戏记录（防逃跑）
        this.sendSignal('end');

        // 对所有有分数变化的玩家保存排行榜分数
        try {
            for (let i = 0; i < 4; i++) {
                const change = playerScoreChanges[i];
                if (change === 0) continue;
                // 已经保存了玩家(索引0)，跳过
                if (i === 0) continue;
                // 跳过得分为0的
                const name = this.players[i].name;
                const resultType = change > 0 ? 'win' : 'lose';
                const fd = new FormData();
                const aiKey = this._getAIKey(i - 1);
                const aiAvatar = (i > 0 && MJ_AI_PLAYERS[aiKey]?.avatar) || '';
                fd.append('nickname', name);
                fd.append('avatar', aiAvatar);
                fd.append('score_change', change); // 原始值（赢为正，输为负）
                fd.append('result', resultType);
                fetch('?plugin=wx_games&game=mj&mj_action=save_ai_score', { method: 'POST', body: fd });
            }
        } catch(e) {}
    }

    // ====== 玩家胡 ======
    async playerWin(isSelfDraw, winTile, isGangShangHua) {
        console.log('[MJ] 玩家胡! 自摸:', isSelfDraw, '杠上花:', isGangShangHua);
        AudioEngine.hu();
        this.state = 'settled';

        // 显示胡牌特效
        const effect = document.createElement('div');
        effect.className = 'mj-win-effect';
        document.body.appendChild(effect);
        setTimeout(() => effect.remove(), 500);

        // 计算番数
        let fanResult = { total: 0, fans: [] };
        try {
            fanResult = FanCalculator.calculate({
                hand: [...this.playerHand],
                melds: this.myMelds || [],
                winTile: winTile,
                isSelfDraw: isSelfDraw,
                isDealer: false,
                wall: this.wall,
                discards: this.discardPile,
                isGangShangHua: isGangShangHua || false
            });
        } catch(e) {
            console.warn('FanCalc error:', e);
            fanResult = { total: 1, fans: [{ name: '胡牌', fan: 1 }] };
        }

        const baseScore = MJ_CONFIG.baseScore || 50;
        const fanTotal = fanResult.total;
        const payPerPerson = fanTotal * baseScore; // 不含buff倍率，倍率只影响玩家

        // 计算4人分数变化（基础分）
        let playerScoreChanges = [0, 0, 0, 0];
        if (isSelfDraw) {
            // 自摸：赢家收3份，其他各出1份
            playerScoreChanges[0] = payPerPerson * 3;
            for (let i = 1; i <= 3; i++) {
                playerScoreChanges[i] = -payPerPerson;
            }
        } else {
            // 点炮：点炮者出3份，其他人不变
            const discarder = this.lastDiscarder !== undefined ? this.lastDiscarder : 1;
            playerScoreChanges[0] = payPerPerson * 3;
            for (let i = 1; i <= 3; i++) {
                playerScoreChanges[i] = (i === discarder) ? -payPerPerson * 3 : 0;
            }
        }

        // 积分加成卡：仅对玩家自己的最终得分应用倍率
        if (this.buffMultiplier > 1) {
            playerScoreChanges[0] = Math.round(playerScoreChanges[0] * this.buffMultiplier);
        }

        this.roundScore = playerScoreChanges[0];

        const fanStr = fanResult.fans.map(f => `${f.name}(${f.fan}番)`).join('、');

        this.showSettlement({
            winner: 'player',
            winnerName: MJ_USER.nickname || '我',
            isSelfDraw: isSelfDraw,
            fanCount: fanTotal,
            fans: fanStr,
            fanArray: fanResult.fans,
            scoreChange: this.roundScore || payPerPerson * 3,
            handTiles: this.playerHand,
            melds: this.myMelds || [],
            winTile: winTile,
            buffMultiplier: this.buffMultiplier > 1 ? this.buffMultiplier : 0,
            players: [
                { name: MJ_USER.nickname || '我', scoreChange: playerScoreChanges[0] },
                { name: this.players[1].name, scoreChange: playerScoreChanges[1] },
                { name: this.players[2].name, scoreChange: playerScoreChanges[2] },
                { name: this.players[3].name, scoreChange: playerScoreChanges[3] }
            ]
        });

        // 保存分数
        try {
            await this.saveGameResult('win', fanResult.total, fanStr, isSelfDraw ? 'self_draw' : 'draw');
        } catch(e) {}
        this.sendSignal('end');
        // 保存输家AI的分数
        try {
            for (let i = 1; i <= 3; i++) {
                if (playerScoreChanges[i] < 0) {
                    const fd = new FormData();
                    fd.append('nickname', this.players[i].name);
                    fd.append('avatar', '');
                    fd.append('score_change', playerScoreChanges[i]); // 原始值（负值表示输）
                    fd.append('result', 'lose');
                    fetch('?plugin=wx_games&game=mj&mj_action=save_ai_score', { method: 'POST', body: fd });
                }
            }
        } catch(e) {}
    }

    // ====== 流局 ======
    settleDraw() {
        console.log('[MJ] 流局! 牌墙已空');
        this.state = 'settled';
        this.showToast('流局！牌墙已摸完', 2000);
        setTimeout(() => {
            this.showSettlement({
                winner: 'draw',
                winnerName: '流局',
                isSelfDraw: false,
                fanCount: 0,
                fans: '',
                scoreChange: 0,
                buffMultiplier: 0
            });
            this.saveGameResult('draw', 0, '', 'draw');
            this.sendSignal('end');
        }, 1500);
    }

    // ====== 显示结算弹窗 ======
    showSettlement(data) {
        console.log('[MJ] 结算:', data.winner, '番数:', data.fanCount, '分数变化:', data.scoreChange);
        if (data.players) {
            console.log('[MJ] 4人分数:', data.players.map(p => `${p.name}:${p.scoreChange}`).join(', '));
        }
        // 构建番种显示（带悬浮说明）
        let fanItemsHtml = '';
        if (data.fanArray && data.fanArray.length > 0) {
            fanItemsHtml = data.fanArray.map(f => {
                const desc = FAN_TYPES[f.key] ? FAN_TYPES[f.key].desc : (f.desc || '');
                const titleAttr = desc ? ` title="${desc}"` : '';
                return `<span class="fan-item"${titleAttr}>${f.name}(${f.fan}番)</span>`;
            }).join('');
        } else if (data.fans) {
            fanItemsHtml = data.fans.split('、').filter(f => f).map(f =>
                `<span class="fan-item">${f}</span>`
            ).join('');
        }

        // 构建赢家手牌显示
        let handHtml = '';
        if (data.handTiles && data.handTiles.length > 0) {
            // 先显示副露（吃碰杠）
            if (data.melds && data.melds.length > 0) {
                handHtml += '<div class="settlement-melds">';
                data.melds.forEach(m => {
                    handHtml += '<div class="settlement-meld-group">';
                    m.tiles.forEach((tileId, idx) => {
                        const tile = typeof tileId === 'object' ? tileId : this._parseTileId(tileId);
                        handHtml += `<span class="mj-tile-sm"><img src="${meldTileImg(tile)}" class="tile-img tile-img-sm" alt="" draggable="false"></span>`;
                    });
                    handHtml += '</div>';
                });
                handHtml += '</div>';
            }
            // 然后显示手牌
            handHtml += '<div class="settlement-hand">';
            data.handTiles.forEach(t => {
                const tile = t.id ? t : { id: t };
                handHtml += `<span class="mj-tile-sm"><img src="${tileImg(tile)}" class="tile-img tile-img-sm" alt="" draggable="false"></span>`;
            });
            // 胡的牌单独展示并高亮（玩家赢时 handTiles 不含 winTile）
            if (data.winTile) {
                const wt = data.winTile.id ? data.winTile : { id: data.winTile };
                handHtml += `<span class="mj-tile-sm mj-tile-win"><img src="${tileImg(wt)}" class="tile-img tile-img-sm" alt="" draggable="false"></span>`;
            }
            handHtml += '</div>';
        }

        let html = '<div class="mj-modal-overlay" onclick="closeModal()"><div class="mj-modal settlement-modal" onclick="event.stopPropagation()">';
        html += '<div class="mj-modal-body">';

        if (data.winner === 'player') {
            html += `<div class="result-icon">🎉</div>
                     <div class="result-text win">胡牌！</div>`;
        } else if (data.winner === 'draw') {
            html += `<div class="result-icon">🤝</div>
                     <div class="result-text" style="color:var(--text-muted)">流局</div>`;
        } else {
            const gain = data.players ? data.players.find(p => parseInt(p.scoreChange) > 0) : null;
            const winScore = gain ? gain.scoreChange : Math.abs(data.scoreChange);
            html += `<div class="result-icon">😅</div>
                     <div class="result-text lose">${data.winnerName} 胡牌</div>
                     <div class="score-change positive" style="margin-top:4px">+${winScore}</div>`;
        }

        html += `${handHtml}
                 <div class="fan-detail">
                    <div>总番数: <strong>${data.fanCount}</strong> 番</div>
                    <div class="mt-1">${fanItemsHtml}</div>
                 </div>
                 <div class="mt-1 player-scores">
                    <div class="player-score-row">
                        <span class="ps-name">${data.players ? data.players[0].name : '我'}</span>
                        <span class="ps-score ${(data.players ? data.players[0].scoreChange : 0) >= 0 ? 'positive' : 'negative'}">${(data.players ? data.players[0].scoreChange : 0) >= 0 ? '+' : ''}${data.players ? data.players[0].scoreChange : 0}${data.buffMultiplier > 1 ? ' <span class="badge-score" style="font-size:10px;background:rgba(243,156,18,0.2);color:#f39c12;padding:0 4px;border-radius:4px;">×' + data.buffMultiplier + '</span>' : ''}</span>
                        <span class="ps-name">${data.players ? data.players[1].name : 'AI1'}</span>
                        <span class="ps-score ${(data.players ? data.players[1].scoreChange : 0) >= 0 ? 'positive' : 'negative'}">${(data.players ? data.players[1].scoreChange : 0) >= 0 ? '+' : ''}${data.players ? data.players[1].scoreChange : 0}</span>
                    </div>
                    <div class="player-score-row">
                        <span class="ps-name">${data.players ? data.players[2].name : 'AI2'}</span>
                        <span class="ps-score ${(data.players ? data.players[2].scoreChange : 0) >= 0 ? 'positive' : 'negative'}">${(data.players ? data.players[2].scoreChange : 0) >= 0 ? '+' : ''}${data.players ? data.players[2].scoreChange : 0}</span>
                        <span class="ps-name">${data.players ? data.players[3].name : 'AI3'}</span>
                        <span class="ps-score ${(data.players ? data.players[3].scoreChange : 0) >= 0 ? 'positive' : 'negative'}">${(data.players ? data.players[3].scoreChange : 0) >= 0 ? '+' : ''}${data.players ? data.players[3].scoreChange : 0}</span>
                    </div>
                 </div>
                 <button class="mj-btn mj-btn-gold" onclick="closeModal(); game.startGame()">再来一局</button>
                 <button class="mj-btn mj-btn-outline mt-1" onclick="closeModal(); game.renderStartPage(false)">返回大厅</button>
                 </div></div></div>`;

        const modal = document.createElement('div');
        modal.innerHTML = html;
        document.body.appendChild(modal.firstElementChild);
    }

    // ====== 保存游戏结果 ======
    async saveGameResult(result, fanCount, fanType, winType) {
        if (MJ_USER.uid <= 0) return;

        try {
            const handStr = TileEngine.tilesToIdString(this.playerHand);
            const winTile = this.lastDiscard ? this.lastDiscard.id : '';
            const finalHand = handStr; // 游戏结束时 playerHand 即为最终手牌

            // 统一走 saveScore 路径（简单可靠）
            const resp = await Leaderboard.saveScore(this.roundScore, {
                result: result,
                fanCount: fanCount,
                fanType: fanType,
                winType: winType,
                handTiles: handStr,
                finalHand: finalHand,
                winTile: winTile,
                winner: result === 'win' ? 'player' : 'ai',
                gameToken: this.gameToken
            });
            if (resp && resp.code === 0) {
                console.log('[Score] 玩家分数已保存:', this.roundScore, result);
                // 保存成功后刷新导航栏积分
                this._refreshNavScore();
            } else {
                console.warn('[Score] 保存失败:', resp);
                // 尝试用 completeGame 兜底
                if (this.gameToken) {
                    try {
                        await Leaderboard.completeGame(this.roundScore, {
                            result, fanCount, fanType, winType,
                            handTiles: handStr,
                            finalHand: finalHand,
                            winTile: winTile,
                            winner: result === 'win' ? 'player' : 'ai',
                            gameToken: this.gameToken
                        });
                        console.log('[Score] completeGame兜底成功');
                    } catch(e2) {
                        console.warn('[Score] completeGame兜底也失败:', e2);
                    }
                }
            }

        } catch(e) {}
    }

    // ====== 刷新导航栏积分 ======
    async _refreshNavScore() {
        if (MJ_USER.uid <= 0) return;
        try {
            const resp = await fetch('?plugin=wx_games&game=mj&mj_action=get_my_rank', { credentials: 'include' });
            const data = await resp.json();
            if (data.code === 0 && data.data) {
                const navScore = document.getElementById('navUserScore');
                if (navScore) navScore.textContent = data.data.score || 0;
                if (window.MJ_USER_SCORE) window.MJ_USER_SCORE.score = data.data.score || 0;
            }
        } catch(e) {
            console.warn('[Score] 刷新积分失败:', e);
        }
    }

    // ====== 渲染出牌区（增量更新） ======
    renderDiscardArea() {
        const area = document.getElementById('discardArea');
        if (!area) return;
        const total = this.discardPile.length;
        const existing = area.children.length;
        // 只添加尚未渲染的新牌
        for (let i = existing; i < total; i++) {
            const tile = this.discardPile[i];
            const div = document.createElement('div');
            const isLatest = i === total - 1;
            div.className = `mj-river-tile${isLatest ? ' latest' : ''}`;
            div.innerHTML = `<img src="${meldTileImg(tile)}" class="tile-img tile-img-sm" alt="" draggable="false">`;
            area.appendChild(div);
        }
        // 最多保留80张，移除最早的
        while (area.children.length > 80) {
            area.removeChild(area.firstChild);
        }
        // 更新最新一张的样式
        if (total > 0) {
            area.querySelectorAll('.latest').forEach(el => el.classList.remove('latest'));
            const lastChild = area.lastChild;
            if (lastChild) lastChild.classList.add('latest');
        }
        area.scrollTop = area.scrollHeight;
    }

    // ====== 渲染玩家副露（吃碰杠显示） ======
    renderPlayerMelds() {
        const container = document.getElementById('playerMelds');
        if (!container) return;
        container.innerHTML = '';
        (this.myMelds || []).forEach(m => {
            const group = document.createElement('div');
            group.className = 'mj-meld-group';
            m.tiles.forEach((tileId, idx) => {
                const div = document.createElement('div');
                const isClaimed = (m.from === 'discard' && idx === m.tiles.length - 1);
                const tile = this._parseTileId(tileId);
                div.className = `mj-meld-tile${isClaimed ? ' mj-meld-claimed' : ''}`;
                div.innerHTML = `<img src="${meldTileImg(tile)}" class="tile-img tile-img-xs" alt="" draggable="false">`;
                group.appendChild(div);
            });
            container.appendChild(group);
        });
    }

    // ====== 渲染AI副露（吃碰杠显示） ======
    renderAiMelds() {
        const aiPositions = ['aiLeftMelds', 'aiTopMelds', 'aiRightMelds'];
        for (let i = 0; i < 3; i++) {
            const container = document.getElementById(aiPositions[i]);
            if (!container) continue;
            container.innerHTML = '';
            (this.aiMelds[i] || []).forEach(m => {
                const group = document.createElement('div');
                group.className = 'mj-meld-group';
                m.tiles.forEach((tileId, idx) => {
                    const div = document.createElement('div');
                    const isClaimed = (m.from === 'discard' && idx === m.tiles.length - 1);
                    const tile = this._parseTileId(tileId);
                    div.className = `mj-meld-tile${isClaimed ? ' mj-meld-claimed' : ''}`;
                    div.innerHTML = `<img src="${meldTileImg(tile)}" class="tile-img tile-img-xs" alt="" draggable="false">`;
                    group.appendChild(div);
                });
                container.appendChild(group);
            });
        }
    }

    // ====== 更新牌墙计数 ======
    updateWallCount() {
        const el = document.getElementById('wallCount');
        if (el) el.textContent = this.wall.length;
    }

    // ====== 更新选中牌的听牌提示 ======
    _updateTingOnSelection() {
        const el = document.getElementById('tingIndicator');
        if (!el) return;
        // 没有选中牌，隐藏提示
        if (this.selectedTiles.size === 0) {
            el.style.display = 'none';
            return;
        }
        // 获取选中的牌索引
        const selIdx = [...this.selectedTiles][0];
        if (selIdx === undefined || selIdx >= this.playerHand.length) {
            el.style.display = 'none';
            return;
        }
        const selectedTile = this.playerHand[selIdx];
        // 构建移除选中牌后的13张手牌
        const testHand = this.playerHand.filter((t, i) => i !== selIdx);
        // 检查听牌：移除该牌后是否听牌
        const tingTiles = HuChecker.checkTing(testHand, this.discardPile, this.myMelds || []);
        if (tingTiles.length > 0) {
            // 计算每张可胡牌的剩余数量
            const allDiscarded = [...this.discardPile, ...(this.myMelds || []).flatMap(m => m.tiles || [])];
            const tingParts = tingTiles.map(t => {
                const p = this._parseTileId(t.tile);
                // 计算剩余：4 - 手牌中有几张 - 副露出几张 - 牌河出几张
                const inHand = this.playerHand.filter(th => th.id === t.tile).length;
                const inMelds = allDiscarded.filter(d => (typeof d === 'string' ? d : d.id) === t.tile).length;
                const remaining = 4 - inHand - inMelds;
                return {
                    name: this.getTileDisplay(p),
                    remaining: Math.max(0, remaining)
                };
            });
            // 去重合并同名牌
            const merged = {};
            tingParts.forEach(tp => {
                if (merged[tp.name]) {
                    merged[tp.name].remaining = Math.max(merged[tp.name].remaining, tp.remaining);
                } else {
                    merged[tp.name] = { ...tp };
                }
            });
            const names = Object.values(merged).map(tp =>
                `${tp.name}(${tp.remaining}张)`
            ).join('、');
            el.innerHTML = `🔔 等: ${names}`;
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    }

    // ====== 显示Toast ======
    showToast(msg, duration) {
        const existing = document.querySelector('.mj-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'mj-toast';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), duration || 2000);
    }

    // ====== 显示AI气泡 ======
    showAiBubble(aiIdx, text) {
        const positions = ['bubbleLeft', 'bubbleTop', 'bubbleRight'];
        const el = document.getElementById(positions[aiIdx]);
        if (!el) return;
        el.textContent = text;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3000);
    }

    // ====== 提示功能 ======
    showHint() {
        if (this.phase !== 'discarding' || this.currentPlayer !== 0) return;
        AudioEngine.click();

        // 用AI引擎推荐出牌
        const hint = AIEngine.decideDiscard(this.playerHand, this.discardPile, { myMelds: this.myMelds });
        if (!hint) return;

        const index = this.playerHand.findIndex(t => t.id === hint.id && !this.selectedTiles.has(this.playerHand.indexOf(t)));
        if (index !== -1) {
            this.selectedTiles.clear();
            this.selectedTiles.add(index);
            this.renderPlayerHand();
            this.updateActionButtons();
        }
    }

    // ====== 取消选择 ======
    cancelSelection() {
        this.selectedTiles.clear();
        this.renderPlayerHand();
        this.updateActionButtons();
    }

    // ====== 显示/隐藏积分加成指示 ======
    showBuffDisplay(show) {
        const el = document.getElementById('buffDisplay');
        if (el) el.style.display = show ? 'flex' : 'none';
    }

    // ====== 发送信号 ======
    sendSignal(type) {
        if (!this.gameToken) {
            console.log('[MJ] sendSignal 跳过, 无gameToken');
            return;
        }
        console.log('[MJ] sendSignal:', type, 'token:', this.gameToken.substring(0,8) + '...');
        try {
            const url = `?plugin=wx_games&game=mj&wx_mojang_signal=${type}&token=${this.gameToken}&_=${Date.now()}`;
            if (type === 'end' || type === 'penalty') {
                // end/penalty 用 sendBeacon（即使页面关闭也能送达）
                navigator.sendBeacon(url);
            } else {
                fetch(url).catch(e => console.warn('[Signal]', type, 'failed:', e));
            }
        } catch(e) {}
    }
}

// ====== 监听浏览器关闭（防逃跑 — 参照 ddz 逻辑） ======
window.addEventListener('beforeunload', function(e) {
    if (!window.game || game.state !== 'playing' || !game.gameToken || !MJ_USER || MJ_USER.uid <= 0) return;

    // 用本地游戏数据计算实际惩罚
    var penalty = (MJ_CONFIG.baseScore || 100) * (MJ_CONFIG.penaltyMultiplier || 2);
    console.log('[MJ] beforeunload 检测到游戏进行中, 可能惩罚:', penalty);

    // sendBeacon 会携带 cookie 发送（浏览器不拦截）
    navigator.sendBeacon(`?plugin=wx_games&game=mj&wx_mojang_signal=penalty&points=${penalty}`);

    // 浏览器原生确认框（ddz 风格）
    e.preventDefault();
    e.returnValue = '游戏进行中，离开将被扣除 ' + penalty + ' 积分（' + (MJ_CONFIG.baseScore || 100) + '×惩罚' + (MJ_CONFIG.penaltyMultiplier || 2) + '）！';
});

// ====== 启动游戏 ======
let game;
document.addEventListener('DOMContentLoaded', () => {
    game = new MJGame();
    window.game = game;

    // 导航栏滚动效果（ddz 风格）
    const nav = document.getElementById('mjNav');
    if (nav) {
        window.addEventListener('scroll', function() {
            nav.classList.toggle('scrolled', window.scrollY > 10);
        }, { passive: true });
    }
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
