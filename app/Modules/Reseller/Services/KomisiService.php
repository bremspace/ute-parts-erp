<?php

namespace App\Modules\Reseller\Services;

use App\Modules\Akunting\Models\Utang;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Hr\Models\Karyawan;
use App\Modules\Hr\Models\KpiHasil;
use App\Modules\Pos\Models\Transaksi;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Reseller\Models\Komisi;
use App\Modules\Reseller\Models\KomisiSkema;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Reseller\Models\SkemaKomisiReseller;
use App\Modules\Servis\Models\TiketServis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Komisi reseller (PRD §4.5):
 * - Transaksi reseller → hitung komisi → status pending.
 * - Approval finance → jurnal (debit Beban Komisi, kredit Utang Komisi) + entri Utang.
 */
class KomisiService
{
    public function __construct(
        protected JurnalService $jurnalService
    ) {}

    /**
     * Hitung komisi dari item agregat (untuk servis & jalur non-Transaksi).
     *
     * @param  array  $itemsAgregat  [['kategori' => string, 'subtotal' => float, 'jumlah' => int], ...]
     */
    public function hitungKomisiDariItems(Pelanggan $pelanggan, array $itemsAgregat, array $referensi): ?Komisi
    {
        if (! $pelanggan->is_reseller || empty($itemsAgregat)) {
            return null;
        }

        $totalKomisi = 0.0;
        $skemaTerpakai = null;

        foreach ($itemsAgregat as $agregat) {
            $kategori = $agregat['kategori'] ?? 'umum';

            // [T-21] Cek override skema PER RESELLER terlebih dahulu; fallback ke skema default
            $skema = SkemaKomisiReseller::where('pelanggan_id', $pelanggan->id)
                ->where('is_active', true)
                ->where(function ($q) use ($kategori) {
                    $q->whereNull('kategori')->orWhere('kategori', $kategori);
                })
                ->first();

            if (! $skema) {
                $skema = SkemaKomisi::where('is_active', true)
                    ->where(function ($q) use ($kategori) {
                        $q->whereNull('kategori')->orWhere('kategori', $kategori);
                    })
                    ->first();
            }

            if ($skema) {
                $totalKomisi += $skema->tipe === 'persen'
                    ? round((float) $agregat['subtotal'] * ((float) $skema->nilai / 100), 2)
                    : (float) $skema->nilai * (int) $agregat['jumlah'];

                $skemaTerpakai = $skema;
            }
        }

        if ($totalKomisi <= 0) {
            return null;
        }

        $today = now()->format('Ymd');
        $count = Komisi::whereDate('created_at', now()->toDateString())->count() + 1;
        $noKomisi = sprintf('KMS-%s-%04d', $today, $count);

        return Komisi::create([
            'no_komisi' => $noKomisi,
            'pelanggan_id' => $pelanggan->id,
            'transaksi_id' => $referensi['transaksi_id'] ?? null,
            'skema_komisi_id' => $skemaTerpakai?->id,
            'jumlah_transaksi' => $referensi['jumlah_transaksi'] ?? array_sum(array_column($itemsAgregat, 'subtotal')),
            'nominal_komisi' => $totalKomisi,
            'status' => 'pending',
            'keterangan' => $referensi['keterangan'] ?? 'Komisi dari transaksi',
        ]);
    }

    /**
     * Hitung komisi untuk transaksi reseller. Panggil saat transaksi POS/marketplace dengan pelanggan is_reseller.
     */
    public function hitungKomisi(Transaksi $transaksi, Pelanggan $pelanggan): ?Komisi
    {
        // Produk dalam transaksi (via items.produk.kategori)
        $grouped = $transaksi->items()->with('produk')->get()
            ->groupBy(fn ($item) => $item->produk?->kategori ?? 'umum');

        $itemsAgregat = [];
        foreach ($grouped as $kategori => $items) {
            $itemsAgregat[] = [
                'kategori' => $kategori,
                'subtotal' => $items->sum(fn ($i) => (float) $i->subtotal),
                'jumlah' => $items->sum('jumlah'),
            ];
        }

        return $this->hitungKomisiDariItems($pelanggan, $itemsAgregat, [
            'jumlah_transaksi' => $transaksi->total_akhir,
            'keterangan' => 'Komisi dari transaksi '.$transaksi->no_transaksi,
            'transaksi_id' => $transaksi->id,
        ]);
    }

    // =====================================================================
    // [F3-8c] Engine komisi multi-aktor (PRD §4.3)
    // =====================================================================

    /**
     * MIGRASI DATA: skema komisi reseller lama (skema_komisi + skema_komisi_reseller)
     * → komisi_skema (idempotent). Paritas lama → baru diuji KomisiMultiAktorTest.
     */
    public function migrasiSkemaResellerKeRuleBaru(): int
    {
        if (! Schema::hasTable('skema_komisi')) {
            return 0;
        }

        $jumlah = 0;
        $salin = function (SkemaKomisi|SkemaKomisiReseller $skema, ?int $aktorId) use (&$jumlah): void {
            $sudahAda = KomisiSkema::where('aktor_tipe', 'reseller')
                ->where('trigger_tipe', 'penjualan')
                ->where('aktor_id', $aktorId)
                ->where('kategori', $skema->kategori)
                ->where('tipe', $skema->tipe)
                ->where('nilai', $skema->nilai)
                ->exists();

            if ($sudahAda) {
                return;
            }

            KomisiSkema::create([
                'nama' => ($skema->nama ?? 'Skema reseller').($aktorId ? " #{$aktorId}" : ''),
                'aktor_tipe' => 'reseller',
                'aktor_id' => $aktorId,
                'trigger_tipe' => 'penjualan',
                'kategori' => $skema->kategori,
                'tipe' => $skema->tipe,
                'nilai' => $skema->nilai,
                'min_amount' => 0,
                'cabang_id' => null,
                'is_aktif' => $skema->is_active ?? true,
            ]);
            $jumlah++;
        };

        foreach (SkemaKomisi::all() as $skema) {
            $salin($skema, null);
        }
        foreach (SkemaKomisiReseller::all() as $skema) {
            $salin($skema, (int) $skema->pelanggan_id);
        }

        return $jumlah;
    }

    /**
     * Hitung komisi multi-aktor utk sebuah trigger. Match semua rule aktif per
     * kandidat aktor; idempotent per trigger+rule+aktor (firstOrCreate idempotensi_key).
     *
     * @param  string  $triggerTipe  penjualan|lead_won|tiket_servis|target_kpi
     * @param  array  $konteks  ['transaksi_id'|'lead_id'|'tiket_servis_id'|'periode' => ...]
     * @return array<int, Komisi>
     */
    public function hitungKomisiMultiAktor(string $triggerTipe, array $konteks): array
    {
        if ($triggerTipe === 'target_kpi') {
            return $this->hitungKomisiTargetKpi((string) ($konteks['periode'] ?? now()->format('Y-m')));
        }

        if (! in_array($triggerTipe, ['penjualan', 'lead_won', 'tiket_servis'], true)) {
            return [];
        }

        [$entitas, $refId, $pelanggan, $jumlahDasar, $cabangId] = $this->resolusiTrigger($triggerTipe, $konteks);
        if ($entitas === null || $jumlahDasar <= 0) {
            return [];
        }

        $kandidat = match ($triggerTipe) {
            'penjualan' => $this->kandidatPenjualan($pelanggan),
            'lead_won' => $this->kandidatLeadWon($pelanggan, $entitas),
            'tiket_servis' => $this->kandidatTiketServis($entitas),
        };

        $hasil = [];
        foreach ($kandidat as [$aktorTipe, $aktorId]) {
            $totalPerRule = [];

            foreach ($this->kategoriDasar($triggerTipe, $konteks, $jumlahDasar) as $agregat) {
                $rule = $this->matchRule($aktorTipe, $triggerTipe, (int) $aktorId, $cabangId, $agregat['kategori']);
                if ($rule === null || (float) $rule->min_amount > (float) $agregat['subtotal']) {
                    continue;
                }

                $totalPerRule[$rule->id] ??= ['rule' => $rule, 'nominal' => 0.0, 'dasar' => 0.0];
                $totalPerRule[$rule->id]['nominal'] += $rule->tipe === 'persen'
                    ? round((float) $agregat['subtotal'] * ((float) $rule->nilai / 100), 2)
                    : (float) $rule->nilai * (int) $agregat['jumlah'];
                $totalPerRule[$rule->id]['dasar'] += (float) $agregat['subtotal'];
            }

            foreach ($totalPerRule as $bagian) {
                if ($bagian['nominal'] <= 0) {
                    continue;
                }
                $komisi = $this->catatKomisi(
                    $triggerTipe, (int) $refId, $bagian['rule'], $aktorTipe, (int) $aktorId,
                    $pelanggan, $bagian['nominal'], $bagian['dasar']
                );
                if ($komisi) {
                    $hasil[] = $komisi;
                }
            }
        }

        return $hasil;
    }

    /**
     * Resolve entitas trigger → [entitas, refId, pelanggan, jumlahDasar, cabangId].
     *
     * @return array{0: mixed, 1: int, 2: ?Pelanggan, 3: float, 4: ?int}
     */
    protected function resolusiTrigger(string $triggerTipe, array $konteks): array
    {
        $cabangAktif = session('cabang_id') ?: null;

        return match ($triggerTipe) {
            'penjualan' => $this->resolusiPenjualan($konteks),
            'lead_won' => $this->resolusiLeadWon($konteks, $cabangAktif),
            'tiket_servis' => $this->resolusiTiketServis($konteks),
            default => [null, 0, null, 0.0, null],
        };
    }

    protected function resolusiPenjualan(array $konteks): array
    {
        $transaksi = Transaksi::with('pelanggan')->find($konteks['transaksi_id'] ?? 0);
        if (! $transaksi) {
            return [null, 0, null, 0.0, null];
        }

        return [$transaksi, (int) $transaksi->id, $transaksi->pelanggan, (float) $transaksi->total_akhir, $transaksi->cabang_id];
    }

    protected function resolusiLeadWon(array $konteks, ?int $cabangAktif): array
    {
        $lead = Lead::with('pelanggan')->find($konteks['lead_id'] ?? 0);
        if (! $lead || $lead->stage !== 'won') {
            return [null, 0, null, 0.0, null];
        }

        return [$lead, (int) $lead->id, $lead->pelanggan, (float) ($lead->nilai_estimasi ?? 0), $cabangAktif];
    }

    protected function resolusiTiketServis(array $konteks): array
    {
        $tiket = TiketServis::with('pelanggan')->find($konteks['tiket_servis_id'] ?? 0);
        if (! $tiket || $tiket->status !== 'selesai') {
            return [null, 0, null, 0.0, null];
        }

        return [$tiket, (int) $tiket->id, $tiket->pelanggan, (float) ($tiket->estimasi_biaya ?? 0), $tiket->cabang_id];
    }

    /**
     * Kandidat aktor penjualan: reseller (is_reseller), agen (via referral code),
     * karyawan marketing pemilik lead (lead source).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    protected function kandidatPenjualan(?Pelanggan $pelanggan): array
    {
        $kandidat = [];

        if ($pelanggan && $pelanggan->is_reseller) {
            $kandidat[] = ['reseller', (int) $pelanggan->id];
        }

        $agen = $this->agenDariPembeli($pelanggan);
        if ($agen) {
            $kandidat[] = ['agen', (int) $agen->id];
        }

        $karyawan = $this->karyawanDariLead($pelanggan);
        if ($karyawan) {
            $kandidat[] = ['karyawan', (int) $karyawan->id];
        }

        return $kandidat;
    }

    /**
     * Kandidat lead_won: karyawan pemilik lead (assigned_to), reseller, agen.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    protected function kandidatLeadWon(?Pelanggan $pelanggan, Lead $lead): array
    {
        $kandidat = [];

        if ($lead->assigned_to) {
            $karyawan = Karyawan::where('user_id', $lead->assigned_to)->where('status_aktif', true)->first();
            if ($karyawan) {
                $kandidat[] = ['karyawan', (int) $karyawan->id];
            }
        }

        if ($pelanggan && $pelanggan->is_reseller) {
            $kandidat[] = ['reseller', (int) $pelanggan->id];
        }

        $agen = $this->agenDariPembeli($pelanggan);
        if ($agen) {
            $kandidat[] = ['agen', (int) $agen->id];
        }

        return $kandidat;
    }

    /**
     * Trigger tiket_servis → teknisi (karyawan). Komisi reseller servis tetap
     * via alur lama hitungKomisiDariItems (paritas; hindari duplikat).
     *
     * @return array<int, array{0: string, 1: int}>
     */
    protected function kandidatTiketServis(TiketServis $tiket): array
    {
        if (! $tiket->teknisi_id) {
            return [];
        }

        $karyawan = Karyawan::where('user_id', $tiket->teknisi_id)->where('status_aktif', true)->first();

        return $karyawan ? [['karyawan', (int) $karyawan->id]] : [];
    }

    /**
     * Agen utk pembeli: pemilik kode_agen yg sama dgn referral_kode pembeli.
     */
    protected function agenDariPembeli(?Pelanggan $pelanggan): ?Pelanggan
    {
        if (! $pelanggan || ! $pelanggan->referral_kode) {
            return null;
        }

        return Pelanggan::where('kode_agen', $pelanggan->referral_kode)
            ->where('id', '!=', $pelanggan->id)
            ->first();
    }

    /**
     * Karyawan marketing utk transaksi: pemilik lead won yg terikat pelanggan ini.
     */
    protected function karyawanDariLead(?Pelanggan $pelanggan): ?Karyawan
    {
        if (! $pelanggan) {
            return null;
        }

        $lead = Lead::where('pelanggan_id', $pelanggan->id)
            ->where('stage', 'won')
            ->whereNotNull('assigned_to')
            ->orderByDesc('id')
            ->first();

        if (! $lead) {
            return null;
        }

        return Karyawan::where('user_id', $lead->assigned_to)->where('status_aktif', true)->first();
    }

    /**
     * Dasar hitung per kategori — paritas dgn skema lama (agregat per kategori
     * produk); trigger lain memakai satu bucket kategori null.
     *
     * @return array<int, array{kategori: ?string, subtotal: float, jumlah: int}>
     */
    protected function kategoriDasar(string $triggerTipe, array $konteks, float $jumlahDasar): array
    {
        if ($triggerTipe === 'penjualan') {
            $transaksi = Transaksi::find($konteks['transaksi_id'] ?? 0);
            if ($transaksi) {
                $grouped = $transaksi->items()->with('produk')->get()
                    ->groupBy(fn ($item) => $item->produk?->kategori ?? 'umum');

                $agregat = [];
                foreach ($grouped as $kategori => $items) {
                    $agregat[] = [
                        'kategori' => (string) $kategori,
                        'subtotal' => (float) $items->sum(fn ($i) => (float) $i->subtotal),
                        'jumlah' => (int) $items->sum('jumlah'),
                    ];
                }

                return $agregat;
            }
        }

        return [['kategori' => null, 'subtotal' => $jumlahDasar, 'jumlah' => 1]];
    }

    /**
     * Rule yg berlaku utk aktor+kategori — prioritas spesifik (aktor_id) lalu umum
     * (aktor_id null), setara alur lama (override per reseller → skema default).
     */
    /**
     * Query dasar rule aktif (aktor_tipe + trigger_tipe + is_aktif), dipakai
     * matchRule & hitungKomisiTargetKpi; semantik min_amount berbeda per trigger
     * (engine: subtotal kategori; target_kpi: nilai_aktual) — sengaja.
     */
    protected function queryRuleAktif(string $aktorTipe, string $triggerTipe): Builder
    {
        return KomisiSkema::where('aktor_tipe', $aktorTipe)
            ->where('trigger_tipe', $triggerTipe)
            ->where('is_aktif', true);
    }

    /**
     * Rule yg berlaku utk aktor+kategori — prioritas spesifik (aktor_id) lalu umum
     * (aktor_id null), setara alur lama (override per reseller → skema default).
     */
    protected function matchRule(string $aktorTipe, string $triggerTipe, int $aktorId, ?int $cabangId, ?string $kategori): ?KomisiSkema
    {
        $query = fn () => $this->queryRuleAktif($aktorTipe, $triggerTipe)
            ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId))
            ->where(fn ($q) => $q->whereNull('kategori')->orWhere('kategori', $kategori));

        return $query()->where('aktor_id', $aktorId)->first()
            ?? $query()->whereNull('aktor_id')->first();
    }

    /**
     * Catat komisi idempotent (firstOrCreate idempotensi_key trigger+rule+aktor).
     */
    protected function catatKomisi(
        string $triggerTipe,
        int $refId,
        KomisiSkema $rule,
        string $aktorTipe,
        int $aktorId,
        ?Pelanggan $pelanggan,
        float $nominal,
        float $jumlahDasar
    ): ?Komisi {
        $key = sprintf('%s:%d:%d:%s:%d', $triggerTipe, $refId, $rule->id, $aktorTipe, $aktorId);

        $labelTrigger = match ($triggerTipe) {
            'penjualan' => 'penjualan',
            'lead_won' => 'lead won',
            'tiket_servis' => 'tiket servis',
            default => 'target KPI',
        };

        return Komisi::firstOrCreate(
            ['idempotensi_key' => $key],
            [
                'no_komisi' => $this->noKomisiBaru(),
                'aktor_tipe' => $aktorTipe,
                'aktor_id' => $aktorId,
                'pelanggan_id' => match ($aktorTipe) {
                    'reseller' => $pelanggan?->id,
                    'agen' => $aktorId, // pelanggan agen (pemilik kode referral)
                    default => null,    // karyawan → masuk payroll via aktor_id
                },
                'transaksi_id' => $triggerTipe === 'penjualan' ? $refId : null,
                'lead_id' => $triggerTipe === 'lead_won' ? $refId : null,
                'tiket_servis_id' => $triggerTipe === 'tiket_servis' ? $refId : null,
                'komisi_skema_id' => $rule->id,
                'jumlah_transaksi' => $jumlahDasar,
                'nominal_komisi' => $nominal,
                'status' => 'pending',
                'keterangan' => sprintf(
                    'Komisi %s (%s) — %s',
                    $labelTrigger,
                    $rule->nama,
                    ($pelanggan?->nama) ?? "aktor #{$aktorId}"
                ),
            ]
        );
    }

    /**
     * Trigger target_kpi: komisi per metric KPI tercapai (persen_capaian >= 100,
     * nilai_aktual >= min_amount). Kategori rule = kpi_metric_id (string);
     * tipe persen dihitung dari gaji pokok karyawan.
     *
     * @return array<int, Komisi>
     */
    protected function hitungKomisiTargetKpi(string $periode): array
    {
        $hasil = [];
        foreach (KpiHasil::with('metric')->where('periode', $periode)->get() as $kpi) {
            $karyawanId = (int) $kpi->karyawan_id;
            $kategori = (string) ($kpi->kpi_metric_id ?? 0);

            $rules = $this->queryRuleAktif('karyawan', KomisiSkema::TRIGGER_TARGET_KPI)
                ->where(fn ($q) => $q->whereNull('aktor_id')->orWhere('aktor_id', $karyawanId))
                ->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', session('cabang_id')))
                ->get();

            foreach ($rules as $rule) {
                if ($kpi->persen_capaian < 100 || (float) $rule->min_amount > (float) $kpi->nilai_aktual) {
                    continue;
                }
                if ($rule->kategori !== null && $rule->kategori !== $kategori) {
                    continue;
                }

                if ($rule->tipe === 'nominal') {
                    $nominal = (float) $rule->nilai;
                } else {
                    $pokok = Karyawan::find($karyawanId)?->gaji_pokok ?? 0;
                    $nominal = round((float) $pokok * ((float) $rule->nilai / 100), 2);
                }
                if ($nominal <= 0) {
                    continue;
                }

                $key = sprintf('target_kpi:%s:%d:%d:%d', $periode, $karyawanId, $rule->id, (int) $kpi->kpi_metric_id);

                $hasil[] = Komisi::firstOrCreate(
                    ['idempotensi_key' => $key],
                    [
                        'no_komisi' => $this->noKomisiBaru(),
                        'aktor_tipe' => 'karyawan',
                        'aktor_id' => $karyawanId,
                        'pelanggan_id' => null,
                        'komisi_skema_id' => $rule->id,
                        'jumlah_transaksi' => (float) $kpi->nilai_aktual,
                        'nominal_komisi' => $nominal,
                        'status' => 'pending',
                        'keterangan' => sprintf(
                            'Komisi target KPI — %s (%s, periode %s)',
                            $rule->nama,
                            $kpi->metric?->nama ?? "metric #{$kpi->kpi_metric_id}",
                            $periode
                        ),
                    ]
                );
            }
        }

        return $hasil;
    }

    protected function noKomisiBaru(): string
    {
        $count = Komisi::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('KMS-%s-%04d', now()->format('Ymd'), $count);
    }

    /**
     * Update status komisi (bulk) + buat jurnal & utang saat disetujui.
     * Butler: yang boleh approve = role finance (dicek di controller).
     *
     * @param  string  $action  approve|reject
     */
    public function prosesApproval(array $komisiIds, string $action, int $userId): array
    {
        $approvedUtangIds = [];

        DB::transaction(function () use ($komisiIds, $action, $userId, &$approvedUtangIds) {
            foreach ($komisiIds as $id) {
                $komisi = Komisi::where('id', $id)->where('status', 'pending')->lockForUpdate()->first();
                if (! $komisi) {
                    continue;
                }

                if ($action === 'reject') {
                    $komisi->update([
                        'status' => 'ditolak',
                        'approved_by_id' => $userId,
                        'approved_at' => now(),
                    ]);

                    app(AuditService::class)->catat(
                        'Komisi', 'reject', $komisi->id,
                        "Komisi {$komisi->no_komisi} ditolak (Rp {$komisi->nominal_komisi})",
                        ['status' => 'pending'], ['status' => 'ditolak']
                    );

                    continue;
                }

                // Approve
                $noJurnal = $this->jurnalService->generateNoJurnal('komisi', null);
                $this->jurnalService->post(
                    $noJurnal,
                    now(),
                    'komisi',
                    [
                        ['akun_kode' => '510-01', 'debit' => (float) $komisi->nominal_komisi, 'kredit' => 0],   // Beban Komisi
                        ['akun_kode' => '210-03', 'debit' => 0, 'kredit' => (float) $komisi->nominal_komisi],   // Utang Komisi
                    ],
                    "Komisi disetujui — {$komisi->no_komisi}",
                    $komisi->transaksi?->cabang_id,
                    $userId,
                    Komisi::class,
                    $komisi->id
                );

                $noUtang = sprintf('UTG-KMS-%s', str_replace('KMS-', '', $komisi->no_komisi));
                Utang::updateOrCreate(
                    [
                        'referensi_tipe' => 'komisi',
                        'referensi_id' => $komisi->id,
                    ],
                    [
                        'no_utang' => $noUtang,
                        'pelanggan_id' => $komisi->pelanggan_id,
                        'cabang_id' => $komisi->transaksi?->cabang_id, // [F2-5] stamp cabang utk export scoping
                        'kreditor_nama' => $komisi->pelanggan?->nama,
                        'jumlah' => (float) $komisi->nominal_komisi,
                        'jumlah_dibayar' => 0,
                        'status' => 'belum_lunas',
                        'keterangan' => 'Utang komisi '.$komisi->no_komisi,
                    ]
                );
                $approvedUtangIds[] = $komisi->id;

                $komisi->update([
                    'status' => 'disetujui',
                    'approved_by_id' => $userId,
                    'approved_at' => now(),
                ]);

                app(AuditService::class)->catat(
                    'Komisi', 'approve', $komisi->id,
                    "Komisi {$komisi->no_komisi} disetujui — jurnal {$noJurnal} + utang {$noUtang}",
                    ['status' => 'pending'], ['status' => 'disetujui']
                );
            }
        });

        return $approvedUtangIds;
    }
}
