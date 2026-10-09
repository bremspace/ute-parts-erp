<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Backoffice — Ute Parts</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-950 text-ink-100 font-sans antialiased min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-up-primary selection:text-white">
    <!-- Ambient Background Glow & Gradient Signature -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-up-primary/30 rounded-full blur-[128px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-up-accent/20 rounded-full blur-[128px] pointer-events-none"></div>

    <!-- Login Card Container -->
    <div class="w-full max-w-md relative z-10">
        <!-- Logo & Title -->
        <div class="text-center mb-8 flex justify-center">
            <x-prism.logo size="lg" layout="vertical" mode="dark" title="UTE PARTS" subtitle="Sistem ERP, Kasir POS & Manajemen Multi-Gudang" />
        </div>

        <!-- Glass Card -->
        <div class="glass-panel p-8 rounded-3xl relative overflow-hidden border border-white/10 shadow-2xl">
            <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>

            <h2 class="text-lg font-semibold text-white mb-6">Masuk ke Backoffice</h2>

            @if(session('error'))
                <div class="mb-5 p-3.5 rounded-xl bg-up-red/15 border border-up-red/30 text-up-red text-xs font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-up-red/15 border border-up-red/30 text-up-red text-xs font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-4" x-data="{
                rememberEmail: true,
                savedEmail: '',
                init() {
                    const cached = localStorage.getItem('up_last_login_email');
                    if (cached && !this.$refs.emailInput.value) {
                        this.savedEmail = cached;
                        this.$refs.emailInput.value = cached;
                    }
                },
                saveHistory() {
                    if (this.rememberEmail && this.$refs.emailInput.value) {
                        localStorage.setItem('up_last_login_email', this.$refs.emailInput.value.trim());
                    } else if (!this.rememberEmail) {
                        localStorage.removeItem('up_last_login_email');
                    }
                },
                clearHistory() {
                    localStorage.removeItem('up_last_login_email');
                    this.savedEmail = '';
                    this.$refs.emailInput.value = '';
                    this.$refs.emailInput.focus();
                }
            }" @submit="saveHistory">
                @csrf
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="email" class="block text-xs font-medium text-ink-300">Email Staf</label>
                        <template x-if="savedEmail">
                            <button
                                type="button"
                                @click="clearHistory"
                                class="text-[10px] text-ink-400 hover:text-up-red transition-colors cursor-pointer"
                                title="Hapus riwayat email di perangkat ini"
                            >
                                Hapus riwayat
                            </button>
                        </template>
                    </div>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        x-ref="emailInput"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="nama@uteparts.id"
                        class="w-full px-4 py-3 rounded-xl glass-input text-sm"
                        value="{{ old('email') }}"
                    />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-medium text-ink-300">Kata Sandi</label>
                    </div>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full px-4 py-3 rounded-xl glass-input text-sm"
                        value=""
                    />
                </div>

                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            x-model="rememberEmail"
                            class="rounded border-white/20 bg-white/5 text-up-primary focus:ring-up-primary/40 focus:ring-offset-0 w-3.5 h-3.5"
                        >
                        <span class="text-xs text-ink-400">Ingat email di perangkat ini</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark hover:to-indigo-700 text-white font-semibold text-sm shadow-lg shadow-up-primary/30 transition-[transform,background-color] duration-150 cursor-pointer border border-white/10 active:scale-[0.97] min-h-[44px]"
                    >
                        Masuk Sistem
                    </button>
                </div>
            </form>
        </div>

        <!-- Footer Notice -->
        <p class="text-center text-xs text-ink-500 mt-6">
            Ute Parts ERP &copy; 2026. Hak Cipta Dilindungi.
        </p>
    </div>
</body>
</html>
