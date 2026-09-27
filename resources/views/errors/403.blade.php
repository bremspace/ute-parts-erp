<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Akses Ditolak · Ute Parts</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/icon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-950 text-ink-100 font-sans antialiased min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-up-red/20 rounded-full blur-[128px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10 text-center">
        <div class="mb-5 flex justify-center">
            <x-prism.logo size="md" :with-text="true" mode="dark" subtitle="Keamanan Sistem" :href="url('/')" />
        </div>

        <div class="glass-panel p-8 rounded-3xl relative overflow-hidden border border-white/10 shadow-2xl">
            <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>

            <span class="inline-block text-5xl font-black text-up-red mb-2">403</span>
            <h1 class="text-xl font-bold text-white mb-2">Akses Tidak Diizinkan</h1>
            <p class="text-sm text-ink-400 mb-6 leading-relaxed">
                Anda tidak memiliki hak akses atau izin yang memadai untuk membuka halaman ini.
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ url('/app/dashboard') }}" class="px-5 py-3 rounded-xl bg-up-primary hover:bg-up-primary-dark text-white font-bold text-xs transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center">
                    Dashboard
                </a>
                <button type="button" onclick="window.history.back()" class="px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-ink-300 font-semibold text-xs border border-white/10 transition-[transform,background-color] active:scale-[0.97] min-h-[44px] flex items-center justify-center cursor-pointer">
                    Kembali
                </button>
            </div>
        </div>
    </div>
</body>
</html>
