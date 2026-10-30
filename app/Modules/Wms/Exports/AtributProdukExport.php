<?php

namespace App\Modules\Wms\Exports;

use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\TipeHp;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AtributProdukExport implements Export, WithMultipleSheets
{
    public function __construct(
        protected string $mode = 'data', // 'template' | 'data'
        protected ?string $spesifikTipe = null, // null (semua sheet) | 'kategori' | 'brand' | 'kualitas' | 'satuan' | 'rak' | 'tipe_hp'
        protected ?int $cabangId = null
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        if (! $this->spesifikTipe || $this->spesifikTipe === 'kategori') {
            $sheets[] = new class($this->mode) implements FromCollection, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(protected string $mode) {}

                public function title(): string
                {
                    return 'Kategori';
                }

                public function headings(): array
                {
                    return ['kategori_utama', 'sub_kategori', 'kode_slug', 'urutan', 'is_active'];
                }

                public function collection(): Collection
                {
                    if ($this->mode === 'template') {
                        return collect([
                            ['kategori_utama' => 'LCD & Touchscreen', 'sub_kategori' => 'LCD iPhone', 'kode_slug' => 'lcd-iphone', 'urutan' => 1, 'is_active' => 'ya'],
                            ['kategori_utama' => 'LCD & Touchscreen', 'sub_kategori' => 'LCD Samsung', 'kode_slug' => 'lcd-samsung', 'urutan' => 2, 'is_active' => 'ya'],
                            ['kategori_utama' => 'Baterai', 'sub_kategori' => 'Baterai Double Power', 'kode_slug' => 'baterai-double-power', 'urutan' => 1, 'is_active' => 'ya'],
                            ['kategori_utama' => 'Fleksibel & Board', 'sub_kategori' => 'Fleksibel Charger', 'kode_slug' => 'fleksibel-charger', 'urutan' => 1, 'is_active' => 'ya'],
                            ['kategori_utama' => 'IC & Chipset', 'sub_kategori' => '', 'kode_slug' => 'ic-chipset', 'urutan' => 5, 'is_active' => 'ya'],
                            ['kategori_utama' => 'Kamera', 'sub_kategori' => 'Kamera Belakang', 'kode_slug' => 'kamera-belakang', 'urutan' => 1, 'is_active' => 'ya'],
                        ]);
                    }

                    $rows = [];
                    $induks = KategoriProduk::whereNull('parent_id')->with('children')->orderBy('urutan')->orderBy('nama')->get();
                    foreach ($induks as $induk) {
                        if ($induk->children->isEmpty()) {
                            $rows[] = [
                                'kategori_utama' => $induk->nama,
                                'sub_kategori' => '',
                                'kode_slug' => $induk->slug,
                                'urutan' => $induk->urutan ?? 0,
                                'is_active' => $induk->is_active ? 'ya' : 'tidak',
                            ];
                        } else {
                            foreach ($induk->children as $child) {
                                $rows[] = [
                                    'kategori_utama' => $induk->nama,
                                    'sub_kategori' => $child->nama,
                                    'kode_slug' => $child->slug,
                                    'urutan' => $child->urutan ?? 0,
                                    'is_active' => $child->is_active ? 'ya' : 'tidak',
                                ];
                            }
                        }
                    }

                    return collect($rows);
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                    ];
                }
            };
        }

        if (! $this->spesifikTipe || $this->spesifikTipe === 'brand') {
            $sheets[] = new class($this->mode) implements FromCollection, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(protected string $mode) {}

                public function title(): string
                {
                    return 'Brand';
                }

                public function headings(): array
                {
                    return ['nama', 'keterangan', 'is_active'];
                }

                public function collection(): Collection
                {
                    if ($this->mode === 'template') {
                        return collect([
                            ['nama' => 'Crown', 'keterangan' => 'Sparepart grade premium & garansi panjang', 'is_active' => 'ya'],
                            ['nama' => 'OG', 'keterangan' => 'Kualitas mendekati original pabrik', 'is_active' => 'ya'],
                            ['nama' => 'Braderpart', 'keterangan' => 'Brand terpercaya teknisi HP Indonesia', 'is_active' => 'ya'],
                            ['nama' => 'Olike', 'keterangan' => 'Aksesoris & baterai terstandarisasi', 'is_active' => 'ya'],
                            ['nama' => 'OEM', 'keterangan' => 'Original Equipment Manufacturer standard', 'is_active' => 'ya'],
                            ['nama' => 'Original', 'keterangan' => 'Komponen copotan / service center resmi', 'is_active' => 'ya'],
                        ]);
                    }

                    return Brand::orderBy('nama')->get()->map(fn ($b) => [
                        'nama' => $b->nama,
                        'keterangan' => $b->keterangan ?? '',
                        'is_active' => $b->is_active ? 'ya' : 'tidak',
                    ]);
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                    ];
                }
            };
        }

        if (! $this->spesifikTipe || $this->spesifikTipe === 'kualitas') {
            $sheets[] = new class($this->mode) implements FromCollection, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(protected string $mode) {}

                public function title(): string
                {
                    return 'Kualitas';
                }

                public function headings(): array
                {
                    return ['nama', 'brand', 'keterangan', 'is_active'];
                }

                public function collection(): Collection
                {
                    if ($this->mode === 'template') {
                        return collect([
                            ['nama' => 'Original Cabutan', 'brand' => '', 'keterangan' => 'Komponen asli copotan unit resmi', 'is_active' => 'ya'],
                            ['nama' => 'Super OLED', 'brand' => 'Crown', 'keterangan' => 'Layar OLED kontras tinggi merk Crown', 'is_active' => 'ya'],
                            ['nama' => 'Incell AAA', 'brand' => '', 'keterangan' => 'Layar tipis responsive harga terjangkau', 'is_active' => 'ya'],
                            ['nama' => 'Grade A', 'brand' => '', 'keterangan' => 'Kualitas standar bengkel', 'is_active' => 'ya'],
                            ['nama' => 'OEM Standard', 'brand' => 'OEM', 'keterangan' => 'Pabrikan non-resmi presisi tinggi', 'is_active' => 'ya'],
                        ]);
                    }

                    return KualitasProduk::with('brand')->orderBy('nama')->get()->map(fn ($k) => [
                        'nama' => $k->nama,
                        'brand' => $k->brand?->nama ?? '',
                        'keterangan' => $k->keterangan ?? '',
                        'is_active' => $k->is_active ? 'ya' : 'tidak',
                    ]);
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                    ];
                }
            };
        }

        if (! $this->spesifikTipe || $this->spesifikTipe === 'satuan') {
            $sheets[] = new class($this->mode) implements FromCollection, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(protected string $mode) {}

                public function title(): string
                {
                    return 'Satuan';
                }

                public function headings(): array
                {
                    return ['kode', 'nama', 'is_active'];
                }

                public function collection(): Collection
                {
                    if ($this->mode === 'template') {
                        return collect([
                            ['kode' => 'pcs', 'nama' => 'Pieces / Butir / Buah', 'is_active' => 'ya'],
                            ['kode' => 'unit', 'nama' => 'Unit Perangkat', 'is_active' => 'ya'],
                            ['kode' => 'box', 'nama' => 'Kotak / Dus', 'is_active' => 'ya'],
                            ['kode' => 'set', 'nama' => 'Set Lengkap', 'is_active' => 'ya'],
                            ['kode' => 'pack', 'nama' => 'Kemasan Pack', 'is_active' => 'ya'],
                            ['kode' => 'roll', 'nama' => 'Gulungan / Roll', 'is_active' => 'ya'],
                            ['kode' => 'meter', 'nama' => 'Meter Panjang', 'is_active' => 'ya'],
                            ['kode' => 'tube', 'nama' => 'Tube / Lem', 'is_active' => 'ya'],
                        ]);
                    }

                    return SatuanUnit::orderBy('kode')->get()->map(fn ($s) => [
                        'kode' => $s->kode,
                        'nama' => $s->nama ?? '',
                        'is_active' => $s->is_active ? 'ya' : 'tidak',
                    ]);
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                    ];
                }
            };
        }

        if (! $this->spesifikTipe || $this->spesifikTipe === 'rak') {
            $sheets[] = new class($this->mode, $this->cabangId) implements FromCollection, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(protected string $mode, protected ?int $cabangId) {}

                public function title(): string
                {
                    return 'Rak';
                }

                public function headings(): array
                {
                    return ['gudang', 'kode', 'nama', 'zona', 'is_active'];
                }

                public function collection(): Collection
                {
                    if ($this->mode === 'template') {
                        $gudangContoh = Gudang::when($this->cabangId, fn ($q) => $q->where('cabang_id', $this->cabangId))->first()?->nama ?? 'Gudang Utama';

                        return collect([
                            ['gudang' => $gudangContoh, 'kode' => 'RAK-A1', 'nama' => 'Rak Sparepart LCD', 'zona' => 'Zona Depan', 'is_active' => 'ya'],
                            ['gudang' => $gudangContoh, 'kode' => 'RAK-A2', 'nama' => 'Rak Baterai HP', 'zona' => 'Zona Depan', 'is_active' => 'ya'],
                            ['gudang' => $gudangContoh, 'kode' => 'RAK-B1', 'nama' => 'Rak IC & Komponen Mikro', 'zona' => 'Zona Belakang', 'is_active' => 'ya'],
                            ['gudang' => $gudangContoh, 'kode' => 'RAK-B2', 'nama' => 'Rak Aksesoris & Charger', 'zona' => 'Zona Belakang', 'is_active' => 'ya'],
                        ]);
                    }

                    return Rak::with('gudang')
                        ->when($this->cabangId, fn ($q) => $q->whereHas('gudang', fn ($g) => $g->where('cabang_id', $this->cabangId)))
                        ->orderBy('kode')
                        ->get()
                        ->map(fn ($r) => [
                            'gudang' => $r->gudang?->nama ?? 'Gudang Utama',
                            'kode' => $r->kode,
                            'nama' => $r->nama ?? '',
                            'zona' => $r->zona ?? '',
                            'is_active' => $r->is_active ? 'ya' : 'tidak',
                        ]);
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                    ];
                }
            };
        }

        if (! $this->spesifikTipe || $this->spesifikTipe === 'tipe_hp') {
            $sheets[] = new class($this->mode) implements FromCollection, WithHeadings, WithStyles, WithTitle
            {
                public function __construct(protected string $mode) {}

                public function title(): string
                {
                    return 'Tipe HP';
                }

                public function headings(): array
                {
                    return ['merk', 'model', 'nama', 'is_active'];
                }

                public function collection(): Collection
                {
                    if ($this->mode === 'template') {
                        return collect([
                            ['merk' => 'Apple', 'model' => 'iPhone 11', 'nama' => 'Apple iPhone 11', 'is_active' => 'ya'],
                            ['merk' => 'Apple', 'model' => 'iPhone 12 Pro', 'nama' => 'Apple iPhone 12 Pro', 'is_active' => 'ya'],
                            ['merk' => 'Samsung', 'model' => 'Galaxy A52', 'nama' => 'Samsung Galaxy A52', 'is_active' => 'ya'],
                            ['merk' => 'Samsung', 'model' => 'Galaxy S21', 'nama' => 'Samsung Galaxy S21', 'is_active' => 'ya'],
                            ['merk' => 'Xiaomi', 'model' => 'Redmi Note 10', 'nama' => 'Xiaomi Redmi Note 10 4G', 'is_active' => 'ya'],
                            ['merk' => 'Oppo', 'model' => 'Reno 6', 'nama' => 'Oppo Reno 6 4G', 'is_active' => 'ya'],
                            ['merk' => 'Vivo', 'model' => 'Y20', 'nama' => 'Vivo Y20 / Y20s', 'is_active' => 'ya'],
                            ['merk' => 'Universal', 'model' => 'Semua HP', 'nama' => 'Universal Model', 'is_active' => 'ya'],
                        ]);
                    }

                    return TipeHp::orderBy('merk')->orderBy('model')->get()->map(fn ($t) => [
                        'merk' => $t->merk,
                        'model' => $t->model,
                        'nama' => $t->nama ?? '',
                        'is_active' => $t->is_active ? 'ya' : 'tidak',
                    ]);
                }

                public function styles(Worksheet $sheet): array
                {
                    return [
                        1 => ['font' => ['bold' => true]],
                    ];
                }
            };
        }

        return $sheets;
    }
}
