<?php

namespace App\Modules\Rbac\Exports;

use App\Modules\Rbac\Livewire\RiwayatAktivitas;
use App\Modules\Rbac\Models\AktivitasLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditLogExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    protected int $nomorUrut = 0;

    public function __construct(
        protected Collection $logs
    ) {}

    public function title(): string
    {
        return 'Audit Trail ISO 27001';
    }

    public function collection(): Collection
    {
        return $this->logs;
    }

    public function headings(): array
    {
        return [
            'No',
            'Waktu Kejadian (WIB)',
            'IP Address & Perangkat',
            'Cabang Toko',
            'Modul / Entitas',
            'No. Dokumen / ID',
            'Aksi / Event',
            'Pelaku (User)',
            'Ringkasan Aktivitas (Deskripsi)',
            'Rincian Perubahan Nilai (Semula ➔ Menjadi)',
            'Rincian Barang & Mutasi Produk',
            'Metadata Keamanan & Parameter Alur',
        ];
    }

    /**
     * @param  AktivitasLog  $log
     */
    public function map($log): array
    {
        $this->nomorUrut++;

        // 1. Format IP & Perangkat (ISO 27001 requirement)
        $ip = $log->ip ?: '—';
        $ua = $log->user_agent ? substr($log->user_agent, 0, 75) : '';
        $perangkatText = $ua ? "{$ip}\n({$ua})" : $ip;

        // 2. Format rincian perubahan data
        $mutasiText = [];
        foreach ($log->daftar_mutasi as $m) {
            if ($m['tipe'] === 'tambah') {
                $mutasiText[] = "• {$m['label']}: [Baru] {$m['sesudah']}";
            } elseif ($m['tipe'] === 'hapus') {
                $mutasiText[] = "• {$m['label']}: [Dihapus] {$m['sebelum']}";
            } else {
                $mutasiText[] = "• {$m['label']}: {$m['sebelum']} ➔ {$m['sesudah']}";
            }
        }

        // 3. Format rincian item produk
        $barangText = [];
        foreach ($log->items_list as $it) {
            $nama = $it['nama_produk'] ?? 'Produk';
            $sku = ! empty($it['sku']) ? " [{$it['sku']}]" : '';
            $qty = ($it['qty'] ?? ($it['jumlah'] ?? 1)).' unit';
            $harga = isset($it['harga_satuan']) ? ' @ Rp '.number_format((float) $it['harga_satuan'], 0, ',', '.') : '';
            $subtotal = isset($it['subtotal']) ? ' (Subtotal: Rp '.number_format((float) $it['subtotal'], 0, ',', '.').')' : '';
            $kondisi = ! empty($it['kondisi']) ? " [Kondisi: {$it['kondisi']}]" : '';
            $barangText[] = "• {$nama}{$sku} - {$qty}{$harga}{$subtotal}{$kondisi}";
        }

        if (! empty($log->items_tukar_list)) {
            $barangText[] = '--- Barang Pengganti / Tukar Produk ---';
            foreach ($log->items_tukar_list as $tIt) {
                $nama = $tIt['nama_produk'] ?? 'Produk';
                $sku = ! empty($tIt['sku']) ? " [{$tIt['sku']}]" : '';
                $qty = ($tIt['qty'] ?? 1).' unit';
                $harga = isset($tIt['harga_satuan']) ? ' @ Rp '.number_format((float) $tIt['harga_satuan'], 0, ',', '.') : '';
                $barangText[] = "• Pengganti: {$nama}{$sku} - {$qty}{$harga}";
            }
        }

        // 4. Metadata Keamanan & Parameter Alur
        $metadataText = [];
        $props = $log->normalized_properties;
        if (! empty($props['guard'])) {
            $metadataText[] = "• Auth Guard: {$props['guard']}";
        }
        if (! empty($props['target_kredensial'])) {
            $metadataText[] = "• Target: {$props['target_kredensial']}";
        }
        if (! empty($props['metode_bayar'])) {
            $metadataText[] = '• Metode Bayar: '.strtoupper(str_replace('_', ' ', (string) $props['metode_bayar']));
        }
        if (! empty($props['tipe_penyelesaian'])) {
            $metadataText[] = '• Penyelesaian: '.($props['tipe_penyelesaian'] === 'tukar_produk' ? 'Tukar Produk' : 'Refund Dana');
        }
        if (! empty($props['gudang_asal']) && ! empty($props['gudang_tujuan'])) {
            $metadataText[] = "• Alur Gudang: {$props['gudang_asal']} ➔ {$props['gudang_tujuan']}";
        }
        if (! empty($props['alasan'])) {
            $metadataText[] = "• Alasan: {$props['alasan']}";
        }
        if (! empty($props['selisih_nominal']) && (float) $props['selisih_nominal'] > 0) {
            $metadataText[] = '• Selisih Bayar: Rp '.number_format((float) $props['selisih_nominal'], 0, ',', '.');
        }

        // Pelaku format
        $pelaku = $log->causer_nama;
        if ($log->causer && ! empty($log->causer->email)) {
            $pelaku .= "\n(".$log->causer->email.')';
        }

        return [
            $this->nomorUrut,
            $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '—',
            $perangkatText,
            $log->cabang_nama ?: ($log->cabang_id ? 'Cabang #'.$log->cabang_id : 'Semua Cabang / Sistem'),
            RiwayatAktivitas::labelEntitas($log->subject_type),
            $log->no_referensi ?: ($log->subject_id ? '#'.$log->subject_id : '—'),
            $log->aksi_label,
            $pelaku,
            $log->ringkasan,
            ! empty($mutasiText) ? implode("\n", $mutasiText) : '—',
            ! empty($barangText) ? implode("\n", $barangText) : '—',
            ! empty($metadataText) ? implode("\n", $metadataText) : '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling (ISO 27001 Audit Header: Navy/Indigo gradient)
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E1B4B'], // Deep Navy
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane('A2');

        // Body formatting
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 1) {
            $sheet->setAutoFilter("A1:L{$highestRow}");

            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C2:C{$highestRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("F2:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H2:H{$highestRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("I2:L{$highestRow}")->getAlignment()->setWrapText(true);

            // Zebra striping on data rows for readable audit reading
            for ($row = 2; $row <= $highestRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:L{$row}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }
            }

            $sheet->getStyle("A1:L{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);
        }

        return [];
    }
}
