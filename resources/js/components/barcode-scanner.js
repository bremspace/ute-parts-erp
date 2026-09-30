import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

/**
 * Ute Parts - Barcode Scanner Engine
 * Mendukung:
 * 1. Hardware Barcode Scanner (USB, Bluetooth, Wireless Dongle via Keyboard Wedge)
 * 2. Web Mobile / Kamera HP Live Stream Scanner via Html5Qrcode
 * 3. Fallback Kamera Native HP via File Capture (100% kompatibel di semua HP & HTTP/HTTPS)
 * 4. Auto Preflight Permission Request saat load pertama kali di HP
 * 5. Audio Synthesizer Beep & Haptic Vibration Feedback
 */

// Web Audio API Synthesizer Beep
export function playBeep(success = true) {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const ctx = new AudioContextClass();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        if (success) {
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1800, ctx.currentTime);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.08);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.08);
        } else {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(320, ctx.currentTime);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.22);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.22);
        }
    } catch (_) {}
}

// Haptic Vibration untuk Web Mobile
export function triggerHaptic() {
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
        try {
            navigator.vibrate([60, 40, 60]);
        } catch (_) {}
    }
}

/**
 * Cek apakah halaman berjalan di Secure Context (HTTPS atau Localhost)
 */
export function isSecureContext() {
    if (typeof window === 'undefined') return false;
    return Boolean(
        window.isSecureContext ||
        window.location.protocol === 'https:' ||
        window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1'
    );
}

/**
 * Preflight Permission Manager:
 * Meminta izin otomatis kamera pada perangkat mobile saat pertama kali load atau interaksi pertama.
 */
export class DevicePermissionManager {
    constructor() {
        this.storageKey = 'ute_camera_preflight_v1';
        this.bannerElement = null;
    }

    init() {
        if (typeof window === 'undefined') return;

        // Jalankan setelah DOM siap
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.checkAndPrompt());
        } else {
            this.checkAndPrompt();
        }
    }

    async checkAndPrompt() {
        const stored = localStorage.getItem(this.storageKey);
        if (stored === 'granted' || stored === 'dismissed') {
            return;
        }

        // Cek API Permissions jika didukung browser
        if (navigator.permissions && navigator.permissions.query) {
            try {
                const status = await navigator.permissions.query({ name: 'camera' });
                if (status.state === 'granted') {
                    localStorage.setItem(this.storageKey, 'granted');
                    return;
                }
            } catch (_) {}
        }

        // Tampilkan Banner Permintaan Izin Otomatis yang Ramah di Bawah Layar
        this.showPermissionPromptBanner();
    }

    showPermissionPromptBanner() {
        if (document.getElementById('ute-camera-permission-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'ute-camera-permission-banner';
        banner.className = 'fixed bottom-4 left-3 right-3 sm:left-auto sm:right-6 sm:max-w-md z-50 glass-panel p-4 rounded-2xl border border-up-primary/30 shadow-2xl animate-slide-up flex flex-col gap-3 bg-ink-950/95';

        const isHttps = isSecureContext();

        banner.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-xl bg-up-primary/20 text-up-primary flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-xs font-bold text-white mb-0.5">Aktifkan Kamera Barcode Scanner</h4>
                    <p class="text-[11px] text-ink-300 leading-relaxed">
                        ${isHttps
                            ? 'Izinkan akses kamera agar dapat memindai barcode produk di POS Kasir dan WMS secara langsung.'
                            : 'Diperlukan untuk pemindaian barcode fisik produk via kamera perangkat HP Anda.'}
                    </p>
                </div>
                <button id="ute-perm-dismiss-btn" class="text-ink-400 hover:text-white p-1 text-sm font-bold cursor-pointer" title="Nanti saja">✕</button>
            </div>
            <div class="flex items-center gap-2 pt-1 border-t border-white/10">
                <button id="ute-perm-allow-btn" type="button" class="flex-1 py-2 px-3 rounded-xl bg-up-primary hover:bg-up-primary/80 text-white font-bold text-xs cursor-pointer active:scale-95 transition-all text-center shadow-md">
                    Izinkan Kamera Sekarang
                </button>
                <button id="ute-perm-later-btn" type="button" class="py-2 px-3 rounded-xl bg-white/10 hover:bg-white/15 text-ink-300 font-semibold text-xs cursor-pointer active:scale-95 transition-all">
                    Nanti
                </button>
            </div>
        `;

        document.body.appendChild(banner);
        this.bannerElement = banner;

        document.getElementById('ute-perm-allow-btn').addEventListener('click', () => this.requestCameraAccess());
        document.getElementById('ute-perm-later-btn').addEventListener('click', () => this.dismiss());
        document.getElementById('ute-perm-dismiss-btn').addEventListener('click', () => this.dismiss());
    }

    async requestCameraAccess() {
        try {
            if (isSecureContext() && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' }
                });
                // Matikan stream segera setelah izin didapat
                stream.getTracks().forEach(t => t.stop());
                localStorage.setItem(this.storageKey, 'granted');
                if (window.showToast) {
                    window.showToast('Kamera barcode scanner berhasil diaktifkan!', 'success');
                }
            } else {
                localStorage.setItem(this.storageKey, 'granted');
                if (window.showToast) {
                    window.showToast('Fitur barcode scanner HP siap digunakan!', 'success');
                }
            }
        } catch (err) {
            console.warn('[BarcodeScanner] Izin kamera otomatis ditolak:', err);
            if (window.showToast) {
                window.showToast('Izin kamera ditolak. Anda tetap dapat menggunakan tombol Foto Barcode.', 'warning');
            }
        } finally {
            this.dismiss();
        }
    }

    dismiss() {
        if (this.bannerElement) {
            this.bannerElement.remove();
            this.bannerElement = null;
        }
        localStorage.setItem(this.storageKey, 'dismissed');
    }
}

export const permissionManager = new DevicePermissionManager();
permissionManager.init();

/**
 * Global Hardware Barcode Scanner Listener (Keyboard Wedge)
 */
class HardwareScannerListener {
    constructor() {
        this.buffer = '';
        this.timestamps = [];
        this.maxCharIntervalMs = 50;
        this.minBarcodeLength = 3;
        this.bound = false;
    }

    init() {
        if (this.bound || typeof window === 'undefined') return;
        this.bound = true;

        window.addEventListener('keydown', (e) => this.handleKeyDown(e), true);
    }

    handleKeyDown(e) {
        if (['Shift', 'Control', 'Alt', 'Meta', 'CapsLock'].includes(e.key)) {
            return;
        }

        const now = performance.now();

        if (e.key === 'Enter') {
            if (this.buffer.length >= this.minBarcodeLength) {
                let totalInterval = 0;
                for (let i = 1; i < this.timestamps.length; i++) {
                    totalInterval += (this.timestamps[i] - this.timestamps[i - 1]);
                }
                const avgInterval = this.timestamps.length > 1
                    ? totalInterval / (this.timestamps.length - 1)
                    : 999;

                if (avgInterval <= this.maxCharIntervalMs) {
                    const scannedCode = this.buffer.trim();
                    if (scannedCode) {
                        e.preventDefault();
                        e.stopPropagation();

                        playBeep(true);
                        triggerHaptic();

                        this.dispatchScan(scannedCode);
                    }
                }
            }

            this.reset();
            return;
        }

        if (this.timestamps.length > 0) {
            const lastTime = this.timestamps[this.timestamps.length - 1];
            if (now - lastTime > 150) {
                this.reset();
            }
        }

        if (e.key.length === 1) {
            this.buffer += e.key;
            this.timestamps.push(now);
        }
    }

    reset() {
        this.buffer = '';
        this.timestamps = [];
    }

    dispatchScan(code) {
        const event = new CustomEvent('ute:barcode-scanned', {
            detail: { code },
            bubbles: true,
            cancelable: true
        });
        window.dispatchEvent(event);

        const activeBarcodeInput = document.querySelector('input[data-barcode-input="true"], input[x-ref="barcodeInput"]');
        if (activeBarcodeInput) {
            activeBarcodeInput.value = code;
            activeBarcodeInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeBarcodeInput.dispatchEvent(new Event('change', { bubbles: true }));

            activeBarcodeInput.dispatchEvent(new KeyboardEvent('keydown', {
                key: 'Enter',
                code: 'Enter',
                keyCode: 13,
                which: 13,
                bubbles: true
            }));
        }
    }
}

export const hardwareScanner = new HardwareScannerListener();
hardwareScanner.init();

/**
 * Camera Scanner Modal Manager
 */
export class CameraScannerModal {
    constructor() {
        this.html5QrCode = null;
        this.modalElement = null;
        this.isScanning = false;
        this.facingMode = 'environment';
        this.torchOn = false;
        this.continuous = false;
        this.onScanCallback = null;
        this.onCloseCallback = null;
        this.lastScannedCode = null;
        this.lastScanTime = 0;
        this.scanCooldownMs = 1500;
    }

    createModalDOM(title) {
        let existing = document.getElementById('ute-camera-scanner-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'ute-camera-scanner-modal';
        modal.className = 'fixed inset-0 z-[100] flex flex-col items-center justify-center bg-black/90 backdrop-blur-md p-3 sm:p-6 transition-opacity animate-fade-in';
        modal.innerHTML = `
            <div class="relative w-full max-w-lg bg-ink-900 border border-white/10 rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-white/10 bg-white/5">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-up-primary/20 text-up-primary flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white tracking-wide">${title || 'Scan Barcode / QR'}</h3>
                            <p class="text-[11px] text-ink-400">Arahkan kamera atau gunakan tombol foto</p>
                        </div>
                    </div>
                    <button id="ute-scanner-close-btn" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-ink-300 hover:text-white flex items-center justify-center transition-colors cursor-pointer" title="Tutup">
                        ✕
                    </button>
                </div>

                <!-- Video Viewfinder Wrapper -->
                <div class="relative w-full aspect-square sm:aspect-[4/3] bg-black overflow-hidden flex items-center justify-center">
                    <div id="ute-camera-reader" class="w-full h-full"></div>

                    <!-- Reticle Viewfinder Overlay -->
                    <div id="ute-scanner-reticle" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="relative w-64 h-64 sm:w-72 sm:h-72 border-2 border-white/30 rounded-2xl overflow-hidden shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                            <div class="absolute top-0 left-0 w-6 h-6 border-t-4 border-l-4 border-up-primary rounded-tl"></div>
                            <div class="absolute top-0 right-0 w-6 h-6 border-t-4 border-r-4 border-up-primary rounded-tr"></div>
                            <div class="absolute bottom-0 left-0 w-6 h-6 border-b-4 border-l-4 border-up-primary rounded-bl"></div>
                            <div class="absolute bottom-0 right-0 w-6 h-6 border-b-4 border-r-4 border-up-primary rounded-br"></div>
                            <div class="absolute left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-up-primary to-transparent shadow-[0_0_8px_#5B4FE9] animate-laser"></div>
                        </div>
                    </div>

                    <!-- Result Notification Banner -->
                    <div id="ute-scanner-result-banner" class="absolute top-4 left-4 right-4 bg-up-mint/90 backdrop-blur-md text-ink-950 font-bold px-4 py-2.5 rounded-xl shadow-lg transform -translate-y-16 opacity-0 transition-all duration-300 flex items-center justify-between z-10">
                        <span class="text-xs truncate" id="ute-scanner-result-text">Barcode Terdeteksi</span>
                        <span class="text-[10px] bg-black/20 px-2 py-0.5 rounded-full uppercase tracking-wider">OK</span>
                    </div>

                    <!-- Error & Permission Notice Banner -->
                    <div id="ute-scanner-error-banner" class="hidden absolute inset-0 bg-ink-950/95 flex-col items-center justify-center p-6 text-center z-20 overflow-y-auto">
                        <div class="w-12 h-12 rounded-full bg-up-red/20 text-up-red flex items-center justify-center mb-3 flex-shrink-0">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <p class="text-sm font-bold text-white mb-1.5" id="ute-scanner-error-title">Akses Kamera Perangkat</p>
                        <p id="ute-scanner-error-msg" class="text-xs text-ink-300 mb-4 max-w-xs leading-relaxed">
                            Kamera live streaming membutuhkan izin atau koneksi HTTPS.
                        </p>

                        <!-- Action Buttons di Banner Error -->
                        <div class="flex flex-col gap-2.5 w-full max-w-xs">
                            <button id="ute-scanner-fallback-capture-btn" type="button" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-up-mint hover:bg-up-mint/90 text-ink-950 text-xs font-bold transition-transform active:scale-95 shadow-md">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Ambil Foto Barcode (Kamera HP)</span>
                            </button>

                            <button id="ute-scanner-retry-btn" type="button" class="w-full px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-transform active:scale-95">
                                Coba Minta Izin Ulang
                            </button>
                        </div>

                        <div class="mt-4 p-3 rounded-xl bg-white/5 border border-white/10 text-left text-[11px] text-ink-400 max-w-xs">
                            <p class="font-semibold text-ink-200 mb-1">Tips untuk pengguna HP:</p>
                            <ul class="list-disc pl-4 space-y-0.5">
                                <li>Pastikan mengetuk <strong>"Izinkan" (Allow)</strong> saat pop-up browser muncul.</li>
                                <li>Di fase staging/testing via IP, gunakan tombol <strong>"Ambil Foto Barcode"</strong> jika browser membatasi video stream HTTP.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Hidden Native Camera / File Input -->
                <input type="file" id="ute-scanner-file-input" accept="image/*" capture="environment" class="hidden" />

                <!-- Controls & Options Footer -->
                <div class="p-3.5 bg-white/5 border-t border-white/10 flex flex-col gap-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <button id="ute-scanner-photo-btn" type="button" class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold cursor-pointer transition-colors" title="Buka kamera bawaan HP untuk foto barcode">
                            <svg class="w-4 h-4 text-up-mint" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span>Foto Barcode</span>
                        </button>

                        <button id="ute-scanner-switch-btn" type="button" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold cursor-pointer transition-colors" title="Balik Kamera Depan / Belakang">
                            <svg class="w-4 h-4 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            <span>Balik</span>
                        </button>

                        <button id="ute-scanner-torch-btn" type="button" class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold cursor-pointer transition-colors" title="Nyalakan Lampu Senter">
                            <svg class="w-4 h-4 text-up-amber" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            <span id="ute-torch-label">Senter</span>
                        </button>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer text-ink-300 text-xs select-none pt-0.5">
                        <input type="checkbox" id="ute-scanner-continuous-toggle" class="w-4 h-4 rounded text-up-primary bg-white/5 border-white/20 focus:ring-up-primary focus:ring-offset-0">
                        <span>Mode Scan Beruntun (Scan item berikutnya tanpa tutup kamera)</span>
                    </label>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.modalElement = modal;

        document.getElementById('ute-scanner-close-btn').addEventListener('click', () => this.stopAndClose());
        document.getElementById('ute-scanner-retry-btn').addEventListener('click', () => this.startScanning());
        document.getElementById('ute-scanner-switch-btn').addEventListener('click', () => this.switchCamera());
        document.getElementById('ute-scanner-torch-btn').addEventListener('click', () => this.toggleTorch());

        const fileInput = document.getElementById('ute-scanner-file-input');
        fileInput.addEventListener('change', (e) => this.handleFileInputChange(e));

        document.getElementById('ute-scanner-photo-btn').addEventListener('click', () => {
            fileInput.click();
        });

        const fallbackBtn = document.getElementById('ute-scanner-fallback-capture-btn');
        if (fallbackBtn) {
            fallbackBtn.addEventListener('click', () => {
                fileInput.click();
            });
        }

        const continuousCheckbox = document.getElementById('ute-scanner-continuous-toggle');
        continuousCheckbox.checked = this.continuous;
        continuousCheckbox.addEventListener('change', (e) => {
            this.continuous = e.target.checked;
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) this.stopAndClose();
        });
    }

    /**
     * Entry Point Utama Panggilan Scan:
     * Cerdas & Fleksibel:
     * Jika di HP berjalan di HTTP (non-secure context di mana browser memblokir live stream):
     * Langsung memicu native camera capture HP!
     * Jika di Secure Context (HTTPS di Prod atau localhost):
     * Membuka live viewfinder stream dengan auto-prompt permission.
     */
    async open({ onScan, onClose, title = 'Scan Barcode', continuous = false }) {
        this.onScanCallback = onScan;
        this.onCloseCallback = onClose;
        this.continuous = continuous;
        this.lastScannedCode = null;
        this.lastScanTime = 0;
        this.facingMode = 'environment';

        const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
        const isSecure = isSecureContext();

        // Jika mobile di HTTP non-SSL, browser menolak WebRTC getUserMedia.
        // Langsung buka native camera capture HP agar pengalaman pengguna 100% mulus tanpa error permission denied!
        if (isMobile && !isSecure) {
            this.triggerDirectMobileCapture(title);
            return;
        }

        this.createModalDOM(title);
        await this.startScanning();
    }

    triggerDirectMobileCapture(title) {
        let fileInput = document.getElementById('ute-direct-mobile-camera-input');
        if (!fileInput) {
            fileInput = document.createElement('input');
            fileInput.id = 'ute-direct-mobile-camera-input';
            fileInput.type = 'file';
            fileInput.accept = 'image/*';
            fileInput.capture = 'environment';
            fileInput.className = 'hidden';
            document.body.appendChild(fileInput);
        }

        const onFilePicked = async (e) => {
            fileInput.removeEventListener('change', onFilePicked);
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            if (window.showToast) {
                window.showToast('Menganalisis foto barcode...', 'info');
            }

            try {
                if (!this.html5QrCode) {
                    const formats = [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.DATA_MATRIX
                    ];
                    let dummyReader = document.getElementById('ute-dummy-qr-reader');
                    if (!dummyReader) {
                        dummyReader = document.createElement('div');
                        dummyReader.id = 'ute-dummy-qr-reader';
                        dummyReader.className = 'hidden';
                        document.body.appendChild(dummyReader);
                    }
                    this.html5QrCode = new Html5Qrcode('ute-dummy-qr-reader', {
                        formatsToSupport: formats,
                        verbose: false
                    });
                }

                const decodedText = await this.html5QrCode.scanFile(file, true);
                if (decodedText) {
                    playBeep(true);
                    triggerHaptic();
                    if (window.showToast) {
                        window.showToast(`Berhasil Scan: ${decodedText}`, 'success');
                    }
                    if (typeof this.onScanCallback === 'function') {
                        this.onScanCallback(decodedText);
                    }
                }
            } catch (err) {
                console.warn('[BarcodeScanner] Gagal membaca barcode dari kamera HP:', err);
                playBeep(false);
                if (window.showToast) {
                    window.showToast('Barcode tidak terdeteksi. Silakan coba foto lebih dekat & fokus.', 'warning');
                }
            } finally {
                fileInput.value = '';
            }
        };

        fileInput.addEventListener('change', onFilePicked);
        fileInput.click();
    }

    async startScanning() {
        const errorBanner = document.getElementById('ute-scanner-error-banner');
        const reticle = document.getElementById('ute-scanner-reticle');
        if (errorBanner) errorBanner.classList.add('hidden');
        if (reticle) reticle.classList.remove('hidden');

        try {
            const hasMediaDevices = typeof navigator !== 'undefined' && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
            const isSecure = isSecureContext();

            if (!hasMediaDevices || !isSecure) {
                throw new Error('Akses live stream kamera dibatasi pada koneksi HTTP. Silakan gunakan tombol Ambil Foto Barcode di bawah.');
            }

            if (!this.html5QrCode) {
                const formats = [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.DATA_MATRIX
                ];

                this.html5QrCode = new Html5Qrcode('ute-camera-reader', {
                    formatsToSupport: formats,
                    verbose: false
                });
            }

            const qrConfig = {
                fps: 15,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    const qrboxSize = Math.floor(minEdge * 0.75);
                    return { width: qrboxSize, height: Math.floor(qrboxSize * 0.65) };
                },
                aspectRatio: 1.333333
            };

            const cameraConstraint = { facingMode: this.facingMode };

            await this.html5QrCode.start(
                cameraConstraint,
                qrConfig,
                (decodedText) => this.handleDecodedText(decodedText),
                () => {}
            );

            this.isScanning = true;
        } catch (err) {
            console.warn('[BarcodeScanner] Live camera tidak dapat dimulai:', err);
            this.showPermissionError(err);
        }
    }

    showPermissionError(err) {
        const errorBanner = document.getElementById('ute-scanner-error-banner');
        const errorMsg = document.getElementById('ute-scanner-error-msg');
        const errorTitle = document.getElementById('ute-scanner-error-title');
        const reticle = document.getElementById('ute-scanner-reticle');

        if (reticle) reticle.classList.add('hidden');

        if (errorBanner) {
            errorBanner.classList.remove('hidden');
            errorBanner.classList.add('flex');
        }

        const msg = (err?.message || err?.name || '').toString().toLowerCase();

        if (msg.includes('permission') || msg.includes('notallowed') || err?.name === 'NotAllowedError') {
            if (errorTitle) errorTitle.textContent = 'Izin Kamera HP Diperlukan';
            if (errorMsg) {
                errorMsg.innerHTML = 'Browser belum diizinkan mengakses kamera.<br>Tekan tombol <strong>"Ambil Foto Barcode"</strong> untuk memindai via kamera HP.';
            }
        } else {
            if (errorTitle) errorTitle.textContent = 'Kamera Perangkat';
            if (errorMsg) {
                errorMsg.innerHTML = `${err?.message || 'Kamera tidak dapat dimulai.'}<br>Gunakan tombol <strong>"Ambil Foto Barcode"</strong> di bawah.`;
            }
        }
    }

    async handleFileInputChange(event) {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        try {
            if (!this.html5QrCode) {
                const formats = [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.QR_CODE,
                    Html5QrcodeSupportedFormats.DATA_MATRIX
                ];
                this.html5QrCode = new Html5Qrcode('ute-camera-reader', {
                    formatsToSupport: formats,
                    verbose: false
                });
            }

            const banner = document.getElementById('ute-scanner-result-banner');
            const text = document.getElementById('ute-scanner-result-text');
            if (banner && text) {
                text.textContent = 'Menganalisis foto barcode...';
                banner.classList.remove('-translate-y-16', 'opacity-0');
                banner.classList.add('translate-y-0', 'opacity-100');
            }

            const decodedText = await this.html5QrCode.scanFile(file, true);
            if (decodedText) {
                this.handleDecodedText(decodedText);
            }
        } catch (e) {
            console.error('[BarcodeScanner] Gagal membaca barcode dari foto:', e);
            playBeep(false);
            const banner = document.getElementById('ute-scanner-result-banner');
            const text = document.getElementById('ute-scanner-result-text');
            if (banner && text) {
                text.textContent = 'Barcode tidak terbaca pada foto. Coba foto lebih dekat & jelas.';
                banner.classList.remove('-translate-y-16', 'opacity-0', 'bg-up-mint/90');
                banner.classList.add('translate-y-0', 'opacity-100', 'bg-up-amber/90');

                setTimeout(() => {
                    banner.classList.remove('translate-y-0', 'opacity-100');
                    banner.classList.add('-translate-y-16', 'opacity-0');
                }, 2500);
            }
        } finally {
            event.target.value = '';
        }
    }

    handleDecodedText(decodedText) {
        const now = Date.now();
        const code = (decodedText || '').trim();
        if (!code) return;

        if (code === this.lastScannedCode && (now - this.lastScanTime) < this.scanCooldownMs) {
            return;
        }

        this.lastScannedCode = code;
        this.lastScanTime = now;

        playBeep(true);
        triggerHaptic();

        this.showResultBanner(code);

        if (typeof this.onScanCallback === 'function') {
            try {
                this.onScanCallback(code);
            } catch (err) {
                console.error('[BarcodeScanner] onScanCallback error:', err);
            }
        }

        if (!this.continuous) {
            setTimeout(() => {
                this.stopAndClose();
            }, 350);
        }
    }

    showResultBanner(code) {
        const banner = document.getElementById('ute-scanner-result-banner');
        const text = document.getElementById('ute-scanner-result-text');
        if (banner && text) {
            text.textContent = `Berhasil Scan: ${code}`;
            banner.classList.remove('-translate-y-16', 'opacity-0', 'bg-up-amber/90', 'bg-up-red/90');
            banner.classList.add('translate-y-0', 'opacity-100', 'bg-up-mint/90');

            setTimeout(() => {
                banner.classList.remove('translate-y-0', 'opacity-100');
                banner.classList.add('-translate-y-16', 'opacity-0');
            }, 1200);
        }
    }

    showNotFoundBanner(code) {
        const banner = document.getElementById('ute-scanner-result-banner');
        const text = document.getElementById('ute-scanner-result-text');
        if (banner && text) {
            text.textContent = `Barcode ${code} belum terdaftar`;
            banner.classList.remove('-translate-y-16', 'opacity-0', 'bg-up-mint/90');
            banner.classList.add('translate-y-0', 'opacity-100', 'bg-up-amber/90');

            setTimeout(() => {
                banner.classList.remove('translate-y-0', 'opacity-100');
                banner.classList.add('-translate-y-16', 'opacity-0');
            }, 2500);
        }
    }

    async switchCamera() {
        this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';

        try {
            if (this.html5QrCode && this.isScanning) {
                await this.html5QrCode.stop();
                this.isScanning = false;
            }
            await this.startScanning();
        } catch (e) {
            console.error('[BarcodeScanner] Gagal beralih kamera:', e);
        }
    }

    async toggleTorch() {
        if (!this.html5QrCode || !this.isScanning) return;
        try {
            this.torchOn = !this.torchOn;
            await this.html5QrCode.applyVideoConstraints({
                advanced: [{ torch: this.torchOn }]
            });
            const label = document.getElementById('ute-torch-label');
            if (label) label.textContent = this.torchOn ? 'Matikan Senter' : 'Senter';
        } catch (e) {
            console.warn('[BarcodeScanner] Lampu senter tidak didukung pada kamera ini:', e);
        }
    }

    async stopAndClose() {
        if (this.html5QrCode && this.isScanning) {
            try {
                await this.html5QrCode.stop();
            } catch (_) {}
            this.isScanning = false;
        }

        if (this.modalElement) {
            this.modalElement.remove();
            this.modalElement = null;
        }

        if (typeof this.onCloseCallback === 'function') {
            this.onCloseCallback();
        }
    }
}

export const cameraScanner = new CameraScannerModal();

// Listener global barcode tidak ditemukan
if (typeof window !== 'undefined') {
    window.addEventListener('ute:barcode-not-found', (e) => {
        const code = e.detail?.code || '';
        playBeep(false);
        triggerHaptic();
        if (cameraScanner && cameraScanner.isScanning) {
            cameraScanner.showNotFoundBanner(code);
        }
    });

    window.uteBarcode = {
        playBeep,
        triggerHaptic,
        hardwareScanner,
        cameraScanner,
        permissionManager,
        isSecureContext,
        openCameraScanner: (opts) => cameraScanner.open(opts || {})
    };
}
