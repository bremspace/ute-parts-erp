{{-- [F1-3] Setup 2FA — konten slot untuk layouts.backoffice (desktop-first backoffice) --}}
<div class="max-w-3xl mx-auto space-y-6" x-data="twoFactorSetup()">
    @if(session('message'))
        <div class="p-3.5 rounded-xl bg-up-mint/15 border border-up-mint/30 text-up-mint text-sm font-medium" role="status">
            {{ session('message') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-3.5 rounded-xl bg-up-red/15 border border-up-red/30 text-up-red text-sm font-medium">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Status --}}
    <x-prism.glass-card title="Status Keamanan" :subtitle="$required ? 'Role Anda wajib mengaktifkan 2FA (G-03).' : 'Aktifkan 2FA untuk perlindungan ekstra akun backoffice.'" :circuit="true">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                @if($enabled)
                    <x-prism.status-pill status="aktif" />
                    <span class="text-sm text-ink-300">
                        Aktif sejak {{ $user->two_factor_confirmed_at?->format('d/m/Y H:i') ?? '-' }}
                    </span>
                @else
                    <x-prism.status-pill status="pending" />
                    <span class="text-sm text-ink-300">Belum aktif — masuk tetap dilanjutkan tanpa kode (MVP tanpa lock-out).</span>
                @endif
            </div>
            <span class="text-xs text-ink-400 tabular-nums">
                Backup code tersisa: <strong class="text-ink-100">{{ $remainingBackupCodes }}</strong>/8
            </span>
        </div>
    </x-prism.glass-card>

    {{-- Backup codes — hanya sekali --}}
    @if(!empty($backupCodes))
        <x-prism.glass-card title="Backup Code (tampil sekali)" subtitle="Simpan di tempat aman. Setiap code hanya bisa dipakai satu kali." :circuit="true">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                @foreach($backupCodes as $code)
                    <div class="px-3 py-2 rounded-lg bg-black/30 border border-white/10 font-mono text-sm text-up-mint text-center tracking-wider select-all">
                        {{ $code }}
                    </div>
                @endforeach
            </div>
            <button type="button" class="text-xs text-up-primary hover:underline" onclick="navigator.clipboard && navigator.clipboard.writeText({{ json_encode($backupCodes) }}); showToast('Backup code disalin', 'success')">
                Salin semua ke clipboard
            </button>
        </x-prism.glass-card>
    @endif

    {{-- Setup / konfirmasi (belum aktif) --}}
    @if(! $enabled)
        <x-prism.glass-card title="Langkah 1 — Masukkan Secret ke Aplikasi" subtitle="Salin manual ke Google Authenticator, Authy, atau 2FA Manager." :circuit="true">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-ink-300 mb-1.5">Secret Key (manual entry)</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            readonly
                            value="{{ $secret }}"
                            class="flex-1 px-4 py-3 rounded-xl glass-input text-sm font-mono tracking-wider select-all"
                            onclick="this.select()"
                        />
                        <x-prism.prism-button type="button" variant="ghost" size="md" onclick="navigator.clipboard && navigator.clipboard.writeText({{ json_encode($secret) }}); showToast('Secret disalin', 'success')">
                            Salin
                        </x-prism.prism-button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-ink-300 mb-1.5">otpauth URL (alternatif manual)</label>
                    <textarea
                        readonly
                        rows="2"
                        class="w-full px-4 py-3 rounded-xl glass-input text-xs font-mono break-all"
                        onclick="this.select()"
                    >{{ $otpauthUrl }}</textarea>
                </div>

                <p class="text-[11px] text-ink-500">
                    QR code opsional di luar MVP — gunakan secret/URL di atas untuk input manual.
                </p>
            </div>
        </x-prism.glass-card>

        <x-prism.glass-card title="Langkah 2 — Konfirmasi Kode" subtitle="Masukkan kode 6 digit dari aplikasi untuk mengaktifkan." :circuit="true">
            <form action="{{ route('two-factor.enable') }}" method="POST" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="code" class="block text-xs font-medium text-ink-300 mb-1.5">Kode Verifikasi</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        required
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        placeholder="123456"
                        class="w-full px-4 py-3 rounded-xl glass-input text-sm text-center tracking-[0.3em] font-mono"
                        value="{{ old('code') }}"
                    />
                </div>
                <x-prism.prism-button type="submit" variant="primary" size="md">
                    Aktifkan 2FA
                </x-prism.prism-button>
            </form>
        </x-prism.glass-card>
    @else
        {{-- Sudah aktif: regen backup + disable --}}
        <x-prism.glass-card title="Backup Code" subtitle="Buat ulang 8 backup code (yang lama hangus). Butuh password akun." :circuit="true">
            <form action="{{ route('two-factor.backup-codes') }}" method="POST" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div class="flex-1">
                    <label for="password_regenerate" class="block text-xs font-medium text-ink-300 mb-1.5">Password Saat Ini</label>
                    <input
                        type="password"
                        id="password_regenerate"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 rounded-xl glass-input text-sm"
                    />
                </div>
                <x-prism.prism-button type="submit" variant="accent" size="md">
                    Buat Ulang Backup Code
                </x-prism.prism-button>
            </form>
        </x-prism.glass-card>

        <x-prism.glass-card title="Nonaktifkan 2FA" subtitle="Kembali ke login hanya dengan password. Butuh password akun." :circuit="true">
            <form action="{{ route('two-factor.disable') }}" method="POST"
                  class="flex flex-col sm:flex-row gap-3 sm:items-end"
                  onsubmit="return confirm('Nonaktifkan verifikasi dua faktor sekarang?')">
                @csrf
                <div class="flex-1">
                    <label for="password_disable" class="block text-xs font-medium text-ink-300 mb-1.5">Password Saat Ini</label>
                    <input
                        type="password"
                        id="password_disable"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="w-full px-4 py-3 rounded-xl glass-input text-sm"
                    />
                </div>
                <x-prism.prism-button type="submit" variant="danger" size="md">
                    Nonaktifkan 2FA
                </x-prism.prism-button>
            </form>
        </x-prism.glass-card>
    @endif
</div>

<script>
    function twoFactorSetup() {
        return {};
    }
</script>
