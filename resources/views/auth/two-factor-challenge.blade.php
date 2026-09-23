<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi 2FA — Ute Parts</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-950 text-ink-100 font-sans antialiased min-h-screen flex items-center justify-center p-4 relative overflow-hidden selection:bg-up-primary selection:text-white">
    <!-- Ambient Background Glow -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-up-primary/30 rounded-full blur-[128px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-up-accent/20 rounded-full blur-[128px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-up-primary via-indigo-500 to-up-accent items-center justify-center shadow-xl shadow-up-primary/30 mb-4 border border-white/20">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">Verifikasi Dua Faktor</h1>
            <p class="text-sm text-ink-400 mt-1">Masukkan kode dari aplikasi authenticator Anda</p>
        </div>

        <div class="glass-panel p-8 rounded-3xl relative overflow-hidden border border-white/10 shadow-2xl">
            <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>

            <div class="mb-5 flex items-center gap-2 text-xs text-ink-400">
                <span class="w-2 h-2 rounded-full bg-up-mint animate-pulse"></span>
                <span>{{ $email ?? '' }}</span>
            </div>

            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-up-red/15 border border-up-red/30 text-up-red text-xs font-medium">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('two-factor.challenge.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-xs font-medium text-ink-300 mb-1.5">Kode Verifikasi (TOTP) atau Backup Code</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        inputmode="numeric"
                        maxlength="32"
                        placeholder="123456 atau XXXXX-XXXXX"
                        class="w-full px-4 py-3 rounded-xl glass-input text-sm text-center tracking-[0.3em] font-mono"
                        value="{{ old('code') }}"
                    />
                    <p class="text-[11px] text-ink-500 mt-1.5">
                        6 digit dari Google Authenticator / Authy, atau salah satu dari 8 backup code.
                    </p>
                </div>

                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full py-3.5 px-4 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark hover:to-indigo-700 text-white font-semibold text-sm shadow-lg shadow-up-primary/30 transition-all duration-200 cursor-pointer border border-white/10 active:scale-[0.99]"
                    >
                        Verifikasi &amp; Masuk
                    </button>
                </div>
            </form>

            <p class="text-center text-xs text-ink-500 mt-6">
                <a href="{{ route('login') }}" class="text-up-primary hover:underline">← Kembali ke login</a>
            </p>
        </div>

        <p class="text-center text-xs text-ink-500 mt-6">
            Ute Parts ERP &copy; 2026. Hak Cipta Dilindungi.
        </p>
    </div>
</body>
</html>
