<?php

namespace App\Modules\Marketplace\Livewire;

use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dashboard pelanggan marketplace (ACCOUNT-01..05):
 * riwayat order, tracking servis, komisi reseller, status membership, poin loyalty,
 * serta manajemen profil konsumen (ganti username, profil picture, & password).
 */
class CustomerAccount extends Component
{
    use WithFileUploads;

    public string $activeTab = 'orders'; // orders, servis, notifikasi, komisi, membership, profil

    // Profil Form State
    public string $nama = '';

    public string $username = '';

    public string $email = '';

    public string $alamat = '';

    public $avatarUpload = null;

    // Ganti Password Form State
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPassword_confirmation = '';

    public function mount(): void
    {
        $tab = request()->query('tab');
        if ($tab && in_array($tab, ['orders', 'servis', 'notifikasi', 'komisi', 'membership', 'profil'], true)) {
            $this->activeTab = $tab;
        }

        $customer = auth('customer')->user();
        if ($customer) {
            $this->nama = $customer->nama ?? '';
            $this->username = $customer->username ?? '';
            $this->email = $customer->email ?? '';
            $this->alamat = $customer->alamat ?? '';
        }
    }

    public function getOrdersProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        return Transaksi::with(['items.produk', 'cabang', 'pengiriman'])
            ->where('pelanggan_id', $customer->id)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function getNotifikasiProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        // Auto-resolve notifikasi menunggu pembayaran untuk pesanan yang sudah lunas/selesai
        $unreadOrdersNotif = NotifikasiKeluar::forPelanggan($customer->id)
            ->whereNull('read_at')
            ->whereNotNull('payload->transaksi_id')
            ->get();

        foreach ($unreadOrdersNotif as $notif) {
            $transaksiId = $notif->payload['transaksi_id'] ?? null;
            if ($transaksiId) {
                $transaksi = Transaksi::find($transaksiId);
                if ($transaksi && in_array($transaksi->status, ['lunas', 'diproses', 'dikirim', 'siap_diambil', 'selesai', 'batal'], true)) {
                    $judul = strtolower($notif->judul ?? '');
                    $konten = strtolower($notif->konten ?? '');
                    if (str_contains($judul, 'pesanan berhasil didaftarkan') || str_contains($konten, 'selesaikan pembayaran')) {
                        $notif->update(['read_at' => now()]);
                    }
                }
            }
        }

        return NotifikasiKeluar::forPelanggan($customer->id)
            ->latest()
            ->limit(20)
            ->get();
    }

    public function markNotifikasiRead(int $id): void
    {
        $notif = NotifikasiKeluar::find($id);
        if ($notif && $notif->pelanggan_id === auth('customer')->id()) {
            $notif->update(['read_at' => now()]);
        }
    }

    /**
     * [T-05] Metode `bayarPesanan()` simulasi (status lunas + stok + jurnal
     * langsung dari frontend) DIHAPUS — pembayaran kini alur nyata Duitku:
     *   1. UI (Alpine) ambil daftar metode  → GET  /api/payment/methods?amount=...
     *   2. Konfirmasi metode                 → POST /api/payment/create
     *   3. Redirect browser ke `payment_url` (halaman checkout Duitku).
     * Konfirmasi lunas, potong stok, jurnal, komisi & notifikasi semuanya
     * diproses oleh webhook PAY-02 (`PaymentController::webhook`) — tidak ada
     * lagi penulisan status dari sisi frontend.
     */

    /**
     * Batalkan pesanan oleh konsumen jika belum dibayar.
     */
    public function batalkanPesanan(int $id): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $transaksi = Transaksi::where('pelanggan_id', $customer->id)
            ->where('id', $id)
            ->where('status', 'menunggu_pembayaran')
            ->firstOrFail();

        $transaksi->update([
            'status' => 'batal',
            'catatan' => trim(($transaksi->catatan ?? '').' [Dibatalkan oleh konsumen]'),
        ]);

        $this->dispatch('alert', ['type' => 'info', 'message' => "Pesanan {$transaksi->no_transaksi} telah dibatalkan."]);
    }

    public function getServisProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return collect();
        }

        // [B-15c] Select eksplisit — `foto_unit` (base64, ratusan KB per tiket) tidak
        // pernah dirender di halaman pelanggan (hanya status/keluhan/garansi/link
        // tracking), tapi ikut ter-fetch karena `select *`.
        //
        // CATATAN: baris TIDAK dipotong dengan limit — kartu statistik di header
        // memakai `$servis->count()` dari koleksi yang sama, jadi limit akan
        // diam-diam mengubah angka. Scope per pelanggan (where pelanggan_id) tetap.
        return TiketServis::with('garansi', 'jenisServis')
            ->where('pelanggan_id', $customer->id)
            ->select([
                'id', 'no_tiket', 'pelanggan_id', 'cabang_id', 'jenis_servis_id', 'teknisi_id',
                'jenis_hp', 'keluhan', 'status', 'token_approval', 'estimasi_biaya', 'status_pembayaran',
                'tanggal_terima', 'tanggal_selesai', 'tanggal_diambil', 'created_at',
            ])
            ->latest()
            ->get();
    }

    public function getKomisiProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer || ! $customer->is_reseller) {
            return collect();
        }

        // [B-15c] Select eksplisit (angka di header = sum koleksi ini → tidak boleh
        // dipotong; hanya kolom yang tidak dirender yang dibuang).
        return $customer->komisi()
            ->select(['id', 'no_komisi', 'status', 'keterangan', 'nominal_komisi', 'created_at'])
            ->latest()
            ->get();
    }

    public function getDownlinesProperty()
    {
        $customer = auth('customer')->user();
        if (! $customer || empty($customer->kode_agen)) {
            return collect();
        }

        return Pelanggan::where('referral_kode', $customer->kode_agen)
            ->where('id', '!=', $customer->id)
            ->select(['id', 'nama', 'telepon', 'tipe_konsumen', 'total_belanja_12bulan', 'created_at'])
            ->latest()
            ->get();
    }

    public function getTiersProperty()
    {
        return TierMembership::where('is_active', true)
            ->orderBy('min_belanja_12bulan', 'asc')
            ->get();
    }

    public function getMembershipInfoProperty(): array
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return [];
        }

        $customer->loadMissing('tierMembership');
        $currentTier = $customer->tierMembership;
        $totalBelanja = (float) ($customer->total_belanja_12bulan ?? 0);
        $tiers = $this->tiers;

        $nextTier = $tiers->first(fn ($t) => (float) $t->min_belanja_12bulan > $totalBelanja);

        $progress = 100;
        $kekurangan = 0.0;
        if ($nextTier) {
            $minCurrent = $currentTier ? (float) $currentTier->min_belanja_12bulan : 0.0;
            $minNext = (float) $nextTier->min_belanja_12bulan;
            $range = max(1.0, $minNext - $minCurrent);
            $progress = (int) min(100, max(0, round((($totalBelanja - $minCurrent) / $range) * 100)));
            $kekurangan = max(0.0, $minNext - $totalBelanja);
        }

        return [
            'currentTier' => $currentTier,
            'tierName' => $currentTier?->nama ?? ($customer->is_reseller ? 'Reseller' : 'Retail / Standar'),
            'diskonPersen' => (float) ($currentTier?->diskon_persen ?? 0),
            'poinMultiplier' => (float) ($currentTier?->poin_multiplier ?? 1.0),
            'totalBelanja' => $totalBelanja,
            'poinLoyalty' => (int) ($customer->poin_loyalty ?? 0),
            'nextTier' => $nextTier,
            'kekurangan' => $kekurangan,
            'progress' => $progress,
            'allTiers' => $tiers,
        ];
    }

    public function simpanProfil(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $this->validate([
            'nama' => 'required|string|max:255',
            'username' => 'nullable|string|min:3|max:40|regex:/^[a-zA-Z0-9._-]+$/|unique:pelanggan,username,'.$customer->id,
            'email' => 'nullable|email|max:255|unique:pelanggan,email,'.$customer->id,
            'alamat' => 'nullable|string|max:500',
            'avatarUpload' => 'nullable|image|max:2048',
        ], [
            'username.regex' => 'Username hanya boleh huruf, angka, titik, dash, dan garis bawah.',
            'username.unique' => 'Username tersebut sudah digunakan pelanggan lain.',
            'avatarUpload.max' => 'Ukuran foto profil maksimal 2MB.',
        ]);

        $updateData = [
            'nama' => trim($this->nama),
            'username' => ! empty($this->username) ? trim($this->username) : null,
            'email' => ! empty($this->email) ? trim($this->email) : null,
            'alamat' => ! empty($this->alamat) ? trim($this->alamat) : null,
        ];

        if ($this->avatarUpload) {
            $path = $this->avatarUpload->store('avatars/customers', 'public');
            $updateData['avatar'] = '/storage/'.$path;
            $this->avatarUpload = null;
        }

        $customer->update($updateData);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Profil akun Anda berhasil diperbarui.']);
    }

    public function hapusAvatar(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $customer->update(['avatar' => null]);
        $this->avatarUpload = null;

        $this->dispatch('alert', ['type' => 'info', 'message' => 'Foto profil telah dihapus.']);
    }

    public function updatePassword(): void
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return;
        }

        $this->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6|confirmed',
        ], [
            'currentPassword.required' => 'Kata sandi saat ini wajib diisi.',
            'newPassword.required' => 'Kata sandi baru wajib diisi.',
            'newPassword.min' => 'Kata sandi baru minimal 6 karakter.',
            'newPassword.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        if (! Hash::check($this->currentPassword, $customer->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => 'Kata sandi saat ini tidak cocok.',
            ]);
        }

        $customer->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->reset(['currentPassword', 'newPassword', 'newPassword_confirmation']);

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kata sandi akun Anda berhasil diperbarui.']);
    }

    public function render()
    {
        // [B-15c] Computed property WAJIB di-pass eksplisit ke view — blade memakai
        // `$orders` / `$servis` / `$komisi` (bukan `$this->...`), sedangkan variabel
        // computed tidak otomatis tersedia di view (pola wajib project, AGENTS.md).
        return view('modules.marketplace.livewire.customer-account', [
            'customer' => auth('customer')->user(),
            'orders' => $this->orders,
            'servis' => $this->servis,
            'notifikasi' => $this->notifikasi,
            'komisi' => $this->komisi,
            'downlines' => $this->downlines,
            'membershipInfo' => $this->membershipInfo,
        ])->layout('layouts.marketplace', ['title' => 'Akun Saya']);
    }
}
