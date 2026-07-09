// ==================== AI 策略（专业版 v2） ====================
// 核心能力：记牌、多策略拆牌、角色配合、拆牌跟牌、风险评估

// ========== 牌值映射表 ==========
// gameState.usedCards 使用显示键（'3','4','J','2','小王'等）
// getCardValue() 返回数值（3~17）
const VALUE_TO_KEY = {
    3:'3', 4:'4', 5:'5', 6:'6', 7:'7', 8:'8', 9:'9', 10:'10',
    11:'J', 12:'Q', 13:'K', 14:'A', 15:'2', 16:'小王', 17:'大王'
};
const KEY_TO_VALUE = {
    '3':3, '4':4, '5':5, '6':6, '7':7, '8':8, '9':9, '10':10,
    'J':11, 'Q':12, 'K':13, 'A':14, '2':15, '小王':16, '大王':17
};
// 每种牌的总数
function totalCountOf(value) {
    return value >= 16 ? 1 : 4;
}

// ========== 记牌器（Deduction Engine） ==========
const CardMemory = {
    /** 初始化/更新记牌器状态 */
    update(gameState) {
        if (!gameState || !gameState.usedCards) {
            this.playedCount = {};
            this.playerCardCount = [17, 17, 17];
            return;
        }
        this.playedCount = {};
        for (const [key, count] of Object.entries(gameState.usedCards)) {
            const val = KEY_TO_VALUE[key];
            if (val !== undefined) this.playedCount[val] = count;
        }
        this.playerCardCount = gameState.players.map(p => (p.cards || []).length);
        const landlord = gameState.players.find(p => p.isLandlord);
        this.landlordIndex = landlord ? gameState.players.indexOf(landlord) : -1;
    },

    /** 某数值牌在外还剩几张 */
    remaining(value) {
        return Math.max(0, totalCountOf(value) - (this.playedCount[value] || 0));
    },

    /** 该值是否已经出完 */
    isExhausted(value) {
        return this.remaining(value) === 0;
    },

    /** 2 还剩几张 */
    twosLeft() { return this.remaining(15); },
    acesLeft() { return this.remaining(14); },
    kingsLeft() { return this.remaining(13); },
    bigJokerLeft() { return this.remaining(17) > 0; },
    smallJokerLeft() { return this.remaining(16) > 0; },

    /** 火箭是否可能（大小王都还在外面） */
    rocketPossible() {
        return this.remaining(16) > 0 && this.remaining(17) > 0;
    },

    /** 推测外面还有几个炸弹（某数值4张全未出） */
    possibleBombs(maxValue = 15) {
        let count = 0;
        for (let v = 3; v <= maxValue; v++) {
            if (this.remaining(v) === 4) count++;
        }
        return count;
    },

    /** 获取某玩家手牌数 */
    playerCards(index) {
        return index >= 0 && index < this.playerCardCount.length
            ? this.playerCardCount[index] : 0;
    },

    /** 获取队友手牌数（对农民而言），地主返回 Infinity */
    teammateCards(playerIndex) {
        if (playerIndex === this.landlordIndex) return Infinity;
        const teammate = playerIndex === 1 ? 2 : 1;
        return this.playerCards(teammate);
    },

    /** 获取地主手牌数 */
    landlordCards() {
        return this.landlordIndex >= 0 ? this.playerCards(this.landlordIndex) : 20;
    },

    /** 地主压力指数（0~100）：值越大越危急 */
    landlordPressure() {
        const lc = this.landlordCards();
        if (lc <= 1) return 100;
        if (lc <= 3) return 80;
        if (lc <= 5) return 60;
        if (lc <= 8) return 40;
        return 20;
    }
};

// ================================================================
//  AI 主对象
// ================================================================
const AI = {
    // ========== 牌型权重 ==========
    CARD_WEIGHT: {
        3: 1, 4: 2, 5: 3, 6: 4, 7: 5, 8: 6, 9: 7, 10: 8,
        11: 9, 12: 10, 13: 11, 14: 12, 15: 13, 16: 14, 17: 15
    },

    // ========== 手牌分析 ==========
    analyzeHand(hand) {
        const groups = {};
        hand.forEach(card => {
            const v = getCardValue(card);
            if (!groups[v]) groups[v] = [];
            groups[v].push(card);
        });

        const singles = [], pairs = [], triples = [], quads = [];
        for (const [v, cards] of Object.entries(groups)) {
            const val = parseInt(v);
            switch(cards.length) {
                case 1: singles.push({ value: val, cards }); break;
                case 2: pairs.push({ value: val, cards }); break;
                case 3: triples.push({ value: val, cards }); break;
                case 4: quads.push({ value: val, cards }); break;
            }
        }

        singles.sort((a, b) => a.value - b.value);
        pairs.sort((a, b) => a.value - b.value);
        triples.sort((a, b) => a.value - b.value);
        quads.sort((a, b) => a.value - b.value);

        return { singles, pairs, triples, quads, groups, hand };
    },

    // ========== 手牌质量评估 ==========
    /**
     * 评估一手牌的综合质量（分值越高牌越好）
     */
    evaluateHandQuality(hand) {
        const analysis = this.analyzeHand(hand);
        let score = 0;

        // 基础大牌分
        hand.forEach(card => {
            const v = getCardValue(card);
            if (v === 17) score += 7;      // 大王
            else if (v === 16) score += 5;  // 小王
            else if (v === 15) score += 4;  // 2
            else if (v === 14) score += 2.5; // A
            else if (v === 13) score += 1.5; // K
        });

        // 炸弹加分
        const bombs = this.findBombs(hand);
        score += bombs.length * 8;

        // 火箭加分
        if (this.findRocket(hand)) score += 10;

        // 三带结构加分（三条越多越灵活）
        score += analysis.triples.length * 2;

        // 孤牌（孤立单张）扣分
        const sortedSingles = analysis.singles.filter(s => {
            const val = s.value;
            const inStraight = this._isInStraight(hand, val);
            return !inStraight;
        });
        const isolatedSingles = sortedSingles.filter(s => {
            // 值 <= 10 且无相同值成对/三条的才是真正的孤牌
            const val = s.value;
            return val <= 10
                && !analysis.pairs.some(p => p.value === val)
                && !analysis.triples.some(t => t.value === val)
                && !analysis.quads.some(q => q.value === val);
        });
        score -= isolatedSingles.length * 2.5;

        // 顺子潜力加分
        const maxStraightLen = this._maxStraightLength(hand);
        score += Math.max(0, maxStraightLen - 4) * 0.5;

        // 连对潜力加分
        const maxDoubleStraightLen = this._maxDoubleStraightLength(hand);
        score += Math.max(0, maxDoubleStraightLen - 2) * 1;

        // 2的数量过多时减分（2成对多说明散）
        if (analysis.pairs.some(p => p.value === 15)) score -= 1;
        if (analysis.singles.some(s => s.value === 15)) score -= 2; // 单张2是负资产

        return score;
    },

    /** 检查某数值是否在顺子中 */
    _isInStraight(hand, value) {
        const values = hand.map(c => getCardValue(c)).filter(v => v <= 14);
        const uniqueVals = [...new Set(values)].sort((a, b) => a - b);
        for (let len = 5; len <= uniqueVals.length; len++) {
            for (let start = 0; start <= uniqueVals.length - len; start++) {
                let valid = true;
                for (let i = 0; i < len; i++) {
                    if (uniqueVals[start + i] !== uniqueVals[start] + i) { valid = false; break; }
                }
                if (valid) {
                    for (let i = 0; i < len; i++) {
                        if (uniqueVals[start + i] === value) return true;
                    }
                }
            }
        }
        return false;
    },

    /** 最长顺子长度 */
    _maxStraightLength(hand) {
        const values = hand.map(c => getCardValue(c)).filter(v => v <= 14);
        const uniqueVals = [...new Set(values)].sort((a, b) => a - b);
        let maxLen = 0;
        for (let i = 0; i < uniqueVals.length; i++) {
            let j = i;
            while (j + 1 < uniqueVals.length && uniqueVals[j + 1] === uniqueVals[j] + 1) j++;
            maxLen = Math.max(maxLen, j - i + 1);
            i = j;
        }
        return maxLen;
    },

    /** 最长连对长度 */
    _maxDoubleStraightLength(hand) {
        const values = {};
        hand.forEach(c => {
            const v = getCardValue(c);
            if (v <= 14) { values[v] = (values[v] || 0) + 1; }
        });
        const pairVals = Object.entries(values).filter(([,c]) => c >= 2).map(([v]) => parseInt(v)).sort((a,b)=>a-b);
        let maxLen = 0;
        for (let i = 0; i < pairVals.length; i++) {
            let j = i;
            while (j + 1 < pairVals.length && pairVals[j + 1] === pairVals[j] + 1) j++;
            maxLen = Math.max(maxLen, j - i + 1);
            i = j;
        }
        return maxLen;
    },

    // ========== 多策略拆牌算法 ==========
    /**
     * 计算手牌的最少出牌手数（多策略）
     */
    calcMinRounds(hand) {
        if (hand.length === 0) return { needRound: 0, plan: [] };
        if (hand.length <= 2) {
            return this._calcTrivial(hand);
        }

        const analysis = this.analyzeHand(hand);
        let bestPlan = [];
        let bestScore = -Infinity;

        // 生成多个不同策略的方案
        const allPlans = this.generatePlans(analysis);

        for (const plan of allPlans) {
            const score = this._scorePlan(plan, hand.length);
            if (score > bestScore) {
                bestScore = score;
                bestPlan = plan;
            }
        }

        return { needRound: bestPlan.length, plan: bestPlan, score: bestScore };
    },

    /** 1-2张牌的简单处理 */
    _calcTrivial(hand) {
        if (hand.length === 1) {
            return { needRound: 1, plan: [{ type: 'single', value: getCardValue(hand[0]) }] };
        }
        const v1 = getCardValue(hand[0]), v2 = getCardValue(hand[1]);
        if (v1 === v2) {
            return { needRound: 1, plan: [{ type: 'pair', value: v1 }] };
        }
        return { needRound: 2, plan: [
            { type: 'single', value: Math.min(v1, v2) },
            { type: 'single', value: Math.max(v1, v2) }
        ]};
    },

    /** 评估出牌方案质量（分数越高越好） */
    _scorePlan(plan, totalCards) {
        const rounds = plan.length;
        if (rounds === 1) return 1000 + totalCards; // 一手出完，极优

        let score = 0;
        // 基础：手数越少越好
        score += (20 - rounds) * 10;

        // 有多少张牌是以高效牌型出的
        let efficientCards = 0;
        for (const p of plan) {
            switch (p.type) {
                case 'straight': efficientCards += (p.length || 5); break;
                case 'doubleStraight': efficientCards += (p.length || 3) * 2; break;
                case 'plane': efficientCards += (p.length || 2) * 3; break;
                case 'bomb': case 'rocket': efficientCards += 1; break; // 炸弹用不掉牌
                default: break;
            }
        }
        score += efficientCards * 5;

        // 惩罚剩余的单张（尤其是小王、2等本应成对的拆成了单）
        let singleCount = 0;
        for (const p of plan) {
            if (p.type === 'single') singleCount++;
        }
        score -= singleCount * 8;

        // 惩罚多余的孤对（单/对越多说明牌越散）
        let pairCount = 0;
        for (const p of plan) {
            if (p.type === 'pair') pairCount++;
        }
        score -= pairCount * 3;

        // 奖励有炸弹的牌型（保留炸弹）
        let bombCount = 0;
        for (const p of plan) {
            if (p.type === 'bomb' || p.type === 'rocket') bombCount++;
        }
        score += bombCount * 8;

        // 惩罚方案中需要使用炸弹/火箭（说明牌不够整齐）
        // 炸弹用越多扣分越多，使非炸弹方案更优
        score -= bombCount * 5;

        return score;
    },

    /**
     * 生成所有可能的出牌方案（多策略）
     */
    generatePlans(analysis) {
        const plans = [];
        const { singles, pairs, triples, quads, hand } = analysis;

        // ---------- 通用查找 ----------
        const straights = this.findStraights(hand, 5);
        const doubleStraights = [];
        const maxPairs = Math.floor(hand.length / 2);
        for (let len = 3; len <= maxPairs; len++) {
            doubleStraights.push(...this.findDoubleStraights(hand, len));
        }
        const planes = this.findPlanes(hand);
        const tripleWithPairs = this.findTripleWithPair(hand);
        const tripleWithOnes = this.findTripleWithOne(hand);
        const bombs = this.findBombs(hand);
        const rocket = this.findRocket(hand);

        // ---- 策略1: 贪心优先出长牌型（原方案改进版） ----
        plans.push(this._buildGreedyPlan(hand, straights, doubleStraights, planes,
            tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket));

        // ---- 策略2: 顺子优先（优先组合所有可能的顺子） ----
        plans.push(this._buildStraightFirstPlan(hand, straights, doubleStraights, planes,
            tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket));

        // ---- 策略3: 三带优先（优先消化掉多余的3条和单张） ----
        plans.push(this._buildTripleFirstPlan(hand, straights, doubleStraights, planes,
            tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket));

        // ---- 策略4: 炸弹主动使用（适合大牌多的情况） ----
        plans.push(this._buildBombActivePlan(hand, straights, doubleStraights, planes,
            tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket));

        // ---- 保底方案 ----
        const allSingles = hand.map(c => ({ type: 'single', value: getCardValue(c) }));
        plans.push(allSingles);

        return plans;
    },

    /** 策略1: 贪心方案（原算法改进） */
    _buildGreedyPlan(hand, straights, doubleStraights, planes,
                    tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket) {
        const plan = [];
        const usedCards = new Set();

        const useCards = (cards) => cards.forEach(c => usedCards.add(c));
        const isFree = (cards) => cards.every(c => !usedCards.has(c));

        // 1. 顺子（最长的优先，但不霸占所有牌）
        const sortedStraights = [...straights].sort((a, b) => b.cards.length - a.cards.length);
        for (const s of sortedStraights) {
            if (isFree(s.cards)) {
                plan.push(s.pattern); useCards(s.cards);
            }
        }

        // 2. 连对
        for (const ds of doubleStraights) {
            if (isFree(ds.cards)) {
                plan.push(ds.pattern); useCards(ds.cards);
            }
        }

        // 3. 飞机
        for (const p of planes) {
            if (isFree(p.cards)) {
                plan.push(p.pattern); useCards(p.cards);
            }
        }

        // 4. 三带二
        for (const tp of tripleWithPairs) {
            if (isFree(tp.cards)) {
                plan.push(tp.pattern); useCards(tp.cards);
            }
        }

        // 5. 三带一
        for (const to of tripleWithOnes) {
            if (isFree(to.cards)) {
                plan.push(to.pattern); useCards(to.cards);
            }
        }

        // 6. 三条（剩余的三条）
        for (const t of triples) {
            if (isFree(t.cards)) {
                // 能带对就先带对，不能就带单
                const availPair = pairs.find(p => isFree(p.cards) && p.value !== t.value && !usedCards.has(p.cards[0]));
                if (availPair) {
                    plan.push({ type: 'tripleWithPair', value: t.value });
                    useCards([...t.cards, ...availPair.cards]);
                } else {
                    const availSingle = singles.find(s => isFree(s.cards) && s.value !== t.value);
                    if (availSingle) {
                        plan.push({ type: 'tripleWithOne', value: t.value });
                        useCards([...t.cards, ...availSingle.cards]);
                    } else {
                        plan.push({ type: 'triple', value: t.value });
                        useCards(t.cards);
                    }
                }
            }
        }

        // 7. 对子
        for (const p of pairs) {
            if (isFree(p.cards)) {
                plan.push({ type: 'pair', value: p.value });
                useCards(p.cards);
            }
        }

        // 8. 单张
        for (const s of singles) {
            if (isFree(s.cards)) {
                plan.push({ type: 'single', value: s.value });
                useCards(s.cards);
            }
        }

        // 9. 炸弹和火箭（最后出）
        for (const b of bombs) {
            plan.push(b.pattern);
        }
        if (rocket) {
            plan.push(rocket.pattern);
        }

        return plan;
    },

    /** 策略2: 顺子优先 */
    _buildStraightFirstPlan(hand, straights, doubleStraights, planes,
                           tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket) {
        const plan = [];
        const usedCards = new Set();

        const isFree = (cards) => cards.every(c => !usedCards.has(c));
        const addToUsed = (cards) => cards.forEach(c => usedCards.add(c));

        // 1. 尽量多地安排顺子，即使短一些也接受（长度5的最低标准）
        if (straights.length > 0) {
            const sortedStrs = [...straights].sort((a, b) => b.cards.length - a.cards.length);
            for (const s of sortedStrs) {
                const fresh = s.cards.every(c => !usedCards.has(c));
                if (fresh) {
                    plan.push(s.pattern);
                    addToUsed(s.cards);
                }
            }
        }

        // 2. 连对
        for (const ds of doubleStraights) {
            if (isFree(ds.cards)) { plan.push(ds.pattern); addToUsed(ds.cards); }
        }

        // 3. 飞机
        for (const pl of planes) {
            if (isFree(pl.cards)) { plan.push(pl.pattern); addToUsed(pl.cards); }
        }

        // 4-6. 三带、对子、单张
        this._addRemainingToPlan(plan, usedCards, tripleWithPairs, tripleWithOnes, triples, pairs, singles, bombs, rocket);
        return plan;
    },

    /** 策略3: 三带优先 */
    _buildTripleFirstPlan(hand, straights, doubleStraights, planes,
                         tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket) {
        const plan = [];
        const usedCards = new Set();
        const isFree = (cards) => cards.every(c => !usedCards.has(c));
        const addToUsed = (cards) => cards.forEach(c => usedCards.add(c));

        // 1. 先处理三带（消耗3条+单/对）
        const processedTriples = new Set();
        for (const tp of tripleWithPairs) {
            if (isFree(tp.cards) && !processedTriples.has(tp.pattern.value)) {
                plan.push(tp.pattern); addToUsed(tp.cards);
                processedTriples.add(tp.pattern.value);
            }
        }
        for (const to of tripleWithOnes) {
            if (isFree(to.cards) && !processedTriples.has(to.pattern.value)) {
                plan.push(to.pattern); addToUsed(to.cards);
                processedTriples.add(to.pattern.value);
            }
        }
        for (const t of triples) {
            if (isFree(t.cards) && !processedTriples.has(t.value)) {
                plan.push({ type: 'triple', value: t.value }); addToUsed(t.cards);
            }
        }

        // 2. 飞机
        for (const pl of planes) {
            if (isFree(pl.cards)) { plan.push(pl.pattern); addToUsed(pl.cards); }
        }

        // 3. 顺子
        const sortedStrs = [...straights].sort((a, b) => b.cards.length - a.cards.length);
        for (const s of sortedStrs) {
            if (isFree(s.cards)) { plan.push(s.pattern); addToUsed(s.cards); }
        }

        // 4. 连对
        for (const ds of doubleStraights) {
            if (isFree(ds.cards)) { plan.push(ds.pattern); addToUsed(ds.cards); }
        }

        // 5. 对子、单张、炸弹、火箭
        this._addRemainingToPlan(plan, usedCards, [], [], [], pairs, singles, bombs, rocket);
        return plan;
    },

    /** 策略4: 炸弹积极使用 */
    _buildBombActivePlan(hand, straights, doubleStraights, planes,
                        tripleWithPairs, tripleWithOnes, singles, pairs, triples, bombs, rocket) {
        // 这个策略与贪心方案类似，但炸弹不会排到最后
        const plan = [];
        const usedCards = new Set();
        const isFree = (cards) => cards.every(c => !usedCards.has(c));
        const addToUsed = (cards) => cards.forEach(c => usedCards.add(c));

        if (rocket && isFree(rocket.cards)) { plan.push(rocket.pattern); addToUsed(rocket.cards); }

        const sortedStrs = [...straights].sort((a, b) => b.cards.length - a.cards.length);
        for (const s of sortedStrs) {
            if (isFree(s.cards)) { plan.push(s.pattern); addToUsed(s.cards); }
        }
        for (const ds of doubleStraights) {
            if (isFree(ds.cards)) { plan.push(ds.pattern); addToUsed(ds.cards); }
        }
        for (const pl of planes) {
            if (isFree(pl.cards)) { plan.push(pl.pattern); addToUsed(pl.cards); }
        }

        // 炸弹提前出（减少后续手数）
        for (const b of bombs) {
            plan.push(b.pattern);
        }

        // 三带、对子、单张
        this._addRemainingToPlan(plan, usedCards, tripleWithPairs, tripleWithOnes, triples, pairs, singles, []);
        return plan;
    },

    /** 将剩余的牌（三带、对子、单张、炸弹）加入方案 */
    _addRemainingToPlan(plan, usedCards, tripleWithPairs, tripleWithOnes, triples, pairs, singles, bombs, rocket) {
        const isFree = (cards) => cards.every(c => !usedCards.has(c));
        const addToUsed = (cards) => cards.forEach(c => usedCards.add(c));

        const processedTriples = new Set();
        for (const tp of tripleWithPairs) {
            if (isFree(tp.cards) && !processedTriples.has(tp.pattern.value)) {
                plan.push(tp.pattern); addToUsed(tp.cards);
                processedTriples.add(tp.pattern.value);
            }
        }
        for (const to of tripleWithOnes) {
            if (isFree(to.cards) && !processedTriples.has(to.pattern.value)) {
                plan.push(to.pattern); addToUsed(to.cards);
                processedTriples.add(to.pattern.value);
            }
        }
        for (const t of triples) {
            if (isFree(t.cards) && !processedTriples.has(t.value)) {
                plan.push({ type: 'triple', value: t.value }); addToUsed(t.cards);
            }
        }
        for (const p of pairs) {
            if (isFree(p.cards)) {
                plan.push({ type: 'pair', value: p.value }); addToUsed(p.cards);
            }
        }
        for (const s of singles) {
            if (isFree(s.cards)) {
                plan.push({ type: 'single', value: s.value }); addToUsed(s.cards);
            }
        }
        // 炸弹和火箭排最后，不轻易使用
        for (const b of bombs) {
            plan.push(b.pattern);
        }
        if (rocket) {
            plan.push(rocket.pattern);
        }
    },

    // ========== 一次出完检测 ==========
    findOneShot(hand) {
        if (hand.length === 0) return null;

        const firstVal = getCardValue(hand[0]);
        if (hand.every(c => getCardValue(c) === firstVal)) {
            if (hand.length === 4) return { cards: [...hand], pattern: { type: 'bomb', value: firstVal } };
            if (hand.length === 3) return { cards: [...hand], pattern: { type: 'triple', value: firstVal } };
            if (hand.length === 2) return { cards: [...hand], pattern: { type: 'pair', value: firstVal } };
        }

        // 火箭
        if (hand.length === 2 && hand[0].isJoker && hand[1].isJoker) {
            return { cards: [...hand], pattern: { type: 'rocket', value: 100 } };
        }

        // 顺子
        if (hand.length >= 5) {
            const values = hand.map(c => getCardValue(c)).sort((a, b) => a - b);
            if (values[values.length - 1] <= 14) {
                let isStraight = true;
                for (let i = 1; i < values.length; i++) {
                    if (values[i] !== values[i-1] + 1) { isStraight = false; break; }
                }
                if (isStraight && new Set(values).size === values.length) {
                    return { cards: [...hand], pattern: { type: 'straight', value: values[0], length: values.length } };
                }
            }
        }

        // 连对（6张起，偶数）
        if (hand.length >= 6 && hand.length % 2 === 0) {
            const pattern = ClassicPatterns.getPattern(hand);
            if (pattern && pattern.type === 'doubleStraight') {
                return { cards: [...hand], pattern };
            }
        }

        // 飞机
        if (hand.length >= 6 && hand.length % 3 === 0) {
            const pattern = ClassicPatterns.getPattern(hand);
            if (pattern && pattern.type === 'plane') {
                return { cards: [...hand], pattern };
            }
        }

        return null;
    },

    /** 安全兜底：出错时的最简出牌方案 */
    _safeFallback(hand, lastPlay) {
        try {
            if (!hand || hand.length === 0) return null;
            if (!lastPlay) {
                // 自由出牌：出最小的牌
                const sorted = [...hand].sort((a, b) => getCardValue(a) - getCardValue(b));
                return { cards: [sorted[0]], pattern: { type: 'single', value: getCardValue(sorted[0]) } };
            }
            // 跟牌：尝试出比 lastPlay 大的单张
            const lastVal = lastPlay.value || 0;
            for (const card of hand) {
                const v = getCardValue(card);
                if (v > lastVal) {
                    return { cards: [card], pattern: { type: 'single', value: v } };
                }
            }
            return null;
        } catch (e) {
            console.error('[AI] _safeFallback error:', e);
            return null;
        }
    },

    // ========== 自由出牌（AI主动） ==========
    freePlay(hand, playerIndex, gameState) {
        try {
            CardMemory.update(gameState);
            const players = gameState.players;
            if (!players || playerIndex === undefined || playerIndex < 0 || playerIndex >= players.length) {
                return this._safeFallback(hand, null);
            }
            const currentPlayer = players[playerIndex];
            if (!currentPlayer) return this._safeFallback(hand, null);

            const isLandlord = currentPlayer.isLandlord || false;
            const allInCards = hand.length;

        // ---- 1. 一手出完 ----
        const oneShot = this.findOneShot(hand);
        if (oneShot) return oneShot;

        // ---- 2. 剩余1-3张出牌策略 ----
        if (allInCards <= 3) {
            return this._endGamePlay(hand, isLandlord);
        }

            // ---- 3. 计算最优出牌方案 ----
            const { plan } = this.calcMinRounds(hand);

            // ---- 4. 角色差异化策略 ----
            if (isLandlord) {
                return this._landlordFreePlay(hand, playerIndex, plan);
            } else {
                return this._farmerFreePlay(hand, playerIndex, plan);
            }
        } catch (e) {
            console.error('[AI] freePlay error:', e);
            return this._safeFallback(hand, null);
        }
    },

    /** 残局（≤3张）出牌策略 */
    _endGamePlay(hand, isLandlord) {
        const vals = hand.map(c => getCardValue(c));

        if (hand.length === 1) {
            return { cards: [hand[0]], pattern: { type: 'single', value: vals[0] } };
        }
        if (hand.length === 2) {
            // 成对就先出对
            if (vals[0] === vals[1]) {
                return { cards: [...hand], pattern: { type: 'pair', value: vals[0] } };
            }
            // 大小王 → 火箭
            if (hand[0].isJoker && hand[1].isJoker) {
                return { cards: [...hand], pattern: { type: 'rocket', value: 100 } };
            }
            // 不同值：出小的单张，留大的
            const sorted = [...hand].sort((a, b) => getCardValue(a) - getCardValue(b));
            return { cards: [sorted[0]], pattern: { type: 'single', value: getCardValue(sorted[0]) } };
        }
        if (hand.length === 3) {
            // 三条直接出完
            if (vals[0] === vals[1] && vals[1] === vals[2]) {
                return { cards: [...hand], pattern: { type: 'triple', value: vals[0] } };
            }
            // 有对子+单张：先出对子
            const groups = {};
            hand.forEach(c => {
                const v = getCardValue(c);
                if (!groups[v]) groups[v] = [];
                groups[v].push(c);
            });
            for (const cards of Object.values(groups)) {
                if (cards.length === 2) {
                    return { cards, pattern: { type: 'pair', value: getCardValue(cards[0]) } };
                }
            }
            // 全单：最小的先出
            const sorted = [...hand].sort((a, b) => getCardValue(a) - getCardValue(b));
            return { cards: [sorted[0]], pattern: { type: 'single', value: getCardValue(sorted[0]) } };
        }
    },

    /** 地主自由出牌 */
    _landlordFreePlay(hand, playerIndex, plan) {
        // 地主策略：快速减少手数，不放过任何出牌机会
        // 但不轻易出炸弹/火箭，除非只剩最后一手
        const noBombPlan = plan.filter(function(p) {
            return p.type !== 'bomb' && p.type !== 'rocket';
        });

        if (noBombPlan.length > 0 && noBombPlan.length <= 4) {
            const nextPattern = noBombPlan[0];
            const play = this.findPlayByPattern(hand, nextPattern);
            if (play) return play;
        } else if (plan.length > 0 && plan.length <= 4) {
            // 只剩炸弹/火箭可用
            const nextPattern = plan[0];
            const play = this.findPlayByPattern(hand, nextPattern);
            if (play) return play;
        }

        // 牌数较多（>12张）：先打小单试探，保留大牌控制权
        if (hand.length > 12) {
            const analysis = this.analyzeHand(hand);
            const midSingle = analysis.singles.find(s => s.value >= 7 && s.value <= 11);
            if (midSingle) {
                return { cards: [midSingle.cards[0]], pattern: { type: 'single', value: midSingle.value } };
            }
        }

        // 优先出牌型方案的第一手（非炸弹火箭）
        if (noBombPlan.length > 0) {
            const nextPattern = noBombPlan[0];
            const play = this.findPlayByPattern(hand, nextPattern);
            if (play) return play;
        }

        return this.playSmallestNonBomb(hand);
    },

    /** 农民自由出牌 */
    _farmerFreePlay(hand, playerIndex, plan) {
        // 农民策略：先垫小牌，保留大牌给地主
        const teammateCards = CardMemory.teammateCards(playerIndex);

        // 送队友：队友牌少时，出可接的牌型
        if (teammateCards <= 3) {
            const analysis = this.analyzeHand(hand);
            if (analysis.singles.length > 0) {
                const validSingles = analysis.singles.filter(s => s.value <= 10);
                const target = validSingles.length > 0 ? validSingles[0] : analysis.singles[0];
                return { cards: [target.cards[0]], pattern: { type: 'single', value: target.value } };
            }
            if (analysis.pairs.length > 0) {
                return {
                    cards: [...analysis.pairs[0].cards],
                    pattern: { type: 'pair', value: analysis.pairs[0].value }
                };
            }
        }

        // 农民不出炸弹/火箭（除非只剩炸弹）
        const noBombPlan = plan.filter(function(p) {
            return p.type !== 'bomb' && p.type !== 'rocket';
        });

        if (noBombPlan.length > 0 && noBombPlan.length <= 5) {
            const nextPattern = noBombPlan[0];
            const play = this.findPlayByPattern(hand, nextPattern);
            if (play) return play;
        }

        // 默认：出最小牌（非关键牌）
        return this.playSmallestNonBomb(hand);
    },

    // ========== 跟牌决策（核心） ==========
    findPlayableCards(hand, lastPlay, playerIndex, gameState) {
        try {
            // ---- 参数兼容（auto-play模式可能不传 playerIndex/gameState） ----
            if (playerIndex === undefined || !gameState) {
                return this._simpleFollow(hand, lastPlay);
            }

            CardMemory.update(gameState);

            // ---- 安全检查 ----
            if (!gameState.players || playerIndex < 0 || playerIndex >= gameState.players.length) {
                console.error('[AI] Invalid playerIndex:', playerIndex);
                return this._simpleFollow(hand, lastPlay);
            }

            const currentPlayer = gameState.players[playerIndex];
            if (!currentPlayer) {
                console.error('[AI] currentPlayer is null');
                return this._simpleFollow(hand, lastPlay);
            }

            const isLandlord = currentPlayer.isLandlord || false;
            const lastPlayer = (typeof gameState.lastPlayer === 'number') ? gameState.lastPlayer : -1;

            // ---- 自由出牌（上家没有可压的对象） ----
            if (!lastPlay || lastPlayer === playerIndex || lastPlayer === -1) {
                return this.freePlay(hand, playerIndex, gameState);
            }

            // ---- 跟牌决策 ----
            // 安全检查：lastPlayer 必须在 player 范围内
            let lastPlayerIsLandlord = false;
            if (lastPlayer >= 0 && lastPlayer < gameState.players.length && gameState.players[lastPlayer]) {
                lastPlayerIsLandlord = gameState.players[lastPlayer].isLandlord || false;
            } else {
                // lastPlayer 不合法，按自由出牌处理
                return this.freePlay(hand, playerIndex, gameState);
            }

            // ===== 情景A: 农民需要跟的是地主的牌 =====
            if (!isLandlord && lastPlayerIsLandlord) {
                return this._farmerBlock(hand, lastPlay, playerIndex, CardMemory.landlordCards());
            }

            // ===== 情景B: 地主需要跟农民的牌 =====
            if (isLandlord && !lastPlayerIsLandlord) {
                return this._landlordDefend(hand, lastPlay, CardMemory.landlordCards());
            }

            // ===== 情景C: 农民需要跟队友的牌 → 放行 =====
            if (!isLandlord && !lastPlayerIsLandlord) {
                return this._farmerYield(hand, lastPlay, playerIndex, CardMemory.landlordCards());
            }

            // 保底
            const plays = this._findMatchingPlays(hand, lastPlay);
            if (plays.length > 0) {
                plays.sort((a, b) => {
                    const aScore = this._cardValueRank(a.pattern.value);
                    const bScore = this._cardValueRank(b.pattern.value);
                    return aScore - bScore;
                });
                return plays[0];
            }
            return null;
        } catch (e) {
            console.error('[AI Error] findPlayableCards:', e);
            // 出错时走最简单安全的出牌逻辑
            return this._safeFallback(hand, lastPlay);
        }
    },

    /**
     * 农民顶牌（跟地主）
     * 核心原则：用够用的牌顶，不浪费大牌，但地主快赢时必须全力阻止
     */
    _farmerBlock(hand, lastPlay, playerIndex, landlordCards) {
        const sameTypePlays = this._findMatchingPlays(hand, lastPlay);
        const pressure = CardMemory.landlordPressure(); // 提前声明，整个函数作用域可用

        if (sameTypePlays.length > 0) {
            if (pressure >= 80) {
                // ⚠️ 地主即将胜利 → 必须全力阻止，用最小的可压牌
                sameTypePlays.sort((a, b) => a.pattern.value - b.pattern.value);
                const play = sameTypePlays[0];
                return this._assessBombNeeded(hand, lastPlay, playerIndex, play, pressure);
            }

            if (pressure >= 60) {
                // 地主牌较少 → 需要适度顶大牌
                // 用中等偏大的牌（刚好比地主的牌大2~4点）
                const modestPlays = sameTypePlays.filter(p =>
                    p.pattern.value <= lastPlay.value + 4 && p.pattern.value <= 14
                );
                if (modestPlays.length > 0) {
                    modestPlays.sort((a, b) => a.pattern.value - b.pattern.value);
                    return modestPlays[0];
                }
                // 没有合适的 → 用最小的
                sameTypePlays.sort((a, b) => a.pattern.value - b.pattern.value);
                return sameTypePlays[0];
            }

            // 常规防守：用刚好大一点的牌顶（保留2/A）
            const efficientPlays = sameTypePlays.filter(p => p.pattern.value <= 14); // 不使用2/王
            if (efficientPlays.length > 0) {
                efficientPlays.sort((a, b) => a.pattern.value - b.pattern.value);
                return efficientPlays[0];
            }

            // 不得不用2/A等大牌
            sameTypePlays.sort((a, b) => a.pattern.value - b.pattern.value);
            return sameTypePlays[0];
        }

        // ---- 没有同类型可压 ----
        // 尝试拆牌跟（拆对子跟单张、拆三条跟对子）
        const splitted = this._findSplittablePlay(hand, lastPlay);
        if (splitted) return splitted;

        // 地主快赢才考虑炸弹
        if (pressure >= 70) {
            return this._assessBombNeeded(hand, lastPlay, playerIndex, null, pressure);
        }

        // 队友牌很少时，考虑放行让队友接
        const teammateCards = CardMemory.teammateCards(playerIndex);
        if (teammateCards <= 3 && landlordCards > 3) {
            return null; // 放行，让队友接
        }

        // 地主牌很少 → 看看有没有炸弹
        if (landlordCards <= 3) {
            return this._assessBombNeeded(hand, lastPlay, playerIndex, null, 80);
        }

        return null; // 过
    },

    /** 地主防守（跟农民） */
    _landlordDefend(hand, lastPlay, landlordCards) {
        const sameTypePlays = this._findMatchingPlays(hand, lastPlay);

        if (sameTypePlays.length > 0) {
            // 地主剩余较多（>8张）：用最小的压，节省大牌
            if (landlordCards > 8) {
                // 不用2+王去压小牌
                const economical = sameTypePlays.filter(p => {
                    // 对单张7以下，不用2（15）去压
                    if (lastPlay.type === 'single' && lastPlay.value <= 7) {
                        return p.pattern.value <= 13; // ≤K
                    }
                    return true;
                });
                const candidates = economical.length > 0 ? economical : sameTypePlays;
                candidates.sort((a, b) => a.pattern.value - b.pattern.value);
                return candidates[0];
            }

            // 地主剩余较少（≤8张）：手数优先，直接压最小的
            sameTypePlays.sort((a, b) => a.pattern.value - b.pattern.value);
            return sameTypePlays[0];
        }

        // 没同类型 → 考虑拆牌
        const splitted = this._findSplittablePlay(hand, lastPlay);
        if (splitted) return splitted;

        // 手牌不多时果断用炸弹
        if (landlordCards <= 6) {
            const bombs = this.findBombs(hand);
            if (bombs.length > 0) {
                if (lastPlay.type === 'bomb') {
                    const bigger = bombs.filter(b => b.pattern.value > lastPlay.value);
                    return bigger.length > 0 ? bigger[0] : null;
                }
                return bombs[0];
            }
        }

        return null;
    },

    /** 农民放牌（跟队友） - 尽量不出，或出最小的 */
    _farmerYield(hand, lastPlay, playerIndex, landlordCards) {
        const teammateCards = CardMemory.teammateCards(playerIndex);

        // 队友只剩1-2张 → 必须放行
        if (teammateCards <= 2) {
            // 除非地主也快赢了而且队友牌太小
            if (landlordCards <= 2 && lastPlay.value <= 10) {
                // 需要帮队友挡住地主
                return this._farmerBlock(hand, lastPlay, playerIndex, landlordCards);
            }
            return null;
        }

        // 队友还剩3张 → 除非地主快赢，否则放行
        if (teammateCards <= 3) {
            if (landlordCards <= 2) {
                // 地主快赢，需要帮忙拦一下 → 用最小的压
                return this._farmerBlock(hand, lastPlay, playerIndex, landlordCards);
            }
            return null;
        }

        // 一般情况：用最小的牌压（让队友省大牌）
        const sameTypePlays = this._findMatchingPlays(hand, lastPlay);
        if (sameTypePlays.length > 0) {
            // 尽量用小牌接（只比队友大1点）
            const minPlay = sameTypePlays.reduce((min, p) =>
                p.pattern.value < min.pattern.value ? p : min
            );
            // 如果最小的都比队友大很多（>6点），就不要接了（浪费牌）
            if (minPlay.pattern.value - lastPlay.value >= 6) {
                // 地主牌少时才接
                if (landlordCards <= 4) return minPlay;
                return null;
            }
            // 用刚大一点的牌接
            const closePlays = sameTypePlays.filter(p =>
                p.pattern.value - lastPlay.value <= 3
            );
            if (closePlays.length > 0) {
                closePlays.sort((a, b) => a.pattern.value - b.pattern.value);
                return closePlays[0];
            }
            return minPlay;
        }

        return null;
    },

    // ========== 炸弹使用评估 ==========
    _assessBombNeeded(hand, lastPlay, playerIndex, fallbackPlay, pressure) {
        // 飞弹（火箭）已经出了 → 不能炸
        if (lastPlay.type === 'rocket') return fallbackPlay;

        const bombs = this.findBombs(hand);
        if (bombs.length === 0) return fallbackPlay;

        const landlordCards = CardMemory.landlordCards();
        const myCards = hand.length;

        // 必须炸的情况：
        // 1. 地主只剩1张且对方在出单
        if (landlordCards <= 1) {
            if (lastPlay.type === 'single') {
                return this._pickBestBomb(bombs, lastPlay);
            }
        }

        // 2. 地主只剩2张且对方在出对
        if (landlordCards <= 2 && lastPlay.type === 'pair') {
            return this._pickBestBomb(bombs, lastPlay);
        }

        // 3. 地主只剩3张
        if (landlordCards <= 3 && pressure >= 80) {
            return this._pickBestBomb(bombs, lastPlay);
        }

        // 4. 自己手数很少（≤4手）+ 能炸完
        if (myCards <= 6 && pressure >= 60) {
            const { needRound } = this.calcMinRounds(hand);
            if (needRound <= 2) {
                return this._pickBestBomb(bombs, lastPlay);
            }
        }

        // 默认不用炸弹
        return fallbackPlay;
    },

    _pickBestBomb(bombs, lastPlay) {
        if (lastPlay.type === 'bomb') {
            const bigger = bombs.filter(b => b.pattern.value > lastPlay.value);
            if (bigger.length > 0) return bigger[0];
            // 自己有火箭或更大的炸弹
            const rocket = this.findRocket([]);
            return null; // 没有能压的炸弹
        }
        // 选最小的炸弹
        bombs.sort((a, b) => a.pattern.value - b.pattern.value);
        return bombs[0];
    },

    // ========== 拆牌跟牌 ==========
    /**
     * 尝试通过拆解对子/三条来跟牌
     */
    _findSplittablePlay(hand, lastPlay) {
        if (!lastPlay) return null;

        switch (lastPlay.type) {
            case 'single': {
                // 拆对子跟单张
                const pairs = this.findPairs(hand);
                const splittable = pairs.filter(p => p.pattern.value > lastPlay.value);
                if (splittable.length > 0) {
                    const target = splittable.sort((a, b) => a.pattern.value - b.pattern.value)[0];
                    return { cards: [target.cards[0]], pattern: { type: 'single', value: target.pattern.value } };
                }
                // 拆三条跟单张
                const triples = this.findTriples(hand);
                const splittableTriples = triples.filter(t => t.pattern.value > lastPlay.value);
                if (splittableTriples.length > 0) {
                    const target = splittableTriples.sort((a, b) => a.pattern.value - b.pattern.value)[0];
                    return { cards: [target.cards[0]], pattern: { type: 'single', value: target.pattern.value } };
                }
                // 拆炸弹跟单张（万不得已）
                const bombs = this.findBombs(hand);
                const splittableBombs = bombs.filter(b => b.pattern.value > lastPlay.value);
                if (splittableBombs.length > 0 && hand.length <= 4) {
                    // 只剩炸弹时可以拆
                    const target = splittableBombs.sort((a, b) => a.pattern.value - b.pattern.value)[0];
                    return { cards: [target.cards[0]], pattern: { type: 'single', value: target.pattern.value } };
                }
                break;
            }

            case 'pair': {
                // 拆三条跟对子
                const triples = this.findTriples(hand);
                const splittable = triples.filter(t => t.pattern.value > lastPlay.value);
                if (splittable.length > 0) {
                    const target = splittable.sort((a, b) => a.pattern.value - b.pattern.value)[0];
                    return { cards: target.cards.slice(0, 2), pattern: { type: 'pair', value: target.pattern.value } };
                }
                // 拆炸弹跟对子
                const bombs = this.findBombs(hand);
                const splittableBombs = bombs.filter(b => b.pattern.value > lastPlay.value);
                if (splittableBombs.length > 0 && hand.length <= 6) {
                    const target = splittableBombs.sort((a, b) => a.pattern.value - b.pattern.value)[0];
                    return { cards: target.cards.slice(0, 2), pattern: { type: 'pair', value: target.pattern.value } };
                }
                break;
            }

            case 'triple': {
                // 拆炸弹跟三条
                const bombs = this.findBombs(hand);
                const splittable = bombs.filter(b => b.pattern.value > lastPlay.value);
                if (splittable.length > 0 && hand.length <= 7) {
                    const target = splittable.sort((a, b) => a.pattern.value - b.pattern.value)[0];
                    return { cards: target.cards.slice(0, 3), pattern: { type: 'triple', value: target.pattern.value } };
                }
                break;
            }
        }

        return null;
    },

    // ========== 通用跟牌查找 ==========
    /**
     * 查找所有可压的同类型牌（不拆牌）
     */
    _findMatchingPlays(hand, lastPlay) {
        const plays = [];

        switch(lastPlay.type) {
            case 'single':
                for (const card of hand) {
                    const v = getCardValue(card);
                    if (v > lastPlay.value) {
                        plays.push({ cards: [card], pattern: { type: 'single', value: v } });
                    }
                }
                this.dedupPlays(plays);
                break;
            case 'pair':
                plays.push(...this.findPairs(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'triple':
                plays.push(...this.findTriples(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'tripleWithOne':
                plays.push(...this.findTripleWithOne(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'tripleWithPair':
                plays.push(...this.findTripleWithPair(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'straight':
                plays.push(...this.findStraights(hand, lastPlay.length)
                    .filter(p => p.pattern.value > lastPlay.value && p.pattern.length === lastPlay.length));
                break;
            case 'doubleStraight':
                plays.push(...this.findDoubleStraights(hand, lastPlay.length)
                    .filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'plane':
                // 飞机跟牌比较复杂，用ClassicPatterns验证
                plays.push(...this.findPlanes(hand)
                    .filter(p => p.pattern.value > lastPlay.value && p.pattern.length === lastPlay.length));
                break;
            case 'fourWithTwoSingles':
                plays.push(...this.findFourWithTwoSingles(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'fourWithTwoPairs':
                plays.push(...this.findFourWithTwoPairs(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
            case 'bomb':
                plays.push(...this.findBombs(hand).filter(p => p.pattern.value > lastPlay.value));
                break;
        }

        // 没同类型可压 → 尝试炸弹/火箭（但需要评估）
        return plays;
    },

    /** 简易跟牌（无记牌器/无gameState时使用，用于auto-play提示） */
    _simpleFollow(hand, lastPlay) {
        if (!lastPlay) {
            return this.playSmallestNonBomb(hand);
        }
        const plays = this._findMatchingPlays(hand, lastPlay);
        if (plays.length > 0) {
            plays.sort((a, b) => a.pattern.value - b.pattern.value);
            // 一手牌策略
            if (hand.length <= 3) {
                const allInOne = plays.find(p => p.cards.length === hand.length);
                if (allInOne) return allInOne;
            }
            return plays[0];
        }
        return null;
    },

    /** 牌值等级（用于排序，分值越低越好越节省） */
    _cardValueRank(value) {
        return AI.CARD_WEIGHT[value] || value;
    },

    // ================================================================
    //  原接口函数（保持兼容）
    // ================================================================

    findPlayByPattern(hand, pattern) {
        switch(pattern.type) {
            case 'single': {
                const card = hand.find(c => getCardValue(c) === pattern.value);
                if (card) return { cards: [card], pattern };
                break;
            }
            case 'pair': {
                const cards = hand.filter(c => getCardValue(c) === pattern.value);
                if (cards.length >= 2) return { cards: cards.slice(0, 2), pattern };
                break;
            }
            case 'triple': {
                const cards = hand.filter(c => getCardValue(c) === pattern.value);
                if (cards.length >= 3) return { cards: cards.slice(0, 3), pattern };
                break;
            }
            case 'tripleWithOne': {
                const tripleCards = hand.filter(c => getCardValue(c) === pattern.value);
                const remaining = hand.filter(c => getCardValue(c) !== pattern.value);
                if (tripleCards.length >= 3 && remaining.length > 0) {
                    const kicker = remaining.sort((a, b) => getCardValue(a) - getCardValue(b))[0];
                    return { cards: [...tripleCards.slice(0, 3), kicker], pattern };
                }
                break;
            }
            case 'tripleWithPair': {
                const tripleCards = hand.filter(c => getCardValue(c) === pattern.value);
                const pairs = this.findPairs(hand).filter(p => p.pattern.value !== pattern.value);
                if (tripleCards.length >= 3 && pairs.length > 0) {
                    return { cards: [...tripleCards.slice(0, 3), ...pairs[0].cards], pattern };
                }
                break;
            }
            case 'straight': {
                const cards = [];
                for (let i = 0; i < pattern.length; i++) {
                    const targetVal = pattern.value + i;
                    const card = hand.find(c => getCardValue(c) === targetVal && !cards.includes(c));
                    if (card) cards.push(card);
                }
                if (cards.length === pattern.length) {
                    return { cards, pattern };
                }
                break;
            }
            case 'doubleStraight': {
                const cards = [];
                for (let i = 0; i < pattern.length; i++) {
                    const targetVal = pattern.value + i;
                    const pairCards = hand.filter(c => getCardValue(c) === targetVal).slice(0, 2);
                    cards.push(...pairCards);
                }
                if (cards.length === pattern.length * 2) {
                    return { cards, pattern: { ...pattern, length: pattern.length } };
                }
                break;
            }
            case 'bomb': {
                const cards = hand.filter(c => getCardValue(c) === pattern.value);
                if (cards.length === 4) {
                    return { cards, pattern };
                }
                break;
            }
            case 'rocket': {
                const jokers = hand.filter(c => c.isJoker);
                if (jokers.length === 2) {
                    return { cards: jokers, pattern };
                }
                break;
            }
            case 'fourWithTwoSingles': {
                const quadCards = hand.filter(c => getCardValue(c) === pattern.value);
                if (quadCards.length < 4) break;
                const remaining = hand.filter(c => getCardValue(c) !== pattern.value);
                if (remaining.length < 2) break;
                const kickers = remaining.sort((a, b) => getCardValue(a) - getCardValue(b)).slice(0, 2);
                return { cards: [...quadCards.slice(0, 4), ...kickers], pattern };
            }
            case 'fourWithTwoPairs': {
                const quadCards = hand.filter(c => getCardValue(c) === pattern.value);
                if (quadCards.length < 4) break;
                const pairs = this.findPairs(hand).filter(p => p.pattern.value !== pattern.value);
                if (pairs.length < 2) break;
                return { cards: [...quadCards.slice(0, 4), ...pairs[0].cards, ...pairs[1].cards], pattern };
            }
        }
        return null;
    },

    playSmallestNonBomb(hand) {
        if (!hand || hand.length === 0) return null;
        const analysis = this.analyzeHand(hand);

        // 优先出顺子
        const straights = this.findStraights(hand, 5);
        if (straights.length > 0 && hand.length > 5) {
            straights.sort((a, b) => b.cards.length - a.cards.length || a.pattern.value - b.pattern.value);
            return straights[0];
        }

        // 连对
        const doubleStraights = this.findDoubleStraights(hand, 3);
        if (doubleStraights.length > 0 && hand.length > 6) {
            // 优先找最长的连对
            for (let len = Math.floor(hand.length / 2); len >= 3; len--) {
                const allLen = this.findDoubleStraights(hand, len);
                if (allLen.length > 0) {
                    allLen.sort((a, b) => a.pattern.value - b.pattern.value);
                    return allLen[0];
                }
            }
            return doubleStraights[0];
        }

        // 三带二（用小对带）
        if (analysis.triples.length > 0 && analysis.pairs.length > 0) {
            const triple = analysis.triples[0];
            const pair = analysis.pairs.find(p => p.value !== triple.value);
            if (pair) {
                return {
                    cards: [...triple.cards, ...pair.cards],
                    pattern: { type: 'tripleWithPair', value: triple.value }
                };
            }
        }

        // 三带一
        if (analysis.triples.length > 0) {
            const triple = analysis.triples[0];
            const remaining = hand.filter(c => !triple.cards.includes(c));
            if (remaining.length > 0) {
                const kicker = remaining.sort((a, b) => getCardValue(a) - getCardValue(b))[0];
                return {
                    cards: [...triple.cards, kicker],
                    pattern: { type: 'tripleWithOne', value: triple.value }
                };
            }
            return {
                cards: [...triple.cards],
                pattern: { type: 'triple', value: triple.value }
            };
        }

        // 对子
        if (analysis.pairs.length > 0) {
            return {
                cards: [...analysis.pairs[0].cards],
                pattern: { type: 'pair', value: analysis.pairs[0].value }
            };
        }

        // 单张
        const sorted = [...hand].sort((a, b) => getCardValue(a) - getCardValue(b));
        return {
            cards: [sorted[0]],
            pattern: { type: 'single', value: getCardValue(sorted[0]) }
        };
    },

    // ========== 牌型查找工具函数 ==========
    dedupPlays(plays) {
        const seen = new Set();
        for (let i = plays.length - 1; i >= 0; i--) {
            if (plays[i].pattern.type === 'single') {
                if (seen.has(plays[i].pattern.value)) {
                    plays.splice(i, 1);
                } else {
                    seen.add(plays[i].pattern.value);
                }
            }
        }
    },

    findPairs(hand) {
        const pairs = [];
        const values = {};
        hand.forEach(card => {
            const v = getCardValue(card);
            if (!values[v]) values[v] = [];
            values[v].push(card);
        });
        for (const [v, cards] of Object.entries(values)) {
            if (cards.length >= 2) {
                pairs.push({ cards: cards.slice(0, 2), pattern: { type: 'pair', value: parseInt(v) } });
            }
        }
        return pairs.sort((a, b) => a.pattern.value - b.pattern.value);
    },

    findTriples(hand) {
        const triples = [];
        const values = {};
        hand.forEach(card => {
            const v = getCardValue(card);
            if (!values[v]) values[v] = [];
            values[v].push(card);
        });
        for (const [v, cards] of Object.entries(values)) {
            if (cards.length >= 3) {
                triples.push({ cards: cards.slice(0, 3), pattern: { type: 'triple', value: parseInt(v) } });
            }
        }
        return triples.sort((a, b) => a.pattern.value - b.pattern.value);
    },

    findTripleWithOne(hand) {
        const plays = [];
        const triples = this.findTriples(hand);
        for (const triple of triples) {
            const remaining = hand.filter(c => !triple.cards.includes(c));
            if (remaining.length > 0) {
                const sorted = remaining.sort((a, b) => getCardValue(a) - getCardValue(b));
                plays.push({
                    cards: [...triple.cards, sorted[0]],
                    pattern: { type: 'tripleWithOne', value: triple.pattern.value }
                });
            }
        }
        return plays;
    },

    findTripleWithPair(hand) {
        const plays = [];
        const triples = this.findTriples(hand);
        const pairs = this.findPairs(hand);
        for (const triple of triples) {
            for (const pair of pairs) {
                if (getCardValue(triple.cards[0]) !== getCardValue(pair.cards[0])) {
                    plays.push({
                        cards: [...triple.cards, ...pair.cards],
                        pattern: { type: 'tripleWithPair', value: triple.pattern.value }
                    });
                    break;
                }
            }
        }
        return plays;
    },

    findStraights(hand, length = 5) {
        const straights = [];
        const values = {};
        hand.forEach(card => {
            const v = getCardValue(card);
            if (v <= 14) {
                if (!values[v]) values[v] = [];
                values[v].push(card);
            }
        });

        const sortedValues = Object.keys(values).map(Number).sort((a, b) => a - b);

        for (let len = Math.max(length, 5); len <= Math.min(12, sortedValues.length); len++) {
            for (let start = 0; start <= sortedValues.length - len; start++) {
                let valid = true;
                const cards = [];
                for (let i = 0; i < len; i++) {
                    if (sortedValues[start + i] !== sortedValues[start] + i) {
                        valid = false;
                        break;
                    }
                    cards.push(values[sortedValues[start + i]][0]);
                }
                if (valid) {
                    straights.push({ cards, pattern: { type: 'straight', value: sortedValues[start], length: len } });
                }
            }
        }

        return straights;
    },

    findDoubleStraights(hand, length = 3) {
        const straights = [];
        const values = {};
        hand.forEach(card => {
            const v = getCardValue(card);
            if (v <= 14) {
                if (!values[v]) values[v] = [];
                values[v].push(card);
            }
        });

        const sortedValues = Object.keys(values)
            .filter(v => values[v].length >= 2)
            .map(Number)
            .sort((a, b) => a - b);

        for (let start = 0; start <= sortedValues.length - length; start++) {
            let valid = true;
            const cards = [];
            for (let i = 0; i < length; i++) {
                if (sortedValues[start + i] !== sortedValues[start] + i) {
                    valid = false;
                    break;
                }
                cards.push(...values[sortedValues[start + i]].slice(0, 2));
            }
            if (valid) {
                straights.push({ cards, pattern: { type: 'doubleStraight', value: sortedValues[start], length } });
            }
        }

        return straights;
    },

    findPlanes(hand) {
        const planes = [];
        const triples = this.findTriples(hand);

        if (triples.length < 2) return planes;

        for (let i = 0; i < triples.length - 1; i++) {
            const seqTriples = [triples[i]];
            for (let j = i + 1; j < triples.length; j++) {
                if (triples[j].pattern.value === seqTriples[seqTriples.length - 1].pattern.value + 1) {
                    seqTriples.push(triples[j]);
                } else {
                    break;
                }
            }

            if (seqTriples.length >= 2) {
                const cards = seqTriples.flatMap(t => t.cards);
                planes.push({
                    cards,
                    pattern: { type: 'plane', value: seqTriples[0].pattern.value, length: seqTriples.length }
                });
            }
        }

        return planes;
    },

    findBombs(hand) {
        const bombs = [];
        const values = {};
        hand.forEach(card => {
            const v = getCardValue(card);
            if (!values[v]) values[v] = [];
            values[v].push(card);
        });
        for (const [v, cards] of Object.entries(values)) {
            if (cards.length === 4) {
                bombs.push({ cards, pattern: { type: 'bomb', value: parseInt(v) } });
            }
        }
        return bombs.sort((a, b) => a.pattern.value - b.pattern.value);
    },

    findRocket(hand) {
        const jokers = hand.filter(c => c.isJoker);
        if (jokers.length === 2) {
            return { cards: jokers, pattern: { type: 'rocket', value: 100 } };
        }
        return null;
    },

    /** 找出所有可出的四带二单（6张） */
    findFourWithTwoSingles(hand) {
        const result = [];
        const quadVals = {};
        hand.forEach(c => {
            const v = getCardValue(c);
            if (!quadVals[v]) quadVals[v] = [];
            quadVals[v].push(c);
        });
        for (const [v, cards] of Object.entries(quadVals)) {
            if (cards.length < 4) continue;
            const remaining = hand.filter(c => getCardValue(c) !== parseInt(v));
            if (remaining.length < 2) continue;
            // 取最小的2张牌作为翅膀
            const kickers = remaining.sort((a, b) => getCardValue(a) - getCardValue(b)).slice(0, 2);
            result.push({
                cards: [...cards.slice(0, 4), ...kickers],
                pattern: { type: 'fourWithTwoSingles', value: parseInt(v) }
            });
        }
        return result.sort((a, b) => a.pattern.value - b.pattern.value);
    },

    /** 找出所有可出的四带二对（8张） */
    findFourWithTwoPairs(hand) {
        const result = [];
        const quadVals = {};
        hand.forEach(c => {
            const v = getCardValue(c);
            if (!quadVals[v]) quadVals[v] = [];
            quadVals[v].push(c);
        });
        for (const [v, cards] of Object.entries(quadVals)) {
            if (cards.length < 4) continue;
            const remaining = hand.filter(c => getCardValue(c) !== parseInt(v));
            const pairs = [];
            const pairVals = {};
            remaining.forEach(c => {
                const rv = getCardValue(c);
                if (!pairVals[rv]) pairVals[rv] = [];
                pairVals[rv].push(c);
            });
            for (const [, pairCards] of Object.entries(pairVals)) {
                if (pairCards.length >= 2) {
                    pairs.push(pairCards.slice(0, 2));
                }
            }
            if (pairs.length >= 2) {
                pairs.sort((a, b) => getCardValue(a[0]) - getCardValue(b[0]));
                result.push({
                    cards: [...cards.slice(0, 4), ...pairs[0], ...pairs[1]],
                    pattern: { type: 'fourWithTwoPairs', value: parseInt(v) }
                });
            }
        }
        return result.sort((a, b) => a.pattern.value - b.pattern.value);
    },

    // ========== 叫地主策略 ==========
    decideBid(hand) {
        try {
            if (!hand || hand.length === 0) return 0;

            const quality = this.evaluateHandQuality(hand);
            const bombs = this.findBombs(hand);
            const rocket = this.findRocket(hand);
            const analysis = this.analyzeHand(hand);

        // 牌型评估
        const isolatedSingles = analysis.singles.filter(s => {
            if (s.value >= 12) return false; // A、K不是问题单张
            if (s.value >= 15) return false; // 2 不是问题单张
            return true;
        }).length;

        const { needRound } = this.calcMinRounds(hand);

        // 底牌强度：计算手牌总牌力值，平均<6.5（<9左右）说明牌面太弱
        const totalCardValue = hand.reduce((sum, c) => sum + (AI.CARD_WEIGHT[getCardValue(c)] || 0), 0);
        const avgCardValue = totalCardValue / hand.length;
        const maxCardValue = hand.reduce((max, c) => Math.max(max, AI.CARD_WEIGHT[getCardValue(c)] || 0), 0);

        let score = quality;

        // 手数修正：手数越少越好
        score += (20 - needRound) * 1.5;

        // 孤牌惩罚：太多散牌就不叫
        score -= isolatedSingles * 3;

        // 底牌潜力：手牌越整齐越值得叫
        if (needRound <= 5) score += 5;
        if (needRound <= 4) score += 8;

        // 炸弹加分
        score += bombs.length * 8;
        if (rocket) score += 10;

        // 2的数量（地主需要2来控制场面）
        const twos = analysis.pairs.filter(p => p.value === 15).length * 2
            + analysis.singles.filter(s => s.value === 15).length;
        score += twos * 3;

        // 基础牌力不够时对分数上限封顶
        // 平均牌力<6.5（相当于<9）说明散牌过多、大牌极少 → 最多叫1分
        const baseStrength = quality + twos * 3 + bombs.length * 8 + (rocket ? 10 : 0);
        if (baseStrength < 15 && maxCardValue < 13) {
            score = Math.min(score, 12); // 封顶叫1分
        }
        // 完全没有2及以上大牌 → 最多叫1分
        if (maxCardValue < 12) {
            score = Math.min(score, 12);
        }

        // 根据分数决定叫牌
        if (score >= 20) return 3;   // 强牌：叫3分
        if (score >= 14) return 2;   // 中强：叫2分
        if (score >= 8) return 1;    // 弱牌：叫1分
        return 0;                     // 不叫
        } catch (e) {
            console.error('[AI] decideBid error:', e);
            return 0; // 出错了不叫
        }
    }
};
