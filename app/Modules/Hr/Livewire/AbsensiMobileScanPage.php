<?php

declare(strict_types=1);

namespace App\Modules\Hr\Livewire;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\AbsensiQrService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.mobile-kiosk')]
#[Title('Mesin Absensi Mobile — UTEParts')]
class AbsensiMobileScanPage extends Component
{
    #[Url(as: 't')]
    public string $token = '';

    // Auth state
    public bool $isLoggedIn = false;
    public ?Karyawan $karyawan = null;

    // Login form fields (jika belum login)
    public string $loginIdentifier = '';
    public string $password = '';
    public bool $rememberMe = true;

    // Feedback state
    public ?array $result = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->token = trim((string) request()->query('t', $this->token));

        // 1. Cek apakah user sedang terautentikasi
        if (Auth::check()) {
            $this->isLoggedIn = true;
            $this->initAndAutoAbsen();
        } else {
            $this->isLoggedIn = false;
            // Simpan intended URL agar jika login lewat /app/login tetap kembali ke token ini
            session()->put('url.intended', request()->fullUrl());
        }
    }

    /**
     * Inisialisasi data karyawan dari user login & langsung eksekusi auto-checkin
     */
    protected function initAndAutoAbsen(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->isLoggedIn = false;
            return;
        }

        $this->karyawan = Karyawan::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();

        if (! $this->karyawan) {
            $this->errorMessage = 'Akun login Anda ('.$user->email.') belum tertaut dengan data Karyawan. Hubungi HR.';
            return;
        }

        if (empty($this->token)) {
            $this->errorMessage = 'Token QR absensi tidak ditemukan. Silakan scan QR Code terbaru di mesin absensi toko.';
            return;
        }

        // Jalankan auto check-in / check-out seketika!
        $this->eksekusiAbsen();
    }

    /**
     * Login dari form mobile scan lalu langsung auto check-in
     */
    public function loginDanAbsen(): void
    {
        $this->errorMessage = null;
        $identifier = trim($this->loginIdentifier);
        $password = (string) $this->password;

        if ($identifier === '' || $password === '') {
            $this->errorMessage = 'Silakan masukkan Email / NIK dan kata sandi Anda.';
            return;
        }

        $email = $identifier;
        if (! str_contains($identifier, '@')) {
            // Identifier berupa NIK: cari user email yang tertaut
            $k = Karyawan::where('nik', $identifier)->first();
            if ($k && $k->user) {
                $email = $k->user->email;
            } elseif ($k && ! empty($k->email)) {
                $email = $k->email;
            }
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password], $this->rememberMe)) {
            $this->errorMessage = 'Email / NIK atau kata sandi yang Anda masukkan salah.';
            return;
        }

        $user = Auth::user();
        if (! ($user->is_active ?? true)) {
            Auth::logout();
            $this->errorMessage = 'Akun Anda dinonaktifkan. Hubungi HR / Administrator.';
            return;
        }

        $this->isLoggedIn = true;
        $this->password = '';
        $this->initAndAutoAbsen();
    }

    /**
     * Eksekusi presensi via AbsensiQrService
     */
    public function eksekusiAbsen(): void
    {
        if (! $this->karyawan) {
            $this->errorMessage = 'Data profil karyawan tidak ditemukan.';
            return;
        }

        if (empty($this->token)) {
            $this->errorMessage = 'Token QR Code tidak valid atau sudah kedaluwarsa.';
            return;
        }

        $qrService = app(AbsensiQrService::class);
        $res = $qrService->processScan($this->token, $this->karyawan->nik ?? (string) $this->karyawan->id, request()->ip());

        if (! ($res['success'] ?? false)) {
            $this->errorMessage = $res['message'] ?? 'Gagal memproses absensi.';
            $this->result = null;
            return;
        }

        $this->errorMessage = null;
        $this->result = $res;

        $this->dispatch('absensi-success', [
            'action' => $res['action'] ?? 'absen',
            'nama' => $res['karyawan']['nama'] ?? $this->karyawan->nama,
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->isLoggedIn = false;
        $this->karyawan = null;
        $this->result = null;
        $this->errorMessage = 'Anda berhasil keluar dari akun.';
    }

    public function render()
    {
        return view('modules.hr.mobile-scan');
    }
}
