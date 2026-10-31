<?php

namespace App\Modules\Hr\Livewire;

use App\Models\User;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KaryawanRiwayat;
use App\Modules\Hr\Services\UserHrSyncService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * [F3-8 / Onboarding-Offboarding-History] Manajemen Karyawan, Rekrutmen, Pemutusan & Riwayat Karir Karyawan.
 */
#[Layout('layouts.backoffice')]
class KaryawanPage extends Component
{
    use WithPagination;

    // Filter & Search
    public string $search = '';

    public string $filterStatus = 'aktif'; // 'semua' | 'aktif' | 'nonaktif'

    public string $filterJabatan = '';

    public ?int $filterCabangId = null;

    // Modal Form Karyawan (Rekrut / Edit)
    public bool $showKaryawanModal = false;

    public bool $isEditMode = false;

    public ?int $selectedKaryawanId = null;

    public array $karyawanForm = [
        'nik' => '',
        'nama' => '',
        'telepon' => '',
        'email' => '',
        'jabatan' => 'staff',
        'cabang_id' => null,
        'tgl_masuk' => '',
        'gaji_pokok' => 0,
        'rekening_bank' => '',
        'buat_user' => true,
        'user_password' => '',
        'user_role' => 'karyawan',
    ];

    // Modal Pemutusan / Terminasi
    public bool $showTerminationModal = false;

    public ?Karyawan $karyawanToTerminate = null;

    public array $terminationForm = [
        'tgl_keluar' => '',
        'alasan_keluar' => 'resign',
        'catatan_keluar' => '',
    ];

    // Modal Tautkan Akun User Existing
    public bool $showLinkUserModal = false;

    public ?int $linkUserId = null;

    // ===== MODAL RIWAYAT / HISTORY KARYAWAN =====
    public bool $showHistoryModal = false;

    public ?Karyawan $historyKaryawan = null;

    public bool $showAddHistoryForm = false;

    public ?int $editingHistoryId = null;

    public array $historyForm = [
        'tipe_event' => 'catatan',
        'tanggal_efektif' => '',
        'judul' => '',
        'deskripsi' => '',
        'dokumen_referensi' => '',
        'jabatan_baru' => '',
        'cabang_baru_id' => null,
        'gaji_baru' => null,
    ];

    public function mount(): void
    {
        $this->filterCabangId = session('cabang_id');
        $this->karyawanForm['cabang_id'] = session('cabang_id') ?? Cabang::where('is_active', true)->value('id');
        $this->karyawanForm['tgl_masuk'] = now()->toDateString();
        $this->terminationForm['tgl_keluar'] = now()->toDateString();
        $this->historyForm['tanggal_efektif'] = now()->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterJabatan(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCabangId(): void
    {
        $this->resetPage();
    }

    /**
     * Buka modal rekrut / tambah karyawan baru.
     */
    public function openCreateModal(): void
    {
        $syncService = app(UserHrSyncService::class);

        $this->isEditMode = false;
        $this->selectedKaryawanId = null;
        $this->karyawanForm = [
            'nik' => $syncService->generateNik(),
            'nama' => '',
            'telepon' => '',
            'email' => '',
            'jabatan' => 'staff',
            'cabang_id' => session('cabang_id') ?? Cabang::where('is_active', true)->value('id'),
            'tgl_masuk' => now()->toDateString(),
            'gaji_pokok' => 0,
            'rekening_bank' => '',
            'buat_user' => true,
            'user_password' => '',
            'user_role' => 'karyawan',
        ];
        $this->showKaryawanModal = true;
    }

    /**
     * Buka modal edit data karyawan.
     */
    public function openEditModal(int $id): void
    {
        $k = Karyawan::with('user.roles')->findOrFail($id);

        $this->isEditMode = true;
        $this->selectedKaryawanId = $k->id;
        $this->karyawanForm = [
            'nik' => $k->nik ?? '',
            'nama' => $k->nama,
            'telepon' => $k->telepon ?? '',
            'email' => $k->email ?? $k->user?->email ?? '',
            'jabatan' => $k->jabatan,
            'cabang_id' => $k->cabang_id,
            'tgl_masuk' => $k->tgl_masuk ? $k->tgl_masuk->format('Y-m-d') : now()->toDateString(),
            'gaji_pokok' => (float) $k->gaji_pokok,
            'rekening_bank' => $k->rekening_bank ?? '',
            'buat_user' => false,
            'user_password' => '',
            'user_role' => $k->user?->roles->first()?->name ?? app(UserHrSyncService::class)->resolveRoleFromJabatan($k->jabatan),
        ];
        $this->showKaryawanModal = true;
    }

    /**
     * Simpan data karyawan (Rekrutmen Baru / Update Profil).
     */
    public function saveKaryawan(): void
    {
        $syncService = app(UserHrSyncService::class);

        $rules = [
            'karyawanForm.nama' => 'required|string|max:255',
            'karyawanForm.nik' => 'required|string|max:50|unique:karyawan,nik,'.($this->selectedKaryawanId ?: 'NULL').',id',
            'karyawanForm.jabatan' => 'required|string|in:teknisi,kasir,admin,marketing,manager,staff,other',
            'karyawanForm.cabang_id' => 'required|exists:cabang,id',
            'karyawanForm.tgl_masuk' => 'required|date',
            'karyawanForm.gaji_pokok' => 'nullable|numeric|min:0',
            'karyawanForm.telepon' => 'nullable|string|max:30',
            'karyawanForm.email' => 'nullable|email|max:100',
            'karyawanForm.rekening_bank' => 'nullable|string|max:100',
        ];

        // Jika mode rekrut baru dan opsi buat user dicentang
        if (! $this->isEditMode && ! empty($this->karyawanForm['buat_user'])) {
            $rules['karyawanForm.email'] = 'required|email|max:100|unique:users,email';
            $rules['karyawanForm.user_password'] = 'required|string|min:6';
            $rules['karyawanForm.user_role'] = 'required|string|exists:roles,name';
        }

        $this->validate($rules, [
            'karyawanForm.nama.required' => 'Nama karyawan wajib diisi.',
            'karyawanForm.nik.required' => 'NIK karyawan wajib diisi.',
            'karyawanForm.nik.unique' => 'NIK sudah digunakan karyawan lain.',
            'karyawanForm.cabang_id.required' => 'Cabang penempatan wajib dipilih.',
            'karyawanForm.email.unique' => 'Email ini sudah terdaftar sebagai akun pengguna.',
            'karyawanForm.user_password.required' => 'Password akun wajib diisi bila membuat akun login.',
            'karyawanForm.user_password.min' => 'Password akun minimal 6 karakter.',
        ]);

        DB::transaction(function () use ($syncService) {
            $cabangId = (int) $this->karyawanForm['cabang_id'];

            if (! $this->isEditMode) {
                // Rekrut Karyawan Baru
                $karyawan = Karyawan::create([
                    'nik' => trim($this->karyawanForm['nik']),
                    'nama' => trim($this->karyawanForm['nama']),
                    'telepon' => trim($this->karyawanForm['telepon'] ?? ''),
                    'email' => trim($this->karyawanForm['email'] ?? ''),
                    'jabatan' => $this->karyawanForm['jabatan'],
                    'cabang_id' => $cabangId,
                    'tgl_masuk' => $this->karyawanForm['tgl_masuk'],
                    'gaji_pokok' => $this->karyawanForm['gaji_pokok'] ?? 0,
                    'rekening_bank' => trim($this->karyawanForm['rekening_bank'] ?? ''),
                    'status_aktif' => true,
                ]);

                // Buat akun User jika diminta
                if (! empty($this->karyawanForm['buat_user'])) {
                    $syncService->createUserFromKaryawan(
                        $karyawan,
                        $this->karyawanForm['email'],
                        $this->karyawanForm['user_password'],
                        $this->karyawanForm['user_role'],
                        $cabangId
                    );
                }

                // Catat otomatis ke Riwayat Karyawan (Event: Rekrut)
                KaryawanRiwayat::create([
                    'karyawan_id' => $karyawan->id,
                    'user_id' => auth()->id(),
                    'tipe_event' => 'rekrut',
                    'tanggal_efektif' => $karyawan->tgl_masuk,
                    'judul' => 'Perekrutan / Onboarding Karyawan',
                    'deskripsi' => "Karyawan bergabung dengan posisi {$karyawan->jabatan} di cabang ".($karyawan->cabang?->nama ?? '-'),
                    'jabatan_baru' => $karyawan->jabatan,
                    'cabang_baru_id' => $karyawan->cabang_id,
                    'gaji_baru' => $karyawan->gaji_pokok,
                ]);

                app(AuditService::class)->catat('Karyawan', 'create', $karyawan->id, "Onboarding Karyawan Baru: {$karyawan->nama} ({$karyawan->nik}) - Jabatan: {$karyawan->jabatan}");
                $this->dispatch('alert', ['type' => 'success', 'message' => "Karyawan {$karyawan->nama} berhasil direkrut"]);
            } else {
                // Update Profil Karyawan & Deteksi Perubahan Signifikan
                $karyawan = Karyawan::findOrFail($this->selectedKaryawanId);

                $oldJabatan = $karyawan->jabatan;
                $oldCabangId = $karyawan->cabang_id;
                $oldGaji = (float) $karyawan->gaji_pokok;

                $newJabatan = $this->karyawanForm['jabatan'];
                $newCabangId = $cabangId;
                $newGaji = (float) ($this->karyawanForm['gaji_pokok'] ?? 0);

                $karyawan->update([
                    'nik' => trim($this->karyawanForm['nik']),
                    'nama' => trim($this->karyawanForm['nama']),
                    'telepon' => trim($this->karyawanForm['telepon'] ?? ''),
                    'email' => trim($this->karyawanForm['email'] ?? ''),
                    'jabatan' => $newJabatan,
                    'cabang_id' => $newCabangId,
                    'tgl_masuk' => $this->karyawanForm['tgl_masuk'],
                    'gaji_pokok' => $newGaji,
                    'rekening_bank' => trim($this->karyawanForm['rekening_bank'] ?? ''),
                ]);

                // Sinkronkan nama ke akun user jika tertaut
                if ($karyawan->user_id && $user = $karyawan->user) {
                    $user->update([
                        'name' => $karyawan->nama,
                        'phone' => $karyawan->telepon,
                    ]);
                }

                // Auto-create history jika terjadi mutasi cabang
                if ($oldCabangId !== $newCabangId) {
                    KaryawanRiwayat::create([
                        'karyawan_id' => $karyawan->id,
                        'user_id' => auth()->id(),
                        'tipe_event' => 'mutasi',
                        'tanggal_efektif' => now()->toDateString(),
                        'judul' => 'Mutasi Cabang Penugasan',
                        'deskripsi' => "Perpindahan penugasan cabang dari {$karyawan->cabang?->nama} ke ".Cabang::find($newCabangId)?->nama,
                        'cabang_lama_id' => $oldCabangId,
                        'cabang_baru_id' => $newCabangId,
                    ]);
                }

                // Auto-create history jika terjadi perubahan jabatan
                if ($oldJabatan !== $newJabatan) {
                    KaryawanRiwayat::create([
                        'karyawan_id' => $karyawan->id,
                        'user_id' => auth()->id(),
                        'tipe_event' => 'promosi',
                        'tanggal_efektif' => now()->toDateString(),
                        'judul' => "Perubahan Jabatan ({$oldJabatan} → {$newJabatan})",
                        'deskripsi' => "Penyesuaian posisi jabatan karyawan dari {$oldJabatan} menjadi {$newJabatan}",
                        'jabatan_lama' => $oldJabatan,
                        'jabatan_baru' => $newJabatan,
                    ]);
                }

                // Auto-create history jika terjadi perubahan gaji pokok
                if (abs($oldGaji - $newGaji) > 0.01) {
                    KaryawanRiwayat::create([
                        'karyawan_id' => $karyawan->id,
                        'user_id' => auth()->id(),
                        'tipe_event' => 'perubahan_gaji',
                        'tanggal_efektif' => now()->toDateString(),
                        'judul' => 'Penyesuaian Gaji Pokok',
                        'deskripsi' => 'Perubahan nominal gaji pokok dari Rp '.number_format($oldGaji, 0, ',', '.').' menjadi Rp '.number_format($newGaji, 0, ',', '.'),
                        'gaji_lama' => $oldGaji,
                        'gaji_baru' => $newGaji,
                    ]);
                }

                app(AuditService::class)->catat('Karyawan', 'update', $karyawan->id, "Update profil karyawan {$karyawan->nama} ({$karyawan->nik})");
                $this->dispatch('alert', ['type' => 'success', 'message' => "Data karyawan {$karyawan->nama} berhasil diperbarui"]);
            }
        });

        $this->showKaryawanModal = false;
    }

    /**
     * Buka modal pemutusan hubungan kerja (Offboarding / Terminasi).
     */
    public function openTerminationModal(int $id): void
    {
        $this->karyawanToTerminate = Karyawan::with('user')->findOrFail($id);
        $this->terminationForm = [
            'tgl_keluar' => now()->toDateString(),
            'alasan_keluar' => 'resign',
            'catatan_keluar' => '',
        ];
        $this->showTerminationModal = true;
    }

    /**
     * Eksekusi pemutusan hubungan kerja karyawan.
     */
    public function executeTermination(): void
    {
        if (! $this->karyawanToTerminate) {
            return;
        }

        $this->validate([
            'terminationForm.tgl_keluar' => 'required|date',
            'terminationForm.alasan_keluar' => 'required|string|max:50',
            'terminationForm.catatan_keluar' => 'nullable|string|max:1000',
        ]);

        app(UserHrSyncService::class)->terminateKaryawan(
            $this->karyawanToTerminate,
            $this->terminationForm['alasan_keluar'],
            $this->terminationForm['tgl_keluar'],
            $this->terminationForm['catatan_keluar']
        );

        $nama = $this->karyawanToTerminate->nama;
        $this->showTerminationModal = false;
        $this->karyawanToTerminate = null;

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Karyawan {$nama} telah dinonaktifkan. Akun login sistem terkait otomatis dicabut.",
        ]);
    }

    /**
     * Mempekerjakan kembali karyawan yang sebelumnya terminated.
     */
    public function rehireKaryawan(int $id): void
    {
        $k = Karyawan::findOrFail($id);
        app(UserHrSyncService::class)->rehireKaryawan($k);

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Karyawan {$k->nama} berhasil diaktifkan kembali (Re-hire).",
        ]);
    }

    /**
     * Buka modal tautkan user yang ada ke karyawan ini.
     */
    public function openLinkUserModal(int $karyawanId): void
    {
        $this->selectedKaryawanId = $karyawanId;
        $this->linkUserId = null;
        $this->showLinkUserModal = true;
    }

    /**
     * Tautkan user existing ke karyawan.
     */
    public function saveLinkUser(): void
    {
        $this->validate([
            'linkUserId' => 'required|exists:users,id',
        ], [
            'linkUserId.required' => 'Pilih akun pengguna yang ingin ditautkan.',
        ]);

        $karyawan = Karyawan::findOrFail($this->selectedKaryawanId);
        app(UserHrSyncService::class)->linkUserToKaryawan($karyawan, (int) $this->linkUserId);

        $this->showLinkUserModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => "Akun login berhasil ditautkan ke {$karyawan->nama}"]);
    }

    /**
     * Putuskan kaitan akun login user dari karyawan.
     */
    public function unlinkUser(int $karyawanId): void
    {
        $karyawan = Karyawan::findOrFail($karyawanId);
        app(UserHrSyncService::class)->unlinkUserFromKaryawan($karyawan);

        $this->dispatch('alert', ['type' => 'success', 'message' => "Tautan akun pengguna dari {$karyawan->nama} berhasil dilepas"]);
    }

    // ===== MODAL RIWAYAT / HISTORY MANAGEMENT =====

    /**
     * Buka modal timeline riwayat karyawan.
     */
    public function openHistoryModal(int $karyawanId): void
    {
        $this->historyKaryawan = Karyawan::with(['riwayats.author', 'riwayats.cabangLama', 'riwayats.cabangBaru', 'cabang'])
            ->findOrFail($karyawanId);

        $this->showAddHistoryForm = false;
        $this->editingHistoryId = null;
        $this->resetHistoryForm();
        $this->showHistoryModal = true;
    }

    /**
     * Reset form input/edit riwayat.
     */
    private function resetHistoryForm(): void
    {
        $this->historyForm = [
            'tipe_event' => 'catatan',
            'tanggal_efektif' => now()->toDateString(),
            'judul' => '',
            'deskripsi' => '',
            'dokumen_referensi' => '',
            'jabatan_baru' => '',
            'cabang_baru_id' => null,
            'gaji_baru' => null,
        ];
    }

    /**
     * Tampilkan form tambah riwayat manual.
     */
    public function showCreateHistory(): void
    {
        $this->editingHistoryId = null;
        $this->resetHistoryForm();
        $this->showAddHistoryForm = true;
    }

    /**
     * Buka form edit untuk entri riwayat tertentu.
     */
    public function editHistory(int $historyId): void
    {
        $h = KaryawanRiwayat::findOrFail($historyId);
        $this->editingHistoryId = $h->id;
        $this->historyForm = [
            'tipe_event' => $h->tipe_event,
            'tanggal_efektif' => $h->tanggal_efektif->format('Y-m-d'),
            'judul' => $h->judul,
            'deskripsi' => $h->deskripsi ?? '',
            'dokumen_referensi' => $h->dokumen_referensi ?? '',
            'jabatan_baru' => $h->jabatan_baru ?? '',
            'cabang_baru_id' => $h->cabang_baru_id,
            'gaji_baru' => $h->gaji_baru ? (float) $h->gaji_baru : null,
        ];
        $this->showAddHistoryForm = true;
    }

    /**
     * Simpan entri riwayat karyawan (tambah baru atau update).
     */
    public function saveHistory(): void
    {
        if (! $this->historyKaryawan) {
            return;
        }

        $this->validate([
            'historyForm.tipe_event' => 'required|string|in:rekrut,promosi,mutasi,perubahan_gaji,terminasi,rehire,peringatan,penghargaan,catatan',
            'historyForm.tanggal_efektif' => 'required|date',
            'historyForm.judul' => 'required|string|max:255',
            'historyForm.deskripsi' => 'nullable|string|max:2000',
            'historyForm.dokumen_referensi' => 'nullable|string|max:100',
            'historyForm.jabatan_baru' => 'nullable|string|max:50',
            'historyForm.cabang_baru_id' => 'nullable|exists:cabang,id',
            'historyForm.gaji_baru' => 'nullable|numeric|min:0',
        ], [
            'historyForm.judul.required' => 'Judul peristiwa riwayat wajib diisi.',
            'historyForm.tanggal_efektif.required' => 'Tanggal efektif wajib diisi.',
        ]);

        if ($this->editingHistoryId) {
            $h = KaryawanRiwayat::findOrFail($this->editingHistoryId);
            $h->update([
                'tipe_event' => $this->historyForm['tipe_event'],
                'tanggal_efektif' => $this->historyForm['tanggal_efektif'],
                'judul' => trim($this->historyForm['judul']),
                'deskripsi' => trim($this->historyForm['deskripsi'] ?? ''),
                'dokumen_referensi' => trim($this->historyForm['dokumen_referensi'] ?? ''),
                'jabatan_baru' => $this->historyForm['jabatan_baru'] ?: null,
                'cabang_baru_id' => $this->historyForm['cabang_baru_id'] ?: null,
                'gaji_baru' => $this->historyForm['gaji_baru'] ?: null,
            ]);
            $msg = 'Catatan riwayat berhasil diperbarui';
        } else {
            KaryawanRiwayat::create([
                'karyawan_id' => $this->historyKaryawan->id,
                'user_id' => auth()->id(),
                'tipe_event' => $this->historyForm['tipe_event'],
                'tanggal_efektif' => $this->historyForm['tanggal_efektif'],
                'judul' => trim($this->historyForm['judul']),
                'deskripsi' => trim($this->historyForm['deskripsi'] ?? ''),
                'dokumen_referensi' => trim($this->historyForm['dokumen_referensi'] ?? ''),
                'jabatan_lama' => $this->historyKaryawan->jabatan,
                'jabatan_baru' => $this->historyForm['jabatan_baru'] ?: null,
                'cabang_lama_id' => $this->historyKaryawan->cabang_id,
                'cabang_baru_id' => $this->historyForm['cabang_baru_id'] ?: null,
                'gaji_lama' => $this->historyKaryawan->gaji_pokok,
                'gaji_baru' => $this->historyForm['gaji_baru'] ?: null,
            ]);
            $msg = 'Catatan riwayat baru berhasil ditambahkan ke timeline';
        }

        $this->historyKaryawan->load(['riwayats.author', 'riwayats.cabangLama', 'riwayats.cabangBaru']);
        $this->showAddHistoryForm = false;
        $this->editingHistoryId = null;
        $this->resetHistoryForm();

        $this->dispatch('alert', ['type' => 'success', 'message' => $msg]);
    }

    /**
     * Hapus entri riwayat.
     */
    public function deleteHistory(int $historyId): void
    {
        $h = KaryawanRiwayat::findOrFail($historyId);
        $h->delete();

        if ($this->historyKaryawan) {
            $this->historyKaryawan->load(['riwayats.author', 'riwayats.cabangLama', 'riwayats.cabangBaru']);
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Entri riwayat berhasil dihapus']);
    }

    public function render(): View
    {
        $isSuperAdmin = auth()->user()?->hasRole('super-admin') || auth()->user()?->hasRole('owner');

        $query = Karyawan::with(['cabang', 'user.roles']);

        // Scope Cabang: jika bukan super admin, batasi ke cabang aktif
        if (! $isSuperAdmin) {
            $cabangAktif = session('cabang_id');
            if ($cabangAktif) {
                $query->where('cabang_id', $cabangAktif);
            }
        } elseif ($this->filterCabangId) {
            $query->where('cabang_id', $this->filterCabangId);
        }

        // Scope Status
        if ($this->filterStatus === 'aktif') {
            $query->where('status_aktif', true);
        } elseif ($this->filterStatus === 'nonaktif') {
            $query->where('status_aktif', false);
        }

        // Scope Jabatan
        if ($this->filterJabatan) {
            $query->where('jabatan', $this->filterJabatan);
        }

        // Search
        if ($this->search !== '') {
            $s = '%'.$this->search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('nama', 'like', $s)
                    ->orWhere('nik', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhere('telepon', 'like', $s);
            });
        }

        $karyawans = $query->orderBy('status_aktif', 'desc')
            ->orderBy('nama', 'asc')
            ->paginate(15);

        // Ringkasan metrik
        $summaryQuery = Karyawan::query();
        if (! $isSuperAdmin && session('cabang_id')) {
            $summaryQuery->where('cabang_id', session('cabang_id'));
        }

        $totalKaryawan = (clone $summaryQuery)->count();
        $totalAktif = (clone $summaryQuery)->where('status_aktif', true)->count();
        $totalTerminated = (clone $summaryQuery)->where('status_aktif', false)->count();
        $totalLinkedUser = (clone $summaryQuery)->whereNotNull('user_id')->count();

        $daftarCabang = Cabang::where('is_active', true)->orderBy('nama')->get();
        $roles = Role::orderBy('name')->pluck('name')->toArray();

        // User kandidat untuk ditautkan (user yang belum punya karyawan)
        $unlinkedUsers = User::whereDoesntHave('karyawan')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('modules.hr.livewire.karyawan-page', [
            'karyawans' => $karyawans,
            'daftarCabang' => $daftarCabang,
            'roles' => $roles,
            'unlinkedUsers' => $unlinkedUsers,
            'totalKaryawan' => $totalKaryawan,
            'totalAktif' => $totalAktif,
            'totalTerminated' => $totalTerminated,
            'totalLinkedUser' => $totalLinkedUser,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }
}
