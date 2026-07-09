// EMLOG配置 - 由PHP动态注入覆盖
// 使用 var 声明（不用 const），确保 window.EMLOG_CONFIG 可被 PHP 内联脚本覆盖
var EMLOG_CONFIG = window.EMLOG_CONFIG || {
    baseUrl: '',
    loginUrl: '',
    leaderboardApi: ''
};

// AI玩家配置 - 由PHP动态注入覆盖（使用 var 声明）
var MEMBERS = window.MEMBERS || [
    { id: 'ai1', name: 'AI玩家1', avatar: 'assets/ai1.jpg' },
    { id: 'ai2', name: 'AI玩家2', avatar: 'assets/ai2.jpg' }
];

// 扑克牌常量
const CARD_VALUES = ['3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A', '2'];
const SUITS = ['♠', '♥', '♣', '♦'];
const SUIT_COLORS = { '♠': 'black', '♥': 'red', '♣': 'black', '♦': 'red' };

// 游戏模式
const GAME_MODES = {
    classic: { name: '经典模式', description: '标准斗地主规则' }
};

// 当前游戏模式
let currentGameMode = 'classic';
