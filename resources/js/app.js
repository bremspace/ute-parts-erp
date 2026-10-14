// Ute Parts - Main Application Entry Point
// PWA Service Worker Registration

import '../css/app.css';
import './theme.js'; // [T-32] Tema dark/light/auto
import './font-size.js'; // [2026] Font size switcher
import './components/format-number.js'; // [T-34] Ribuan separator input nominal
import './components/thermal-print.js'; // [T-35] Cetak thermal Bluetooth 58/80mm + fallback web print
import './components/barcode-scanner.js'; // Barcode scanner engine (hardware keyboard wedge + web mobile camera)

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

function showToast(message, type = 'info') {
    let container = document.getElementById('ute-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ute-toast-container';
        container.className = 'fixed bottom-4 left-4 z-[60] flex flex-col gap-2 max-w-sm pointer-events-none';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-up-mint/15 text-up-mint border-up-mint/30',
        warning: 'bg-up-amber/15 text-up-amber border-up-amber/30',
        error: 'bg-up-red/15 text-up-red border-up-red/30',
        info: 'bg-up-primary/15 text-up-primary border-up-primary/30'
    };

    toast.setAttribute('role', 'status');
    toast.className = `glass-panel p-3 rounded-xl border pointer-events-auto animate-slide-up ${colors[type] || colors.info}`;
    toast.textContent = message;

    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast-visible'));

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(8px)';
        setTimeout(() => toast.remove(), 250);
    }, 5000);
}

window.showToast = showToast;

// [T-45] Feedback toast utk event Livewire `$dispatch('alert', ...)` — dipakai
// buka/tutup kas, transaksi POS, pelanggan baru, stok, dll. Sebelumnya tidak ada
// listener → submit "tidak ada respon".
let alertBound = false;
document.addEventListener('livewire:init', () => {
    if (alertBound) return;
    alertBound = true;
    Livewire.on('alert', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
        showToast(data?.message || 'Notifikasi', data?.type || 'info');
    });
    Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? (payload[0] || {}) : (payload || {});
        showToast(data?.message || 'Notifikasi', data?.type || 'info');
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