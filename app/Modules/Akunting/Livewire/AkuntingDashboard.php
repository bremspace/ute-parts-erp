<?php

namespace App\Modules\Akunting\Livewire;

use App\Models\User;
use App\Modules\Akunting\Jobs\ExportLaporanJob;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Models\Piutang;
use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\ExportLaporanService;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Akunting\Services\PembayaranSubledgerService;
use App\Modules\Akunting\Services\ValidasiBarisJurnal;
use App\Modules\Pos\Services\KasSesiState;
use App\Modules\Rbac\Traits\PunyaRiwayatAktivitas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;
use Livewire\Component;
use Livewire\WithPagination;

class AkuntingDashboard extends Component
{
    use PunyaRiwayatAktivitas;
    use WithPagination;

    /**
     * [B-10g] TTL cache widget Neraca (detik) — SAMA dengan API [API: ACC-06]
     * (15 menit, Constraint RAM 1GB: laporan berat wajib async/cache).
     * Belum ada key config TTL di repo ini; kalau suatu saat ditambahkan,
     * cukup dibaca di satu tempat ini.
     */
    private const NERACA_CACHE_TTL = 900;

    /**
     * [B-15b] TTL cache laporan PERIODE (laba rugi + arus kas) — detik.
     * Sama dengan NERACA_CACHE_TTL & API [API: ACC-05] (15 menit): laporan
     * berat di server RAM 1GB wajib lewat cache, bukan dihitung tiap request.
     */
    private const LAPORAN_PERIODE_CACHE_TTL = 900;

    /**
     * [B-15b] Jumlah baris per halaman untuk daftar Piutang / Utang.
     * Kedua tabel TIDAK punya filter/search (hanya tab + export), jadi tidak
     * ada filter yang perlu reset halaman; pemisahan nama halaman
     * (`pagePiutang` / `pageUtang` / `page` untuk jurnal) mencegah halaman
     * tabel satu ikut bergeser saat tabel lain dimuat ulang.
     */
    private const PER_HALAMAN_SUBLEDGER = 15;

    public string $activeTab = 'laporan'; // laporan, jurnal, coa, piutang, utang

    public string $periodeDari = '';

    public string $periodeSampai = '';

    // Jurnal manual modal
    public bool $showJurnalManual = false;

    public bool $showJurnalConfirmation = false;

    public string $manualTanggal = '';

    public string $manualDeskripsi = '';

    public array $manualLines = [];

    // COA modal
    public bool $showCoaModal = false;

    public array $coaForm = [
        'kode' => '', 'nama' => '', 'tipe' => 'aset', 'kelompok' => '', 'saldo_normal' => 'debit',
    ];

    public bool $showBayarPiutangModal = false;

    public ?int $piutangId = null;

    public float $bayarPiutangJumlah = 0;

    // [B-10a / P0-1] Kunci idempotensi per opening modal — double submit / retry
    // tidak boleh menghasilkan jurnal + update subledger dua kali.
    public string $bayarPiutangKunci = '';

    // Bayar utang modal
    public bool $showBayarUtangModal = false;

    public ?int $utangId = null;

    public float $bayarUtangJumlah = 0;

    public string $bayarUtangKunci = '';

    public function mount()
    {
        $this->periodeDari = now()->startOfMonth()->toDateString();
        $this->periodeSampai = now()->toDateString();
        $this->resetManualJournalForm();
    }

    // ===== GUARD [B-10a / P0-2] =====

    /**
     * Guard permission server-side untuk setiap method mutasi.
     * UI-only guard tidak cukup: Livewire action bisa dipanggil langsung.
     */
    private function izin(string $permission): void
    {
        abort_unless(
            auth()->user()?->can($permission),
            403,
            'Anda tidak memiliki izin untuk aksi ini.'
        );
    }

    /**
     * Scope cabang aktif untuk query baca.
     * Session null = 0 = tidak match baris mana pun (bukan "semua cabang").
     */
    private function cabangScopeId(): int
    {
        return (int) (session('cabang_id') ?? 0);
    }

    /**
     * Cabang aktif wajib ada untuk mutasi (fail-closed → 403).
     */
    private function cabangAktif(): int
    {
        $cabangId = (int) (session('cabang_id') ?? 0);

        abort_if($cabangId <= 0, 403, 'Cabang aktif belum dipilih.');

        return $cabangId;
    }

    // ===== LAPORAN =====

    /**
     * [F2-5] Export tab jurnal/piutang/utang via queue (async — jangan sinkron di request).
     */
    public function exportLaporan(string $jenis, string $format = 'xlsx'): void
    {
        if (! in_array($jenis, ['jurnal', 'piutang', 'utang'], true)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Jenis export tidak didukung']);

            return;
        }
        if (! auth()->user()?->can('laporan.cabang')) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

            return;
        }

        dispatch(new ExportLaporanJob(
            jenis: $jenis,
            periodeDari: $this->periodeDari ?: null,
            periodeSampai: $this->periodeSampai ?: null,
            cabangId: session('cabang_id'),
            akunId: null,
            userId: auth()->id(),
            format: $format === 'csv' ? 'csv' : 'xlsx',
        ));

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export '.str_replace('_', ' ', $jenis).' diantre — notifikasi + link unduh muncul setelah selesai.',
        ]);
    }

    /**
     * [B-15b] Laba rugi = agregasi SQL per akun COA + cache 15 menit.
     *
     * SEBELUMNYA: `JurnalAkuntansi::with('akun')->get()` menarik SELURUH baris
     * periode ke memori (produksi 12.000-25.000 baris/bulan → 50.000+ model
     * terhidrasi, ±150-250MB per klik) hanya untuk menjumlah per akun.
     * Sekarang: `GROUP BY akun` di SQL (1 query, hasil = jumlah akun COA)
     * + `Cache::remember` 15 menit memakai pola yang sama dengan
     * `getNeracaProperty()`. Key cache mengikuti konvensi API [API: ACC-05]
     * (`laporan-labarugi-{cabang}-{dari}-{sampai}`).
     *
     * PERHITUNGANNYA TIDAK BERUBAH: pemisahan per akun, pemfilteran tipe
     * (pendapatan vs beban), pembulatan `round(..., 2)`, dan urutan baris
     * (urutan jurnal pertama, lewat `MIN(id)`) semuanya dipertahankan.
     */
    public function getLabaRugiProperty(): array
    {
        $dari = $this->periodeDari;
        $sampai = $this->periodeSampai;
        $cabangId = $this->cabangScopeId();

        return Cache::remember(
            'laporan-labarugi-'.$cabangId.'-'.$dari.'-'.$sampai,
            self::LAPORAN_PERIODE_CACHE_TTL,
            function () use ($dari, $sampai, $cabangId): array {
                $perAkun = $this->agregatJurnalPeriode($dari, $sampai, $cabangId);

                $pendapatan = $perAkun->where('tipe', 'pendapatan')->map(fn (array $a): array => [
                    'nama' => $a['nama'],
                    'kode' => $a['kode'],
                    'total' => round($a['total_kredit'] - $a['total_debit'], 2),
                ])->values();

                $beban = $perAkun->where('tipe', 'beban')->map(fn (array $a): array => [
                    'nama' => $a['nama'],
                    'kode' => $a['kode'],
                    'total' => round($a['total_debit'] - $a['total_kredit'], 2),
                ])->values();

                return [
                    'pendapatan' => $pendapatan,
                    'beban' => $beban,
                    'total_pendapatan' => round($pendapatan->sum('total'), 2),
                    'total_beban' => round($beban->sum('total'), 2),
                    'laba_bersih' => round($pendapatan->sum('total') - $beban->sum('total'), 2),
                ];
            }
        );
    }

    /**
     * [B-15b] Agregasi jurnal PERIODE per akun COA langsung di SQL.
     *
     * Satu query `GROUP BY akun_coa` + join `akun_coa` (kode/nama/tipe/kelompok/
     * saldo_normal ikut terbaca tanpa query per akun). Jumlah baris hasil =
     * jumlah akun COA yang bergerak, bukan jumlah baris jurnal.
     *
     * `MIN(id)` dipakai untuk urutan baris: versi lama (`->get()` lalu
     * `groupBy()` di PHP) menampilkan akun sesuai urutan jurnal pertama, jadi
     * urutan itu dipertahankan agar tampilan tidak berubah.
     *
     * @return Collection<int,array{akun_coa_id:int,kode:string,nama:string,tipe:string,kelompok:string,saldo_normal:string,total_debit:float,total_kredit:float}>
     */
    private function agregatJurnalPeriode(string $dari, string $sampai, int $cabangId): Collection
    {
        return JurnalAkuntansi::query()
            ->join('akun_coa', 'akun_coa.id', '=', 'jurnal_akuntansi.akun_coa_id')
            // [B-10a / P0-2] scope cabang aktif (session null = 0 = tidak match)
            ->where('jurnal_akuntansi.cabang_id', $cabangId)
            ->whereDate('jurnal_akuntansi.tanggal', '>=', $dari)
            ->whereDate('jurnal_akuntansi.tanggal', '<=', $sampai)
            ->select('jurnal_akuntansi.akun_coa_id')
            ->selectRaw('akun_coa.kode, akun_coa.nama, akun_coa.tipe, akun_coa.kelompok, akun_coa.saldo_normal')
            ->selectRaw('SUM(jurnal_akuntansi.debit) as total_debit, SUM(jurnal_akuntansi.kredit) as total_kredit')
            ->selectRaw('MIN(jurnal_akuntansi.id) as urut_pertama')
            ->groupBy(
                'jurnal_akuntansi.akun_coa_id',
                'akun_coa.kode',
                'akun_coa.nama',
                'akun_coa.tipe',
                'akun_coa.kelompok',
                'akun_coa.saldo_normal',
            )
            ->orderBy('urut_pertama')
            ->get()
            ->map(fn (JurnalAkuntansi $a): array => [
                'akun_coa_id' => (int) $a->akun_coa_id,
                'kode' => (string) $a->kode,
                'nama' => (string) $a->nama,
                'tipe' => (string) $a->tipe,
                'kelompok' => (string) $a->kelompok,
                'saldo_normal' => (string) $a->saldo_normal,
                'total_debit' => (float) $a->total_debit,
                'total_kredit' => (float) $a->total_kredit,
            ]);
    }

    /**
     * [B-10g] Widget Neraca dashboard = sumber yang SAMA dengan laporan Neraca.
     *
     * SEBELUMNYA widget ini menjumlah sendiri saldo akun ekuitas TANPA laba
     * periode berjalan, sedangkan laporan Neraca (canonical) menghitung saldo
     * kumulatif + `laba_periode_berjalan`. Akibatnya indikator "balance" di
     * kartu dashboard bisa salah: laba positif membuat dashboard menulis
     * "tidak balance" padahal seluruh jurnal double-entry sudah benar.
     *
     * Sekarang widget memakai `ExportLaporanService::neracaSaldo()` — sumber
     * yang sama dengan export Excel dan API [API: ACC-06] — sehingga angka,
     * status SEIMBANG/TIDAK SEIMBANG, dan selisih tidak bisa berbeda. Cache
     * memakai key + TTL yang sama dengan API (15 menit) supaya keduanya
     * benar-benar membaca data yang sama.
     */
    public function getNeracaProperty(): array
    {
        $cabangId = $this->cabangScopeId();
        $sampai = $this->periodeSampai ?: now()->toDateString();

        return Cache::remember(
            'laporan-neraca-'.$cabangId.'-'.$sampai,
            self::NERACA_CACHE_TTL,
            fn (): array => app(ExportLaporanService::class)->neracaSaldo($cabangId, $sampai)
        );
    }

    // ===== JURNAL =====
    public function getJurnalsProperty()
    {
        // [B-10a / P0-2] list jurnal wajib scoped cabang aktif
        return JurnalAkuntansi::with(['akun', 'cabang'])
            ->where('cabang_id', $this->cabangScopeId())
            ->latest('tanggal')
            ->paginate(25);
    }

    public function openJurnalManualModal(): void
    {
        $this->resetManualJournalForm();
        $this->showJurnalManual = true;
    }

    public function tutupJurnalManual(): void
    {
        $this->showJurnalManual = false;
        $this->showJurnalConfirmation = false;
    }

    public function getAkunJurnalOptionsProperty(): Collection
    {
        return AkunCOA::query()
            ->where('is_active', true)
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'tipe', 'kelompok', 'saldo_normal']);
    }

    public function addManualLine(): void
    {
        $sisi = count($this->manualLines) % 2 === 0 ? 'debit' : 'kredit';
        $this->manualLines[] = $this->newManualLine($sisi);
    }

    public function removeManualLine(int $index): void
    {
        if (count($this->manualLines) <= 2 || ! array_key_exists($index, $this->manualLines)) {
            return;
        }

        unset($this->manualLines[$index]);
        $this->manualLines = array_values($this->manualLines);
    }

    public function setManualLineSide(int $index, string $sisi): void
    {
        if (! array_key_exists($index, $this->manualLines) || ! in_array($sisi, ['debit', 'kredit'], true)) {
            return;
        }

        if (($this->manualLines[$index]['sisi'] ?? null) === $sisi) {
            return;
        }

        $this->manualLines[$index]['sisi'] = $sisi;
        $this->manualLines[$index]['akun_kode'] = '';
    }

    public function tinjauJurnalManual(): void
    {
        $validation = $this->buildManualJournalValidation();

        if (! $validation['summary']['can_submit']) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $validation['summary']['messages'][0] ?? __('Jurnal belum siap diposting.'),
            ]);

            return;
        }

        $this->showJurnalConfirmation = true;
    }

    public function batalTinjauJurnalManual(): void
    {
        $this->showJurnalConfirmation = false;
    }

    public function getManualValidationProperty(): array
    {
        return $this->buildManualJournalValidation()['summary'];
    }

    public function simpanJurnalManual(): void
    {
        // [B-10a / P0-2] guard server-side: UI tidak cukup
        $this->izin('akunting.create');
        $this->cabangAktif();

        $validation = $this->buildManualJournalValidation();

        if (! $validation['summary']['can_submit']) {
            $this->showJurnalConfirmation = false;
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => $validation['summary']['messages'][0] ?? __('Jurnal belum siap diposting.'),
            ]);

            return;
        }

        try {
            $jurnalService = app(JurnalService::class);
            $cabangId = $this->cabangAktif();
            $noJurnal = $jurnalService->generateNoJurnal('manual', $cabangId);
            $jurnalService->post(
                $noJurnal,
                $this->manualTanggal,
                'manual',
                $validation['lines'],
                $this->manualDeskripsi,
                $cabangId,
                auth()->id()
            );

            $this->resetManualJournalForm();
            $this->showJurnalManual = false;
            $this->dispatch('alert', [
                'type' => 'success',
                'message' => "Jurnal manual {$noJurnal} diposting",
            ]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Validasi yang sama untuk indikator real-time dan penjagaan server.
     * Data dinormalisasi ke format JurnalService sebelum service dipanggil.
     *
     * [B-10g] Aturan baris (akun ada → aktif → nominal > 0 → satu sisi →
     * sisi sesuai saldo_normal) dan pesan jurnal belum balance TIDAK lagi
     * disalin di sini: semuanya lewat `ValidasiBarisJurnal`, service yang sama
     * dipakai `JurnalService::post()` dan `AkuntingController::storeJurnalManual()`
     * ([API: ACC-04]). Class ini hanya menyusun pesan per-field agar indikator
     * real-time di form tetap bisa menunjuk baris yang salah.
     */
    private function buildManualJournalValidation(): array
    {
        $serviceLines = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;

        foreach ($this->manualLines as $line) {
            $line = is_array($line) ? $line : [];
            $sisi = (string) ($line['sisi'] ?? '');
            $jumlah = round($this->parseNominal($line['jumlah'] ?? 0), 2);
            $debit = $sisi === 'debit' ? $jumlah : 0.0;
            $kredit = $sisi === 'kredit' ? $jumlah : 0.0;

            $totalDebit += $debit;
            $totalKredit += $kredit;
            $serviceLines[] = [
                'akun_kode' => trim((string) ($line['akun_kode'] ?? '')),
                'debit' => $debit,
                'kredit' => $kredit,
            ];
        }

        $normalizedLines = collect($this->manualLines)
            ->map(function (mixed $line): array {
                $line = is_array($line) ? $line : [];

                return [
                    'akun_kode' => trim((string) ($line['akun_kode'] ?? '')),
                    'sisi' => (string) ($line['sisi'] ?? ''),
                    'jumlah' => round($this->parseNominal($line['jumlah'] ?? 0), 2),
                ];
            })
            ->values()
            ->all();

        // Aturan STRUKTUR form (tanggal, deskripsi, minimal dua baris, kolom
        // wajib) tetap di sini; aturan isi baris sepenuhnya di service.
        // `gt:0` sengaja TIDAK dipakai karena nominal > 0 sudah dinilai
        // ValidasiBarisJurnal (pesan service, bukan duplikat).
        $validator = Validator::make(
            [
                'tanggal' => $this->manualTanggal,
                'deskripsi' => $this->manualDeskripsi,
                'baris' => $normalizedLines,
            ],
            [
                'tanggal' => ['required', 'date'],
                'deskripsi' => ['required', 'string', 'max:255'],
                'baris' => ['required', 'array', 'min:2'],
                'baris.*.akun_kode' => ['required', 'string', 'max:20'],
                'baris.*.sisi' => ['required', Rule::in(['debit', 'kredit'])],
                'baris.*.jumlah' => ['required', 'numeric'],
            ],
            [
                'tanggal.required' => __('Tanggal jurnal wajib dipilih.'),
                'tanggal.date' => __('Tanggal jurnal tidak valid.'),
                'deskripsi.required' => __('Deskripsi wajib diisi.'),
                'deskripsi.max' => __('Deskripsi maksimal 255 karakter.'),
                'baris.required' => __('Jurnal membutuhkan minimal dua baris entri.'),
                'baris.min' => __('Jurnal membutuhkan minimal dua baris entri.'),
                'baris.*.akun_kode.required' => __('Pilih akun COA untuk baris ini.'),
                'baris.*.sisi.required' => __('Pilih sisi debit atau kredit.'),
                'baris.*.sisi.in' => __('Sisi baris harus debit atau kredit.'),
                'baris.*.jumlah.required' => __('Nominal baris wajib diisi.'),
                'baris.*.jumlah.numeric' => __('Nominal harus berupa angka.'),
            ],
            [
                'tanggal' => __('tanggal'),
                'deskripsi' => __('deskripsi'),
                'baris.*.akun_kode' => __('akun COA'),
                'baris.*.sisi' => __('sisi baris'),
                'baris.*.jumlah' => __('nominal'),
            ]
        );

        $validator->after(function (LaravelValidator $validator) use ($serviceLines, $totalDebit, $totalKredit): void {
            // Sumber tunggal: ValidasiBarisJurnal (sama dgn API ACC-04 + JurnalService).
            foreach (ValidasiBarisJurnal::cekBarisPerBaris($serviceLines) as $index => $perBaris) {
                foreach ($perBaris as $item) {
                    $validator->errors()->add("baris.{$index}.{$item['field']}", $item['pesan']);
                }
            }

            if (! $validator->errors()->isEmpty()) {
                return;
            }

            if (round($totalDebit, 2) !== round($totalKredit, 2)) {
                $validator->errors()->add('baris', ValidasiBarisJurnal::pesanBelumBalance($totalDebit, $totalKredit));
            } elseif ($totalDebit <= 0) {
                $validator->errors()->add('baris', ValidasiBarisJurnal::pesanNilaiTransaksiNol());
            }
        });

        $balanced = round($totalDebit, 2) === round($totalKredit, 2) && $totalDebit > 0;
        $canSubmit = $validator->errors()->isEmpty() && $balanced;

        return [
            'lines' => $serviceLines,
            'summary' => [
                'debit' => round($totalDebit, 2),
                'kredit' => round($totalKredit, 2),
                'selisih' => round($totalDebit - $totalKredit, 2),
                'balanced' => $balanced,
                'can_submit' => $canSubmit,
                'errors' => $validator->errors()->toArray(),
                'messages' => array_values(array_unique($validator->errors()->all())),
            ],
        ];
    }

    private function parseNominal(mixed $value): float
    {
        $normalized = str_replace(['Rp', ' '], '', trim((string) $value));

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $parts = explode('.', $normalized);
            $groupedAsThousands = count($parts) > 1;

            foreach (array_slice($parts, 1) as $part) {
                if (strlen($part) !== 3) {
                    $groupedAsThousands = false;
                    break;
                }
            }

            if ($groupedAsThousands) {
                $normalized = str_replace('.', '', $normalized);
            }
        }

        return (float) $normalized;
    }

    private function resetManualJournalForm(): void
    {
        $this->manualTanggal = now()->toDateString();
        $this->manualDeskripsi = '';
        $this->manualLines = [
            $this->newManualLine('debit'),
            $this->newManualLine('kredit'),
        ];
        $this->showJurnalConfirmation = false;
    }

    private function newManualLine(string $sisi): array
    {
        return [
            'uid' => (string) Str::uuid(),
            'akun_kode' => '',
            'sisi' => $sisi,
            'jumlah' => 0,
        ];
    }

    // ===== COA =====
    public function getCoaListProperty()
    {
        return AkunCOA::orderBy('kode')->get();
    }

    public function openCoaModal()
    {
        $this->coaForm = ['kode' => '', 'nama' => '', 'tipe' => 'aset', 'kelompok' => '', 'saldo_normal' => 'debit'];
        $this->showCoaModal = true;
    }

    public function simpanCoa()
    {
        // [B-10a / P0-2] guard server-side: buat COA wajib akunting.edit
        $this->izin('akunting.edit');

        $this->validate([
            'coaForm.kode' => 'required|string|max:20|unique:akun_coa,kode',
            'coaForm.nama' => 'required|string|max:255',
            'coaForm.tipe' => 'required|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'coaForm.kelompok' => 'required|string|max:100',
            'coaForm.saldo_normal' => 'required|in:debit,kredit',
        ]);

        AkunCOA::create($this->coaForm);
        $this->showCoaModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Akun COA ditambahkan']);
    }

    // ===== PIUTANG / UTANG =====
    /**
     * [B-15b] Daftar piutang = paginasi, bukan `get()`.
     *
     * SEBELUMNYA `->latest()->get()` menarik seluruh piutang cabang (200-1.000
     * baris di produksi) ke memori + 1 query eager-load `pelanggan` per halaman
     * render. Sekarang `simplePaginate` (1 query, tanpa COUNT) per 15 baris.
     * Kolom, urutan, dan isi tabel tidak berubah. Export tetap lewat
     * `ExportLaporanService` (queue) sehingga data lengkap tidak terpotong.
     */
    public function getPiutangsProperty()
    {
        // [B-10a / P0-2] AR scoped cabang aktif
        return Piutang::with('pelanggan')
            ->where('cabang_id', $this->cabangScopeId())
            ->latest()
            ->simplePaginate(self::PER_HALAMAN_SUBLEDGER, ['*'], 'pagePiutang');
    }

    /**
     * [B-15b] Arus kas (metode tidak langsung) = agregasi SQL per akun COA
     * + cache 15 menit. Semua angka, tanda, dan pembulatan TIDAK berubah —
     * hanya sumber datanya: bukan semua baris jurnal periode, tapi hasil
     * `GROUP BY akun` (lihat `agregatJurnalPeriode()`).
     *
     * Peringatan: delta piutang/persediaan/utang di sini adalah PERGERAKAN
     * periode (debit - kredit), bukan saldo akhir — itu kontrak lama (sama
     * dengan API [API: ACC-04b]) dan sengaja dipertahankan.
     */
    public function getArusKasProperty(): array
    {
        $dari = $this->periodeDari;
        $sampai = $this->periodeSampai;
        $cabangId = $this->cabangScopeId();

        return Cache::remember(
            'laporan-aruskas-'.$cabangId.'-'.$dari.'-'.$sampai,
            self::LAPORAN_PERIODE_CACHE_TTL,
            function () use ($dari, $sampai, $cabangId): array {
                $akun = $this->agregatJurnalPeriode($dari, $sampai, $cabangId);

                $labaBersih = round(
                    $akun->where('tipe', 'pendapatan')->sum(fn (array $a) => $a['total_kredit'] - $a['total_debit'])
                    - $akun->where('tipe', 'beban')->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit']),
                    2
                );

                $piutangDelta = round($akun->where('kode', '120-01')->sum('total_debit') - $akun->where('kode', '120-01')->sum('total_kredit'), 2);
                $persediaanDelta = round($akun->where('kode', '130-01')->sum('total_debit') - $akun->where('kode', '130-01')->sum('total_kredit'), 2);
                $utangDelta = round(
                    $akun->where('kode', '210-01')->sum('total_kredit') - $akun->where('kode', '210-01')->sum('total_debit')
                    + $akun->where('kode', '210-03')->sum('total_kredit') - $akun->where('kode', '210-03')->sum('total_debit'),
                    2
                );

                $arusKasOperasi = round($labaBersih - $piutangDelta - $persediaanDelta + $utangDelta, 2);

                $arusInvestasi = round(
                    -$akun->where('kelompok', 'aset_tetap')->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit'])
                    - $akun->where('kelompok', 'peralatan')->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit'])
                    - $akun->where('kelompok', 'perlengkapan')->sum(fn (array $a) => $a['total_debit'] - $a['total_kredit']),
                    2
                );

                $arusPendanaan = round(
                    $akun->where('kelompok', 'modal')->sum(fn (array $a) => $a['total_kredit'] - $a['total_debit'])
                    + $akun->where('kelompok', 'laba_ditahan')->sum(fn (array $a) => $a['total_kredit'] - $a['total_debit']),
                    2
                );

                return [
                    'laba_bersih' => $labaBersih,
                    'penyesuaian' => [
                        'kenaikan_piutang' => -$piutangDelta,
                        'kenaikan_persediaan' => -$persediaanDelta,
                        'kenaikan_utang' => $utangDelta,
                    ],
                    'arus_kas_operasi' => $arusKasOperasi,
                    'arus_kas_investasi' => $arusInvestasi,
                    'arus_kas_pendanaan' => $arusPendanaan,
                    'kenaikan_kas_neto' => round($arusKasOperasi + $arusInvestasi + $arusPendanaan, 2),
                ];
            }
        );
    }

    public function openBayarPiutangModal(int $id)
    {
        // [B-10a / P0-2] id datang dari client → guard permission + scope cabang
        $this->izin('piutang.manage');

        $piutang = Piutang::where('cabang_id', $this->cabangAktif())->find($id);

        if (! $piutang) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Data piutang tidak ditemukan.']);

            return;
        }

        $this->piutangId = $piutang->id;
        $this->bayarPiutangJumlah = (float) $piutang->sisa;
        $this->bayarPiutangKunci = (string) Str::uuid();
        $this->showBayarPiutangModal = true;
    }

    /**
     * [B-10a / P0-1] Terima pembayaran piutang via PembayaranSubledgerService.
     *
     * Sebelumnya method ini HANYA update `jumlah_dibayar` + status tanpa mem-post
     * jurnal sama sekali. Sekarang subledger + jurnal wajib satu transaksi.
     */
    public function bayarPiutang()
    {
        $this->izin('piutang.manage');

        if (! $this->piutangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih data piutang terlebih dahulu.']);

            return;
        }

        try {
            $hasil = app(PembayaranSubledgerService::class)->bayarPiutang(
                (int) $this->piutangId,
                (float) $this->bayarPiutangJumlah,
                $this->cabangAktif(),
                auth()->id(),
                $this->bayarPiutangKunci !== '' ? $this->bayarPiutangKunci : null
            );
        } catch (\Exception $e) {
            // [P0-1] Jurnal/subledger gagal = tidak ada perubahan sama sekali
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->showBayarPiutangModal = false;
        $this->piutangId = null;
        $this->bayarPiutangKunci = '';
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Penerimaan piutang dicatat (jurnal '.$hasil['no_jurnal'].')',
        ]);
    }

    /** [B-15b] Daftar utang = paginasi (lihat `getPiutangsProperty()`). */
    public function getUtangsProperty()
    {
        // [B-10a / P0-2] AP scoped cabang aktif
        return Utang::where('cabang_id', $this->cabangScopeId())
            ->latest()
            ->simplePaginate(self::PER_HALAMAN_SUBLEDGER, ['*'], 'pageUtang');
    }

    public function openBayarUtangModal(int $id)
    {
        $this->izin('utang.manage');

        $utang = Utang::where('cabang_id', $this->cabangAktif())->find($id);

        if (! $utang) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Data utang tidak ditemukan.']);

            return;
        }

        $this->utangId = $utang->id;
        $this->bayarUtangJumlah = (float) $utang->sisa;
        $this->bayarUtangKunci = (string) Str::uuid();
        $this->showBayarUtangModal = true;
    }

    /**
     * [B-10a / P0-1] Bayar utang via PembayaranSubledgerService (atomic).
     */
    public function bayarUtang()
    {
        $this->izin('utang.manage');

        if (! $this->utangId) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Pilih data utang terlebih dahulu.']);

            return;
        }

        try {
            $hasil = app(PembayaranSubledgerService::class)->bayarUtang(
                (int) $this->utangId,
                (float) $this->bayarUtangJumlah,
                $this->cabangAktif(),
                auth()->id(),
                $this->bayarUtangKunci !== '' ? $this->bayarUtangKunci : null
            );
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => $e->getMessage()]);

            return;
        }

        $this->showBayarUtangModal = false;
        $this->utangId = null;
        $this->bayarUtangKunci = '';
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Pembayaran utang dicatat (jurnal '.$hasil['no_jurnal'].')',
        ]);
    }

    // [T-33] Riwayat sesi kas per cabang (laporan Akunting)
    public function getKasSesiRiwayatProperty()
    {
        $rows = app(KasSesiState::class)->riwayat(session('cabang_id'), 50);
        $userIds = $rows->pluck('user_id')->filter()->unique()->values();
        $users = User::whereIn('id', $userIds)->pluck('name', 'id');

        return $rows->map(fn ($r) => (object) [
            'id' => $r->id,
            'kasir' => $r->user_id ? ($users[$r->user_id] ?? "Kasir #{$r->user_id}") : 'Bersama (legacy)',
            'saldo_awal' => $r->saldo_awal,
            'saldo_akhir_sistem' => $r->saldo_akhir_sistem,
            'saldo_akhir_fisik' => $r->saldo_akhir_fisik,
            'selisih' => $r->selisih,
            'sumber' => $r->sumber ?? 'manual',
            'status' => $r->status,
            'dibuka_at' => $r->dibuka_at,
            'ditutup_at' => $r->ditutup_at,
        ]);
    }

    public function render()
    {
        $manualFormVisible = $this->showJurnalManual || $this->showJurnalConfirmation;

        return view('modules.akunting.livewire.akunting-dashboard', [
            'akunCoaList' => $this->coaList,
            'akunJurnalOptions' => $manualFormVisible ? $this->akunJurnalOptions : collect(),
            'manualValidation' => $manualFormVisible ? $this->manualValidation : [
                'debit' => 0,
                'kredit' => 0,
                'selisih' => 0,
                'balanced' => false,
                'can_submit' => false,
                'errors' => [],
                'messages' => [],
            ],
            'labaRugi' => $this->labaRugi,
            'neraca' => $this->neraca,
            'arusKas' => $this->arusKas,
            'jurnals' => $this->jurnals,
            'piutangs' => $this->piutangs,
            'utangs' => $this->utangs,
            'kasSesiRiwayat' => $this->kasSesiRiwayat,
        ])->layout('layouts.backoffice', ['header' => 'Akunting & Keuangan']);
    }
}
