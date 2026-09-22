// [T-32] Tema: dark / light / auto — localStorage instan + sinkronisasi DB staf via POST /api/user/theme
// Terdaftar seperti branchSwitcher di blade layout. Zona default: backoffice → dark, marketplace → light.
document.addEventListener('alpine:init', () => {
    Alpine.data('themeManager', () => ({
        preference: null, // 'dark' | 'light' | 'auto' — pilihan tersimpan
        theme: 'dark',    // tema efektif yang sedang ditampilkan
        media: null,

        init() {
            // Urutan: pref DB dari server > localStorage > default zona
            const server = window.UTE_THEME || null;
            let stored = null;
            try { stored = localStorage.getItem('ute-theme'); } catch (e) { /* mode privat */ }
            const zoneDefault = window.UTE_ZONE === 'marketplace' ? 'light' : 'dark';
            this.preference = server || stored || zoneDefault;

            this.media = window.matchMedia('(prefers-color-scheme: dark)');
            this.applyTheme(this.preference);

            // Mode 'auto' mengikuti perubahan sistem secara langsung
            const onChange = () => { if (this.preference === 'auto') this.applyTheme('auto'); };
            if (this.media.addEventListener) this.media.addEventListener('change', onChange);
            else if (this.media.addListener) this.media.addListener(onChange);
        },

        cycleTheme() {
            const order = ['dark', 'light', 'auto'];
            const next = order[(order.indexOf(this.preference) + 1) % order.length];
            this.setPreference(next);
        },

        setPreference(pref) {
            this.preference = pref;
            this.applyTheme(pref);
            try { localStorage.setItem('ute-theme', pref); } catch (e) { /* mode privat */ }
            this.syncToServer(pref);
        },

        applyTheme(pref) {
            const effective = pref === 'auto'
                ? (this.media && this.media.matches ? 'dark' : 'light')
                : pref;
            this.theme = effective;
            const root = document.documentElement;
            root.dataset.theme = effective;
            root.classList.toggle('dark', effective === 'dark');
        },

        syncToServer(pref) {
            if (!window.UTE_AUTHED) return; // hanya staff yang masuk; marketplace tidak sinkron
            const token = document.querySelector('meta[name="csrf-token"]');
            // Fire-and-forget: localStorage sudah diterapkan secara lokal, gagal pun tidak mengganggu UI
            fetch('/api/user/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ theme: pref }),
                credentials: 'same-origin',
            }).catch(() => {});
        },
    }));
});