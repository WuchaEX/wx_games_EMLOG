/**
 * AI 玩家台词气泡系统
 * 
 * 触发时机：
 *   bomb     - 出炸弹
 *   rocket   - 出火箭（王炸）
 *   plane    - 出飞机
 *   straight - 出顺子（5张+）
 *   bigCard  - 出单张 2/A/王
 *   bid      - 叫地主
 *   pass     - 不出/过牌
 *   win      - 胜利
 *   lose     - 失败
 */

const QuoteSystem = {
    // 说话冷却时间（毫秒），同一 AI 两次发言至少间隔
    COOLDOWN_MS: 5000,
    // 触发概率（0-1），避免太频繁
    TRIGGER_CHANCE: 0.6,
    // 高风险事件（炸弹/火箭）必触发
    HIGH_RISK_TYPES: ['bomb', 'rocket'],

    // 每个 AI 的上次说话时间
    _lastQuoteTime: {},

    /**
     * 获取 AI 玩家的随机台词
     * @param {object} member - AI 玩家 member 对象（含 quotes）
     * @param {string} type   - 台词类型
     * @returns {string|null}
     */
    getRandomQuote(member, type) {
        if (!member || !member.quotes) return null;
        const quotes = member.quotes[type];
        if (!quotes || !Array.isArray(quotes) || quotes.length === 0) return null;
        return quotes[Math.floor(Math.random() * quotes.length)];
    },

    /**
     * 判断是否应该发言
     * @param {number} playerIndex - 玩家索引（1=左AI，2=右AI）
     * @param {string} type        - 台词类型
     * @returns {boolean}
     */
    shouldSpeak(playerIndex, type) {
        // 冷却检查
        const key = playerIndex;
        const now = Date.now();
        if (this._lastQuoteTime[key] && (now - this._lastQuoteTime[key]) < this.COOLDOWN_MS) {
            return false;
        }
        // 高优先级事件必触发
        if (this.HIGH_RISK_TYPES.indexOf(type) !== -1) {
            this._lastQuoteTime[key] = now;
            return true;
        }
        // 其他事件按概率触发
        if (Math.random() >= this.TRIGGER_CHANCE) {
            return false;
        }
        this._lastQuoteTime[key] = now;
        return true;
    },

    /**
     * 显示 AI 说话气泡
     * @param {number} playerIndex - 玩家索引（1=左AI，2=右AI）
     * @param {string} type        - 台词类型（bomb/rocket/plane/straight/bigCard/bid/pass/win/lose）
     * @param {string} [customText] - 可选，直接指定文字（不传则从 member.quotes 随机取）
     */
    speak(playerIndex, type, customText) {
        const player = gameState.players[playerIndex];
        if (!player || !player.member) return;

        const text = customText || this.getRandomQuote(player.member, type);
        if (!text) return;

        // 冷却+概率判定
        if (!customText && !this.shouldSpeak(playerIndex, type)) return;

        // 找到对应的气泡 DOM
        const prefix = playerIndex === 1 ? 'Left' : 'Right';
        const bubble = document.getElementById('speechBubble' + prefix);
        const textEl = document.getElementById('speechBubble' + prefix + 'Text');
        if (!bubble || !textEl) return;

        textEl.textContent = text;
        bubble.classList.remove('hidden');

        // 重置动画：移除再重新添加 class
        bubble.classList.remove('speech-animate');
        // 强制重排
        void bubble.offsetWidth;
        bubble.classList.add('speech-animate');

        // 移除定时器（避免重叠）
        if (bubble._hideTimer) {
            clearTimeout(bubble._hideTimer);
        }

        // 3 秒后自动隐藏
        bubble._hideTimer = setTimeout(function () {
            bubble.classList.add('hidden');
            bubble.classList.remove('speech-animate');
        }, 3000);
    },

    /**
     * 根据出牌牌型自动触发相应台词
     * @param {number} playerIndex - 玩家索引（1=左AI，2=右AI）
     * @param {array}  cards       - 打出的牌
     */
    speakByPlay(playerIndex, cards) {
        if (!cards || cards.length === 0) return;

        const player = gameState.players[playerIndex];
        if (!player || !player.member) return;

        // 检测牌型
        // 火箭：两张牌且包含小王大王
        if (cards.length === 2 && cards.indexOf(16) !== -1 && cards.indexOf(17) !== -1) {
            this.speak(playerIndex, 'rocket');
            return;
        }

        // 炸弹：4张相同
        if (cards.length === 4) {
            const val = cards[0];
            if (cards.every(function (c) { return c === val; })) {
                this.speak(playerIndex, 'bomb');
                return;
            }
        }

        // 飞机：需要调用 CardPatterns 检测
        const pattern = window.CardPatterns ? CardPatterns.getPattern(cards) : null;
        if (pattern) {
            if (pattern.type === 'plane' || pattern.type === 'plane_with_singles' || pattern.type === 'plane_with_pairs') {
                this.speak(playerIndex, 'plane');
                return;
            }
            if (pattern.type === 'straight') {
                this.speak(playerIndex, 'straight');
                return;
            }
            if (pattern.type === 'single' && cards.length === 1) {
                const v = cards[0];
                // 单张大牌：2(15), A(14), 小王(16), 大王(17)
                if (v >= 14) {
                    this.speak(playerIndex, 'bigCard');
                    return;
                }
            }
        } else if (cards.length === 1) {
            // 即使 CardPatterns 不可用，也可以检测单张大牌
            const v = cards[0];
            if (v >= 14) {
                this.speak(playerIndex, 'bigCard');
                return;
            }
        }
    }
};
