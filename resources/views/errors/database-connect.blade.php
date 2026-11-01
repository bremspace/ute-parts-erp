<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koneksi Database Terputus · {{ config('app.name', 'ERP & POS System') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #090D16; color: #f1f5f9; font-family: 'Instrument Sans', system-ui, sans-serif; }
        .glass-panel { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .brand-gradient { background: linear-gradient(135deg, #5B4FE9 0%, #E8873B 100%); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="w-full max-w-lg relative z-10 text-center space-y-6">
        <!-- Logo / Icon -->
        <div class="inline-flex w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/30 items-center justify-center text-3xl shadow-xl shadow-amber-500/10">
            🔌
        </div>

        <div class="glass-panel p-6 sm:p-8 rounded-3xl text-left space-y-5 border border-white/10 shadow-2xl">
            <div class="border-b border-white/10 pb-4">
                <span class="text-[10px] px-2.5 py-1 rounded-full bg-rose-500/20 text-rose-300 font-bold border border-rose-500/30 uppercase tracking-wider">
                    Database Connection Refused
                </span>
                <h1 class="text-xl font-bold text-white mt-2">
                    Layanan MySQL Tidak Terhubung
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    Aplikasi tidak dapat menghubungi server database di <code class="text-amber-300 bg-black/40 px-1.5 py-0.5 rounded font-mono">{{ $dbHost }}:{{ $dbPort }}</code> (Database: <code class="text-amber-300 bg-black/40 px-1.5 py-0.5 rounded font-mono">{{ $dbName }}</code>).
                </p>
            </div>

            <!-- Petunjuk Solusi -->
            <div class="space-y-3 text-xs text-slate-300">
                <div class="font-bold text-white text-xs flex items-center gap-2">
                    <span>💡</span> <span>Langkah Pemulihan:</span>
                </div>
                <div class="p-3.5 rounded-xl bg-black/50 border border-white/5 space-y-2">
                    <div>
                        <strong class="text-amber-300">Opsi 1: Nyalakan Service MySQL</strong>
                        <ul class="list-disc list-inside text-slate-400 text-[11px] mt-1 space-y-0.5">
                            <li><strong>Windows (XAMPP):</strong> Buka XAMPP Control Panel, klik tombol <em>Start</em> pada MySQL.</li>
                            <li><strong>Windows (Laragon):</strong> Buka Laragon, klik tombol <em>Start All</em>.</li>
                            <li><strong>Linux:</strong> Jalankan perintah <code class="text-white bg-slate-800 px-1 rounded">sudo systemctl start mysql</code> di terminal.</li>
                        </ul>
                    </div>
                    <div class="pt-2 border-t border-white/5">
                        <strong class="text-indigo-300">Opsi 2: Gunakan SQLite (Tanpa Perlu Pasang MySQL)</strong>
                        <p class="text-slate-400 text-[11px] mt-0.5">
                            Jika komputer ini tidak memiliki MySQL, Anda dapat mengganti database ke <strong>SQLite</strong> (bawaan PHP, mandiri &amp; zero-config) melalui Setup Wizard.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                <a href="/install.php" class="w-full sm:w-auto px-5 py-2.5 rounded-xl brand-gradient hover:opacity-95 text-white font-bold text-xs transition text-center shadow-lg shadow-indigo-600/20">
                    Buka Setup Wizard / Ganti Database →
                </a>
                <button type="button" onclick="window.location.reload()" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-slate-200 font-bold text-xs border border-white/10 transition text-center cursor-pointer">
                    Coba Hubungkan Ulang ↻
                </button>
            </div>
        </div>

        <p class="text-xs text-slate-500 font-mono">
            ERP &amp; POS System &bull; Port 1741
        </p>
    </div>
</body>
</html>
