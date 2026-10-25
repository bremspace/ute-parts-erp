<?php

namespace App\Modules\Wms\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BulkFotoSkippedExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  array<int, array{filename: string, relative_path?: string, sku: string, reason: string, size?: int}>  $skippedItems
     */
    public function __construct(
        protected array $skippedItems
    ) {}

    public function collection(): Collection
    {
        return collect($this->skippedItems)->map(function ($item, $index) {
            $sizeKb = ! empty($item['size']) ? round($item['size'] / 1024, 1).' KB' : '-';

            return [
                'no' => $index + 1,
                'filename' => $item['filename'] ?? '-',
                'relative_path' => $item['relative_path'] ?? '-',
                'sku' => $item['sku'] ?? '(Kosong)',
                'reason' => $item['reason'] ?? 'Dilewati oleh sistem',
                'size' => $sizeKb,
                'saran' => empty($item['sku'])
                    ? 'Ubah nama file menjadi format [SKU].jpg sebelum mengunggah.'
                    : 'Pastikan produk dengan SKU ini sudah terdaftar di Master Produk WMS.',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama File',
            'Jalur / Subfolder',
            'Tebakan SKU',
            'Alasan Dilewati',
            'Ukuran File',
            'Saran Tindakan Perbaikan',
        ];
    }

    public function title(): string
    {
        return 'Laporan File Foto Dilewati';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF5B4FE9'], // Ute Prism Primary
                ],
            ],
        ];
    }
}
