/**
 * wx_mojang - 核心游戏引擎
 * 麻将算法引擎：牌生成、判胡、番型计算、AI策略、游戏状态管理
 */

// ============================================================
// 牌引擎
// ============================================================
const TileEngine = {
    /**
     * 创建一副完整的136张麻将牌
     */
    createDeck() {
        const deck = [];
        // 万条筒各9种×4
        ['wan', 'tiao', 'tong'].forEach(suit => {
            for (let num = 1; num <= 9; num++) {
                for (let copy = 0; copy < 4; copy++) {
                    deck.push({ suit, num, id: `${suit}_${num}` });
                }
            }
        });
        // 风牌4种×4
        for (let i = 0; i < 4; i++) {
            for (let copy = 0; copy < 4; copy++) {
                deck.push({ suit: 'feng', num: i, id: `feng_${i}` });
            }
        }
        // 箭牌3种×4
        for (let i = 0; i < 3; i++) {
            for (let copy = 0; copy < 4; copy++) {
                deck.push({ suit: 'jian', num: i, id: `jian_${i}` });
            }
        }
        return deck;
    },

    /**
     * Fisher-Yates 洗牌
     */
    shuffle(deck) {
        const d = [...deck];
        for (let i = d.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [d[i], d[j]] = [d[j], d[i]];
        }
        return d;
    },

    /**
     * 发牌
     * @param {Array} deck - 洗好的牌墙
     * @returns {{hands: Array, wall: Array, dealerIndex: number}}
     */
    deal(deck) {
        const hands = [[], [], [], []];
        // 每人13张
        for (let i = 0; i < 4; i++) {
            hands[i] = deck.slice(i * 13, (i + 1) * 13);
        }
        // 庄家多拿一张（第14张）
        const remaining = deck.slice(52);
        return {
            hands,
            wall: remaining,
            dealerIndex: 0 // 默认东风位为庄
        };
    },

    /**
     * 牌排序（按花色+数字）
     */
    sortTiles(tiles) {
        return [...tiles].sort((a, b) => {
            const va = TILE_VALUES[a.id] || 0;
            const vb = TILE_VALUES[b.id] || 0;
            return va - vb;
        });
    },

    /**
     * 获取单张牌的权重值
     */
    getValue(tile) {
        return TILE_VALUES[tile.id] || 0;
    },

    /**
     * 牌转为ID字符串
     */
    tilesToIdString(tiles) {
        return tiles.map(t => t.id).join(',');
    },

    /**
     * ID字符串转牌
     */
    idStringToTiles(str) {
        if (!str) return [];
        return str.split(',').filter(id => id).map(id => {
            const parts = id.split('_');
            return { suit: parts[0], num: parseInt(parts[1]), id };
        });
    },

    /**
     * 统计手牌中每张牌的数量
     * @returns {Object} { 'wan_1': 2, 'wan_2': 0, ... }
     */
    countTiles(hand) {
        const counts = {};
        hand.forEach(t => {
            counts[t.id] = (counts[t.id] || 0) + 1;
        });
        return counts;
    },

    /**
     * 从手牌中移除指定牌
     */
    removeTiles(hand, tilesToRemove) {
        const h = [...hand];
        for (const tile of tilesToRemove) {
            const idx = h.findIndex(t => t.id === tile.id);
            if (idx !== -1) h.splice(idx, 1);
        }
        return h;
    },

    /**
     * 从牌墙摸牌
     */
    drawTile(wall) {
        if (wall.length === 0) return null;
        return wall.shift();
    },

    /**
     * 判断是否为字牌（风牌或箭牌）
     */
    isHonor(tile) {
        return tile.suit === 'feng' || tile.suit === 'jian';
    },

    /**
     * 判断是否为幺九牌
     */
    isYaoJiu(tile) {
        if (this.isHonor(tile)) return true;
        return tile.num === 1 || tile.num === 9;
    },

    /**
     * 判断是否为中张牌
     */
    isMiddle(tile) {
        if (this.isHonor(tile)) return false;
        return tile.num >= 2 && tile.num <= 8;
    }
};

// ============================================================
// 胡牌检测引擎
// ============================================================
const HuChecker = {
    /**
     * 检查是否胡牌（标准4面子+1雀头，或特殊牌型）
     * @param {Array} tiles - 手牌
     * @param {Array} externalTiles - 外部可用牌（如打出牌、弃牌堆）
     * @returns {boolean}
     */
    isHu(tiles, externalTiles = []) {
        if (tiles.length % 3 !== 2 || tiles.length < 2) return false;

        // Block优化：如果隔断块数>6，不可能和牌，提前返回
        if (this._countBlocks(tiles) > 6) return false;

        // 特殊牌型：七对
        if (tiles.length === 14 && this._isQiDui(tiles)) return true;

        // 合并手牌 + 外部可用牌（弃牌堆里"已出"的牌，玩家也能识别）
        const allTiles = externalTiles && externalTiles.length > 0
            ? [...tiles, ...externalTiles] : tiles;
        return this._canFormMelds(this.countTiles(allTiles), 1);
    },

    /**
     * Block优化：计算手牌的隔断块数
     * 块数 = 手牌中不相邻的牌组数。块数>6时无法和牌。
     */
    _countBlocks(tiles) {
        if (tiles.length === 0) return 0;
        const sorted = TileEngine.sortTiles(tiles);
        let blocks = 1;
        for (let i = 1; i < sorted.length; i++) {
            const a = TILE_VALUES[sorted[i-1].id] || 0;
            const b = TILE_VALUES[sorted[i].id] || 0;
            if (b - a > 2) blocks++;
        }
        return blocks;
    },

    /**
     * 检查七对（含连七对）
     */
    _isQiDui(tiles) {
        if (tiles.length !== 14) return false;
        const counts = {};
        tiles.forEach(t => { counts[t.id] = (counts[t.id] || 0) + 1; });
        for (const id in counts) {
            if (counts[id] % 2 !== 0) return false;
        }
        return true;
    },

    /**
     * 递归检测能否组成面子组合
     */
    _canFormMelds(counts, pairsNeeded) {
        // 找到第一张数量>0的牌
        let firstId = null;
        for (const id in counts) {
            if (counts[id] > 0) { firstId = id; break; }
        }
        if (!firstId) return pairsNeeded === 0;

        const first = this._parseId(firstId);

        // 如果当前牌有2张或以上且还需要雀头，尝试做雀头
        if (counts[firstId] >= 2 && pairsNeeded > 0) {
            const newCounts = { ...counts };
            newCounts[firstId] -= 2;
            if (this._canFormMelds(newCounts, pairsNeeded - 1)) return true;
        }

        // 尝试做刻子
        if (counts[firstId] >= 3) {
            const newCounts = { ...counts };
            newCounts[firstId] -= 3;
            if (this._canFormMelds(newCounts, pairsNeeded)) return true;
        }

        // 尝试做顺子（只有万条筒可以做顺子）
        if (first.suit !== 'feng' && first.suit !== 'jian' && first.num <= 7) {
            const id2 = `${first.suit}_${first.num + 1}`;
            const id3 = `${first.suit}_${first.num + 2}`;
            if ((counts[id2] || 0) > 0 && (counts[id3] || 0) > 0) {
                const newCounts = { ...counts };
                newCounts[firstId] -= 1;
                newCounts[id2] -= 1;
                newCounts[id3] -= 1;
                if (this._canFormMelds(newCounts, pairsNeeded)) return true;
            }
        }

        return false;
    },

    /**
     * 解析牌ID
     */
    _parseId(id) {
        const parts = id.split('_');
        return { suit: parts[0], num: parseInt(parts[1]) };
    },

    /**
     * 统计手牌中各种牌的数量
     */
    countTiles(tiles) {
        const counts = {};
        tiles.forEach(t => {
            counts[t.id] = (counts[t.id] || 0) + 1;
        });
        return counts;
    },

    /**
     * 检测是否听牌（遍历所有未出现的牌，检查是否能胡）
     * @param {Array} hand - 13张手牌
     * @param {Array} discards - 已出的牌
     * @param {Array} melds - 已吃碰杠的牌组
     * @returns {Array} 听的牌列表 [{tile, waitType}]
     */
    checkTing(hand, discards = [], melds = []) {
        const waitingTiles = [];
        const meldTiles = melds.flat();
        const allDiscarded = [...discards, ...meldTiles];

        // 收集所有可能胡的牌
        const allTiles = this._getAllPossibleTiles(allDiscarded, hand);

        for (const tile of allTiles) {
            const testHand = [...hand, { ...tile }];
            if (this.isHu(testHand, allDiscarded)) {
                const waitType = this._determineWaitType(hand, tile);
                waitingTiles.push({ tile: tile.id, type: waitType });
            }
        }

        return waitingTiles;
    },

    /**
     * 获取所有可能的牌（排除已出现的）
     */
    _getAllPossibleTiles(allDiscarded, hand) {
        const existing = {};
        allDiscarded.forEach(t => { existing[t.id] = (existing[t.id] || 0) + 1; });
        hand.forEach(t => { existing[t.id] = (existing[t.id] || 0) + 1; });

        const all = [];
        ['wan', 'tiao', 'tong'].forEach(suit => {
            for (let num = 1; num <= 9; num++) {
                const id = `${suit}_${num}`;
                const count = existing[id] || 0;
                if (count < 4) {
                    for (let i = count; i < 4; i++) {
                        all.push({ suit, num, id });
                    }
                }
            }
        });
        ['feng'].forEach(suit => {
            for (let num = 0; num < 4; num++) {
                const id = `${suit}_${num}`;
                if ((existing[id] || 0) < 4) all.push({ suit, num, id });
            }
        });
        ['jian'].forEach(suit => {
            for (let num = 0; num < 3; num++) {
                const id = `${suit}_${num}`;
                if ((existing[id] || 0) < 4) all.push({ suit, num, id });
            }
        });
        return all;
    },

    /**
     * 判断听牌类型（单钓/两面/嵌张/边张）
     */
    _determineWaitType(hand, winTile) {
        // 如果手牌中已有一个对子且14张时胡的牌正好与那个对子搭配
        const counts = this.countTiles(hand);
        const winCount = counts[winTile.id] || 0;

        // 单钓：胡的牌在手牌中出现2次（凑成一刻）
        if (winCount >= 2) return 'SINGLE';

        // 检查是否为边张/嵌张
        if (winTile.suit !== 'feng' && winTile.suit !== 'jian') {
            if (winTile.num === 3 || winTile.num === 7) return 'EDGE';
            // 坎张：需手牌中有同花色 num-1 和 num+1，形成嵌搭（如1条+3条胡2条）
            if (winTile.num >= 2 && winTile.num <= 8) {
                const suit = winTile.suit;
                const hasPrev = (counts[`${suit}_${winTile.num-1}`] || 0) > 0;
                const hasNext = (counts[`${suit}_${winTile.num+1}`] || 0) > 0;
                if (hasPrev && hasNext) return 'MIDDLE';
            }
        }

        return 'TWO_SIDED';
    },

    /**
     * 检查是否可以碰
     */
    canPeng(hand, discardTile) {
        const count = hand.filter(t => t.id === discardTile.id).length;
        return count >= 2;
    },

    /**
     * 检查是否可以吃
     */
    canChi(hand, discardTile) {
        if (discardTile.suit === 'feng' || discardTile.suit === 'jian') return [];

        const suit = discardTile.suit;
        const num = discardTile.num;
        const results = [];

        // 按手牌中对应花色的牌
        const suitTiles = hand.filter(t => t.suit === suit);
        const nums = [...new Set(suitTiles.map(t => t.num))];

        // 检查三种吃法
        // 1. num-2, num-1, num
        if (nums.includes(num - 2) && nums.includes(num - 1)) {
            results.push({ tiles: [`${suit}_${num-2}`, `${suit}_${num-1}`, discardTile.id], middle: discardTile.id });
        }
        // 2. num-1, num, num+1
        if (nums.includes(num - 1) && nums.includes(num + 1)) {
            results.push({ tiles: [`${suit}_${num-1}`, discardTile.id, `${suit}_${num+1}`], middle: discardTile.id });
        }
        // 3. num, num+1, num+2
        if (nums.includes(num + 1) && nums.includes(num + 2)) {
            results.push({ tiles: [discardTile.id, `${suit}_${num+1}`, `${suit}_${num+2}`], middle: discardTile.id });
        }

        return results;
    },

    /**
     * 检查是否可以杠（明杠）
     */
    canGang(hand, discardTile) {
        const count = hand.filter(t => t.id === discardTile.id).length;
        return count >= 3;
    },

    /**
     * 检查是否可以暗杠
     */
    canAnGang(hand) {
        const counts = this.countTiles(hand);
        const results = [];
        for (const id in counts) {
            if (counts[id] >= 4) {
                results.push(id);
            }
        }
        return results;
    },

    /**
     * 检查是否可以补杠（已碰过该牌）
     */
    canBuGang(hand, melds) {
        const results = [];
        for (const meld of melds) {
            if (meld.type === 'peng') {
                const id = meld.tiles[0];
                if (hand.some(t => t.id === id)) {
                    results.push({ meldIndex: melds.indexOf(meld), tileId: id });
                }
            }
        }
        return results;
    },

    /**
     * 检查抢杠胡：当有人补杠时，检查是否可以抢杠胡
     * @param {Array} hand - 手牌
     * @param {string} gangTileId - 被补杠的那张牌的ID
     * @returns {boolean}
     */
    canQiangGang(hand, gangTileId) {
        const testHand = [...hand, { id: gangTileId, suit: gangTileId.split('_')[0], num: parseInt(gangTileId.split('_')[1]) }];
        return this.isHu(testHand);
    }
};

// ============================================================
// 番型计算引擎
// ============================================================
const FanCalculator = {
    /**
     * 计算一手胡牌的总番数
     * @param {Object} params - 计算参数
     * @returns {{total: number, fans: Array}}
     */
    calculate(params) {
        const { hand, melds, winTile, isSelfDraw, isDealer, wall, discards } = params;
        const fans = [];
        const allMelds = [...(melds || [])];

        // 计算基础番型
        this._checkPatterns(hand, allMelds, winTile, isSelfDraw, fans, params);

        // 如果没有任何番种（且不是通过互斥归零的），自动添加无番和
        if (fans.length === 0) {
            // 检查是否真的没有番（排除自摸等已经加的）
            fans.push({ key: 'wu_fan_he', ...FAN_TYPES.wu_fan_he });
        }

        // 合并同番种、排序
        fans.sort((a, b) => b.fan - a.fan);

        const total = fans.reduce((sum, f) => sum + f.fan, 0);
        return { total, fans };
    },

    /**
     * 检测各种番型（完整版，含互斥逻辑）
     */
    _checkPatterns(hand, melds, winTile, isSelfDraw, fans, params) {
        // === 特殊牌型检测（优先返回） ===

        // 十三幺
        if (this._checkShiSanYao(hand)) {
            fans.push({ key: 'shi_san_yao', ...FAN_TYPES.shi_san_yao });
            this._applyRepeal(fans);
            return;
        }

        // 九莲宝灯
        if (this._checkJiuLianBaoDeng(hand)) {
            fans.push({ key: 'jiu_lian_bao_deng', ...FAN_TYPES.jiu_lian_bao_deng });
            this._applyRepeal(fans);
            return;
        }

        // 连七对
        if (this._checkLianQiDui(hand)) {
            fans.push({ key: 'lian_qi_dui', ...FAN_TYPES.lian_qi_dui });
            this._applyRepeal(fans);
            return;
        }

        // 七对检测
        if (this._checkQiDui(hand)) {
            fans.push({ key: 'qi_dui', ...FAN_TYPES.qi_dui });
            this._applyRepeal(fans);
            return;
        }

        // 字一色
        if (this._checkZiYiSe(hand, melds)) {
            fans.push({ key: 'zi_yi_se', ...FAN_TYPES.zi_yi_se });
        }

        // 绿一色
        if (this._checkLvYiSe(hand, melds)) {
            fans.push({ key: 'lv_yi_se', ...FAN_TYPES.lv_yi_se });
            this._applyRepeal(fans);
            return;
        }

        // 七星不靠
        if (this._checkQiXingBuKao(hand)) {
            fans.push({ key: 'qi_xing_bu_kao', ...FAN_TYPES.qi_xing_bu_kao });
            this._applyRepeal(fans);
            return;
        }

        // 全不靠
        if (this._checkQuanBuKao(hand)) {
            fans.push({ key: 'quan_bu_kao', ...FAN_TYPES.quan_bu_kao });
            return;
        }

        // === 一般型（分解面子+雀头） ===
        const groups = this._analyzeGroups(hand, melds);

        // 单钓将/边张/坎张检测（必须在分解前，因为需要原始手牌信息）
        if (winTile && hand.length === 1) {
            fans.push({ key: 'dan_diao', ...FAN_TYPES.dan_diao });
        } else if (winTile) {
            const waitType = HuChecker._determineWaitType(hand, winTile);
            if (waitType === 'EDGE') fans.push({ key: 'bian_zhang', ...FAN_TYPES.bian_zhang });
            else if (waitType === 'MIDDLE') fans.push({ key: 'kan_zhang', ...FAN_TYPES.kan_zhang });
            else if (waitType === 'SINGLE') fans.push({ key: 'dan_diao', ...FAN_TYPES.dan_diao });
        }

        // === 和牌方式类 ===
        if (isSelfDraw) {
            fans.push({ key: 'zi_mo', ...FAN_TYPES.zi_mo });
        }

        // 门前清
        if (this._checkMenQianQing(melds)) {
            fans.push({ key: 'men_qian_qing', ...FAN_TYPES.men_qian_qing });
        }

        // 全求人
        if (this._checkQuanQiuRen(hand, melds)) {
            fans.push({ key: 'quan_qiu_ren', ...FAN_TYPES.quan_qiu_ren });
        }

        // 不求人（自摸+门清）
        if (isSelfDraw && this._checkMenQianQing(melds)) {
            fans.push({ key: 'bu_qiu_ren', ...FAN_TYPES.bu_qiu_ren });
        }

        // 杠上花
        if (params.isGangShangHua) {
            fans.push({ key: 'gang_shang_hua', ...FAN_TYPES.gang_shang_hua });
        }

        // 抢杠胡
        if (params.isQiangGangHu) {
            fans.push({ key: 'qiang_gang_hu', ...FAN_TYPES.qiang_gang_hu });
        }

        // 海底捞月 / 海底胡
        if (params.isLastTile) {
            if (isSelfDraw) fans.push({ key: 'hai_di_lao_yue', ...FAN_TYPES.hai_di_lao_yue });
            else fans.push({ key: 'hai_di_hu', ...FAN_TYPES.hai_di_hu });
        }

        // 和绝张
        if (this._checkHeJueZhang(winTile, params.discards)) {
            fans.push({ key: 'he_jue_zhang', ...FAN_TYPES.he_jue_zhang });
        }

        // === 花色类（合并手牌+副露） ===
        const suitsUsed = new Set();
        let hasZi = false;
        const allTilesForSuit = [...hand, ...melds.flatMap(m => m.tiles || []).map(tId => {
            if (typeof tId === 'object') return tId;
            const parts = tId.split('_');
            return { suit: parts[0], num: parseInt(parts[1]) };
        })];
        allTilesForSuit.forEach(t => {
            if (t.suit === 'feng' || t.suit === 'jian') hasZi = true;
            else suitsUsed.add(t.suit);
        });

        // 清一色
        if (suitsUsed.size === 1 && !hasZi) {
            fans.push({ key: 'qing_yi_se', ...FAN_TYPES.qing_yi_se });
        }
        // 混一色
        else if (suitsUsed.size === 1 && hasZi) {
            fans.push({ key: 'hun_yi_se', ...FAN_TYPES.hun_yi_se });
        }

        // 缺一门
        if (this._checkQueYiMen(suitsUsed)) {
            fans.push({ key: 'que_yi_men', ...FAN_TYPES.que_yi_men });
        }

        // 无字
        if (!hasZi && suitsUsed.size > 0) {
            fans.push({ key: 'wu_zi', ...FAN_TYPES.wu_zi });
        }

        // 五门齐
        if (this._checkWuMenQi(hand, suitsUsed, hasZi, melds)) {
            fans.push({ key: 'wu_men_qi', ...FAN_TYPES.wu_men_qi });
        }

        // === 碰碰胡 / 四暗刻类 ===
        if (this._checkPengPengHu(groups)) {
            // 四暗刻
            if (this._checkSiAnKe(groups)) {
                fans.push({ key: 'si_an_ke', ...FAN_TYPES.si_an_ke });
            } else {
                fans.push({ key: 'peng_peng_hu', ...FAN_TYPES.peng_peng_hu });
            }
        }

        // 平和
        if (this._checkPingHe(groups, melds)) {
            fans.push({ key: 'ping_he', ...FAN_TYPES.ping_he });
        }

        // 断幺
        if (this._checkDuanYao(hand, melds)) {
            fans.push({ key: 'duan_yao', ...FAN_TYPES.duan_yao });
        }

        // 全带幺 / 清幺九 / 混幺九
        if (this._checkQuanDaiYao(groups, melds)) {
            fans.push({ key: 'quan_dai_yao', ...FAN_TYPES.quan_dai_yao });
        }
        if (this._checkQingYaoJiu(groups)) {
            fans.push({ key: 'qing_yao_jiu', ...FAN_TYPES.qing_yao_jiu });
        }
        if (this._checkHunYaoJiu(groups, hasZi)) {
            fans.push({ key: 'hun_yao_jiu', ...FAN_TYPES.hun_yao_jiu });
        }

        // 全大/全中/全小
        if (this._checkQuanDa(hand, melds)) {
            fans.push({ key: 'quan_da', ...FAN_TYPES.quan_da });
        }
        if (this._checkQuanZhong(hand, melds)) {
            fans.push({ key: 'quan_zhong', ...FAN_TYPES.quan_zhong });
        }
        if (this._checkQuanXiao(hand, melds)) {
            fans.push({ key: 'quan_xiao', ...FAN_TYPES.quan_xiao });
        }

        // === 刻子类 ===
        const jianKeCount = this._countJianKe(groups);
        if (jianKeCount >= 3) {
            fans.push({ key: 'da_san_yuan', ...FAN_TYPES.da_san_yuan });
        } else if (jianKeCount === 2) {
            fans.push({ key: 'shuang_jian_ke', ...FAN_TYPES.shuang_jian_ke });
        } else if (jianKeCount === 1) {
            fans.push({ key: 'jian_ke', ...FAN_TYPES.jian_ke });
        }

        // 小三元（两箭刻+箭牌将）
        if (this._checkXiaoSanYuan(groups)) {
            fans.push({ key: 'xiao_san_yuan', ...FAN_TYPES.xiao_san_yuan });
        }

        // 风刻
        const fengKeCount = this._countFengKe(groups);
        if (fengKeCount >= 4) {
            fans.push({ key: 'da_si_xi', ...FAN_TYPES.da_si_xi });
        } else if (fengKeCount === 3) {
            fans.push({ key: 'san_feng_ke', ...FAN_TYPES.san_feng_ke });
        }
        // 小四喜
        if (this._checkXiaoSiXi(groups)) {
            fans.push({ key: 'xiao_si_xi', ...FAN_TYPES.xiao_si_xi });
        }

        // 幺九刻
        const yaoJiuKeCount = this._countYaoJiuKe(groups);
        for (let i = 0; i < yaoJiuKeCount; i++) {
            fans.push({ key: 'yao_jiu_ke', ...FAN_TYPES.yao_jiu_ke });
        }

        // 三暗刻 / 双暗刻
        const anKeCount = this._countAnKe(groups);
        if (anKeCount >= 3) {
            fans.push({ key: 'san_an_ke', ...FAN_TYPES.san_an_ke });
        } else if (anKeCount >= 2) {
            fans.push({ key: 'shuang_an_ke', ...FAN_TYPES.shuang_an_ke });
        }

        // 双同刻 / 三同刻
        if (this._checkSanTongKe(groups)) {
            fans.push({ key: 'san_tong_ke', ...FAN_TYPES.san_tong_ke });
        }
        if (this._checkShuangTongKe(groups)) {
            fans.push({ key: 'shuang_tong_ke', ...FAN_TYPES.shuang_tong_ke });
        }

        // 全双刻
        if (this._checkQuanShuangKe(groups)) {
            fans.push({ key: 'quan_shuang_ke', ...FAN_TYPES.quan_shuang_ke });
        }

        // === 顺子类 ===
        // 清龙
        if (this._checkQingLong(groups)) {
            fans.push({ key: 'qing_long', ...FAN_TYPES.qing_long });
        }

        // 组合龙
        if (this._checkZuHeLong(groups)) {
            fans.push({ key: 'zu_he_long', ...FAN_TYPES.zu_he_long });
        }

        // 一色四步高/三步高
        if (this._checkYiSeSiBuGao(groups)) {
            fans.push({ key: 'yi_se_si_bu_gao', ...FAN_TYPES.yi_se_si_bu_gao });
        } else if (this._checkYiSeSanBuGao(groups)) {
            fans.push({ key: 'yi_se_san_bu_gao', ...FAN_TYPES.yi_se_san_bu_gao });
        }

        // 一色四节高
        if (this._checkYiSeSiJie(groups)) {
            fans.push({ key: 'yi_se_si_jie_gao', ...FAN_TYPES.yi_se_si_jie_gao });
        }
        // 一色三节高
        if (this._checkYiSeSanJie(groups)) {
            fans.push({ key: 'yi_se_san_jie', ...FAN_TYPES.yi_se_san_jie });
        }

        // 一色四同顺 / 一色三同顺
        if (this._checkYiSeSiTongShun(groups)) {
            fans.push({ key: 'yi_se_si_tong_shun', ...FAN_TYPES.yi_se_si_tong_shun });
        } else if (this._checkYiSeSanTongShun(groups)) {
            fans.push({ key: 'yi_se_san_tong_shun', ...FAN_TYPES.yi_se_san_tong_shun });
        }

        // 一色双龙会
        if (this._checkYiSeShuangLong(groups)) {
            fans.push({ key: 'yi_se_shuang_long', ...FAN_TYPES.yi_se_shuang_long });
        }

        // 三色双龙会
        if (this._checkSanSeShuangLong(groups)) {
            fans.push({ key: 'san_se_shuang_long', ...FAN_TYPES.san_se_shuang_long });
        }

        // 连六
        if (this._checkLianLiu(groups)) {
            fans.push({ key: 'lian_liu', ...FAN_TYPES.lian_liu });
        }

        // 老少副
        if (this._checkLaoShaoFu(groups)) {
            fans.push({ key: 'lao_shao_fu', ...FAN_TYPES.lao_shao_fu });
        }

        // 一般高
        if (this._checkYiBanGao(groups)) {
            fans.push({ key: 'yi_ban_gao', ...FAN_TYPES.yi_ban_gao });
        }

        // 喜相逢
        if (this._checkXiXiangFeng(groups)) {
            fans.push({ key: 'xi_xiang_feng', ...FAN_TYPES.xi_xiang_feng });
        }

        // 花龙
        if (this._checkHuaLong(groups)) {
            fans.push({ key: 'hua_long', ...FAN_TYPES.hua_long });
        }

        // 三色三同顺
        if (this._checkSanSeSanTongShun(groups)) {
            fans.push({ key: 'san_se_san_tong_shun', ...FAN_TYPES.san_se_san_tong_shun });
        }

        // 三色三步高
        if (this._checkSanSeSanBuGao(groups)) {
            fans.push({ key: 'san_se_san_bu_gao', ...FAN_TYPES.san_se_san_bu_gao });
        }

        // 三色三节高
        if (this._checkSanSeSanJieGao(groups)) {
            fans.push({ key: 'san_se_san_jie_gao', ...FAN_TYPES.san_se_san_jie_gao });
        }

        // 全带五
        if (this._checkQuanDaiWu(groups, melds)) {
            fans.push({ key: 'quan_dai_wu', ...FAN_TYPES.quan_dai_wu });
        }

        // 大于五 / 小于五
        if (this._checkDaYuWu(hand, melds)) {
            fans.push({ key: 'da_yu_wu', ...FAN_TYPES.da_yu_wu });
        }
        if (this._checkXiaoYuWu(hand, melds)) {
            fans.push({ key: 'xiao_yu_wu', ...FAN_TYPES.xiao_yu_wu });
        }

        // === 杠类 ===
        const mingGangCount = melds.filter(m => m.type === 'gang' && !m.isHidden).length;
        const anGangCount = melds.filter(m => m.type === 'gang' && m.isHidden).length;
        const totalGang = mingGangCount + anGangCount;

        if (totalGang >= 4) {
            fans.push({ key: 'si_gang', ...FAN_TYPES.si_gang });
        } else if (totalGang >= 3) {
            fans.push({ key: 'san_gang', ...FAN_TYPES.san_gang });
        }
        if (mingGangCount >= 2) {
            fans.push({ key: 'shuang_ming_gang', ...FAN_TYPES.shuang_ming_gang });
        }
        if (anGangCount >= 2) {
            fans.push({ key: 'shuang_an_gang', ...FAN_TYPES.shuang_an_gang });
        }
        if (mingGangCount >= 1 && anGangCount >= 1) {
            fans.push({ key: 'ming_an_gang', ...FAN_TYPES.ming_an_gang });
        }
        for (let i = 0; i < mingGangCount; i++) {
            fans.push({ key: 'ming_gang', ...FAN_TYPES.ming_gang });
        }
        if (anGangCount >= 1) {
            fans.push({ key: 'an_gang', ...FAN_TYPES.an_gang });
        }

        // === 四归一 ===
        if (this._checkSiGuiYi(hand, melds)) {
            fans.push({ key: 'si_gui_yi', ...FAN_TYPES.si_gui_yi });
        }

        // === 推不倒 ===
        if (this._checkTuiBuDao(hand, melds)) {
            fans.push({ key: 'tui_bu_dao', ...FAN_TYPES.tui_bu_dao });
        }

        // 应用番种互斥
        this._applyRepeal(fans);
    },

    /**
     * 应用番种互斥逻辑：高番排斥低番
     */
    _applyRepeal(fans) {
        const keys = fans.map(f => f.key);
        const toRemove = new Set();
        for (const key of keys) {
            const repelled = REPEL_MODEL[key];
            if (repelled) {
                repelled.forEach(rk => toRemove.add(rk));
            }
        }
        // 不移除高番自身
        for (const key of keys) {
            toRemove.delete(key);
        }
        // 只保留没有被排斥的
        for (let i = fans.length - 1; i >= 0; i--) {
            if (toRemove.has(fans[i].key)) {
                fans.splice(i, 1);
            }
        }
    },

    /**
     * 分析手牌为面子+雀头组合
     */
    _analyzeGroups(hand, melds) {
        const groups = {
            shunzi: [],   // 顺子
            kezi: [],     // 刻子
            gang: [],     // 杠
            jiang: null   // 雀头
        };

        // 处理明牌（吃、碰、杠）
        melds.forEach(m => {
            if (m.type === 'chi') groups.shunzi.push(m);
            else if (m.type === 'peng') groups.kezi.push(m);
            else if (m.type === 'gang') groups.gang.push(m);
        });

        // 分析暗手
        const counts = {};
        hand.forEach(t => {
            counts[t.id] = (counts[t.id] || 0) + 1;
        });

        // 递归分解
        this._decomposeHand(hand, counts, groups);

        return groups;
    },

    _decomposeHand(hand, counts, groups) {
        // 找到第一张牌
        let firstId = null;
        for (const id of Object.keys(counts)) {
            if (counts[id] > 0) { firstId = id; break; }
        }
        if (!firstId) return true;

        const parts = firstId.split('_');
        const suit = parts[0];
        const num = parseInt(parts[1]);

        // 尝试雀头
        if (counts[firstId] >= 2 && groups.jiang === null) {
            groups.jiang = firstId;
            counts[firstId] -= 2;
            if (this._decomposeHand(hand, counts, groups)) return true;
            counts[firstId] += 2;
            groups.jiang = null;
        }

        // 尝试刻子
        if (counts[firstId] >= 3) {
            counts[firstId] -= 3;
            groups.kezi.push({ tiles: [firstId, firstId, firstId], isConcealed: true });
            if (this._decomposeHand(hand, counts, groups)) return true;
            counts[firstId] += 3;
            groups.kezi.pop();
        }

        // 尝试顺子
        if (suit !== 'feng' && suit !== 'jian' && num <= 7) {
            const id2 = `${suit}_${num + 1}`;
            const id3 = `${suit}_${num + 2}`;
            if ((counts[id2] || 0) > 0 && (counts[id3] || 0) > 0) {
                counts[firstId]--;
                counts[id2]--;
                counts[id3]--;
                groups.shunzi.push({ tiles: [firstId, id2, id3], type: 'chi', isConcealed: true });
                if (this._decomposeHand(hand, counts, groups)) return true;
                groups.shunzi.pop();
                counts[firstId]++;
                counts[id2]++;
                counts[id3]++;
            }
        }

        return false;
    },

    // ====== 番型检测方法 ======

    _checkQiDui(hand) {
        if (hand.length !== 14) return false;
        const counts = {};
        hand.forEach(t => {
            counts[t.id] = (counts[t.id] || 0) + 1;
        });
        for (const id in counts) {
            if (counts[id] % 2 !== 0) return false;
        }
        return true;
    },

    _checkPengPengHu(groups) {
        const totalKezi = groups.kezi.length + groups.gang.length;
        return totalKezi === 4;
    },

    _checkQingYiSe(hand) {
        if (hand.length === 0) return false;
        const suits = new Set(hand.map(t => t.suit));
        // 只有一种花色且不含字牌
        return suits.size === 1 && !suits.has('feng') && !suits.has('jian');
    },

    _checkHunYiSe(hand) {
        if (hand.length === 0) return false;
        const suits = new Set(hand.map(t => t.suit));
        const numSuits = ['wan', 'tiao', 'tong'].filter(s => suits.has(s));
        const honorSuits = ['feng', 'jian'].filter(s => suits.has(s));
        return numSuits.length === 1 && honorSuits.length >= 1;
    },

    _checkPingHe(groups, melds) {
        // 平和：4个顺子+序数牌雀头，没有字牌，没有刻子
        if (groups.kezi.length > 0 || groups.gang.length > 0) return false;
        if (groups.shunzi.length !== 4) return false;

        // 雀头不能是字牌
        if (groups.jiang) {
            const parts = groups.jiang.split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian') return false;
        }

        // 必须没有吃碰（门清状态）
        const meldChi = melds.filter(m => m.type === 'chi');
        return melds.length === 0 || meldChi.length === melds.length;
    },

    _checkDuanYao(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.length > 0 && allTiles.every(t => {
            const id = typeof t === 'string' ? t : t.id;
            return DUANYAO_SET.has(tileIdToPyCode(id));
        });
    },

    _checkQuanDaiYao(groups, melds) {
        const allGroups = [...groups.shunzi, ...groups.kezi, ...groups.gang];
        // 每组必须含1或9（全带幺的"幺九"不包括字牌，仅1和9）
        for (const g of allGroups) {
            if (!g.tiles.some(t => QINGYAOJIU_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)))) return false;
            // 组内不能含有字牌
            if (g.tiles.some(t => {
                const c = tileIdToPyCode(typeof t === 'string' ? t : t.id);
                return c >= 41; // 41-47 为字牌编码
            })) return false;
        }
        // 雀头也必须是1或9，不能是字牌
        if (!groups.jiang) return false;
        const jiangCode = tileIdToPyCode(groups.jiang);
        if (jiangCode >= 41) return false; // 字牌不能做将
        return QINGYAOJIU_SET.has(jiangCode);
    },

    _checkMenQianQing(melds) {
        // 没有吃、碰、明杠
        return melds.length === 0 || melds.every(m => m.type === 'gang' && m.isHidden);
    },

    _checkQuanQiuRen(hand, melds) {
        if (melds.length < 4) return false;
        return hand.length === 1; // 只剩最后一张单钓
    },

    _countJianKe(groups) {
        let count = 0;
        const honorIds = ['jian_0', 'jian_1', 'jian_2'];
        for (const ke of groups.kezi) {
            if (honorIds.includes(ke.tiles[0])) count++;
        }
        return count;
    },

    _countYaoJiuKe(groups) {
        let count = 0;
        for (const ke of groups.kezi) {
            const id = ke.tiles[0];
            const parts = id.split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian' ||
                parseInt(parts[1]) === 1 || parseInt(parts[1]) === 9) {
                count++;
            }
        }
        return count;
    },

    _checkLianLiu(groups) {
        // 连六：同一花色两副顺子，起始数差恰好为3（如123+456）
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const nums = suitShunzi.map(s => {
                const parts = s.tiles[0].split('_');
                return parseInt(parts[1]);
            }).sort((a, b) => a - b);

            for (let i = 0; i < nums.length - 1; i++) {
                if (nums[i + 1] - nums[i] === 3) return true;
            }
        }
        return false;
    },

    _checkQingLong(groups) {
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const numsSet = new Set(suitShunzi.map(s => {
                const parts = s.tiles[0].split('_');
                return parseInt(parts[1]);
            }));
            if (numsSet.has(1) && numsSet.has(4) && numsSet.has(7)) return true;
        }
        return false;
    },

    _checkYiSeSanJie(groups) {
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitKezi = groups.kezi.filter(s => s.tiles[0].startsWith(suit));
            const nums = suitKezi.map(s => {
                const parts = s.tiles[0].split('_');
                return parseInt(parts[1]);
            });
            if (nums.length >= 3) {
                // 检查是否连续三张
                nums.sort((a, b) => a - b);
                for (let i = 0; i <= nums.length - 3; i++) {
                    if (nums[i+1] === nums[i]+1 && nums[i+2] === nums[i]+2) return true;
                }
            }
        }
        return false;
    },

    _checkShuangTongKe(groups) {
        const numMap = {};
        for (const ke of groups.kezi) {
            const parts = ke.tiles[0].split('_');
            if (parts[0] !== 'feng' && parts[0] !== 'jian') {
                numMap[parts[1]] = (numMap[parts[1]] || 0) + 1;
            }
        }
        return Object.values(numMap).some(v => v >= 2);
    },

    _checkLaoShaoFu(groups) {
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const numsSet = new Set(suitShunzi.map(s => {
                const parts = s.tiles[0].split('_');
                return parseInt(parts[1]);
            }));
            if (numsSet.has(1) && numsSet.has(7)) return true;
        }
        return false;
    },

    _checkYiBanGao(groups) {
        const patterns = {};
        for (const sz of groups.shunzi) {
            const key = sz.tiles.join(',');
            patterns[key] = (patterns[key] || 0) + 1;
        }
        return Object.values(patterns).some(v => v >= 2);
    },

    // ===== 新增QQ番型检测 =====

    _checkShiSanYao(hand) {
        if (hand.length !== 14) return false;
        const idSet = new Set(hand.map(t => t.id));
        const need = ['wan_1','wan_9','tiao_1','tiao_9','tong_1','tong_9',
                      'feng_0','feng_1','feng_2','feng_3','jian_0','jian_1','jian_2'];
        const needSet = new Set(need);
        // 每张牌都必须是幺九牌
        if (!hand.every(t => needSet.has(t.id))) return false;
        let count = 0;
        for (const n of need) {
            if (idSet.has(n)) count++;
        }
        return count >= 13;
    },

    _checkQueYiMen(suitsUsed) {
        const allSuits = new Set(['wan', 'tiao', 'tong']);
        let missing = 0;
        for (const s of allSuits) {
            if (!suitsUsed.has(s)) missing++;
        }
        return missing === 1;
    },

    _checkWuMenQi(hand, suitsUsed, hasZi, melds) {
        if (!hasZi) return false;
        const orderSuits = new Set(['wan', 'tiao', 'tong']);
        let orderCount = 0;
        for (const s of orderSuits) {
            if (suitsUsed.has(s)) orderCount++;
        }
        // needs 3 序数 + 风 + 箭
        if (orderCount === 3) {
            // 合并手牌和副露检查风牌和箭牌
            const allTileIds = [
                ...hand.map(t => t.id),
                ...(melds || []).flatMap(m => m.tiles || [])
            ];
            const hasFeng = allTileIds.some(id => id.startsWith('feng_'));
            const hasJian = allTileIds.some(id => id.startsWith('jian_'));
            return hasFeng && hasJian;
        }
        return false;
    },

    _checkXiXiangFeng(groups) {
        // 两种花色序数相同的顺子
        for (let i = 0; i < groups.shunzi.length; i++) {
            for (let j = i + 1; j < groups.shunzi.length; j++) {
                const a = groups.shunzi[i], b = groups.shunzi[j];
                const aId = a.tiles[0], bId = b.tiles[0];
                const aParts = aId.split('_'), bParts = bId.split('_');
                if (aParts[0] !== bParts[0] && aParts[1] === bParts[1]) return true;
            }
        }
        return false;
    },

    _checkHuaLong(groups) {
        // 花龙：三种花色分别有 123、456、789 各一副（同一区间三色不行，需不同区间）
        const starts = { wan: new Set(), tiao: new Set(), tong: new Set() };
        for (const sz of groups.shunzi) {
            const tile = this._parseTileId(sz.tiles[0]);
            if (starts[tile.suit]) starts[tile.suit].add(tile.num);
        }
        // 三门花色各取一个不同区间(1/4/7)，且互不重复
        for (const s1 of ['wan', 'tiao', 'tong']) {
            if (!starts[s1].has(1)) continue;
            for (const s2 of ['wan', 'tiao', 'tong']) {
                if (s2 === s1 || !starts[s2].has(4)) continue;
                for (const s3 of ['wan', 'tiao', 'tong']) {
                    if (s3 === s1 || s3 === s2 || !starts[s3].has(7)) continue;
                    return true;
                }
            }
        }
        return false;
    },

    _checkSanSeSanTongShun(groups) {
        // 三种花色相同数字的顺子
        const numGroups = {};
        for (const sz of groups.shunzi) {
            const tile = this._parseTileId(sz.tiles[0]);
            const key = tile.num;
            if (!numGroups[key]) numGroups[key] = new Set();
            numGroups[key].add(tile.suit);
        }
        for (const key in numGroups) {
            if (numGroups[key].size >= 3) return true;
        }
        return false;
    },

    _checkSanSeSanBuGao(groups) {
        // 三种花色各一副递增一位的顺子（不限花色顺序）
        const bySuit = { wan: [], tiao: [], tong: [] };
        for (const sz of groups.shunzi) {
            const tile = this._parseTileId(sz.tiles[0]);
            if (bySuit[tile.suit]) bySuit[tile.suit].push(tile.num);
        }
        // 从三种花色各取一个值，检查是否能形成递增序列
        for (const n1 of bySuit.wan) {
            for (const n2 of bySuit.tiao) {
                for (const n3 of bySuit.tong) {
                    const vals = [n1, n2, n3].sort((a, b) => a - b);
                    if (vals[1] === vals[0] + 1 && vals[2] === vals[1] + 1) return true;
                }
            }
        }
        return false;
    },

    _parseTileId(tileId) {
        const parts = tileId.split('_');
        return { suit: parts[0], num: parseInt(parts[1]) };
    },

    // ====== 新增强大番型检测方法 ======

    _checkJiuLianBaoDeng(hand) {
        if (hand.length !== 14) return false;
        const suits = new Set(hand.map(t => t.suit));
        if (suits.size !== 1) return false;
        if (suits.has('feng') || suits.has('jian')) return false;
        const counts = {};
        hand.forEach(t => { counts[t.id] = (counts[t.id] || 0) + 1; });
        const ids = Object.keys(counts);
        // 九莲宝灯：1112345678999 加任意一张同花色
        // 表现为：counts中1和9各>=3, 2-8各>=1, 总数14
        if (ids.length !== 9) return false;
        for (let n = 1; n <= 9; n++) {
            const id = `${[...suits][0]}_${n}`;
            if (!counts[id]) return false;
            if ((n === 1 || n === 9) && counts[id] < 3) return false;
            if (n >= 2 && n <= 8 && counts[id] < 1) return false;
        }
        return true;
    },

    _checkLianQiDui(hand) {
        if (hand.length !== 14) return false;
        const counts = {};
        hand.forEach(t => { counts[t.id] = (counts[t.id] || 0) + 1; });
        for (const id in counts) {
            if (counts[id] !== 2) return false;
        }
        // 检查是否同花色且连续
        const ids = Object.keys(counts);
        if (ids.length !== 7) return false;
        const first = this._parseTileId(ids[0]);
        if (first.suit === 'feng' || first.suit === 'jian') return false;
        const suit = first.suit;
        let num = first.num;
        for (const id of ids.sort()) {
            const t = this._parseTileId(id);
            if (t.suit !== suit) return false;
            if (t.num !== num) return false;
            num++;
        }
        return true;
    },

    _checkZiYiSe(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.every(t => ZIPAI_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)));
    },

    _checkLvYiSe(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.every(t => LVYISE_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)));
    },

    _checkQiXingBuKao(hand) {
        if (hand.length !== 14) return false;
        // 7张字牌（东南西北中发白各1）
        const fengJian = hand.filter(t => t.suit === 'feng' || t.suit === 'jian');
        if (fengJian.length !== 7) return false;
        const fengJianSet = new Set(fengJian.map(t => t.id));
        const needFJ = ['feng_0','feng_1','feng_2','feng_3','jian_0','jian_1','jian_2'];
        if (!needFJ.every(id => fengJianSet.has(id))) return false;

        // 7张序数牌必须符合147/258/369分布（每门花色各取一个不同区间）
        const numTiles = hand.filter(t => !fengJianSet.has(t.id));
        if (numTiles.length !== 7) return false;
        return this._checkBuKaoPattern(numTiles);
    },

    _checkQuanBuKao(hand) {
        if (hand.length !== 14) return false;
        // 全不靠：3种花色147/258/369各取一组，外加5张字牌
        const numTiles = hand.filter(t => t.suit === 'wan' || t.suit === 'tiao' || t.suit === 'tong');
        const honorTiles = hand.filter(t => t.suit === 'feng' || t.suit === 'jian');
        // 序数牌必须9张（3花色×3张），字牌必须5张，一共14张
        if (numTiles.length !== 9 || honorTiles.length !== 5) return false;
        // 字牌不能重复
        const honorIds = honorTiles.map(t => t.id);
        if (new Set(honorIds).size !== 5) return false;
        // 序数牌必须符合147/258/369
        return this._checkBuKaoPattern(numTiles);
    },

    // 辅助：检查手牌是否由147/258/369各取一门花色组成
    _checkBuKaoPattern(tiles) {
        // 不能有重复牌
        const counts = {};
        tiles.forEach(t => { counts[t.id] = (counts[t.id] || 0) + 1; });
        if (Object.values(counts).some(c => c > 1)) return false;

        const patterns = [[1,4,7],[2,5,8],[3,6,9]];
        const bySuit = { wan: [], tiao: [], tong: [] };
        tiles.forEach(t => { if (bySuit[t.suit]) bySuit[t.suit].push(t.num); });

        const getPatternIndex = (nums) => {
            for (let i = 0; i < patterns.length; i++) {
                if (nums.every(n => patterns[i].includes(n))) return i;
            }
            return -1;
        };

        const usedPatterns = new Set();
        for (const suit of ['wan', 'tiao', 'tong']) {
            const nums = bySuit[suit];
            if (nums.length === 0) continue;
            const pi = getPatternIndex(nums);
            if (pi === -1 || usedPatterns.has(pi)) return false;
            usedPatterns.add(pi);
        }
        // 三种区间必须全部用上
        return usedPatterns.size === 3;
    },

    _checkZuHeLong(groups) {
        // 检查是否包含一组147/258/369（花色不限）
        const shunziHelpers = { 1: [1,4,7], 2: [2,5,8], 3: [3,6,9] };
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const nums = new Set(suitShunzi.map(s => parseInt(s.tiles[0].split('_')[1])));
            if (nums.has(1) && nums.has(4) && nums.has(7)) return true;
            if (nums.has(2) && nums.has(5) && nums.has(8)) return true;
            if (nums.has(3) && nums.has(6) && nums.has(9)) return true;
        }
        return false;
    },

    _checkXiaoSanYuan(groups) {
        const jianIds = ['jian_0', 'jian_1', 'jian_2'];
        let keCount = 0;
        let jiangIsJian = false;
        for (const ke of groups.kezi) {
            if (jianIds.includes(ke.tiles[0])) keCount++;
        }
        if (groups.jiang && jianIds.includes(groups.jiang)) jiangIsJian = true;
        return keCount >= 2 && jiangIsJian;
    },

    _countFengKe(groups) {
        let count = 0;
        const fengIds = ['feng_0','feng_1','feng_2','feng_3'];
        for (const ke of groups.kezi) {
            if (fengIds.includes(ke.tiles[0])) count++;
        }
        return count;
    },

    _checkXiaoSiXi(groups) {
        const fengIds = ['feng_0', 'feng_1', 'feng_2', 'feng_3'];
        let keCount = 0;
        let jiangIsFeng = false;
        for (const ke of groups.kezi) {
            if (fengIds.includes(ke.tiles[0])) keCount++;
        }
        if (groups.jiang && fengIds.includes(groups.jiang)) jiangIsFeng = true;
        return keCount >= 3 && jiangIsFeng;
    },

    _checkSiAnKe(groups) {
        // 全部4组刻子都是暗的（包括暗杠）
        const totalKeAn = groups.kezi.filter(k => k.isConcealed).length;
        const totalGangAn = groups.gang.filter(g => g.isHidden).length;
        return (totalKeAn + totalGangAn) === 4;
    },

    _countAnKe(groups) {
        return groups.kezi.filter(k => k.isConcealed).length;
    },

    _checkSanTongKe(groups) {
        const numMap = {};
        for (const ke of groups.kezi) {
            const parts = ke.tiles[0].split('_');
            if (parts[0] !== 'feng' && parts[0] !== 'jian') {
                numMap[parts[1]] = (numMap[parts[1]] || 0) + 1;
            }
        }
        return Object.values(numMap).some(v => v >= 3);
    },

    _checkQuanShuangKe(groups) {
        if (groups.shunzi.length > 0) return false;
        for (const ke of groups.kezi) {
            const parts = ke.tiles[0].split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian') return false;
            const num = parseInt(parts[1]);
            if (num % 2 !== 0) return false;
        }
        // 将牌也必须是双数
        if (groups.jiang) {
            const parts = groups.jiang.split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian') return false;
            const num = parseInt(parts[1]);
            if (num % 2 !== 0) return false;
        }
        return true;
    },

    _checkYiSeSiBuGao(groups) {
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const nums = suitShunzi.map(s => parseInt(s.tiles[0].split('_')[1])).sort((a,b) => a-b);
            // 四副步进为1的顺子
            if (nums.length >= 4) {
                for (let i = 0; i <= nums.length - 4; i++) {
                    if (nums[i+1]-nums[i]===1 && nums[i+2]-nums[i+1]===1 && nums[i+3]-nums[i+2]===1) return true;
                }
            }
        }
        return false;
    },

    _checkYiSeSanBuGao(groups) {
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const nums = suitShunzi.map(s => parseInt(s.tiles[0].split('_')[1])).sort((a,b) => a-b);
            if (nums.length >= 3) {
                for (let i = 0; i <= nums.length - 3; i++) {
                    if (nums[i+1]-nums[i]===1 && nums[i+2]-nums[i+1]===1) return true;
                }
            }
        }
        return false;
    },

    _checkYiSeSiJie(groups) {
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitKezi = groups.kezi.filter(s => s.tiles[0].startsWith(suit));
            const nums = suitKezi.map(s => parseInt(s.tiles[0].split('_')[1])).sort((a,b) => a-b);
            if (nums.length >= 4) {
                for (let i = 0; i <= nums.length - 4; i++) {
                    if (nums[i+1]-nums[i]===1 && nums[i+2]-nums[i+1]===1 && nums[i+3]-nums[i+2]===1) return true;
                }
            }
        }
        return false;
    },

    _checkYiSeSiTongShun(groups) {
        const patterns = {};
        for (const sz of groups.shunzi) {
            const key = sz.tiles.join(',');
            patterns[key] = (patterns[key] || 0) + 1;
        }
        return Object.values(patterns).some(v => v >= 4);
    },

    _checkYiSeSanTongShun(groups) {
        const patterns = {};
        for (const sz of groups.shunzi) {
            const key = sz.tiles.join(',');
            patterns[key] = (patterns[key] || 0) + 1;
        }
        return Object.values(patterns).some(v => v >= 3);
    },

    _checkYiSeShuangLong(groups) {
        // 同花色：123 + 789 + 5万雀头
        for (const suit of ['wan', 'tiao', 'tong']) {
            const suitShunzi = groups.shunzi.filter(s => s.tiles[0].startsWith(suit));
            const nums = new Set(suitShunzi.map(s => parseInt(s.tiles[0].split('_')[1])));
            if (nums.has(1) && nums.has(7)) {
                // 雀头是5
                if (groups.jiang) {
                    const jp = groups.jiang.split('_');
                    if (jp[0] === suit && parseInt(jp[1]) === 5) return true;
                }
            }
        }
        return false;
    },

    _checkSanSeShuangLong(groups) {
        // 两种花色各有一副老少副(123+789)，另一种花色5为雀头
        const bySuit = { wan: new Set(), tiao: new Set(), tong: new Set() };
        for (const sz of groups.shunzi) {
            const tile = this._parseTileId(sz.tiles[0]);
            if (bySuit[tile.suit]) {
                if (tile.num === 1) bySuit[tile.suit].add(1);
                else if (tile.num === 7) bySuit[tile.suit].add(7);
            }
        }
        let suitWithBoth = 0;
        let suitWithJiang = null;
        for (const s of ['wan', 'tiao', 'tong']) {
            if (bySuit[s].has(1) && bySuit[s].has(7)) suitWithBoth++;
            else if (bySuit[s].size > 0) suitWithJiang = s;
        }
        if (suitWithBoth === 2 && groups.jiang) {
            const jp = groups.jiang.split('_');
            if (jp[0] !== 'feng' && jp[0] !== 'jian' && parseInt(jp[1]) === 5) return true;
        }
        return false;
    },

    _checkSanSeSanJieGao(groups) {
        // 三种花色各一副数字递增一位的刻子（不限花色顺序）
        const bySuit = { wan: [], tiao: [], tong: [] };
        for (const ke of groups.kezi) {
            const tile = this._parseTileId(ke.tiles[0]);
            if (bySuit[tile.suit]) bySuit[tile.suit].push(tile.num);
        }
        for (const n1 of bySuit.wan) {
            for (const n2 of bySuit.tiao) {
                for (const n3 of bySuit.tong) {
                    const vals = [n1, n2, n3].sort((a, b) => a - b);
                    if (vals[1] === vals[0] + 1 && vals[2] === vals[1] + 1) return true;
                }
            }
        }
        return false;
    },

    _checkQingYaoJiu(groups) {
        if (groups.shunzi.length > 0) return false;
        // 刻子必须全是1和9
        const allIds = groups.kezi.flatMap(k => k.tiles || []);
        if (allIds.length === 0) return false;
        if (!allIds.every(t => QINGYAOJIU_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)))) return false;
        // 将牌也必须是1或9
        return !groups.jiang || QINGYAOJIU_SET.has(tileIdToPyCode(groups.jiang));
    },

    _checkHunYaoJiu(groups, hasZi) {
        if (!hasZi) return false;
        if (groups.shunzi.length > 0) return false;
        // 刻子必须全是幺九牌（1/9/字牌）
        const allIds = groups.kezi.flatMap(k => k.tiles || []);
        if (allIds.length === 0) return false;
        if (!allIds.every(t => HUNYAOJIU_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)))) return false;
        // 不能全是清幺九（需包含至少一张字牌）
        if (allIds.every(t => QINGYAOJIU_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)))) return false;
        // 将牌也必须是幺九牌
        return !groups.jiang || HUNYAOJIU_SET.has(tileIdToPyCode(groups.jiang));
    },

    _checkQuanDa(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.length > 0 && allTiles.every(t => QUANDA_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)));
    },

    _checkQuanZhong(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.length > 0 && allTiles.every(t => QUANZHONG_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)));
    },

    _checkQuanXiao(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.every(t => {
            const id = typeof t === 'string' ? t : t.id;
            const parts = id.split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian') return false;
            return parseInt(parts[1]) <= 3;
        });
    },

    _checkQuanDaiWu(groups, melds) {
        const allGroups = [...groups.shunzi, ...groups.kezi, ...groups.gang];
        // 每组必须含5（组内其他牌可以是 1~9，不需要全部在 1-5）
        for (const g of allGroups) {
            if (!g.tiles.some(t => tileIdToPyCode(typeof t === 'string' ? t : t.id) % 10 === 5)) return false;
        }
        // 雀头也必须是5
        return !groups.jiang || tileIdToPyCode(groups.jiang) % 10 === 5;
    },

    _checkDaYuWu(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.length > 0 && allTiles.every(t => DAYUWU_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)));
    },

    _checkXiaoYuWu(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.length > 0 && allTiles.every(t => {
            const id = typeof t === 'string' ? t : t.id;
            const parts = id.split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian') return false;
            return parseInt(parts[1]) <= 4;
        });
    },

    _checkHeJueZhang(winTile, discards) {
        if (!winTile || !discards) return false;
        const count = discards.filter(d => d.id === winTile.id).length;
        return count >= 3;
    },

    _checkSiGuiYi(hand, melds) {
        // 同花色4张相同但非杠
        const allMeldIds = melds.flatMap(m => m.tiles || []);
        const handCounts = {};
        hand.forEach(t => { handCounts[t.id] = (handCounts[t.id] || 0) + 1; });
        for (const id in handCounts) {
            if (handCounts[id] === 4) return true;
        }
        // 或者手牌中有3张+副露中有1张同牌
        for (const id in handCounts) {
            if (handCounts[id] === 3 && allMeldIds.includes(id)) return true;
        }
        return false;
    },

    _checkTuiBuDao(hand, melds) {
        const allTiles = [...hand, ...melds.flatMap(m => m.tiles || [])];
        return allTiles.every(t => TUIBUDAO_SET.has(tileIdToPyCode(typeof t === 'string' ? t : t.id)));
    }
};

// ============================================================
// AI 策略引擎（向听数+进张数评分）
// ============================================================
const AIEngine = {
    /**
     * AI出牌决策：计算每张牌打出后的向听数+进张数，选择最优
     */
    decideDiscard(hand, discards, gameState) {
        if (hand.length === 0) return null;
        if (hand.length === 1) return hand[0];

        const melds = gameState.myMelds || [];
        const visible = this._getVisibleTiles(hand, discards, melds);

        let bestTile = null;
        let bestScore = -99999;

        for (const tile of hand) {
            const testHand = TileEngine.removeTiles(hand, [tile]);
            const score = this._evaluateHand(testHand, melds, visible);
            if (score > bestScore) {
                bestScore = score;
                bestTile = tile;
            }
        }

        return bestTile || hand[hand.length - 1];
    },

    /**
     * 评估一手牌的牌效率
     * 评分 = (10 - 向听数) * 1000 + 进张数
     * 向听数越少越好，进张数越多越好
     */
    _evaluateHand(hand, melds, visible) {
        const meldCount = melds.length;
        const shanten = this._calcShanten(hand, meldCount);
        const acceptCount = this._calcAcceptCount(hand, melds, visible);

        // 听牌评分大幅提高
        let score = (10 - shanten) * 1000 + acceptCount * 10;

        // 附加：面子完整度
        const counts = {};
        hand.forEach(t => { counts[t.id] = (counts[t.id] || 0) + 1; });
        for (const id in counts) {
            if (counts[id] >= 3) score += 20;           // 刻子
            else if (counts[id] === 2) score += 5;      // 对子
        }

        // 顺子潜力加分
        for (const suit of ['wan', 'tiao', 'tong']) {
            const nums = hand.filter(t => t.suit === suit).map(t => t.num).sort((a,b) => a-b);
            for (let i = 0; i < nums.length - 1; i++) {
                if (nums[i+1] - nums[i] === 1) score += 3;
                else if (nums[i+1] - nums[i] === 2) score += 1;
            }
        }

        // 孤牌减分
        for (const id in counts) {
            if (counts[id] === 1) {
                const parts = id.split('_');
                const tile = { suit: parts[0], num: parseInt(parts[1]) };
                if (!this._hasAdjacent(hand, tile)) {
                    score -= 10;
                }
            }
        }

        return score;
    },

    /**
     * 计算向听数：离和牌还差几步
     * 向听数 = 0：听牌；向听数 = -1：已胡牌
     */
    _calcShanten(hand, meldCount) {
        // 标准型：需要 4-meldCount 个面子 + 1 个雀头
        const targetMelds = 4 - meldCount;
        // 向听数 = 需要的最少替换牌数
        // 简单估算法：总牌数/3 越接近 targetMelds+1 越好
        const groups = this._countGroups(hand);
        const pairs = groups.pairs;
        const sequences = groups.sequences;
        const triplets = groups.triplets;

        let partial = sequences + triplets; // 已有面子的数量
        let pairs_count = pairs;

        // 每缺一个面子需要一张牌来补
        let shanten = targetMelds - partial;
        if (shanten < 0) shanten = 0;

        // 雀头调整
        if (pairs_count === 0 && targetMelds > 0) shanten++;

        return shanten;
    },

    /**
     * 计算手牌中的对子、顺子候选、刻子数量
     */
    _countGroups(hand) {
        const counts = {};
        hand.forEach(t => { counts[t.id] = (counts[t.id] || 0) + 1; });

        let pairs = 0;
        let triplets = 0;
        const singles = [];

        for (const id in counts) {
            if (counts[id] >= 3) triplets++;
            else if (counts[id] === 2) pairs++;
            else singles.push(id);
        }

        // 计算顺子候选
        let sequences = 0;
        const processed = new Set();
        for (const id of singles.sort()) {
            if (processed.has(id)) continue;
            const parts = id.split('_');
            if (parts[0] === 'feng' || parts[0] === 'jian') continue;
            const suit = parts[0];
            const num = parseInt(parts[1]);
            if (num <= 7) {
                const id2 = `${suit}_${num+1}`;
                const id3 = `${suit}_${num+2}`;
                if (counts[id2] && counts[id3]) {
                    sequences++;
                    processed.add(id);
                    processed.add(id2);
                    processed.add(id3);
                }
            }
        }

        return { pairs, triplets, sequences };
    },

    /**
     * 计算有效进张数：摸到哪些牌可以降向听数
     */
    _calcAcceptCount(hand, melds, visible) {
        let count = 0;
        const allTiles = [];
        ['wan', 'tiao', 'tong'].forEach(suit => {
            for (let n = 1; n <= 9; n++) allTiles.push({ suit, num: n, id: `${suit}_${n}` });
        });
        for (let i = 0; i < 4; i++) allTiles.push({ suit: 'feng', num: i, id: `feng_${i}` });
        for (let i = 0; i < 3; i++) allTiles.push({ suit: 'jian', num: i, id: `jian_${i}` });

        const currentShanten = this._calcShanten(hand, 0);

        for (const tile of allTiles) {
            // 跳过已经用光的牌
            if (visible[tile.id] && visible[tile.id] >= 4) continue;
            const testHand = [...hand, { ...tile }];
            const newShanten = this._calcShanten(testHand, 0);
            if (newShanten < currentShanten) count++;
        }

        return count;
    },

    /**
     * 获取可见牌（手牌+副露+出牌）
     */
    _getVisibleTiles(hand, discards, melds) {
        const visible = {};
        hand.forEach(t => { visible[t.id] = (visible[t.id] || 0) + 1; });
        discards.forEach(t => { visible[t.id] = (visible[t.id] || 0) + 1; });
        melds.forEach(m => {
            (m.tiles || []).forEach(tId => {
                visible[tId] = (visible[tId] || 0) + 1;
            });
        });
        return visible;
    },

    _hasAdjacent(hand, tile) {
        if (tile.suit === 'feng' || tile.suit === 'jian') return false;
        return hand.some(t =>
            t.suit === tile.suit && Math.abs(t.num - tile.num) <= 2 && t.num !== tile.num
        );
    },

    /**
     * AI吃牌决策：模拟吃牌后的评分
     */
    decideChi(hand, discardTile, chiOptions) {
        if (!chiOptions || chiOptions.length === 0) return null;
        let bestOption = null;
        let bestScore = -99999;

        for (const option of chiOptions) {
            const tilesToRemove = option.tiles.filter(id => id !== discardTile.id);
            const removeTiles = tilesToRemove.map(id => {
                const parts = id.split('_');
                return { suit: parts[0], num: parseInt(parts[1]), id };
            });
            const testHand = TileEngine.removeTiles(hand, removeTiles);
            const score = this._evaluateHand(testHand, [], {});
            if (score > bestScore) {
                bestScore = score;
                bestOption = option;
            }
        }

        return bestOption;
    },

    /**
     * AI碰牌决策：模拟碰牌后评分，高于pass才碰
     */
    decidePeng(hand, discardTile) {
        if (!HuChecker.canPeng(hand, discardTile)) return false;
        const beforeScore = this._evaluateHand(hand, [], {});
        const testHand = TileEngine.removeTiles(hand, [discardTile, discardTile]);
        const afterScore = this._evaluateHand(testHand, [], {});
        return afterScore > beforeScore - 5;
    },

    /**
     * AI杠牌决策
     */
    decideGang(hand, discardTile) {
        if (!HuChecker.canGang(hand, discardTile)) return false;
        const beforeScore = this._evaluateHand(hand, [], {});
        const testHand = TileEngine.removeTiles(hand, [discardTile, discardTile, discardTile]);
        const afterScore = this._evaluateHand(testHand, [], {});
        // 杠牌不降分则杠
        return afterScore >= beforeScore - 10;
    },

    decideHu(hand, discardTile) {
        const testHand = [...hand, { ...discardTile }];
        return HuChecker.isHu(testHand);
    }
};
