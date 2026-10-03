<?php

namespace App\Modules\Pos\Jobs;

use App\Modules\Pos\Models\Transaksi;
use App\Modules\Pos\Services\EscPosWriter;
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
        $transaksi = Transaksi::with(['items.produk', 'items.skuVariant', 'pelanggan', 'kasir', 'cabang', 'tiketServis'])
            ->find($this->transaksiId);

        if (! $transaksi) {
            Log::warning('PrintThermal: transaksi tidak ditemukan', ['id' => $this->transaksiId]);

            return;
        }

        $items = $transaksi->items->map(fn ($item) => [
            'nama' => $this->itemName($item),
            'qty' => $item->jumlah,
            'harga' => (float) $item->harga_satuan,
            'subtotal' => (float) $item->subtotal,
        ])->all();

        if (empty($items) && $transaksi->tiketServis) {
            $ts = $transaksi->tiketServis;
            $rincian = $ts->getRincianBiayaLengkap();
            foreach ($rincian['items'] as $it) {
                $items[] = [
                    'nama' => $it['nama'],
                    'qty' => $it['qty'],
                    'harga' => (float) $it['harga'],
                    'subtotal' => (float) $it['subtotal'],
                ];
            }
        }

        $data = [
            'headerLines' => [$transaksi->cabang?->nama ?? 'UTE PARTS', 'Jln. Contoh No. 1, Kota'],
            'items' => $items,
            'total' => (float) $transaksi->total_akhir,
            'bayar' => (float) $transaksi->jumlah_bayar,
            'kembali' => (float) $transaksi->kembalian,
            'no_transaksi' => $transaksi->no_transaksi,
            'no_tiket' => $transaksi->tiketServis?->no_tiket,
            'jenis_hp' => $transaksi->tiketServis?->jenis_hp,
            'metode_bayar' => $transaksi->metode_bayar,
            'footerLines' => ['Terima kasih atas kunjungan Anda', 'Barang yang sudah dibeli tidak dapat dikembalikan'],
            'tanggal' => $transaksi->created_at?->format('d-m-Y H:i'),
            'kasir' => $transaksi->kasir?->name ?? (string) $transaksi->kasir_id,
            'cabang' => $transaksi->cabang?->nama ?? (string) $transaksi->cabang_id,
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
