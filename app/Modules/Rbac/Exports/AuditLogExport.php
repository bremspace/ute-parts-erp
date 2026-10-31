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
        return 'Audit & Aktivitas Log';
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
            'Cabang',
            'Modul / Entitas',
            'No. Dokumen / ID',
            'Aksi',
            'Pelaku (User)',
            'Ringkasan Aktivitas',
            'Rincian Perubahan Nilai (Semula ➔ Menjadi)',
            'Rincian Barang / Produk Terlibat',
        ];
    }

    /**
     * @param  AktivitasLog  $log
     */
    public function map($log): array
    {
        $this->nomorUrut++;

        // Format rincian perubahan data
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

        // Format rincian item produk
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

        return [
            $this->nomorUrut,
            $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '—',
            $log->cabang_nama ?: ($log->cabang_id ? 'Cabang #'.$log->cabang_id : 'Semua Cabang / Sistem'),
            RiwayatAktivitas::labelEntitas($log->subject_type),
            $log->no_referensi ?: ($log->subject_id ? '#'.$log->subject_id : '—'),
            $log->aksi_label,
            $log->causer_nama,
            $log->ringkasan,
            ! empty($mutasiText) ? implode("\n", $mutasiText) : '—',
            ! empty($barangText) ? implode("\n", $barangText) : '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Header styling
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo / Primary Ute
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(30);

        // Body alignment & text wrapping
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 1) {
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E2:F{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$highestRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A1:J{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);
        }

        return [];
    }
}
