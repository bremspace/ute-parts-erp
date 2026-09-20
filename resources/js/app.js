// Ute Parts - Main Application Entry Point
// PWA Service Worker Registration

import '../css/app.css';
import './theme.js'; // [T-32] Tema dark/light/auto
import './components/format-number.js'; // [T-34] Ribuan separator input nominal
import './components/thermal-print.js'; // [T-35] Cetak thermal Bluetooth 58/80mm + fallback web print

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/'
            });
            
            console.log('[PWA] Service Worker registered:', registration.scope);
            
            // Handle updates
            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // New version available
                        console.log('[PWA] New version available');
                        showUpdateNotification(registration);
                    }
                });
            });
            
            // Check for updates periodically
            setInterval(() => registration.update(), 60 * 60 * 1000); // Every hour
            
        } catch (error) {
            console.error('[PWA] Service Worker registration failed:', error);
        }
    });
    
    // Handle offline/online status
    window.addEventListener('online', () => {
        console.log('[PWA] Back online');
        document.body.classList.remove('offline');
        // Optionally show notification
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
}

function showUpdateNotification(registration) {
    // Create update toast
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 z-50 glass-panel p-4 rounded-xl shadow-xl max-w-sm animate-slide-up';
    toast.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-up-primary/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-up-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-white">Versi Baru Tersedia</p>
                <p class="text-xs text-ink-400 mt-0.5">Klik untuk memperbarui aplikasi</p>
            </div>
            <button class="text-ink-400 hover:text-white" onclick="this.parentElement.parentElement.remove()">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    `;
    
    toast.addEventListener('click', (e) => {
        if (e.target.closest('button')) return;
        registration.waiting?.postMessage({ type: 'SKIP_WAITING' });
        window.location.reload();
    });
    
    document.body.appendChild(toast);
    
    // Auto-remove after 10 seconds
    setTimeout(() => toast.remove(), 10000);
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const colors = {
        success: 'bg-up-mint/20 text-up-mint border-up-mint/30',
        warning: 'bg-up-amber/20 text-up-amber border-up-amber/30',
        error: 'bg-up-red/20 text-up-red border-up-red/30',
        info: 'bg-up-primary/20 text-up-primary border-up-primary/30'
    };
    
    toast.className = `fixed bottom-4 left-4 z-50 glass-panel p-3 rounded-xl border ${colors[type] || colors.info} animate-slide-up max-w-sm`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 5000);
}

// Alpine.js components will be registered in their respective blade files
console.log('[Ute Parts] App initialized');