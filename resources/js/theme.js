// [T-32 & Taste Palettes] Tema & Palet Warna: Dark/Light/Auto + 10 Design System Palettes
// localStorage instan + sinkronisasi DB staf via POST /api/user/theme
document.addEventListener('alpine:init', () => {
    Alpine.data('themeManager', () => ({
        preference: null, // 'dark' | 'light' | 'auto'
        theme: 'dark',    // tema efektif ('dark' | 'light')
        palette: 'default',
        paletteOpen: false,
        media: null,

        palettes: [
            { id: 'default', name: 'Cobalt Baseline', desc: 'Indigo & Mint Classic ERP', primary: '#4F46E5', accent: '#D97706', bgDark: '#090D16', bgLight: '#F8FAFC' },
            { id: 'linear', name: 'Linear Dark', desc: 'Obsidian & Indigo Violet', primary: '#5E6AD2', accent: '#D97706', bgDark: '#08090A', bgLight: '#FAFBFB' },
            { id: 'apple', name: 'Apple Developer Pro', desc: 'Space Gray & California Blue', primary: '#2997FF', accent: '#FF9F0A', bgDark: '#161617', bgLight: '#F5F5F7' },
            { id: 'raycast', name: 'Raycast / Geist', desc: 'Charcoal & Electric Cyan', primary: '#00E5FF', accent: '#FF6363', bgDark: '#0F0F11', bgLight: '#FAFAFA' },
            { id: 'stripe', name: 'Stripe Workbench', desc: 'Deep Navy Slate & Modern Blue', primary: '#635BFF', accent: '#00D4B2', bgDark: '#0A192F', bgLight: '#F6F9FC' },
            { id: 'supabase', name: 'Supabase Forest', desc: 'Neutral Charcoal & Studio Emerald', primary: '#3ECF8E', accent: '#FBBF24', bgDark: '#121212', bgLight: '#F8F9FA' },
            { id: 'arc', name: 'Arc Browser', desc: 'Espresso Velvet & Warm Coral', primary: '#FF6B4A', accent: '#FFA26B', bgDark: '#181515', bgLight: '#FAF7F5' },
            { id: 'nordic', name: 'Nordic Petrol', desc: 'Slate & Soft Teal', primary: '#14B8A6', accent: '#FB923C', bgDark: '#0F1416', bgLight: '#F3F6F6' },
            { id: 'github', name: 'GitHub Primer', desc: 'Clean Charcoal & Marine Sapphire', primary: '#2F81F7', accent: '#D29922', bgDark: '#0D1117', bgLight: '#F6F8FA' },
            { id: 'cyberpunk', name: 'Teenage Engineering', desc: 'Matte Carbon & Safety Orange', primary: '#FF5722', accent: '#FACC15', bgDark: '#141416', bgLight: '#F7F7F8' },
            { id: 'bronze', name: 'Champagne Bronze', desc: 'Warm Obsidian & Champagne Gold', primary: '#D4A373', accent: '#60A5FA', bgDark: '#141415', bgLight: '#FAF8F5' }
        ],

        init() {
            // 1. Inisialisasi Mode Gelap / Terang / Auto
            let storedTheme = null;
            try { storedTheme = localStorage.getItem('ute-theme'); } catch (e) {}
            const serverTheme = window.UTE_THEME || null;
            const zoneDefault = window.UTE_ZONE === 'marketplace' ? 'light' : 'dark';
            this.preference = storedTheme || serverTheme || zoneDefault;

            this.media = window.matchMedia('(prefers-color-scheme: dark)');
            this.applyTheme(this.preference);

            const onChange = () => { if (this.preference === 'auto') this.applyTheme('auto'); };
            if (this.media.addEventListener) this.media.addEventListener('change', onChange);
            else if (this.media.addListener) this.media.addListener(onChange);

            // 2. Inisialisasi Palet Desain
            let storedPalette = null;
            try { storedPalette = localStorage.getItem('ute-palette'); } catch (e) {}
            const serverPalette = window.UTE_PALETTE || null;
            this.palette = storedPalette || serverPalette || 'default';
            this.applyPalette(this.palette);

            // Sinkronisasi antar komponen Alpine jika ada perubahan palet dari tempat lain
            window.addEventListener('ute-palette-changed', (e) => {
                if (e.detail && e.detail !== this.palette) {
                    this.palette = e.detail;
                    this.applyPalette(e.detail);
                }
            });
        },

        cycleTheme() {
            const order = ['dark', 'light', 'auto'];
            const next = order[(order.indexOf(this.preference) + 1) % order.length];
            this.setPreference(next);
        },

        setPreference(pref) {
            this.preference = pref;
            this.applyTheme(pref);
            try { localStorage.setItem('ute-theme', pref); } catch (e) {}
            this.syncToServer({ theme: pref });
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

        setPalette(pal) {
            this.palette = pal;
            this.applyPalette(pal);
            try { localStorage.setItem('ute-palette', pal); } catch (e) {}
            window.dispatchEvent(new CustomEvent('ute-palette-changed', { detail: pal }));
            this.syncToServer({ palette: pal });
        },

        applyPalette(pal) {
            const root = document.documentElement;
            root.dataset.palette = pal || 'default';
        },

        syncToServer(payload) {
            if (!window.UTE_AUTHED) return;
            const token = document.querySelector('meta[name="csrf-token"]');
            fetch('/api/user/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            }).catch(() => {});
        },
    }));
});
