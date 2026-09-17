<?php

namespace Database\Seeders;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Notifikasi\Models\NotifikasiKeluar;
use App\Modules\Omnichannel\Models\Channel;
use App\Modules\Omnichannel\Models\ChannelOrder;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\SkuVariant;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\StokLog;
use App\Modules\Wms\Models\StokOpname;
use App\Modules\Wms\Models\StokOpnameItem;
use App\Modules\Wms\Models\StokTransfer;
use App\Modules\Wms\Models\StokTransferItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * [T-01] Data uji semua jenis transaksi (idempotent).
 * Menciptakan: POS (tunai/transfer/member tiap tier/reseller), marketplace lunas,
 * channel dummy, servis di tiap status, transfer, opname, broadcast.
 * Semua membuat jurnal otomatis yang balance (acceptance: debit = kredit).
 */
class TransaksiDemoSeeder extends Seeder
{
    private JurnalService $jurnal;
    private ?Cabang $cabang;
    private ?Gudang $gudangUtama;
    private ?Gudang $gudangKedua;
    private ?User $kasir;
    private ?User $teknisi;

    // Guard: hanya jalankan sekali (idempotent secara keseluruhan)
    private function sudahAdaDemo(): bool
    {
        return Transaksi::where('no_transaksi', 'like', 'DEMO-%')->exists()
            || TiketServis::where('no_tiket', 'like', 'DEMO-%')->exists();
    }

    public function run(): void
    {
        if ($this->sudahAdaDemo()) {
            return;
        }

        $this->jurnal = app(JurnalService::class);
        $this->cabang = Cabang::first();
        $this->gudangUtama = Gudang::first();
        $this->gudangKedua = Gudang::skip(1)->first();
        $this->kasir = User::role('kasir')->first();
        $this->teknisi = User::role('teknisi')->first();

        if (!$this->cabang || !$this->gudangUtama) {
            return;
        }

        DB::transaction(function () {
            // Persiapan data
            $this->siapkanPelanggan();

            // ===== 1. POS TUNAI =====
            $this->buatPos('Tunai', 'tunai', null);

            // ===== 2. POS NON-TUNAI (transfer) =====
            $this->buatPos('Transfer', 'transfer', null);

            // ===== 3-5. POS MEMBER tiap tier =====
            foreach (TierMembership::all() as $tier) {
                $member = Pelanggan::where('telepon', 'demo-member-' . $tier->kode)->first();
                $this->buatPos('Tier' . ucfirst($tier->kode), 'tunai', $member);
            }

            // ===== 6. POS RESELLER (komisi pending) =====
            $reseller = Pelanggan::where('telepon', 'demo-reseller')->first();
            if ($reseller) {
                $transaksi = $this->buatPos('Reseller', 'tunai', $reseller);
                if ($transaksi && $reseller->is_reseller) {
                    app(\App\Modules\Reseller\Services\KomisiService::class)->hitungKomisi($transaksi, $reseller);
                }
            }

            // ===== 7. MARKETPLACE lunas (dummy-paid) =====
            $this->buatMarketplaceLunas();

            // ===== 8. CHANNEL OMNICHANNEL dummy =====
            $this->buatChannelOrderDummy();

            // ===== 9. TIKET SERVIS di tiap status =====
            $this->buatServisPerStatus();

            // ===== 10. TRANSFER antar gudang: pending + selesai =====
            $this->buatTransfer();

            // ===== 11. STOCK OPNAME dengan selisih =====
            $this->buatOpname();

            // ===== 12. BROADCAST CRM contoh =====
            NotifikasiKeluar::firstOrCreate(
                ['judul' => 'Broadcast Demo - Akhir Bulan', 'tipe' => 'inapp'],
                ['konten' => 'Diskon LCD 10% untuk member Gold minggu ini! (demo)', 'status' => 'pending']
            );
        });
    }

    // ============================================================
    private function siapkanPelanggan(): void
    {
        $tiers = TierMembership::all()->keyBy('kode');
        $silver = $tiers['silver'] ?? null;
        $gold = $tiers['gold'] ?? null;
        $platinum = $tiers['platinum'] ?? null;

        $def = [
            'silver' => [$silver, false],
            'gold' => [$gold, false],
            'platinum' => [$platinum, false],
        ];

        foreach ($def as $kode => [$tier, $reseller]) {
            Pelanggan::firstOrCreate(
                ['telepon' => "demo-member-{$kode}"],
                [
                    'nama' => 'Member Demo ' . ucfirst($kode),
                    'email' => "member.{$kode}@demo.test",
                    'tier_membership_id' => $tier?->id,
                    'is_reseller' => $reseller,
                ]
            );
        }

        Pelanggan::firstOrCreate(
            ['telepon' => 'demo-reseller'],
            [
                'nama' => 'Reseller Demo',
                'email' => 'reseller@demo.test',
                'tier_membership_id' => $gold?->id,
                'is_reseller' => true,
            ]
        );
    }

    private function produkPertama(): ?Produk
    {
        return Produk::first();
    }

    private function buatPos(string $label, string $metode, ?Pelanggan $pelanggan): ?Transaksi
    {
        $produk = $this->produkPertama();
        $variant = $produk?->skuVariants()->first();
        if (!$produk || !$produk->harga_jual_retail) {
            return null;
        }

        $qty = 1;
        $harga = (float) $produk->harga_jual_retail;
        $cabangId = $this->cabang->id;

        $count = Transaksi::whereDate('created_at', now()->toDateString())->count() + random_int(1, 50);
        $no = sprintf('DEMO-%s-%s-%04d', now()->format('Ymd'), $label, $count);

        $transaksi = Transaksi::create([
            'no_transaksi' => $no,
            'cabang_id' => $cabangId,
            'kasir_id' => $this->kasir?->id,
            'pelanggan_id' => $pelanggan?->id,
            'gudang_id' => $this->gudangUtama->id,
            'sumber' => 'pos',
            'subtotal' => $harga,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'pajak_nominal' => 0,
            'total_akhir' => $harga,
            'metode_bayar' => $metode,
            'jumlah_bayar' => $harga,
            'kembalian' => 0,
            'status' => 'selesai',
            'catatan' => "Demo seeder: POS {$label}",
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant?->id,
            'jumlah' => $qty,
            'harga_satuan' => $harga,
            'diskon_nominal' => 0,
            'subtotal' => $harga,
            'hpp' => (float) $produk->harga_beli,
        ]);

        // Stok berkurang
        $stok = StokItem::where('produk_id', $produk->id)->where('gudang_id', $this->gudangUtama->id)->first();
        $sebelum = $stok?->jumlah ?? 0;
        $setelah = max(0, $sebelum - $qty);
        if ($stok) {
            $stok->update(['jumlah' => $setelah]);
        }
        StokLog::create([
            'gudang_id' => $this->gudangUtama->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $variant?->id,
            'user_id' => $this->kasir?->id,
            'jenis' => 'penjualan',
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'jumlah_sebelum' => $sebelum,
            'perubahan' => -$qty,
            'jumlah_setelah' => $setelah,
            'catatan' => $no,
        ]);

        // Jurnal (Kas/Transfer debit, Pendapatan kredit; kasbon → Piutang di alur lain)
        $akunDebit = match ($metode) {
            'piutang' => '120-01',
            'tunai' => '110-01',
            default => '110-01',
        };
        $this->jurnal->post(
            $this->jurnal->generateNoJurnal('pos', $cabangId),
            now(),
            'pos',
            [
                ['akun_kode' => $akunDebit, 'debit' => $harga, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $harga],
                ['akun_kode' => '510-02', 'debit' => (float) $produk->harga_beli, 'kredit' => 0],
                ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => (float) $produk->harga_beli],
            ],
            "Demo seeder POS {$label} {$no}",
            $cabangId,
            $this->kasir?->id,
            Transaksi::class,
            $transaksi->id
        );

        return $transaksi;
    }

    private function buatMarketplaceLunas(): void
    {
        $produk = $this->produkPertama();
        $member = Pelanggan::where('telepon', 'demo-member-gold')->first();
        if (!$produk) {
            return;
        }

        $no = 'DEMO-MP-' . now()->format('Ymd') . '-0001';
        $harga = (float) $produk->harga_jual_retail;

        $transaksi = Transaksi::create([
            'no_transaksi' => $no,
            'cabang_id' => $this->cabang->id,
            'pelanggan_id' => $member?->id,
            'sumber' => 'marketplace',
            'subtotal' => $harga,
            'total_akhir' => $harga,
            'metode_bayar' => 'duitku',
            'jumlah_bayar' => $harga,
            'status' => 'lunas',
            'paid_at' => now(),
            'catatan' => 'Demo seeder: marketplace lunas (dummy-paid)',
        ]);

        TransaksiItem::create([
            'transaksi_id' => $transaksi->id,
            'produk_id' => $produk->id,
            'jumlah' => 1,
            'harga_satuan' => $harga,
            'subtotal' => $harga,
            'hpp' => (float) $produk->harga_beli,
        ]);

        // Jurnal + stok (setara alur webhook duitku lunas)
        $stok = StokItem::where('produk_id', $produk->id)->where('gudang_id', $this->gudangUtama->id)->first();
        if ($stok) {
            $stok->update(['jumlah' => max(0, $stok->jumlah - 1)]);
        }
        StokLog::create([
            'gudang_id' => $this->gudangUtama->id,
            'produk_id' => $produk->id,
            'user_id' => null,
            'jenis' => 'penjualan',
            'referensi_tipe' => Transaksi::class,
            'referensi_id' => $transaksi->id,
            'jumlah_sebelum' => $stok?->jumlah ?? 0,
            'perubahan' => -1,
            'jumlah_setelah' => ($stok?->jumlah ?? 0) - 1,
            'catatan' => $no,
        ]);

        $this->jurnal->post(
            $this->jurnal->generateNoJurnal('pos', $this->cabang->id),
            now(),
            'pos',
            [
                ['akun_kode' => '110-01', 'debit' => $harga, 'kredit' => 0],
                ['akun_kode' => '410-01', 'debit' => 0, 'kredit' => $harga],
                ['akun_kode' => '510-02', 'debit' => (float) $produk->harga_beli, 'kredit' => 0],
                ['akun_kode' => '130-01', 'debit' => 0, 'kredit' => (float) $produk->harga_beli],
            ],
            "Demo seeder MP lunas {$no}",
            $this->cabang->id,
            null,
            Transaksi::class,
            $transaksi->id
        );
    }

    private function buatChannelOrderDummy(): void
    {
        // Pastikan ada channel contoh (idempotent) agar order channel dummy bisa dibuat
        $channel = Channel::firstOrCreate(
            ['platform' => 'shopee', 'nama' => 'Ute Parts Demo — Shopee'],
            ['status' => 'terhubung', 'kredensial' => [], 'is_active' => true]
        );

        $orderId = 'DEMO-' . $channel->platform . '-' . now()->timestamp;

        if (!ChannelOrder::where('channel_id', $channel->id)->where('channel_order_id', $orderId)->exists()) {
            ChannelOrder::create([
                'channel_id' => $channel->id,
                'channel_order_id' => $orderId,
                'channel_status' => 'COMPLETED',
                'payload' => ['demo' => true, 'notes' => 'Channel order dummy dari seeder'],
                'status' => 'selesai',
            ]);
        }
    }

    private function buatServisPerStatus(): void
    {
        if (!$this->teknisi) {
            return;
        }

        $cabangId = $this->cabang->id;
        $member = Pelanggan::where('telepon', 'demo-member-gold')->first();

        $statuses = [
            'diterima', 'diagnosa', 'menunggu_approval', 'disetujui',
            'dikerjakan', 'qc', 'selesai', 'diambil', 'ditolak',
        ];

        foreach ($statuses as $i => $status) {
            $no = sprintf('DEMO-SRV-%s-%02d', now()->format('Ymd'), $i + 1);

            if (TiketServis::where('no_tiket', $no)->exists()) {
                continue;
            }

            $tiket = TiketServis::create([
                'no_tiket' => $no,
                'cabang_id' => $cabangId,
                'pelanggan_id' => $member?->id,
                'nama_pelanggan' => $member?->nama ?? 'Customer Demo',
                'telepon_pelanggan' => $member?->telepon ?? '08000000000',
                'jenis_hp' => 'iPhone 13 Demo',
                'keluhan' => 'Layar retak + baterai cepat habis (demo)',
                'kondisi_fisik' => ['Layar', 'Baterai'],
                'foto_unit' => null,
                'status' => $status,
                'sumber' => 'walkin',
                'estimasi_biaya' => in_array($status, ['menunggu_approval', 'disetujui', 'dikerjakan', 'qc', 'selesai', 'diambil']) ? 250000 : null,
                'token_approval' => Str::random(64),
                'tanggal_terima' => now()->subDays(5),
                'teknisi_id' => $this->teknisi->id,
            ]);

            // Garansi: tiket selesai lama (expired) + selesai baru (masih garansi)
            if ($status === 'selesai') {
                $tiket->update(['tanggal_selesai' => now()->subDays(40)]);
                // garansi 30 hari → sudah expired
            }
        }

        // Satu tiket selesai baru (masih dalam garansi) + status diambil
        $baru = 'DEMO-SRV-' . now()->format('Ymd') . '-99';
        if (!TiketServis::where('no_tiket', $baru)->exists()) {
            TiketServis::create([
                'no_tiket' => $baru,
                'cabang_id' => $cabangId,
                'pelanggan_id' => $member?->id,
                'nama_pelanggan' => $member?->nama ?? 'Customer Demo',
                'telepon_pelanggan' => $member?->telepon ?? '08000000000',
                'jenis_hp' => 'Samsung A52 Demo',
                'keluhan' => 'Ganti baterai (demo - dalam garansi)',
                'status' => 'selesai',
                'sumber' => 'walkin',
                'estimasi_biaya' => 150000,
                'token_approval' => Str::random(64),
                'tanggal_terima' => now()->subDays(2),
                'tanggal_selesai' => now()->subHours(5),
                'teknisi_id' => $this->teknisi->id,
            ]);
        }
    }

    private function buatTransfer(): void
    {
        if (!$this->gudangKedua) {
            return;
        }

        $produk = $this->produkPertama();
        if (!$produk) {
            return;
        }

        $no = 'DEMO-TRF-' . now()->format('Ymd') . '-0001';
        if (StokTransfer::where('no_transfer', $no)->exists()) {
            return;
        }

        // Pending
        StokTransfer::create([
            'no_transfer' => $no,
            'gudang_asal_id' => $this->gudangUtama->id,
            'gudang_tujuan_id' => $this->gudangKedua->id,
            'user_pengirim_id' => $this->kasir?->id,
            'status' => 'draft',
            'catatan' => 'Demo seeder transfer (pending)',
        ])->items()->create([
            'produk_id' => $produk->id,
            'sku_variant_id' => $produk->skuVariants()->first()?->id,
            'jumlah' => 1,
        ]);

        // Selesai (kirim + terima)
        $no2 = 'DEMO-TRF-' . now()->format('Ymd') . '-0002';
        $transfer2 = StokTransfer::create([
            'no_transfer' => $no2,
            'gudang_asal_id' => $this->gudangUtama->id,
            'gudang_tujuan_id' => $this->gudangKedua->id,
            'user_pengirim_id' => $this->kasir?->id,
            'user_penerima_id' => $this->kasir?->id,
            'status' => 'diterima',
            'tanggal_kirim' => now(),
            'tanggal_terima' => now(),
        ]);
        StokTransferItem::create([
            'stok_transfer_id' => $transfer2->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $produk->skuVariants()->first()?->id,
            'jumlah' => 1,
        ]);
    }

    private function buatOpname(): void
    {
        $produk = $this->produkPertama();
        if (!$produk) {
            return;
        }

        $no = 'DEMO-OPN-' . now()->format('Ymd') . '-0001';
        if (StokOpname::where('no_opname', $no)->exists()) {
            return;
        }

        $sti = StokItem::where('produk_id', $produk->id)->where('gudang_id', $this->gudangUtama->id)->first();
        $stokSistem = $sti?->jumlah ?? 0;

        $opname = StokOpname::create([
            'no_opname' => $no,
            'gudang_id' => $this->gudangUtama->id,
            'user_id' => $this->kasir?->id,
            'status' => 'disetujui',
            'catatan' => 'Demo seeder opname dengan selisih',
        ]);

        StokOpnameItem::create([
            'stok_opname_id' => $opname->id,
            'produk_id' => $produk->id,
            'sku_variant_id' => $produk->skuVariants()->first()?->id,
            'stok_sistem' => $stokSistem,
            'stok_fisik' => max(0, $stokSistem - 1), // selisih -1
            'selisih' => -1,
        ]);
    }
}