<?php

namespace App\Modules\Pos\Jobs;

use App\Modules\Akunting\Models\Piutang;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Crm\Services\PelangganService;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Models\TransaksiItem;
use App\Modules\Pos\Services\EscPosWriter;
use App\Modules\Servis\Models\TiketServis;
use App\Modules\Wms\Models\Produk;
use App\Services\BranchContextManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * [T-35] Cetak struk thermal via queue (ADR 0009 fallback).
 * Tidak pernah dieksekusi sinkron di request — selalu via antrian database.
 */
class PrintThermalJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $transaksiId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $transaksi = Transaksi::withoutGlobalScopes()
            ->with(['items.produk', 'items.skuVariant', 'pelanggan', 'kasir', 'cabang', 'tiketServis', 'pengiriman'])
            ->find($this->transaksiId);

        if (! $transaksi) {
            Log::warning('PrintThermal: transaksi tidak ditemukan', ['id' => $this->transaksiId]);

            return;
        }

        $cabangId = $transaksi->cabang_id ?? 1;

        app(BranchContextManager::class)->runInBranchContext((int) $cabangId, function () use ($transaksi) {
            $this->prosesCetak($transaksi);
        });
    }

    protected function prosesCetak(Transaksi $transaksi): void
    {
        $items = [];
        foreach ($transaksi->items as $item) {
            /** @var TransaksiItem $item */
            $items[] = [
                'nama' => $this->itemName($item),
                'qty' => $item->jumlah,
                'harga' => (float) $item->harga_satuan,
                'subtotal' => (float) $item->subtotal,
                'harga_retail' => (float) ($item->produk?->harga_jual_retail ?? $item->harga_satuan),
                'satuan' => $item->produk?->satuan ?? 'pcs',
                'catatan' => $item->catatan,
            ];
        }

        if (empty($items) && $transaksi->tiketServis) {
            /** @var TiketServis $ts */
            $ts = $transaksi->tiketServis;
            $rincian = $ts->getRincianBiayaLengkap();
            /** @var array<string, mixed>[] $rincianItems */
            $rincianItems = $rincian['items'];
            foreach ($rincianItems as $it) {
                $items[] = [
                    'nama' => $it['nama'],
                    'qty' => $it['qty'],
                    'harga' => (float) $it['harga'],
                    'subtotal' => (float) $it['subtotal'],
                    'harga_retail' => (float) (isset($it['harga_retail']) ? $it['harga_retail'] : $it['harga']),
                    'satuan' => 'pcs',
                ];
            }
        }

        // Load struk config untuk header/footer/opsi cetak
        $strukConfig = app(KonfigurasiService::class)->getStrukConfig();

        $headerLines = array_values(array_filter([
            $strukConfig['nama_toko'] ?? ($transaksi->cabang?->nama ?? config('app.name', 'BREMSPACE')),
            $strukConfig['slogan'] ?? null,
            $strukConfig['alamat'] ?? null,
            ! empty($strukConfig['telepon']) ? 'Telp/WA: '.$strukConfig['telepon'] : null,
        ]));

        $footerLines = array_values(array_filter([
            $strukConfig['footer_text'] ?? 'Terima kasih atas kunjungan Anda',
            $strukConfig['kebijakan_retur'] ?? null,
            $strukConfig['catatan_kaki'] ?? null,
        ]));

        // Data pelanggan & tier
        /** @var Pelanggan|null $pelanggan */
        $pelanggan = $transaksi->pelanggan;
        $tipeKonsumen = $pelanggan ? app(PelangganService::class)->tipeKonsumen($pelanggan) : 'retail';
        $tierName = $pelanggan?->tierMembership?->nama ?? null;

        // Piutang & Pengiriman (declare for PHPStan)
        /** @var Piutang|null $piutang */
        $piutang = $transaksi->piutang;
        /** @var Pengiriman|null $pengiriman */
        $pengiriman = $transaksi->pengiriman;

        $data = [
            // Header info from struk config
            'nama_toko' => $strukConfig['nama_toko'] ?? 'BREMSPACE',
            'slogan' => $strukConfig['slogan'] ?? 'Pusat Sparepart & Servis Gadget',
            'alamat' => $strukConfig['alamat'] ?? 'Jl. Veteran No. 12',
            'telepon' => $strukConfig['telepon'] ?? '0812-3456-7890',
            'website_medsos' => $strukConfig['website_medsos'] ?? 'www.bremspace.com',

            // Display options
            'show_cabang' => $strukConfig['show_cabang'] ?? true,
            'show_kasir' => $strukConfig['show_kasir'] ?? true,
            'show_pelanggan' => $strukConfig['show_pelanggan'] ?? true,
            'show_waktu' => $strukConfig['show_waktu'] ?? true,
            'show_subtotal_breakdown' => $strukConfig['show_subtotal_breakdown'] ?? true,
            'show_qr_retur' => $strukConfig['show_qr_retur'] ?? true,
            'qr_caption' => $strukConfig['qr_caption'] ?? 'Scan QR untuk Retur & Garansi',
            'qr_size' => $strukConfig['qr_size'] ?? 4,

            // Paper & font settings
            'paper_width' => $strukConfig['default_width'] === '80mm' ? EscPosWriter::WIDTH_80MM : EscPosWriter::WIDTH_58MM,
            'font_size' => $strukConfig['font_size'] ?? 'normal', // 'normal' | 'small'

            // Transaction data
            'items' => $items,
            'subtotal' => (float) $transaksi->subtotal,
            'subtotal_retail' => (float) ($transaksi->subtotal_retail ?? 0),
            'diskon_tier' => (float) ($transaksi->diskon_tier ?? 0),
            'diskon_partai' => (float) ($transaksi->diskon_partai ?? 0),
            'diskon_poin' => (float) ($transaksi->diskon_poin ?? 0),
            'poin_ditukar' => (int) ($transaksi->poin_ditukar ?? 0),
            'diskon' => (float) ($transaksi->diskon_nominal ?? 0),
            'diskon_persen' => (float) ($transaksi->diskon_persen ?? 0),
            'diskon_nominal' => (float) ($transaksi->diskon_nominal ?? 0),
            'pajak' => (float) ($transaksi->pajak_nominal ?? 0),
            'ppn_persen' => (float) ($transaksi->pajak_persen ?? 0),
            'ongkir' => (float) ($transaksi->pengiriman?->ongkir ?? 0),
            'total' => (float) $transaksi->total_akhir,
            'bayar' => (float) $transaksi->jumlah_bayar,
            'kembali' => (float) $transaksi->kembalian,
            'hemat_total' => (float) ($transaksi->hemat_total ?? 0),
            'komisi_mitra_nominal' => (float) ($transaksi->komisi_mitra_nominal ?? 0),

            'no_transaksi' => $transaksi->no_transaksi,
            'no_tiket' => $transaksi->tiketServis?->no_tiket,
            'jenis_hp' => $transaksi->tiketServis?->jenis_hp,
            'metode_bayar' => $transaksi->metode_bayar,
            'footerLines' => ! empty($footerLines) ? $footerLines : ['Terima kasih atas kunjungan Anda'],
            'tanggal' => $transaksi->created_at?->format('d/m/Y H:i'),
            'waktu' => $transaksi->created_at?->format('d/m/Y H:i'),
            'kasir' => $transaksi->kasir?->name ?? (string) $transaksi->kasir_id,
            'cabang' => $transaksi->cabang?->nama ?? (string) $transaksi->cabang_id,

            // Pelanggan & tier
            'pelanggan' => $pelanggan?->nama ?? 'Umum',
            'tier' => $tierName ?? 'Retail',
            'tipe_konsumen' => $tipeKonsumen,
            'saldo_poin' => $pelanggan?->poin_loyalty ?? 0,

            // Piutang
            'is_piutang' => in_array(strtolower($transaksi->metode_bayar ?? ''), ['piutang', 'kasbon']) || (bool) $piutang,
            'jatuh_tempo' => $piutang?->jatuh_tempo?->format('d/m/Y') ?? ($transaksi->created_at?->copy()->addDays(30)->format('d/m/Y')),
            'sisa_piutang' => $piutang?->sisa ?? $transaksi->total_akhir,

            // Kurir / Pengiriman
            'kurir' => $pengiriman?->kurir ?? null,
            'is_pickup' => (strtolower($pengiriman?->layanan ?? '') === 'pickup'),
            'resi' => $pengiriman?->tracking_id ?? '-',
            'sumber' => $pengiriman ? 'Online' : 'POS',

            // Garansi
            'garansi' => $transaksi->garansi_teks ?? '3 Hari Toko (Sesuai T&C Penyerahan)',

            // Footer custom
            'footer_text' => $strukConfig['footer_text'] ?? 'Terima kasih atas kunjungan Anda!',
            'kebijakan_retur' => $strukConfig['kebijakan_retur'] ?? null,
            'catatan_kaki' => $strukConfig['catatan_kaki'] ?? null,
        ];

        $bytes = app(EscPosWriter::class)->receipt($data);

        $printer = env('THERMAL_PRINTER', '');

        if ($printer !== '') {
            $this->sendToPrinter($printer, $bytes, $transaksi->no_transaksi);

            return;
        }

        // Fallback staging: simpan artefak agar perilaku dapat diverifikasi tanpa hardware printer.
        $path = 'prints/'.$transaksi->no_transaksi.'_'.uniqid().'.bin';
        Storage::disk('local')->put($path, $bytes);
        Log::info('Thermal print artifact (no printer configured)', [
            'path' => Storage::disk('local')->path($path),
            'no_transaksi' => $transaksi->no_transaksi,
            'bytes' => strlen($bytes),
        ]);
    }

    /** Nama item: prioritas produk, + varian bila ada; fallback id produk. */
    private function itemName($item): string
    {
        $nama = $item->produk?->nama;
        if (! $nama && $item->produk_id) {
            $nama = 'Item #'.$item->produk_id;
        }
        if ($item->skuVariant?->nama_varian) {
            $nama .= ' ('.$item->skuVariant->nama_varian.')';
        }

        return $nama;
    }

    private function sendToPrinter(string $printer, string $bytes, string $noTransaksi): void
    {
        $process = new Process(['lp', '-d', $printer, '-o', 'raw']);
        $process->setInput($bytes);

        try {
            $process->run();
            if ($process->isSuccessful()) {
                Log::info('Thermal print terkirim ke printer', ['printer' => $printer, 'no_transaksi' => $noTransaksi]);
            } else {
                Log::error('Thermal print gagal (lp)', [
                    'printer' => $printer,
                    'no_transaksi' => $noTransaksi,
                    'exit' => $process->getExitCode(),
                    'stderr' => $process->getErrorOutput(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Thermal print gagal (proses lp)', [
                'printer' => $printer,
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
