/**
 * wx_mojang - AI 台词系统
 */

const AIQuotes = {
    // 默认台词（当AI配置中没有时使用）
    defaultQuotes: {
        good: ['好牌来了', '不错不错', '这局有戏', '运气不错'],
        bad: ['牌不好啊', '等等看', '有点难', '再看看'],
        win: ['胡了胡了！', '承让承让', '运气好而已', '胡牌！'],
        lose: ['厉害厉害', '下一把', '手气不错', '再来一局']
    },

    // 计时器
    timers: {},

    /**
     * 获取AI台词
     * @param {number} aiIndex - AI索引
     * @param {string} type - 类型: good/bad/win/lose
     * @param {Array} quotes - AI配置的台词列表
     * @returns {string|null}
     */
    getQuote(aiIndex, type, quotes) {
        const now = Date.now();
        const key = `${aiIndex}_${type}`;

        // 冷却检查（5秒内不重复）
        if (this.timers[key] && now - this.timers[key] < 5000) {
            return null;
        }

        const pool = quotes && quotes[type] ? quotes[type] : this.defaultQuotes[type];
        if (!pool || pool.length === 0) return null;

        // 60%概率触发
        if (Math.random() > 0.6) return null;

        const quote = pool[Math.floor(Math.random() * pool.length)];
        this.timers[key] = now;
        return quote;
    },

    /**
     * AI出牌时台词
     */
    onDiscard(aiIndex, quotes) {
        return this.getQuote(aiIndex, Math.random() > 0.5 ? 'good' : 'bad', quotes);
    },

    /**
     * AI碰/杠时台词
     */
    onMeld(aiIndex, quotes) {
        return this.getQuote(aiIndex, 'good', quotes);
    },

    /**
     * AI胡牌时台词
     */
    onWin(aiIndex, quotes) {
        return this.getQuote(aiIndex, 'win', quotes);
    },

    /**
     * AI输牌时台词
     */
    onLose(aiIndex, quotes) {
        return this.getQuote(aiIndex, 'lose', quotes);
    }
};
