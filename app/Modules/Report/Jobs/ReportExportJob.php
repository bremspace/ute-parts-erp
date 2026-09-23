<?php

namespace App\Modules\Report\Jobs;

use App\Modules\Notifikasi\Services\NotificationService;
use App\Modules\Report\Services\ReportBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * [F2-4] Queued export job for custom reports.
 * Dispatched from request — never synchronous. RAM 1GB safe.
 * Selesai → notifikasi inapp.
 */
class ReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** [P2-11] Batas baris ekspor — data dipotong + diberi tanda pada notifikasi. */
    public const ROW_LIMIT = 10000;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * [P2-11] Batas baris per job (override pada test agar tidak perlu
     * menyisipkan 10rb baris). Default = ROW_LIMIT.
     */
    public int $rowLimit = self::ROW_LIMIT;

    public function __construct(
        public string $modelName,
        public array $columns,
        public ?array $filters,
        public ?array $groupBy,
        public ?int $cabangId,
        public string $format, // xlsx or csv
        public int $userId,
        public string $reportName
    ) {}

    public function handle(ReportBuilderService $service, NotificationService $notif): void
    {
        try {
            $query = $service->buildQuery(
                $this->modelName,
                $this->columns ?: ['*'],
                $this->filters,
                $this->groupBy,
                $this->cabangId
            );

            // [P2-11] Tanpa cache dataset penuh di store — baris dibaca sekali
            // (limit+1 untuk deteksi pemotongan) lalu langsung ditulis ke file.
            $rows = $query->limit($this->rowLimit + 1)->get()
                ->map(fn ($item) => $item->toArray())
                ->all();

            $truncated = count($rows) > $this->rowLimit;
            if ($truncated) {
                $rows = array_slice($rows, 0, $this->rowLimit);
            }

            // [P2-11/CROSS-LANE] Nama file WAJIB diawali "{userId}_" — cek
            // kepemilikan unduh di AkuntingController membaca integer sebelum "_".
            $ext = $this->format === 'csv' ? 'csv' : 'xlsx';
            $slug = Str::slug($this->reportName) ?: 'laporan';
            $filename = $this->userId.'_laporan_'.$slug.'-'.now()->format('Ymd-His').'.'.$ext;
            $path = 'exports/'.$filename;

            if ($this->format === 'csv') {
                $csv = $this->convertToCsv($rows);
                Storage::put($path, $csv);
            } else {
                Excel::store(new class($rows) implements FromArray
                {
                    public function __construct(private array $rows) {}

                    public function array(): array
                    {
                        return $this->rows;
                    }
                }, $path, 'local');
            }

            // [P2-11] Tanda pemotongan pada pesan notifikasi (bukan senyap).
            $konten = 'Laporan "'.$this->reportName.'" ('.strtoupper($this->format).') siap diunduh.';
            if ($truncated) {
                $konten .= ' - dipotong maksimal '.number_format($this->rowLimit, 0, ',', '.').' baris';
            }

            $notif->kirim(
                'inapp', null,
                'Export laporan "'.$this->reportName.'" selesai',
                $konten,
                ['url' => url('/api/akunting/export/download?path='.base64_encode($path)), 'jenis' => $this->modelName, 'user_id' => $this->userId]
            );
        } catch (\Throwable $e) {
            Log::error("ReportExportJob gagal: {$e->getMessage()}");
            if ($this->userId) {
                $notif->kirim(
                    'inapp', null,
                    'Export laporan gagal',
                    'Laporan "'.$this->reportName.'" gagal: '.$e->getMessage(),
                    ['jenis' => $this->modelName, 'user_id' => $this->userId]
                );
            }
        }
    }

    private function convertToCsv(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }
        $output = fopen('php://memory', 'w');
        fputcsv($output, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($output, array_map(fn ($v) => is_array($v) || is_object($v) ? json_encode($v) : $v, $row));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
