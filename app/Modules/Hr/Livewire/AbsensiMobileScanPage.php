<?php

declare(strict_types=1);

namespace App\Modules\Hr\Livewire;

use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Services\AbsensiQrService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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

    public string $nikInput = '';
    public bool $rememberDevice = true;
    public ?array $result = null;
    public ?string $errorMessage = null;

    public function mount(): void
    {
        // Ambil token dari URL jika ada
        $this->token = trim((string) request()->query('t', $this->token));

        if ($this->token === '') {
            $this->errorMessage = 'Tautan tidak valid atau tidak memiliki token QR absensi.';
            return;
        }

        // Cek apakah perangkat sudah mengingat NIK karyawan sebelumnya
        $savedNik = Cookie::get('karyawan_device_nik');
        if ($savedNik) {
            $this->nikInput = (string) $savedNik;
        }

        // Jika user sedang login di backoffice/portal, utamakan akun mereka
        if (Auth::check()) {
            $userId = Auth::id();
            if ($userId) {
                $karyawan = Karyawan::where('user_id', $userId)->first();
                if ($karyawan && $karyawan->nik) {
                    $this->nikInput = $karyawan->nik;
                }
            }
        }
    }

    public function submitAbsensi(): void
    {
        $nik = trim($this->nikInput);
        if ($nik === '') {
            $this->errorMessage = 'Silakan masukkan NIK atau ID Karyawan Anda.';
            return;
        }

        if ($this->token === '') {
            $this->errorMessage = 'Token QR tidak valid.';
            return;
        }

        $qrService = app(AbsensiQrService::class);
        $res = $qrService->processScan($this->token, $nik, request()->ip());

        if (! ($res['success'] ?? false)) {
            $this->errorMessage = $res['message'] ?? 'Gagal memproses absensi.';
            $this->result = null;
            return;
        }

        $this->errorMessage = null;
        $this->result = $res;

        // Simpan NIK di cookie jika opsi ingat perangkat dicentang
        if ($this->rememberDevice) {
            Cookie::queue('karyawan_device_nik', $nik, 60 * 24 * 30); // 30 hari
        }

        $this->dispatch('absensi-success', [
            'action' => $res['action'] ?? 'absen',
            'nama' => $res['karyawan']['nama'] ?? 'Karyawan',
        ]);
    }

    public function render()
    {
        return view('modules.hr.mobile-scan');
    }
}
