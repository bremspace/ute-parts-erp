<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan — Ute Parts</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-up-ink-50 text-ink-900 font-sans antialiased min-h-screen flex flex-col items-center justify-center p-4 relative overflow-hidden selection:bg-up-primary selection:text-white">
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-up-primary/20 rounded-full blur-[128px] pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-up-accent/15 rounded-full blur-[128px] pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <a href="{{ route('shop') }}" class="inline-flex">
                <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-up-primary via-indigo-500 to-up-accent items-center justify-center shadow-xl shadow-up-primary/30 mb-4 border border-white/20">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </a>
            <h1 class="text-2xl font-bold text-ink-900 tracking-wide">Daftar Gratis</h1>
            <p class="text-sm text-ink-500 mt-1">Satu langkah cepat — cuma butuh nomor HP</p>
        </div>

        <div class="bg-white rounded-3xl shadow-xl shadow-ink-100/50 border border-ink-100 p-8 relative overflow-hidden">
            <div class="circuit-line absolute top-0 left-0 w-full h-[2px]"></div>

            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-up-red/10 border border-up-red/20 text-up-red text-xs font-medium">
                    @foreach($errors->all() as $e)
                        <p>{{ $e }}</p>
                    @endforeach
                </div>
            @endif

            <form action="{{ route('customer.register.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="nama" class="block text-xs font-semibold text-ink-700 mb-1.5">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" required autofocus placeholder="Nama Anda"
                        class="w-full px-4 py-3 rounded-xl bg-up-ink-50 border border-ink-100 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium" />
                </div>
                <div>
                    <label for="telepon" class="block text-xs font-semibold text-ink-700 mb-1.5">Nomor HP</label>
                    <input type="text" id="telepon" name="telepon" required placeholder="08xx-xxxx-xxxx"
                        class="w-full px-4 py-3 rounded-xl bg-up-ink-50 border border-ink-100 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium" />
                    <p class="text-[11px] text-ink-400 mt-1">Nomor ini dipakai untuk login & verifikasi OTP (menyusul).</p>
                </div>
                <div>
                    <label for="password" class="block text-xs font-semibold text-ink-700 mb-1.5">Kata Sandi</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="Minimal 6 karakter"
                        class="w-full px-4 py-3 rounded-xl bg-up-ink-50 border border-ink-100 focus:border-up-primary focus:ring-2 focus:ring-up-primary/20 outline-none text-sm font-medium" />
                </div>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-gradient-to-r from-up-primary to-indigo-600 hover:from-up-primary-dark text-white font-bold text-sm shadow-lg shadow-up-primary/25 transition-all cursor-pointer active:scale-[0.99]">
                    Daftar & Lanjut Checkout
                </button>
            </form>

            <p class="text-center text-xs text-ink-500 mt-5">
                Sudah punya akun?
                <a href="{{ route('customer.login') }}" class="text-up-primary font-bold hover:underline">Masuk di sini</a>
            </p>
        </div>

        <p class="text-center text-xs text-ink-400 mt-6">
            <a href="{{ route('shop') }}" class="hover:text-up-primary">← Kembali ke katalog</a>
        </p>
    </div>
</body>
</html>