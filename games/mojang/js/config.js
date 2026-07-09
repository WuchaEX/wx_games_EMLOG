/**
 * wx_mojang - 麻将常量定义
 * 牌值、花色、游戏模式等
 */

// 牌的花色类型
const TILE_SUITS = {
    WAN: 'wan',     // 万
    TIAO: 'tiao',   // 条
    TONG: 'tong',   // 筒
    FENG: 'feng',   // 风牌 (东南西北)
    JIAN: 'jian'    // 箭牌 (中发白)
};

// 牌面文字
const TILE_NAMES = {
    wan:  ['一', '二', '三', '四', '五', '六', '七', '八', '九'],
    tiao: ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧', '⑨'],
    tong: ['Ⅰ', 'Ⅱ', 'Ⅲ', 'Ⅳ', 'Ⅴ', 'Ⅵ', 'Ⅶ', 'Ⅷ', 'Ⅸ'],
    feng: ['东', '南', '西', '北'],
    jian: ['中', '发', '白']
};

// 牌面显示文字（简化版，用于UI）
const TILE_DISPLAY = {
    'wan_1': '一万', 'wan_2': '二万', 'wan_3': '三万', 'wan_4': '四万', 'wan_5': '五万',
    'wan_6': '六万', 'wan_7': '七万', 'wan_8': '八万', 'wan_9': '九万',
    'tiao_1': '一条', 'tiao_2': '二条', 'tiao_3': '三条', 'tiao_4': '四条', 'tiao_5': '五条',
    'tiao_6': '六条', 'tiao_7': '七条', 'tiao_8': '八条', 'tiao_9': '九条',
    'tong_1': '一筒', 'tong_2': '二筒', 'tong_3': '三筒', 'tong_4': '四筒', 'tong_5': '五筒',
    'tong_6': '六筒', 'tong_7': '七筒', 'tong_8': '八筒', 'tong_9': '九筒',
    'feng_0': '东', 'feng_1': '南', 'feng_2': '西', 'feng_3': '北',
    'jian_0': '中', 'jian_1': '发', 'jian_2': '白'
};

// 牌面缩写（UI显示）
const TILE_SHORT = {
    'wan_1': '1万', 'wan_2': '2万', 'wan_3': '3万', 'wan_4': '4万', 'wan_5': '5万',
    'wan_6': '6万', 'wan_7': '7万', 'wan_8': '8万', 'wan_9': '9万',
    'tiao_1': '1条', 'tiao_2': '2条', 'tiao_3': '3条', 'tiao_4': '4条', 'tiao_5': '5条',
    'tiao_6': '6条', 'tiao_7': '7条', 'tiao_8': '8条', 'tiao_9': '9条',
    'tong_1': '1筒', 'tong_2': '2筒', 'tong_3': '3筒', 'tong_4': '4筒', 'tong_5': '5筒',
    'tong_6': '6筒', 'tong_7': '7筒', 'tong_8': '8筒', 'tong_9': '9筒',
    'feng_0': '东', 'feng_1': '南', 'feng_2': '西', 'feng_3': '北',
    'jian_0': '中', 'jian_1': '发', 'jian_2': '白'
};

// 牌值排序权重（用于比较和排序）
const TILE_VALUES = {
    'wan_1': 1, 'wan_2': 2, 'wan_3': 3, 'wan_4': 4, 'wan_5': 5, 'wan_6': 6, 'wan_7': 7, 'wan_8': 8, 'wan_9': 9,
    'tiao_1': 11, 'tiao_2': 12, 'tiao_3': 13, 'tiao_4': 14, 'tiao_5': 15, 'tiao_6': 16, 'tiao_7': 17, 'tiao_8': 18, 'tiao_9': 19,
    'tong_1': 21, 'tong_2': 22, 'tong_3': 23, 'tong_4': 24, 'tong_5': 25, 'tong_6': 26, 'tong_7': 27, 'tong_8': 28, 'tong_9': 29,
    'feng_0': 31, 'feng_1': 32, 'feng_2': 33, 'feng_3': 34,
    'jian_0': 41, 'jian_1': 42, 'jian_2': 43
};

// 花色对应的CSS颜色
const TILE_COLORS = {
    wan: '#d32f2f',   // 万 -> 红色
    tiao: '#2e7d32',  // 条 -> 绿色
    tong: '#1565c0',  // 筒 -> 蓝色
    feng: '#6a1b9a',  // 风 -> 紫色
    jian: '#e65100'   // 箭 -> 橙色
};

// 牌面简化符号（用于紧凑显示）
const TILE_SYMBOLS = {
    wan: ['一', '二', '三', '四', '五', '六', '七', '八', '九'],
    tiao: ['①', '②', '③', '④', '⑤', '⑥', '⑦', '⑧', '⑨'],
    tong: ['●', '●●', '●●●', '●●●●', '●●●●●', '●●●●●●', '●●●●●●●', '●●●●●●●●', '●●●●●●●●●'],
    feng: ['东', '南', '西', '北'],
    jian: ['中', '发', '白']
};

// 风牌方向（用于判断自风/场风）
const WINDS = ['东', '南', '西', '北'];

// 座次方向
const SEATS = ['东', '南', '西', '北'];

// 游戏阶段（细化）
const PHASE = {
    DEALING: 'dealing',              // 发牌
    DRAWING: 'drawing',              // 摸牌
    WAITING_HAND_ACTION: 'waiting_hand_action',   // 等待手牌操作（暗杠/补杠/补花/自摸）
    WAITING_AFTER_CUT: 'waiting_after_cut',       // 等待其他玩家对出牌的响应（吃碰杠胡）
    WAITING_QIANG_GANG: 'waiting_qiang_gang',     // 等待抢杠和
    ONLY_CUT_AFTER_ACTION: 'only_cut_after_action', // 吃碰后只能出牌
    DISCARDING: 'discarding',        // 出牌
    CLAIMING: 'claiming',            // 吃碰杠胡等待（兼容旧代码）
    SETTLEMENT: 'settlement',        // 结算
    GAME_OVER: 'game_over'           // 游戏结束
};

// 操作类型
const ACTION = {
    CHI: 'chi',       // 吃
    PENG: 'peng',     // 碰
    GANG: 'gang',     // 杠
    HU: 'hu',         // 胡
    PASS: 'pass',     // 过
    DRAW: 'draw',     // 摸牌
    DISCARD: 'discard' // 出牌
};

// 听牌类型
const TING_TYPE = {
    SINGLE: 'single',       // 单钓
    TWO_SIDED: 'two_sided', // 两面听
    MIDDLE: 'middle',       // 嵌张
    EDGE: 'edge'            // 边张
};

// 游戏状态
const GAME_STATE = {
    IDLE: 'idle',
    PLAYING: 'playing',
    PAUSED: 'paused'
};

// 最小显示番数（国标8番起胡 - JS侧用0表示不限制，由PHP配置控制）
const DEFAULT_MIN_FAN = 0; // 前端不限制，由后端配置决定

// AI思考延迟（毫秒）
const AI_THINK_DELAY = {
    QUICK: 600,    // 快速出牌
    NORMAL: 1200,  // 正常出牌
    SLOW: 2000     // 思考复杂情况
};

// 番种定义（QQ国标麻将完整番种）
const FAN_TYPES = {
    // ==================== 88番 ====================
    'tian_hu':        { name: '天胡',     fan: 88, desc: '庄家起手14张胡牌' },
    'di_hu':          { name: '地胡',     fan: 88, desc: '闲家起手听牌，第一巡胡' },
    'da_san_yuan':    { name: '大三元',   fan: 88, desc: '中发白三副刻子' },
    'da_si_xi':       { name: '大四喜',   fan: 88, desc: '东南西北四副风刻' },
    'lv_yi_se':       { name: '绿一色',   fan: 88, desc: '由23468条及发组成' },
    'jiu_lian_bao_deng': { name: '九莲宝灯', fan: 88, desc: '1112345678999同花色见同花色任一张成和' },
    'si_gang':        { name: '四杠',     fan: 88, desc: '四个杠' },
    'lian_qi_dui':    { name: '连七对',   fan: 88, desc: '同花色七对且序数相连' },
    'shi_san_yao':    { name: '十三幺',   fan: 88, desc: '19万19条19筒东南西北中发白各一再加任一张' },

    // ==================== 64番 ====================
    'qing_yao_jiu':   { name: '清幺九',   fan: 64, desc: '由序数牌一九刻子组成' },
    'xiao_si_xi':     { name: '小四喜',   fan: 64, desc: '风牌三副刻子加将牌' },
    'xiao_san_yuan':  { name: '小三元',   fan: 64, desc: '箭牌两副刻子加将牌' },
    'zi_yi_se':       { name: '字一色',   fan: 64, desc: '全部由字牌组成' },
    'si_an_ke':       { name: '四暗刻',   fan: 64, desc: '四个暗刻或暗杠' },
    'yi_se_shuang_long': { name: '一色双龙会', fan: 64, desc: '同花色两个老少副，5为将' },

    // ==================== 48番 ====================
    'yi_se_si_tong_shun': { name: '一色四同顺', fan: 48, desc: '同花色四副相同顺子' },
    'yi_se_si_jie_gao':   { name: '一色四节高', fan: 48, desc: '同花色四副递增刻子' },

    // ==================== 32番 ====================
    'yi_se_si_bu_gao':{ name: '一色四步高', fan: 32, desc: '同花色四副递增顺子' },
    'san_gang':       { name: '三杠',     fan: 32, desc: '三个杠' },
    'hun_yao_jiu':    { name: '混幺九',   fan: 32, desc: '由一九牌和字牌刻子组成' },

    // ==================== 24番 ====================
    'qi_dui':         { name: '七对',     fan: 24, desc: '七个对子' },
    'qi_xing_bu_kao': { name: '七星不靠', fan: 24, desc: '7张字牌+3花色147/258/369各一张' },
    'quan_shuang_ke': { name: '全双刻',   fan: 24, desc: '由2468数牌刻子组成' },
    'qing_yi_se':     { name: '清一色',   fan: 24, desc: '由一种花色序数牌组成' },
    'yi_se_san_tong_shun': { name: '一色三同顺', fan: 24, desc: '同花色三副相同顺子' },
    'yi_se_san_jie':  { name: '一色三节高', fan: 24, desc: '同花色三副递增刻子' },
    'quan_da':        { name: '全大',     fan: 24, desc: '由789数牌组成' },
    'quan_zhong':     { name: '全中',     fan: 24, desc: '由456数牌组成' },
    'quan_xiao':      { name: '全小',     fan: 24, desc: '由123数牌组成' },

    // ==================== 16番 ====================
    'qing_long':      { name: '清龙',     fan: 16, desc: '同花色123456789三副顺子' },
    'san_se_shuang_long': { name: '三色双龙会', fan: 16, desc: '两种花色老少副+另一种花色5将' },
    'yi_se_san_bu_gao': { name: '一色三步高', fan: 16, desc: '同花色三副递增一位的顺子' },
    'quan_dai_wu':    { name: '全带五',   fan: 16, desc: '每副牌及将牌都含5' },
    'san_tong_ke':    { name: '三同刻',   fan: 16, desc: '三个相同数字的刻子' },
    'san_an_ke':      { name: '三暗刻',   fan: 16, desc: '三个暗刻' },

    // ==================== 12番 ====================
    'quan_bu_kao':    { name: '全不靠',   fan: 12, desc: '3花色147/258/369+字牌各一张' },
    'zu_he_long':     { name: '组合龙',   fan: 12, desc: '3花色的147/258/369各一副顺子' },
    'da_yu_wu':       { name: '大于五',   fan: 12, desc: '由6789数牌组成' },
    'xiao_yu_wu':     { name: '小于五',   fan: 12, desc: '由1234数牌组成' },
    'san_feng_ke':    { name: '三风刻',   fan: 12, desc: '三个风刻' },

    // ==================== 8番 ====================
    'hua_long':       { name: '花龙',     fan: 8, desc: '三种花色123/456/789各一副' },
    'tui_bu_dao':     { name: '推不倒',   fan: 8, desc: '由可翻转的牌组成（245689条258筒白板）' },
    'san_se_san_tong_shun': { name: '三色三同顺', fan: 8, desc: '三种花色相同数字的顺子' },
    'san_se_san_jie_gao': { name: '三色三节高', fan: 8, desc: '三种花色递增一位的刻子' },
    'wu_fan_he':      { name: '无番和',   fan: 8, desc: '和牌没有任何番种' },
    'gang_shang_hua': { name: '杠上花',   fan: 8, desc: '杠后补牌胡牌' },
    'qiang_gang_hu':  { name: '抢杠胡',   fan: 8, desc: '抢别人的明杠胡牌' },
    'hai_di_lao_yue': { name: '海底捞月', fan: 8, desc: '摸到最后一张牌胡牌' },
    'hai_di_hu':      { name: '海底胡',   fan: 8, desc: '和最后一张打出的牌' },

    // ==================== 6番 ====================
    'peng_peng_hu':   { name: '碰碰胡',   fan: 6, desc: '由4副刻子或杠+将牌组成' },
    'hun_yi_se':      { name: '混一色',   fan: 6, desc: '由一种花色序数牌+字牌组成' },
    'san_se_san_bu_gao': { name: '三色三步高', fan: 6, desc: '三种花色各一副递增一位顺子' },
    'wu_men_qi':      { name: '五门齐',   fan: 6, desc: '和牌中有三种序数牌+风牌+箭牌' },
    'quan_qiu_ren':   { name: '全求人',   fan: 6, desc: '全靠吃碰明杠，最后单钓别人出牌' },
    'shuang_an_gang': { name: '双暗杠',   fan: 6, desc: '两个暗杠' },
    'shuang_jian_ke': { name: '双箭刻',   fan: 6, desc: '两个箭刻' },

    // ==================== 4番 ====================
    'quan_dai_yao':   { name: '全带幺',   fan: 4, desc: '每副牌及将牌都含1或9' },
    'bu_qiu_ren':     { name: '不求人',   fan: 4, desc: '自摸且无吃碰明杠' },
    'shuang_ming_gang': { name: '双明杠', fan: 4, desc: '两个明杠' },
    'he_jue_zhang':   { name: '和绝张',   fan: 4, desc: '和牌时池中已出该牌3张' },

    // ==================== 2番 ====================
    'jian_ke':        { name: '箭刻',     fan: 2, desc: '中发白刻子或杠' },
    'quan_feng_ke':   { name: '圈风刻',   fan: 2, desc: '与圈风相同的风刻' },
    'men_feng_ke':    { name: '门风刻',   fan: 2, desc: '与本门风相同的风刻' },
    'men_qian_qing':  { name: '门前清',   fan: 2, desc: '无吃碰明杠' },
    'ping_he':        { name: '平和',     fan: 2, desc: '4顺子+序数牌将，无字牌' },
    'si_gui_yi':      { name: '四归一',   fan: 2, desc: '同花色4张相同非杠' },
    'shuang_tong_ke': { name: '双同刻',   fan: 2, desc: '两个相同数字的刻子' },
    'shuang_an_ke':   { name: '双暗刻',   fan: 2, desc: '两个暗刻' },
    'an_gang':        { name: '暗杠',     fan: 2, desc: '暗杠' },
    'duan_yao':       { name: '断幺',     fan: 2, desc: '不含幺九牌和字牌' },

    // ==================== 1番 ====================
    'yi_ban_gao':     { name: '一般高',   fan: 1, desc: '同花色两副相同顺子' },
    'xi_xiang_feng':  { name: '喜相逢',   fan: 1, desc: '两种花色序数相同的顺子' },
    'lian_liu':       { name: '连六',     fan: 1, desc: '同花色6张相邻两副顺子' },
    'lao_shao_fu':    { name: '老少副',   fan: 1, desc: '同花色123和789两副顺子' },
    'yao_jiu_ke':     { name: '幺九刻',   fan: 1, desc: '1/9或字牌的刻子，每个1番' },
    'ming_gang':      { name: '明杠',     fan: 1, desc: '明杠' },
    'que_yi_men':     { name: '缺一门',   fan: 1, desc: '和牌中缺少一种花色' },
    'wu_zi':          { name: '无字',     fan: 1, desc: '和牌中没有字牌' },
    'bian_zhang':     { name: '边张',     fan: 1, desc: '12胡3或89胡7' },
    'kan_zhang':      { name: '坎张',     fan: 1, desc: '胡两张之间的牌' },
    'dan_diao':       { name: '单钓将',   fan: 1, desc: '钓单张牌做将' },
    'zi_mo':          { name: '自摸',     fan: 1, desc: '自摸成和' },
    'ming_an_gang':   { name: '明暗杠',   fan: 5, desc: '一个明杠和一个暗杠' },
};

// 番种互斥表（高番排斥低番）
// 当匹配到高番时，移除被排斥的低番，避免重复计分
const REPEL_MODEL = {
    'da_si_xi':    ['peng_peng_hu', 'quan_feng_ke', 'men_feng_ke', 'yao_jiu_ke', 'san_feng_ke'],
    'da_san_yuan': ['shuang_jian_ke', 'jian_ke'],
    'xiao_si_xi':  ['peng_peng_hu', 'quan_feng_ke', 'men_feng_ke', 'yao_jiu_ke', 'san_feng_ke'],
    'xiao_san_yuan': ['shuang_jian_ke', 'jian_ke'],
    'qing_yao_jiu': ['quan_dai_yao'],
    'hun_yao_jiu':  ['quan_dai_yao'],
    'jiu_lian_bao_deng': ['qing_yi_se', 'men_qian_qing'],
    'lian_qi_dui':  ['qi_dui', 'qing_yi_se', 'men_qian_qing'],
    'shi_san_yao':  ['men_qian_qing'],
    'si_an_ke':     ['peng_peng_hu', 'san_an_ke', 'shuang_an_ke'],
    'qi_xing_bu_kao': ['quan_bu_kao'],
    'yi_se_shuang_long': ['qing_yi_se', 'lao_shao_fu', 'yi_ban_gao'],
    'yi_se_si_tong_shun': ['yi_ban_gao'],
    'yi_se_si_jie_gao': ['yi_se_san_jie'],
    'yi_se_san_tong_shun': ['yi_ban_gao'],
    'yi_se_san_jie': [],
    'san_gang':     ['shuang_ming_gang', 'shuang_an_gang'],
    'si_gang':      ['san_gang', 'shuang_ming_gang', 'shuang_an_gang'],
    'quan_shuang_ke': ['peng_peng_hu', 'shuang_tong_ke'],
    'san_tong_ke':  ['shuang_tong_ke'],
    'san_an_ke':    ['shuang_an_ke'],
    'gang_shang_hua': [],
    'bu_qiu_ren':   ['men_qian_qing', 'zi_mo'],
    'he_jue_zhang': [],
    'quan_dai_wu':  ['duan_yao'],
    'quan_dai_yao': ['duan_yao'],
    'qing_yao_jiu': ['quan_dai_yao'],
    'hun_yao_jiu':  ['quan_dai_yao'],
    'si_an_ke':     ['peng_peng_hu', 'san_an_ke', 'shuang_an_ke'],
    'san_an_ke':    ['shuang_an_ke'],
    'quan_shuang_ke': ['shuang_tong_ke'],
    'san_tong_ke':  ['shuang_tong_ke'],
    'yi_se_si_tong_shun': ['yi_ban_gao'],
    'yi_se_san_tong_shun': ['yi_ban_gao'],
    'bu_qiu_ren':   ['men_qian_qing', 'zi_mo'],
    'gang_shang_hua': [],
    'qiang_gang_hu': [],
    'hai_di_lao_yue': [],
    'hai_di_hu': [],
    'ming_an_gang': [],
};

// ============================================================
// 以下为从 open_mahjong_unity 提取的番型计算数据
// 编码：万=11~19 筒=21~29 条=31~39 字牌=41~47
// ============================================================

// 牌编码转换：游戏 tile 对象 → Python 数值编码
function tileToPyCode(tile) {
    const suitBase = { wan: 10, tong: 20, tiao: 30 };
    if (suitBase[tile.suit]) return suitBase[tile.suit] + tile.num;
    const honorBase = { feng: 41, jian: 45 };
    return honorBase[tile.suit] + tile.num;
}
function tileIdToPyCode(id) {
    const parts = id.split('_');
    return tileToPyCode({ suit: parts[0], num: parseInt(parts[1]) });
}

// 集合定义
const DUANYAO_SET = new Set([12,13,14,15,16,17,18,22,23,24,25,26,27,28,32,33,34,35,36,37,38]);
const QUANZHONG_SET = new Set([14,15,16,24,25,26,34,35,36]);
const DAYUWU_SET = new Set([16,17,18,19,26,27,28,29,36,37,38,39]);
const QUANDA_SET = new Set([17,18,19,27,28,29,37,38,39]);
const XIAOYUWU_SET = new Set([11,12,13,14,21,22,23,24,31,32,33,34]);
const QUANXIAO_SET = new Set([11,12,13,21,22,23,31,32,33]);
const ZIPAI_SET = new Set([41,42,43,44,45,46,47]);
const YAOJIU_SET = new Set([11,19,21,29,31,39,41,42,43,44,45,46,47]);
const QINGYAOJIU_SET = new Set([11,19,21,29,31,39]);
const HUNYAOJIU_SET = new Set([11,19,21,29,31,39,41,42,43,44,45,46,47]);
const LVYISE_SET = new Set([32,33,34,36,38,46]);  // 2条3条4条6条8条+发财
const QUANDAIWU_SET = new Set([11,12,13,14,15,21,22,23,24,25,31,32,33,34,35,45,46,47]);
const QUANDAIYAO_SET = new Set([11,19,21,29,31,39,41,42,43,44,45,46,47]);
const TUIBUDAO_SET = new Set([12,14,15,16,18,19,22,25,28,32,35,38,42,44,45]);
