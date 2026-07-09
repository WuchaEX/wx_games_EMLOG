/**
 * wx_mojang - 音效系统（Web Audio API）
 */

const AudioEngine = {
    ctx: null,

    _getCtx() {
        if (!this.ctx) {
            try {
                this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            } catch (e) {
                return null;
            }
        }
        return this.ctx;
    },

    /**
     * 播放音调
     */
    _playTone(freq, type, duration) {
        const ctx = this._getCtx();
        if (!ctx) return;
        try {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = type;
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + duration);
        } catch (e) { /* 静默失败 */ }
    },

    /** 摸牌音 */
    draw() { this._playTone(440, 'sine', 0.08); },

    /** 出牌音 */
    discard() { this._playTone(600, 'square', 0.06); },

    /** 过音 */
    pass() { this._playTone(300, 'sine', 0.12); },

    /** 碰牌音 */
    peng() { this._playTone(523, 'triangle', 0.15); setTimeout(() => this._playTone(659, 'triangle', 0.15), 120); },

    /** 吃牌音 */
    chi() { this._playTone(440, 'triangle', 0.12); setTimeout(() => this._playTone(554, 'triangle', 0.12), 100); },

    /** 杠牌音 */
    gang() {
        [440, 554, 659, 880].forEach((f, i) => {
            setTimeout(() => this._playTone(f, 'sawtooth', 0.2), i * 100);
        });
    },

    /** 胡牌音 */
    hu() {
        [523, 659, 784, 1047].forEach((f, i) => {
            setTimeout(() => this._playTone(f, 'sine', 0.3), i * 150);
        });
    },

    /** 赢牌音 */
    win() {
        [523, 659, 784, 1047, 1319].forEach((f, i) => {
            setTimeout(() => this._playTone(f, 'sine', 0.4), i * 200);
        });
    },

    /** 输牌音 */
    lose() { this._playTone(200, 'sawtooth', 0.4); },

    /** 按钮点击音 */
    click() { this._playTone(800, 'sine', 0.05); }
};
