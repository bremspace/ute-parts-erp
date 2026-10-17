// Ute Parts - Main Application Entry Point
// PWA Service Worker Registration

import '../css/app.css';
import './theme.js'; // [T-32] Tema dark/light/auto
import './font-size.js'; // [2026] Font size switcher
import './components/format-number.js'; // [T-34] Ribuan separator input nominal
import './components/thermal-print.js'; // [T-35] Cetak thermal Bluetooth 58/80mm + fallback web print
import './components/barcode-scanner.js'; // Barcode scanner engine (hardware keyboard wedge + web mobile camera)
import './components/notification-sound.js'; // [2026] Precision Web Audio notification synthesizer

// PWA Service Worker — SATU-SATUNYA registrasi via VitePWA (generateSW, /build/sw.js).
// Registrasi manual lama `/sw.js` (404) dihapus; virtual module ini menyuntik
// registrasi scope-equivalent dan mematikan injeksi <script> registerSW.js (no double register).
import { registerSW } from 'virtual:pwa-register';

registerSW({ immediate: true });

// Handle offline/online status
window.addEventListener('online', () => {
    console.log('[PWA] Back online');
    document.body.classList.remove('offline');
    showToast('Kembali online', 'success');
});

window.addEventListener('offline', () => {
    console.log('[PWA] Gone offline');
    document.body.classList.add('offline');
    showToast('Mode offline - Beberapa fitur terbatas', 'warning');
});

// Check initial connection
if (!navigator.onLine) {
    document.body.classList.add('offline');
}

const seenNotificationIds = new Set();

function showToast(message, type = 'info', options = {}) {
    // Prevent duplicate alert triggers within 15 seconds
    if (options.id) {
        if (seenNotificationIds.has(options.id)) return;
        seenNotificationIds.add(options.id);
        setTimeout(() => seenNotificationIds.delete(options.id), 15000);
    }

    let container = document.getElementById('ute-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ute-toast-container';
        container.className = 'fixed right-3 sm:right-4 z-[9999] flex flex-col gap-2.5 max-w-sm sm:max-w-md w-[calc(100vw-1.5rem)] sm:w-full pointer-events-none transition-all duration-200';
        container.style.top = 'max(env(safe-area-inset-top, 0px), 1rem)';
        document.body.appendChild(container);
    }

    // Play project-identical precision sound (unless explicitly disabled)
    if (options.sound !== false && window.playUteNotificationSound) {
        window.playUteNotificationSound(type);
    }

    const title = options.title || (type === 'error' ? 'Peringatan / Error' : (type === 'warning' ? 'Perhatian' : (type === 'success' ? 'Sukses' : 'Notifikasi')));
    const isUnprocessed = options.unprocessed ?? (type === 'warning' || type === 'error' || type === 'approval' || Boolean(options.actionUrl));
    const actionUrl = options.actionUrl || null;
    const actionLabel = options.actionLabel || (actionUrl ? 'Buka / Proses' : null);

    const colors = {
        success: {
            border: 'border-emerald-500/40 bg-emerald-950/80 text-emerald-100 dark:border-emerald-500/50 dark:bg-[#06231a]/95',
            glow: 'shadow-[0_10px_35px_rgba(16,185,129,0.25)]',
            accent: 'bg-emerald-500',
            pill: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            icon: `<svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
        },
        warning: {
            border: 'border-amber-500/50 bg-amber-950/85 text-amber-100 dark:border-amber-500/60 dark:bg-[#2b1803]/95',
            glow: 'shadow-[0_12px_40px_rgba(245,166,35,0.3)]',
            accent: 'bg-amber-500',
            pill: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
            icon: `<svg class="w-5 h-5 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>`
        },
        error: {
            border: 'border-rose-500/50 bg-rose-950/90 text-rose-100 dark:border-rose-500/60 dark:bg-[#2e090f]/95',
            glow: 'shadow-[0_12px_40px_rgba(239,68,68,0.35)]',
            accent: 'bg-rose-500',
            pill: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
            icon: `<svg class="w-5 h-5 text-rose-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
        },
        approval: {
            border: 'border-indigo-500/50 bg-indigo-950/90 text-indigo-100 dark:border-indigo-500/60 dark:bg-[#110e38]/95',
            glow: 'shadow-[0_12px_40px_rgba(91,79,233,0.35)]',
            accent: 'bg-indigo-500',
            pill: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
            icon: `<svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>`
        },
        info: {
            border: 'border-up-primary/40 bg-slate-950/85 text-slate-100 dark:border-white/20 dark:bg-[#070b14]/95',
            glow: 'shadow-[0_10px_35px_rgba(91,79,233,0.22)]',
            accent: 'bg-up-primary',
            pill: 'bg-up-primary/20 text-indigo-300 border-up-primary/30',
            icon: `<svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
        }
    };

    const scheme = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.setAttribute('role', 'alert');
    toast.className = `group pointer-events-auto relative overflow-hidden rounded-2xl border backdrop-blur-2xl p-4 transition-all duration-300 transform translate-y-[-10px] opacity-0 ${scheme.border} ${scheme.glow}`;

    // Persistent blinking indicator if unprocessed
    const blinkBadge = isUnprocessed ? `
        <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-rose-500/25 border border-rose-500/40 text-rose-300">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500 animate-pulse"></span>
            </span>
            <span>Belum Diproses</span>
        </div>
    ` : '';

    const actionButtonHtml = actionUrl ? `
        <a href="${actionUrl}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white bg-up-primary hover:bg-indigo-500 shadow-md shadow-up-primary/30 transition-all duration-150 cursor-pointer">
            <span>${actionLabel}</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    ` : '';

    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="mt-0.5 p-2 rounded-xl bg-black/20 dark:bg-white/5 border border-white/10 flex-shrink-0">
                ${scheme.icon}
            </div>
            <div class="flex-1 min-w-0 pr-6">
                <div class="flex items-center gap-2 flex-wrap mb-1">
                    <span class="text-xs font-black uppercase tracking-wider text-white">${title}</span>
                    ${blinkBadge}
                </div>
                <p class="text-xs sm:text-sm font-medium leading-relaxed text-slate-200">${message}</p>
                ${actionButtonHtml ? `<div class="mt-2.5 flex items-center gap-2">${actionButtonHtml}</div>` : ''}
            </div>
            <button type="button" aria-label="Tutup notifikasi" class="absolute top-3 right-3 p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-1 bg-white/10 overflow-hidden">
            <div class="h-full ${scheme.accent} transition-all duration-[7000ms] ease-linear w-full" id="toast-progress"></div>
        </div>
    `;

    // Close button handler
    const closeBtn = toast.querySelector('button');
    const dismiss = () => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-12px) scale(0.95)';
        setTimeout(() => toast.remove(), 250);
    };
    closeBtn.addEventListener('click', dismiss);

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0) scale(1)';
    });

    // Auto-dismiss duration: longer (10s) or persistent if explicitly marked
    if (!isUnprocessed) {
        setTimeout(dismiss, 7000);
    } else {
        // Unprocessed stays longer (20s) before gentle minimize, but user can dismiss anytime
        setTimeout(dismiss, 20000);
    }
}

window.showToast = showToast;

// Listen to Livewire and Custom Events
let alertBound = false;
document.addEventListener('livewire:init', () => {
    if (alertBound) return;
    alertBound = true;

    Livewire.on('alert', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
        showToast(data?.message || 'Notifikasi', data?.type || 'info', {
            id: data?.id || null,
            title: data?.title || null,
            sound: data?.sound ?? true,
            unprocessed: data?.unprocessed ?? (data?.type === 'warning' || data?.type === 'error'),
            actionUrl: data?.actionUrl || data?.url || null,
            actionLabel: data?.actionLabel || null
        });
    });

    Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
        showToast(data?.message || 'Notifikasi', data?.type || 'info', {
            id: data?.id || null,
            title: data?.title || null,
            sound: data?.sound ?? true,
            unprocessed: data?.unprocessed ?? false,
            actionUrl: data?.actionUrl || null
        });
    });
});

// Single unified Window-level listener for Livewire dispatched events & custom scripts
window.addEventListener('ute:notification-arrived', (e) => {
    const data = e.detail || {};
    showToast(data.konten || data.message || 'Notifikasi Baru', data.tipe || 'info', {
        id: data.id || null,
        title: data.judul || 'Notifikasi Sistem',
        sound: data.sound ?? true,
        unprocessed: true,
        actionUrl: data.action_url || data.url || null
    });
});

// PWA Installation Prompt Manager
window.deferredPWAInstallPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPWAInstallPrompt = e;
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

window.pwaInstaller = function () {
    return {
        canInstall: false,
        installed: false,
        dismissed: false,
        appName: window.UTE_ZONE === 'backoffice' ? 'Ute ERP' : 'Ute Parts',
        init() {
            if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
                this.installed = true;
                return;
            }
            if (sessionStorage.getItem('pwa-prompt-dismissed') === 'true') {
                this.dismissed = true;
            }
            if (window.deferredPWAInstallPrompt) {
                this.canInstall = true;
            }
            window.addEventListener('pwa-installable', () => {
                this.canInstall = true;
            });
            window.addEventListener('appinstalled', () => {
                this.installed = true;
                this.canInstall = false;
                window.deferredPWAInstallPrompt = null;
                showToast(`${this.appName} berhasil dipasang di layar utama!`, 'success');
            });
        },
        async promptInstall() {
            const promptEvent = window.deferredPWAInstallPrompt;
            if (!promptEvent) return;
            promptEvent.prompt();
            const { outcome } = await promptEvent.userChoice;
            if (outcome === 'accepted') {
                this.canInstall = false;
            }
            window.deferredPWAInstallPrompt = null;
        },
        dismiss() {
            this.dismissed = true;
            sessionStorage.setItem('pwa-prompt-dismissed', 'true');
        }
    };
};

// Screen Wake Lock API (Kasir POS & Servis)
window.screenWakeLock = function () {
    return {
        isSupported: 'wakeLock' in navigator,
        isActive: false,
        sentinel: null,
        init() {
            document.addEventListener('visibilitychange', async () => {
                if (this.isActive && document.visibilityState === 'visible') {
                    await this.request();
                }
            });
        },
        async toggle() {
            if (!this.isSupported) return;
            if (this.isActive) {
                await this.release();
            } else {
                await this.request();
            }
        },
        async request() {
            try {
                this.sentinel = await navigator.wakeLock.request('screen');
                this.isActive = true;
                this.sentinel.addEventListener('release', () => {
                    this.isActive = false;
                });
                showToast('Layar dikunci agar tetap menyala', 'info');
            } catch (err) {
                this.isActive = false;
            }
        },
        async release() {
            if (this.sentinel) {
                await this.sentinel.release();
                this.sentinel = null;
            }
            this.isActive = false;
            showToast('Kunci layar dilepas (mode hemat daya)', 'info');
        }
    };
};

// Alpine.js components will be registered in their respective blade files
console.log('[Ute Parts] App initialized');