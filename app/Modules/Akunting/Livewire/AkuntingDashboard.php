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
use App\Traits\ParsesNominal;
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
    use ParsesNominal;
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

    /** Cakupan laporan keuangan: 'cabang' (cabang aktif) atau 'konsolidasi' (gabungan seluruh cabang) */
    public string $cakupanLaporan = 'cabang';

    public string $periodeDari = '';

    public string $periodeSampai = '';

    // Jurnal manual modal
    public bool $showJurnalManual = false;

    public bool $showJurnalConfirmation = false;

    public string $manualTanggal = '';

    public string $manualDeskripsi = '';

    public array $manualLines = [];

    // COA modal. `id` = null → mode tambah; terisi → mode edit.
    public bool $showCoaModal = false;

    public array $coaForm = [
        'id' => null, 'kode' => '', 'nama' => '', 'tipe' => 'aset', 'kelompok' => '', 'saldo_normal' => 'debit',
    ];

    public bool $showBayarPiutangModal = false;

    public ?int $piutangId = null;

    public mixed $bayarPiutangJumlah = 0;

    // [B-10a / P0-1] Kunci idempotensi per opening modal — double submit / retry
    // tidak boleh menghasilkan jurnal + update subledger dua kali.
    public string $bayarPiutangKunci = '';

    // Bayar utang modal
    public bool $showBayarUtangModal = false;

    public ?int $utangId = null;

    public mixed $bayarUtangJumlah = 0;

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
     * Scope cabang aktif untuk query baca laporan / subledger.
     * Jika cakupan laporan = 'konsolidasi' dan user punya izin laporan.konsolidasi,
     * kembalikan null (mengagregasi data seluruh cabang aktif).
     * Jika cabang, return cabang_id dari session (session null = 0 = fail-closed).
     */
    private function cabangScopeId(): ?int
    {
        if ($this->cakupanLaporan === 'konsolidasi' && auth()->user()?->can('laporan.konsolidasi')) {
            return null;
        }

        return (int) (session('cabang_id') ?? 0);
    }

    public function setCakupanLaporan(string $cakupan): void
    {
        if ($cakupan === 'konsolidasi') {
            $this->izin('laporan.konsolidasi');
        }

        $this->cakupanLaporan = in_array($cakupan, ['cabang', 'konsolidasi'], true) ? $cakupan : 'cabang';
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

        if ($this->cakupanLaporan === 'konsolidasi') {
            if (! auth()->user()?->can('laporan.konsolidasi')) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin laporan konsolidasi']);

                return;
            }
            $exportCabangId = null;
        } else {
            if (! auth()->user()?->can('laporan.cabang')) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak punya izin export laporan']);

                return;
            }
            $exportCabangId = session('cabang_id');
        }

        dispatch(new ExportLaporanJob(
            jenis: $jenis,
            periodeDari: $this->periodeDari ?: null,
            periodeSampai: $this->periodeSampai ?: null,
            cabangId: $exportCabangId,
            akunId: null,
            userId: auth()->id(),
            format: $format === 'csv' ? 'csv' : 'xlsx',
        ));

        $cakupanText = $this->cakupanLaporan === 'konsolidasi' ? ' konsolidasi' : '';
        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Export '.str_replace('_', ' ', $jenis).$cakupanText.' diantre — notifikasi + link unduh muncul setelah selesai.',
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
        $cacheKey = $cabangId !== null
            ? 'laporan-labarugi-'.$cabangId.'-'.$dari.'-'.$sampai
            : 'laporan-labarugi-konsolidasi-'.$dari.'-'.$sampai;

        return Cache::remember(
            $cacheKey,
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
                    'pendapatan' => $pendapatan->all(),
                    'beban' => $beban->all(),
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
    private function agregatJurnalPeriode(string $dari, string $sampai, ?int $cabangId): Collection
    {
        $query = JurnalAkuntansi::query()
            ->join('akun_coa', 'akun_coa.id', '=', 'jurnal_akuntansi.akun_coa_id');

        // [B-10a / P0-2] scope cabang aktif (jika null = konsolidasi seluruh cabang)
        if ($cabangId !== null) {
            $query->where('jurnal_akuntansi.cabang_id', $cabangId);
        }

        return $query->whereDate('jurnal_akuntansi.tanggal', '>=', $dari)
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
        $cacheKey = $cabangId !== null
            ? 'laporan-neraca-'.$cabangId.'-'.$sampai
            : 'laporan-neraca-konsolidasi-'.$sampai;

        return Cache::remember(
            $cacheKey,
            self::NERACA_CACHE_TTL,
            fn (): array => app(ExportLaporanService::class)->neracaSaldo($cabangId, $sampai)
        );
    }

    // ===== JURNAL =====
    public function getJurnalsProperty()
    {
        // [B-10a / P0-2] list jurnal scoped cabang aktif (atau seluruh cabang jika konsolidasi)
        $cabangId = $this->cabangScopeId();
        $query = JurnalAkuntansi::with(['akun', 'cabang']);

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        return $query->latest('tanggal')->paginate(25);
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

    /**
     * [COA-CRUD] Daftar akun COA = paginasi, bukan `get()`.
     *
     * SEBELUMNYA `->get()` menarik SELURUH akun COA ke memori tiap render tab
     * COA. COA bersifat global (tanpa cabang_id) sehingga grow-nya mengikuti
     * seluruh cabang — di produksi ratusan baris, dan `get()` di server RAM
     * 1GB adalah risiko nyata. Sekarang 25 baris per halaman lewat
     * `WithPagination` (komponen sudah memakainya untuk jurnal). Nama halaman
     * `pageCoa` dipisah dari `page` (jurnal) / `pagePiutang` / `pageUtang`
     * supaya halaman tabel satu tidak ikut bergeser saat tabel lain dimuat
     * ulang — sama seperti `PER_HALAMAN_SUBLEDGER` di atas.
     */
    public function getCoaListProperty()
    {
        return AkunCOA::orderBy('kode')->paginate(25, ['*'], 'pageCoa');
    }

    public function openCoaModal()
    {
        $this->resetCoaForm();
        $this->showCoaModal = true;
    }

    /**
     * [COA-CRUD] Buka modal edit. Guard permission tetap di `simpanCoa()`
     * (satu-satunya titik mutasi), tapi aksi ini datang dari client
     * `wire:click` dengan id arbitrary → guard di sini juga, supaya user tanpa
     * izin tidak bisa memuat form akun yang tidak berhak dia lihat ubah.
     */
    public function openEditCoaModal(int $id)
    {
        $this->izin('akunting.edit');

        $akun = AkunCOA::find($id);

        if (! $akun) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Akun COA tidak ditemukan.']);

            return;
        }

        $this->resetCoaForm();
        $this->coaForm = [
            'id' => $akun->id,
            'kode' => $akun->kode,
            'nama' => $akun->nama,
            'tipe' => $akun->tipe,
            'kelompok' => $akun->kelompok,
            'saldo_normal' => $akun->saldo_normal,
        ];
        $this->showCoaModal = true;
    }

    /**
     * [COA-CRUD] Tambah ATAU ubah akun COA — mode ditentukan `coaForm.id`.
     * Audit trail otomatis dari `AkunCOA` (`LogsActivity` + `logOnly` kolom
     * sensitif + `logOnlyDirty`), jadi update yang tidak mengubah apa pun
     * tidak menulis baris log.
     */
    public function simpanCoa()
    {
        // [B-10a / P0-2] guard server-side: tambah & ubah COA wajib akunting.edit
        $this->izin('akunting.edit');

        $this->validate([
            // Mode edit: aturan unique mengabaikan record yang sedang diedit.
            'coaForm.kode' => 'required|string|max:20|unique:akun_coa,kode,'.($this->coaForm['id'] ?? 'NULL'),
            'coaForm.nama' => 'required|string|max:255',
            'coaForm.tipe' => 'required|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'coaForm.kelompok' => 'required|string|max:100',
            'coaForm.saldo_normal' => 'required|in:debit,kredit',
        ]);

        $data = [
            'kode' => $this->coaForm['kode'],
            'nama' => $this->coaForm['nama'],
            'tipe' => $this->coaForm['tipe'],
            'kelompok' => $this->coaForm['kelompok'],
            'saldo_normal' => $this->coaForm['saldo_normal'],
        ];

        if (! empty($this->coaForm['id'])) {
            AkunCOA::findOrFail($this->coaForm['id'])->update($data);
            $pesan = 'Akun COA diperbarui';
        } else {
            AkunCOA::create($data);
            $pesan = 'Akun COA ditambahkan';
        }

        $this->resetCoaForm();
        $this->showCoaModal = false;
        $this->resetPage('pageCoa');
        $this->dispatch('alert', ['type' => 'success', 'message' => $pesan]);
    }

    /**
     * [COA-CRUD] Hapus akun COA.
     *
     * Menghapus chart of accounts adalah aksi TIDAK bisa dibalik dan berdampak
     * ke pembukuan seluruh cabang (COA global, tanpa `cabang_id`), jadi
     * gerbang = Super Admin / Owner — mengikuti preseden `ProdukTab::hapusProduk`
     * (satu-satunya master-data delete yang sudah ada di repo ini). Permission
     * `akunting.edit` cukup untuk tambah/ubah, TIDAK untuk hapus.
     *
     * Tiga cabang:
     * 1. Ada baris jurnal → JANGAN hard delete (`jurnal_akuntansi.akun_coa_id`
     *    `cascadeOnDelete`, jadi delete diam-diam menghapus bukti pembukuan).
     *    Nonaktifkan + tulis baris log eksplisit via `activity()`, karena
     *    `dontLogEmptyChanges()` pada model bisa tidak menghasilkan baris log
     *    yang menjelaskan MENGGAPA akun dinonaktifkan.
     * 2. Ada akun anak (`parent_id` menunjuk ke akun ini) → nonaktifkan;
     *    akun induk tidak boleh hilang dari hierarki COA.
     * 3. Bersih (tidak ada jurnal, tidak ada anak) → hapus permanen.
     */
    public function hapusCoa(int $id)
    {
        if (! isSuperAdminOrOwner(auth()->user())) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Hanya Super Admin / Owner yang berhak menghapus akun COA.']);

            return;
        }

        $akun = AkunCOA::find($id);
        if (! $akun) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Akun COA tidak ditemukan.']);

            return;
        }

        // 1. Jurnal yang sudah posted = jejak audit yang tidak boleh hilang.
        if ($akun->jurnal()->exists()) {
            $this->nonaktifkanCoa($akun, 'memiliki riwayat jurnal');

            return;
        }

        // 2. Ada akun anak yang menunjuk ke akun ini.
        if (AkunCOA::where('parent_id', $akun->id)->exists()) {
            $this->nonaktifkanCoa($akun, 'masih memiliki akun anak');

            return;
        }

        // 3. Bersih dari jurnal & anak akun → hapus permanen.
        try {
            $akun->delete();
            $this->resetPage('pageCoa');
            $this->dispatch('alert', ['type' => 'success', 'message' => "Akun COA '{$akun->kode} — {$akun->nama}' berhasil dihapus permanen."]);
        } catch (\Exception $e) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Gagal menghapus akun COA: '.$e->getMessage()]);
        }
    }

    /**
     * [COA-CRUD] Nonaktifkan akun + catat alasan eksplisit di activity log.
     *
     * Baris `activity()` manual WAJIB: `AkunCOA::getActivitylogOptions()` memakai
     * `logOnlyDirty()->dontLogEmptyChanges()`, jadi flip `is_active` pada akun
     * yang sudah nonaktif (atau update lain tanpa efek) tidak menghasilkan jejak
     * yang menjelaskan keputusan nonaktif — padahal inherit predefined tetap
     * menulis `Akun COA diperbarui`. Pola ini disamakan dengan
     * `ProdukTab::hapusProduk()`.
     */
    private function nonaktifkanCoa(AkunCOA $akun, string $alasan): void
    {
        $akun->update(['is_active' => false]);
        activity()
            ->performedOn($akun)
            ->causedBy(auth()->user())
            ->log("Akun COA '{$akun->kode} — {$akun->nama}' dinonaktifkan karena {$alasan}.");

        if (($this->coaForm['id'] ?? null) === $akun->id) {
            $this->showCoaModal = false;
            $this->resetCoaForm();
        }

        $this->resetPage('pageCoa');
        $this->dispatch('alert', [
            'type' => 'warning',
            'message' => "Akun COA '{$akun->kode} — {$akun->nama}' {$alasan} sehingga tidak dapat dihapus permanen demi keutuhan data audit & akuntansi. Status akun berhasil diubah menjadi NONAKTIF.",
        ]);
    }

    private function resetCoaForm(): void
    {
        $this->resetValidation();
        $this->coaForm = [
            'id' => null, 'kode' => '', 'nama' => '', 'tipe' => 'aset', 'kelompok' => '', 'saldo_normal' => 'debit',
        ];
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
        // [B-10a / P0-2] AR scoped cabang aktif (atau seluruh cabang bila konsolidasi)
        $cabangId = $this->cabangScopeId();
        $query = Piutang::with('pelanggan');

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        return $query->latest()
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
        $cacheKey = $cabangId !== null
            ? 'laporan-aruskas-'.$cabangId.'-'.$dari.'-'.$sampai
            : 'laporan-aruskas-konsolidasi-'.$dari.'-'.$sampai;

        return Cache::remember(
            $cacheKey,
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
                (float) $this->parseNominal($this->bayarPiutangJumlah),
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
        // [B-10a / P0-2] AP scoped cabang aktif (atau seluruh cabang bila konsolidasi)
        $cabangId = $this->cabangScopeId();
        $query = Utang::query();

        if ($cabangId !== null) {
            $query->where('cabang_id', $cabangId);
        }

        return $query->latest()
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
                (float) $this->parseNominal($this->bayarUtangJumlah),
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
