const AudioSystem = {
    context: null,

    init() {
        try {
            this.context = new (window.AudioContext || window.webkitAudioContext)();
        } catch(e) {
            console.warn('Web Audio API not supported');
        }
    },

    play(type) {
        if (!this.context) this.init();
        if (!this.context) return;

        // Resume context if suspended (mobile browsers)
        if (this.context.state === 'suspended') {
            this.context.resume();
        }

        const oscillator = this.context.createOscillator();
        const gainNode = this.context.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(this.context.destination);

        switch(type) {
            case 'play':
                oscillator.frequency.value = 600;
                gainNode.gain.value = 0.15;
                oscillator.type = 'square';
                break;
            case 'pass':
                oscillator.frequency.value = 300;
                gainNode.gain.value = 0.1;
                oscillator.type = 'sine';
                break;
            case 'win':
                oscillator.frequency.value = 523;
                gainNode.gain.value = 0.2;
                oscillator.type = 'sine';
                setTimeout(() => this.playNote(659), 150);
                setTimeout(() => this.playNote(784), 300);
                break;
            case 'lose':
                oscillator.frequency.value = 200;
                gainNode.gain.value = 0.15;
                oscillator.type = 'sawtooth';
                break;
            case 'bomb':
                oscillator.frequency.value = 100;
                gainNode.gain.value = 0.3;
                oscillator.type = 'sawtooth';
                break;
        }

        oscillator.start();
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.context.currentTime + 0.3);
        oscillator.stop(this.context.currentTime + 0.3);
    },

    playNote(freq) {
        if (!this.context) return;
        const osc = this.context.createOscillator();
        const gain = this.context.createGain();
        osc.connect(gain);
        gain.connect(this.context.destination);
        osc.frequency.value = freq;
        gain.gain.value = 0.2;
        osc.type = 'sine';
        osc.start();
        gain.gain.exponentialRampToValueAtTime(0.01, this.context.currentTime + 0.2);
        osc.stop(this.context.currentTime + 0.2);
    }
};
