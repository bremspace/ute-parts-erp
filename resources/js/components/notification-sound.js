/**
 * Ute Parts ERP - Precision Notification Audio Synthesizer (Web Audio API)
 * Identik dengan nuansa otomotif / spare parts presisi (clean metallic diagnostic chime).
 * Zero network latency, zero MP3 404, reliable PWA/offline & mobile support.
 */

let audioCtx = null;

function getAudioContext() {
    if (!audioCtx) {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (AudioContextClass) {
            audioCtx = new AudioContextClass();
        }
    }
    return audioCtx;
}

// Unlock audio on first user gesture (touch/click/keydown)
['click', 'touchstart', 'keydown'].forEach((evt) => {
    window.addEventListener(evt, () => {
        const ctx = getAudioContext();
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
    }, { once: true, passive: true });
});

export function isUteNotificationSoundEnabled() {
    try {
        return localStorage.getItem('ute-notification-sound') !== 'muted';
    } catch (e) {
        return true;
    }
}

export function toggleUteNotificationSound() {
    const current = isUteNotificationSoundEnabled();
    const next = !current;
    try {
        localStorage.setItem('ute-notification-sound', next ? 'enabled' : 'muted');
    } catch (e) {}
    return next;
}

/**
 * Main sound synthesizer for Ute Parts
 * @param {'default'|'urgent'|'warning'|'error'|'success'|'info'|'approval'} type
 */
export function playUteNotificationSound(type = 'default') {
    if (!isUteNotificationSoundEnabled()) return;

    try {
        const ctx = getAudioContext();
        if (!ctx) return;

        // Auto-resume if suspended (mobile Safari / Chrome policy)
        if (ctx.state === 'suspended') {
            ctx.resume().then(() => {
                playUteNotificationSound(type);
            }).catch(() => {});
            return;
        }

        const now = ctx.currentTime;

        if (type === 'urgent' || type === 'error' || type === 'warning' || type === 'approval') {
            // Ute Parts Urgent Alert: Triple high-vis diagnostic pulse (740Hz -> 880Hz -> 1046.5Hz)
            const notes = [
                { freq: 740, start: 0, dur: 0.09 },
                { freq: 880, start: 0.10, dur: 0.09 },
                { freq: 1046.5, start: 0.20, dur: 0.16 }
            ];

            notes.forEach((n) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'triangle'; // rich metallic diagnostic chime
                osc.frequency.setValueAtTime(n.freq, now + n.start);

                gain.gain.setValueAtTime(0.001, now + n.start);
                gain.gain.exponentialRampToValueAtTime(0.35, now + n.start + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.001, now + n.start + n.dur);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(now + n.start);
                osc.stop(now + n.start + n.dur);
            });
        } else if (type === 'success') {
            // Ute Parts Precision Success Chime (Bright rising triad: C5 -> E5 -> C6)
            const notes = [
                { freq: 523.25, start: 0, dur: 0.09 },
                { freq: 659.25, start: 0.08, dur: 0.10 },
                { freq: 1046.50, start: 0.18, dur: 0.28 }
            ];

            notes.forEach((n) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(n.freq, now + n.start);

                gain.gain.setValueAtTime(0.001, now + n.start);
                gain.gain.exponentialRampToValueAtTime(0.28, now + n.start + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.001, now + n.start + n.dur);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start(now + n.start);
                osc.stop(now + n.start + n.dur);
            });
        } else {
            // Default: Ute Parts Precision Dual-Harmonic Chime (D5 587.33Hz -> A5 880Hz)
            const osc1 = ctx.createOscillator();
            const osc2 = ctx.createOscillator();
            const gain = ctx.createGain();

            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now); // D5

            osc2.type = 'triangle'; // subtle metallic overtone
            osc2.frequency.setValueAtTime(880.00, now + 0.06); // A5

            gain.gain.setValueAtTime(0.001, now);
            gain.gain.exponentialRampToValueAtTime(0.30, now + 0.025);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.35);

            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(ctx.destination);

            osc1.start(now);
            osc1.stop(now + 0.35);
            osc2.start(now + 0.06);
            osc2.stop(now + 0.35);
        }
    } catch (e) {
        // Fallback silently if audio context is blocked
    }
}

// Expose globally
window.playUteNotificationSound = playUteNotificationSound;
window.toggleUteNotificationSound = toggleUteNotificationSound;
window.isUteNotificationSoundEnabled = isUteNotificationSoundEnabled;
