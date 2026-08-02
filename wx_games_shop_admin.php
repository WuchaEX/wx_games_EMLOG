<?php
defined('EMLOG_ROOT') || exit('access denied!');
require_once __DIR__ . '/wx_games.php';

$db = Database::getInstance();
$table_shop = DB_PREFIX . 'wx_games_shop_items';
$base_url = BLOG_URL . 'admin/plugin.php?plugin=wx_games&game=shop';
$filter = isset($_GET['filter']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['filter']) : 'global';

// ========== 道具类型映射 ==========
$ALL_ITEM_TYPES = [
    // 通用类 (is_global=1)
    'title_colored' => '昵称变色',
    'title_effect'  => '昵称特效',
    'title_badge'   => '称号徽章',
    'emoticon'      => '专属表情',
    'win_effect'    => '获胜效果',
    // 斗地主专属
    'bomb_effect'   => '炸弹特效',
    'score_buff'    => '积分加成',
    // 弹珠台专属
    'plinko_coin_pack' => '弹珠数量',
    'plinko_skin'   => '弹珠皮肤',
    'member_unlock' => '成员解锁',
];
// 通用道具（is_global=1 可创建的）
$GLOBAL_TYPES = ['title_colored', 'title_effect', 'title_badge', 'emoticon', 'win_effect'];
// 各游戏专属道具
$GAME_TYPES = [
    'ddz'    => ['bomb_effect', 'score_buff'],
    'mj'     => ['score_buff'],
    'niuniu' => ['bomb_effect', 'score_buff'],
    'plinko' => ['plinko_coin_pack', 'plinko_skin', 'member_unlock', 'score_buff'],
];
// 按 $filter 显示
if ($filter === 'global') {
    $ITEM_TYPES = array_intersect_key($ALL_ITEM_TYPES, array_flip($GLOBAL_TYPES));
} elseif (isset($GAME_TYPES[$filter])) {
    $ITEM_TYPES = array_intersect_key($ALL_ITEM_TYPES, array_flip(array_merge($GLOBAL_TYPES, $GAME_TYPES[$filter])));
} else {
    $ITEM_TYPES = $ALL_ITEM_TYPES;
}
$ITEM_TYPE_ICONS = [
    'title_colored' => ['🎨', '{"color":"#ff4500"}'],
    'title_effect'  => ['✨', '{"effect":"glow","color":"gold"}'],
    'title_badge'   => ['👑', '{"badge":"称号"}'],
    'emoticon'      => ['😎', '{"code":"victory"}'],
    'win_effect'    => ['🎉', '{"effect":"confetti","color":"gold"}'],
    'bomb_effect'   => ['💥', '{"effect":"fire"}'],
    'score_buff'    => ['⚡', '{"multiplier":1.5,"games":5}'],
    'plinko_coin_pack' => ['💰', '{"coins":1000}'],
    'plinko_skin'   => ['🎱', '{"skin_name":"金球"}'],
    'member_unlock' => ['🔓', '{"member":"boram"}'],
];
$GAME_NAMES = ['ddz' => '斗地主', 'mj' => '麻将', 'niuniu' => '斗牛', 'plinko' => '弹珠台'];

// ========== POST: 新增商品 ==========
if (Input::postStrVar('shop_action') === 'add_item') {
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) emMsg('商品名称不能为空', $base_url);
    $game = addslashes(trim(Input::postStrVar('game', 'ddz')));
    $is_global = Input::postIntVar('is_global', 0);
    $item_type = addslashes(trim(Input::postStrVar('item_type', 'title_colored')));
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_game = Input::postIntVar('price_game', 0);
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $sort_order = Input::postIntVar('sort_order', 10);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $db->query("INSERT INTO `$table_shop` (`game`,`name`,`description`,`icon`,`item_type`,`effect_data`,`price_emlog`,`price_game`,`stock`,`max_per_user`,`sort_order`,`status`,`is_global`,`created_at`)
        VALUES ('$game','$name','$description','$icon','$item_type','$effect_data',$price_emlog,$price_game,$stock,$max_per_user,$sort_order,1,$is_global," . time() . ")");
    emMsg('商品已添加', $base_url);
}

// ========== POST: 编辑商品 ==========
if (Input::postStrVar('shop_action') === 'edit_item') {
    $edit_id = Input::postIntVar('item_id', 0);
    if ($edit_id <= 0) emMsg('参数错误', $base_url);
    $name = addslashes(trim(Input::postStrVar('name', '')));
    if (empty($name)) emMsg('商品名称不能为空', $base_url);
    $game = addslashes(trim(Input::postStrVar('game', 'ddz')));
    $is_global = Input::postIntVar('is_global', 0);
    $item_type = addslashes(trim(Input::postStrVar('item_type', 'title_colored')));
    $effect_data = $db->escape_string(stripslashes(trim(Input::postStrVar('effect_data', '{}'))));
    $price_emlog = Input::postIntVar('price_emlog', 0);
    $price_game = Input::postIntVar('price_game', 0);
    $description = addslashes(trim(Input::postStrVar('description', '')));
    $icon = addslashes(trim(Input::postStrVar('icon', '')));
    $sort_order = Input::postIntVar('sort_order', 10);
    $status = Input::postIntVar('status', 1);
    $stock = Input::postIntVar('stock', -1);
    $max_per_user = Input::postIntVar('max_per_user', 0);
    $db->query("UPDATE `$table_shop` SET `name`='$name',`game`='$game',`is_global`=$is_global,`item_type`='$item_type',`effect_data`='$effect_data',`price_emlog`=$price_emlog,`price_game`=$price_game,`stock`=$stock,`max_per_user`=$max_per_user,`description`='$description',`icon`='$icon',`sort_order`=$sort_order,`status`=$status WHERE `id`=$edit_id");
    emMsg('商品已更新', $base_url);
}

// ========== GET: 删除商品 ==========
if (Input::getStrVar('shop_action') === 'del_item') {
    $del_id = Input::getIntVar('item_id', 0);
    if ($del_id > 0) $db->query("DELETE FROM `$table_shop` WHERE `id` = $del_id");
    emMsg('商品已删除', $base_url);
}

// ========== 读取数据 ==========
// 通用道具
$global_items = [];
$gResult = $db->query("SELECT * FROM `$table_shop` WHERE `is_global` = 1 ORDER BY `sort_order` ASC, `id` ASC");
while ($r = $db->fetch_array($gResult)) { $r['effect_data'] = stripslashes($r['effect_data']); $global_items[] = $r; }

// 各游戏专属道具
$game_items = [];
foreach (array_keys($GAME_NAMES) as $gk) {
    $game_items[$gk] = [];
    $gr = $db->query("SELECT * FROM `$table_shop` WHERE `is_global` = 0 AND `game` = '$gk' ORDER BY `sort_order` ASC, `id` ASC");
    while ($r = $db->fetch_array($gr)) { $r['effect_data'] = stripslashes($r['effect_data']); $game_items[$gk][] = $r; }
}

// ========== 渲染函数 ==========
function wx_shop_admin_render() {
    global $base_url, $filter, $ITEM_TYPES, $ITEM_TYPE_ICONS, $GAME_NAMES, $global_items, $game_items;
?>
<style>
.shop-admin-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
.shop-admin-toolbar select,.shop-admin-toolbar input{height:32px;font-size:13px;padding:4px 10px}
.wx-card .form-control{width:100%!important;max-width:100%!important;min-width:0;box-sizing:border-box;padding:5px 10px}
.wx-card select.form-control{padding:5px 8px}
.wx-card .form-control::placeholder{color:#bbb}
.edit-form-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:6px 0}
.wx-toast{position:fixed;top:20px;right:20px;z-index:99999;background:#e17055;color:#fff;padding:10px 20px;border-radius:8px;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,.2);animation:wxToastIn .3s ease;max-width:300px}
.wx-toast.error{background:#e74c3c}
@keyframes wxToastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
</style>
<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">🛒 棋牌大厅 - 商城管理</h1>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?= $filter === 'global' ? 'active' : '' ?>" href="<?= $base_url ?>&filter=global">🌐 通用道具 <span class="badge badge-light"><?= count($global_items) ?></span></a></li>
        <?php foreach ($GAME_NAMES as $gk => $gn): ?>
        <li class="nav-item"><a class="nav-link <?= $filter === $gk ? 'active' : '' ?>" href="<?= $base_url ?>&filter=<?= $gk ?>"><?= $gn ?> <span class="badge badge-light"><?= count($game_items[$gk]) ?></span></a></li>
        <?php endforeach; ?>
    </ul>

<?php
// 根据 filter 决定显示哪个道具列表
$display_items = [];
$display_label = '全部';
if ($filter === 'global') {
    $display_items = $global_items;
    $display_label = '通用道具';
} elseif (isset($game_items[$filter])) {
    $display_items = $game_items[$filter];
    $display_label = $GAME_NAMES[$filter] . ' 专属';
}
$total_count = count($display_items);
?>

    <!-- 新增表单 -->
    <div class="wx-card card-dark mb-4">
        <div class="card-header">➕ 新增商品 - <?= $display_label ?></div>
        <div class="card-body">
            <form method="post" action="<?= $base_url ?>&filter=<?= $filter ?>">
                <input type="hidden" name="shop_action" value="add_item">
                <div class="row">
                    <div class="col-md-3"><div class="form-group"><label>名称</label><input class="form-control" name="name" required></div></div>
                    <div class="col-md-3"><div class="form-group"><label>类型</label><select class="form-control" name="item_type" onchange="updateHint(this.value,'add')"><?php foreach ($ITEM_TYPES as $tk => $tl): ?><option value="<?= $tk ?>"><?= $tl ?></option><?php endforeach; ?></select></div></div>
                    <?php if (isset($GAME_TYPES[$filter])): ?>
                        <input type="hidden" name="game" value="<?= $filter ?>">
                        <input type="hidden" name="is_global" value="0">
                        <div class="col-md-3"><div class="form-group"><label>归属</label><input class="form-control" value="<?= $GAME_NAMES[$filter] ?> 专属" readonly disabled></div></div>
                        <div class="col-md-3"><div class="form-group"><label>通用</label><input class="form-control" value="否" readonly disabled></div></div>
                    <?php elseif ($filter === 'global'): ?>
                        <input type="hidden" name="game" value="">
                        <div class="col-md-3"><div class="form-group"><label>归属</label><input class="form-control" value="🌐 通用" readonly disabled></div></div>
                        <div class="col-md-3"><div class="form-group"><label>通用</label><select class="form-control" name="is_global"><option value="1" selected>是</option></select></div></div>
                    <?php else: ?>
                        <div class="col-md-3"><div class="form-group"><label>归属</label><select class="form-control" name="game"><?php foreach ($GAME_NAMES as $gk => $gn): ?><option value="<?= $gk ?>"><?= $gn ?></option><?php endforeach; ?></select></div></div>
                        <div class="col-md-3"><div class="form-group"><label>通用</label><select class="form-control" name="is_global"><option value="0">否</option><option value="1">是</option></select></div></div>
                    <?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-md-6"><div class="form-group"><label>效果数据 <span id="addHint" style="color:#999;font-weight:400;">{}</span></label><input class="form-control" name="effect_data" value='{}'></div></div>
                    <div class="col-md-3"><div class="form-group"><label>排序</label><input class="form-control" name="sort_order" type="number" value="10"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>状态</label><select class="form-control" name="status"><option value="1">上架</option><option value="0">下架</option></select></div></div>
                </div>
                <div class="row">
                    <div class="col-md-3"><div class="form-group"><label>图标</label><input class="form-control" name="icon" value="📦"></div></div>
                    <div class="col-md-9"><div class="form-group"><label>描述</label><input class="form-control" name="description" placeholder="简短描述"></div></div>
                </div>
                <div class="row">
                    <div class="col-md-3"><div class="form-group"><label>站点积分</label><input class="form-control" name="price_emlog" type="number" value="0"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>游戏积分</label><input class="form-control" name="price_game" type="number" value="0"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>库存（-1=无限）</label><input class="form-control" name="stock" type="number" value="-1"></div></div>
                    <div class="col-md-3"><div class="form-group"><label>限购/人</label><input class="form-control" name="max_per_user" type="number" value="0"></div></div>
                </div>
                <div class="form-actions"><button type="submit" class="wx-btn wx-btn-sm">添加商品</button></div>
            </form>
        </div>
    </div>

    <!-- 道具列表 -->
    <div class="wx-card card-dark">
        <div class="card-header">📦 <?= $display_label ?> 列表（共 <?= $total_count ?> 个）</div>
        <div class="card-body" style="padding:0;">
            <div style="overflow-x:auto;">
                <table class="table-admin">
                    <thead><tr>
                        <th>ID</th><th>名称</th><th>类型</th><th>归属</th><th>描述</th><th>效果</th><th>站点积分</th><th>游戏积分</th><th>库存</th><th>限购</th><th>排序</th><th>状态</th><th>操作</th>
                    </tr></thead>
                    <tbody>
<?php foreach ($display_items as $item): ?>
                        <tr>
                            <td><?= $item['id'] ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><span style="font-size:11px;background:#f0f0f5;padding:2px 6px;border-radius:4px;"><?= $ITEM_TYPES[$item['item_type']] ?? $item['item_type'] ?></span></td>
                            <td><?= $item['is_global'] ? '🌐 通用' : ($GAME_NAMES[$item['game']] ?? $item['game']) ?></td>
                            <td style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;"><?= htmlspecialchars($item['description'] ?? '') ?></td>
                            <td style="max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;font-family:monospace;"><?= htmlspecialchars($item['effect_data']) ?></td>
                            <td><?= $item['price_emlog'] ?></td>
                            <td><?= $item['price_game'] ?></td>
                            <td><?= ($item['stock'] ?? -1) == -1 ? '∞' : (int)($item['stock'] ?? 0) ?></td>
                            <td><?= ($item['max_per_user'] ?? 0) > 0 ? (int)$item['max_per_user'] : '-' ?></td>
                            <td><?= $item['sort_order'] ?></td>
                            <td><?= $item['status'] ? '🟢' : '⚫' ?></td>
                            <td style="white-space:nowrap;">
                                <a href="javascript:void(0)" onclick="openEdit(<?= $item['id'] ?>,'<?= addslashes(htmlspecialchars($item['name'])) ?>','<?= $item['item_type'] ?>','<?= $item['game'] ?>',<?= $item['is_global'] ?>,'<?= addslashes(htmlspecialchars($item['effect_data'])) ?>',<?= $item['price_emlog'] ?>,<?= $item['price_game'] ?>,'<?= addslashes(htmlspecialchars($item['description'] ?? '')) ?>','<?= htmlspecialchars($item['icon'] ?? '') ?>',<?= $item['sort_order'] ?>,<?= $item['status'] ?>,<?= (int)($item['stock'] ?? -1) ?>,<?= (int)($item['max_per_user'] ?? 0) ?>)" class="wx-btn wx-btn-sm" style="padding:2px 8px;">编辑</a>
                                <a href="<?= $base_url ?>&filter=<?= $filter ?>&shop_action=del_item&item_id=<?= $item['id'] ?>" class="wx-btn wx-btn-sm wx-btn-danger" style="padding:2px 8px;" onclick="return confirm('确定删除？')">删除</a>
                            </td>
                        </tr>
<?php endforeach; ?>
<?php if (empty($display_items)): ?>
                        <tr><td colspan="12" class="wx-empty">暂无商品</td></tr>
<?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 编辑弹窗 -->
<div class="popup-bg hidden" id="editPopup">
    <div class="popup-box card-dark">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <strong>✏️ 编辑商品</strong>
            <button onclick="closeEdit()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <form method="post" action="<?= $base_url ?>&filter=<?= $filter ?>">
            <input type="hidden" name="shop_action" value="edit_item">
            <input type="hidden" name="item_id" id="eid">
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label>名称</label><input class="form-control" name="name" id="ename" required></div></div>
                <div class="col-md-3"><div class="form-group"><label>类型</label><select class="form-control" name="item_type" id="etype" onchange="updateHint(this.value,'edit')"><?php foreach ($ITEM_TYPES as $tk => $tl): ?><option value="<?= $tk ?>"><?= $tl ?></option><?php endforeach; ?></select></div></div>
                <div class="col-md-3"><div class="form-group"><label>归属</label><select class="form-control" name="game" id="egame"><?php foreach ($GAME_NAMES as $gk => $gn): ?><option value="<?= $gk ?>"><?= $gn ?></option><?php endforeach; ?></select></div></div>
                <div class="col-md-3"><div class="form-group"><label>通用</label><select class="form-control" name="is_global" id="eglobal"><option value="0">否</option><option value="1">是</option></select></div></div>
            </div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>效果数据 <span id="editHint" style="color:#999;font-weight:400;">{}</span></label><input class="form-control" name="effect_data" id="eeffect"></div></div>
                <div class="col-md-3"><div class="form-group"><label>排序</label><input class="form-control" name="sort_order" id="esort" type="number" min="0"></div></div>
                <div class="col-md-3"><div class="form-group"><label>状态</label><select class="form-control" name="status" id="estatus"><option value="1">上架</option><option value="0">下架</option></select></div></div>
            </div>
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label>图标</label><input class="form-control" name="icon" id="eicon"></div></div>
                <div class="col-md-9"><div class="form-group"><label>描述</label><input class="form-control" name="description" id="edesc" placeholder="简短描述"></div></div>
            </div>
            <div class="row">
                <div class="col-md-3"><div class="form-group"><label>站点积分</label><input class="form-control" name="price_emlog" id="eprice_emlog" type="number" min="0"></div></div>
                <div class="col-md-3"><div class="form-group"><label>游戏积分</label><input class="form-control" name="price_game" id="eprice_game" type="number" min="0"></div></div>
                <div class="col-md-3"><div class="form-group"><label>库存（-1=无限）</label><input class="form-control" name="stock" id="estock" type="number"></div></div>
                <div class="col-md-3"><div class="form-group"><label>限购/人</label><input class="form-control" name="max_per_user" id="emax_per_user" type="number"></div></div>
            </div>
            <div class="form-actions">
                <button type="button" class="wx-btn wx-btn-sm" onclick="closeEdit()">取消</button>
                <button type="submit" class="wx-btn wx-btn-sm">保存</button>
            </div>
        </form>
    </div>
</div>

<style>
.popup-bg{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.4);z-index:9999;display:flex;align-items:center;justify-content:center}
.popup-bg.hidden{display:none}
.popup-box{background:#fff;border-radius:12px;padding:22px;box-shadow:0 8px 30px rgba(0,0,0,.2);min-width:500px;max-width:600px;max-height:85vh;overflow-y:auto;color:#333}
.popup-box label{color:#555;font-size:12px;margin-bottom:4px}
.popup-box .form-group{margin-bottom:8px}
.popup-box .form-control,.popup-box input,.popup-box select,.popup-box textarea{width:100%!important;min-width:0;box-sizing:border-box}
.popup-box select{max-width:100%;padding-right:20px}
.popup-box .form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid #eee}
.popup-box .form-actions .wx-btn{min-width:80px;padding:6px 20px;font-size:13px}
.popup-box{position:relative;overflow:visible}
.popup-box select{padding:5px 8px}
.popup-box .form-control{padding:5px 10px}
.card-dark{background:#fff;border:1px solid #e8e8e8;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.card-dark .card-header{background:#f0f4f8;color:#e17055;padding:12px 16px;border-bottom:1px solid #e8e8e8;font-weight:600;border-radius:10px 10px 0 0}
.card-dark .card-body{padding:16px;background:#fff}
.card-dark label{color:#555;font-weight:500}
.card-dark .form-control{background:#fff;border:1px solid #ddd;color:#333}
.card-dark .form-control:focus{border-color:#e17055;box-shadow:0 0 0 2px rgba(225,112,85,.1)}
.table-admin{width:100%;border-collapse:collapse;font-size:13px}
.table-admin th{background:#fafafa;padding:8px 10px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e17055}
.table-admin td{padding:8px 10px;border-bottom:1px solid #f0f0f0;color:#333}
.table-admin tr:hover td{background:#fdf5f3}
.wx-empty{text-align:center;color:#666;padding:30px}
</style>

<script>
var TYPE_HINTS = <?= json_encode(array_map(function($v) { return $v[1]; }, $ITEM_TYPE_ICONS)) ?>;
function updateHint(type, mode) {
    var h = TYPE_HINTS[type] || '{}';
    document.getElementById(mode + 'Hint').textContent = h;
}
function openEdit(id,name,type,game,global,effect,pe,pg,desc,icon,sort,status,stock,mpu) {
    document.getElementById('eid').value = id;
    document.getElementById('ename').value = name;
    document.getElementById('etype').value = type;
    document.getElementById('egame').value = game;
    document.getElementById('eglobal').value = global;
    document.getElementById('eeffect').value = effect;
    document.getElementById('eprice_emlog').value = pe;
    document.getElementById('eprice_game').value = pg;
    document.getElementById('edesc').value = desc;
    document.getElementById('eicon').value = icon;
    document.getElementById('esort').value = sort;
    document.getElementById('estatus').value = status;
    document.getElementById('estock').value = (stock !== undefined ? stock : -1);
    document.getElementById('emax_per_user').value = (mpu !== undefined ? mpu : 0);
    updateHint(type, 'edit');
    document.getElementById('editPopup').classList.remove('hidden');
}
function closeEdit() { document.getElementById('editPopup').classList.add('hidden'); }

// Toast notification
(function(){
  var params = new URLSearchParams(location.search);
  var toast = params.get('toast');
  if(toast){
    var div = document.createElement('div');
    div.className = 'wx-toast';
    div.textContent = decodeURIComponent(toast);
    document.body.appendChild(div);
    setTimeout(function(){ div.remove(); }, 2500);
    // Clean URL
    if(window.history.replaceState){
      params.delete('toast');
      var newUrl = location.pathname + '?' + params.toString();
      window.history.replaceState({}, '', newUrl);
    }
  }
})();
</script>
<?php
}
