// ==================== 牌组基础函数 ====================
function createDeck() {
    const deck = [];
    for (const suit of SUITS) {
        for (const value of CARD_VALUES) {
            deck.push({ suit, value, id: `${suit}${value}` });
        }
    }
    deck.push({ suit: '🃏', value: 'S', id: 'jokerS', isJoker: true, isSmall: true });
    deck.push({ suit: '🃏', value: 'B', id: 'jokerB', isJoker: true, isSmall: false });
    return deck;
}

function shuffleDeck(deck) {
    for (let i = deck.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [deck[i], deck[j]] = [deck[j], deck[i]];
    }
    return deck;
}

function getCardValue(card) {
    if (card.isJoker) {
        return card.isSmall ? 16 : 17;
    }
    return CARD_VALUES.indexOf(card.value) + 3;
}

function sortCards(cards) {
    return cards.sort((a, b) => getCardValue(b) - getCardValue(a));
}

// ==================== 经典模式牌型判定 ====================
const ClassicPatterns = {
    isSingle(cards) { return cards.length === 1; },

    isPair(cards) {
        return cards.length === 2 && getCardValue(cards[0]) === getCardValue(cards[1]);
    },

    isTriple(cards) {
        return cards.length === 3 &&
            getCardValue(cards[0]) === getCardValue(cards[1]) &&
            getCardValue(cards[1]) === getCardValue(cards[2]);
    },

    isTripleWithOne(cards) {
        if (cards.length !== 4) return false;
        const values = cards.map(c => getCardValue(c));
        const counts = {};
        values.forEach(v => counts[v] = (counts[v] || 0) + 1);
        return Object.values(counts).includes(3);
    },

    isTripleWithPair(cards) {
        if (cards.length !== 5) return false;
        const values = cards.map(c => getCardValue(c));
        const counts = {};
        values.forEach(v => counts[v] = (counts[v] || 0) + 1);
        const vals = Object.values(counts);
        return vals.includes(3) && vals.includes(2);
    },

    isStraight(cards) {
        if (cards.length < 5) return false;
        const values = cards.map(c => getCardValue(c)).sort((a, b) => a - b);
        if (values[values.length - 1] > 14) return false;
        for (let i = 1; i < values.length; i++) {
            if (values[i] - values[i-1] !== 1) return false;
        }
        return true;
    },

    isDoubleStraight(cards) {
        if (cards.length < 6 || cards.length % 2 !== 0) return false;
        const values = cards.map(c => getCardValue(c)).sort((a, b) => a - b);
        if (values[values.length - 1] > 14) return false;
        for (let i = 0; i < values.length; i += 2) {
            if (values[i] !== values[i+1]) return false;
            if (i > 0 && values[i] - values[i-1] !== 1) return false;
        }
        return true;
    },

    isPlane(cards) {
        if (cards.length < 6 || cards.length % 3 !== 0) return false;
        const values = cards.map(c => getCardValue(c)).sort((a, b) => a - b);
        if (values[values.length - 1] > 14) return false;
        for (let i = 0; i < values.length; i += 3) {
            if (values[i] !== values[i+1] || values[i] !== values[i+2]) return false;
            if (i > 0 && values[i] - values[i-1] !== 1) return false;
        }
        return true;
    },

    // 飞机带翅膀（单张）：如 33344456 = 2组三张 + 2张单牌
    // 飞机带翅膀（对子）：如 3334445566 = 2组三张 + 2个对子
    isPlaneWithWings(cards) {
        if (cards.length < 8) return false;

        const values = cards.map(c => getCardValue(c));
        const counts = {};
        values.forEach(v => counts[v] = (counts[v] || 0) + 1);

        // 找出所有出现3次以上的牌值（三张部分）
        const tripleVals = Object.entries(counts)
            .filter(([, v]) => v >= 3)
            .map(([k]) => parseInt(k))
            .sort((a, b) => a - b);

        if (tripleVals.length < 2) return false;

        // 遍历所有可能的连续三张组合
        for (let start = 0; start < tripleVals.length; start++) {
            for (let end = start + 1; end < tripleVals.length; end++) {
                const n = end - start + 1; // 三张组数

                // 检查是否连续
                let consecutive = true;
                for (let i = start + 1; i <= end; i++) {
                    if (tripleVals[i] - tripleVals[i - 1] !== 1) {
                        consecutive = false;
                        break;
                    }
                }
                if (!consecutive) continue;

                // 不能包含2和王
                const groupMax = tripleVals[end];
                if (groupMax > 14) continue;

                const tripleCards = n * 3;
                const remaining = cards.length - tripleCards;

                // 取当前组的连续三张值
                const inGroup = triples => triples.slice(start, end + 1);

                // === 飞机带单张：剩余张数 = n ===
                if (remaining === n) {
                    let valid = true;
                    const groupVals = inGroup(tripleVals);
                    for (const [val, cnt] of Object.entries(counts)) {
                        const v = parseInt(val);
                        if (groupVals.includes(v)) continue; // 三张部分
                        if (cnt !== 1) { valid = false; break; } // 翅膀必须是单张
                    }
                    if (valid) return true;
                }

                // === 飞机带对子：剩余张数 = n * 2 ===
                if (remaining === n * 2) {
                    let valid = true;
                    const groupVals = inGroup(tripleVals);
                    for (const [val, cnt] of Object.entries(counts)) {
                        const v = parseInt(val);
                        if (groupVals.includes(v)) continue; // 三张部分
                        if (cnt !== 2) { valid = false; break; } // 翅膀必须是对子
                    }
                    if (valid) return true;
                }
            }
        }

        return false;
    },

    isBomb(cards) {
        return cards.length === 4 &&
            getCardValue(cards[0]) === getCardValue(cards[1]) &&
            getCardValue(cards[1]) === getCardValue(cards[2]) &&
            getCardValue(cards[2]) === getCardValue(cards[3]);
    },

    isRocket(cards) {
        return cards.length === 2 && cards[0].isJoker && cards[1].isJoker;
    },

    /**
     * 四带二单：6张牌，1组4张相同 + 2张单牌
     * 例：3333+45
     */
    isFourWithTwoSingles(cards) {
        if (cards.length !== 6) return false;
        const values = cards.map(c => getCardValue(c));
        const counts = {};
        values.forEach(v => counts[v] = (counts[v] || 0) + 1);
        const vals = Object.values(counts);
        // 必须有1个值出现4次，其余2个值各出现1次（或相同值凑成2张，但这也算四带二单）
        return vals.includes(4) && vals.length >= 2;
    },

    /**
     * 四带二对：8张牌，1组4张相同 + 2个对子
     * 例：3333+4455
     */
    isFourWithTwoPairs(cards) {
        if (cards.length !== 8) return false;
        const values = cards.map(c => getCardValue(c));
        const counts = {};
        values.forEach(v => counts[v] = (counts[v] || 0) + 1);
        const vals = Object.values(counts);
        // 必须有1个值出现4次，其余的值各出现2次
        if (!vals.includes(4)) return false;
        const pairCount = vals.filter(c => c === 2).length;
        return pairCount === 2;
    },

    /**
     * 获取四带二的牌型值（即四张相同的牌值）
     */
    getFourValue(cards) {
        const values = cards.map(c => getCardValue(c));
        const counts = {};
        values.forEach(v => counts[v] = (counts[v] || 0) + 1);
        for (const [val, cnt] of Object.entries(counts)) {
            if (cnt >= 4) return parseInt(val);
        }
        return 0;
    },

    getPattern(cards) {
        if (cards.length === 0) return null;

        if (this.isRocket(cards)) return { type: 'rocket', value: 100 };
        if (this.isBomb(cards)) return { type: 'bomb', value: getCardValue(cards[0]) };
        if (this.isSingle(cards)) return { type: 'single', value: getCardValue(cards[0]) };
        if (this.isPair(cards)) return { type: 'pair', value: getCardValue(cards[0]) };
        if (this.isTriple(cards)) return { type: 'triple', value: getCardValue(cards[0]) };

        // 四带二优先于三带一/三带二（以防误判）
        if (this.isFourWithTwoPairs(cards)) {
            return { type: 'fourWithTwoPairs', value: this.getFourValue(cards) };
        }
        if (this.isFourWithTwoSingles(cards)) {
            return { type: 'fourWithTwoSingles', value: this.getFourValue(cards) };
        }

        if (this.isTripleWithOne(cards)) {
            const values = cards.map(c => getCardValue(c));
            const counts = {};
            values.forEach(v => counts[v] = (counts[v] || 0) + 1);
            const tripleValue = Object.entries(counts).find(([k, v]) => v === 3)[0];
            return { type: 'tripleWithOne', value: parseInt(tripleValue) };
        }
        if (this.isTripleWithPair(cards)) {
            const values = cards.map(c => getCardValue(c));
            const counts = {};
            values.forEach(v => counts[v] = (counts[v] || 0) + 1);
            const tripleValue = Object.entries(counts).find(([k, v]) => v === 3)[0];
            return { type: 'tripleWithPair', value: parseInt(tripleValue) };
        }
        if (this.isStraight(cards)) return { type: 'straight', value: getCardValue(cards[cards.length-1]), length: cards.length };
        if (this.isDoubleStraight(cards)) return { type: 'doubleStraight', value: getCardValue(cards[0]), length: cards.length / 2 };
        if (this.isPlane(cards)) return { type: 'plane', value: getCardValue(cards[0]), length: cards.length / 3 };
        if (this.isPlaneWithWings(cards)) {
            const values = cards.map(c => getCardValue(c));
            const counts = {};
            values.forEach(v => counts[v] = (counts[v] || 0) + 1);

            // 提取三张部分的牌值并排序
            const triples = Object.entries(counts)
                .filter(([, v]) => v >= 3)
                .map(([k]) => parseInt(k))
                .sort((a, b) => a - b);

            // 找最长的连续三张组
            let bestStart = 0, bestEnd = 0;
            for (let i = 0; i < triples.length; i++) {
                for (let j = i + 1; j < triples.length; j++) {
                    if (triples[j] - triples[j - 1] !== 1) break;
                    if (j - i > bestEnd - bestStart) { bestStart = i; bestEnd = j; }
                }
            }
            const n = bestEnd - bestStart + 1;
            if (n < 2) return null;

            const remaining = cards.length - n * 3;
            const wingType = (remaining === n * 2) ? 'pairs' : 'singles';
            return { type: 'planeWithWings', value: triples[bestEnd], length: n, wingType: wingType };
        }

        return null;
    },

    canBeat(newCards, lastPlay) {
        if (!lastPlay) return true;

        const newPattern = this.getPattern(newCards);
        if (!newPattern) return false;

        if (newPattern.type === 'rocket') return true;
        if (lastPlay.type === 'rocket') return false;

        if (newPattern.type === 'bomb' && lastPlay.type !== 'bomb') return true;

        if (newPattern.type !== lastPlay.type) return false;

        if (newPattern.length && newPattern.length !== lastPlay.length) return false;

        // 飞机带翅膀还需匹配翅膀类型（单张/对子）
        if (newPattern.type === 'planeWithWings' && newPattern.wingType !== lastPlay.wingType) return false;

        return newPattern.value > lastPlay.value;
    }
};

// 当前使用的牌型规则（可通过模式切换）
let CardPatterns = ClassicPatterns;
