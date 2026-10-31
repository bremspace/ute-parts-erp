<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Pos\Models\QrisCabang;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Wms\Exports\ImportProdukTemplateExport;
use App\Modules\Wms\Jobs\ImportProdukExcelJob;
use App\Modules\Wms\Models\ImportLog;
use App\Modules\Wms\Services\ImportProdukService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

#[Layout('layouts.backoffice', ['header' => 'Setup Wizard Toko — Inisialisasi Sistem'])]
class SetupWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 1;

    public int $totalSteps = 6;

    public bool $isAlreadyCompleted = false;

    public ?string $completedAt = null;

    // Step 1: Profil Toko & Cabang
    public string $namaToko = 'Ute Parts Sentral';

    public string $namaCabang = 'Cabang Pusat';

    public string $kodeCabang = 'CBG-01';

    public string $alamatToko = '';

    public string $teleponToko = '';

    public string $strukHeader = 'UTE PARTS';

    public string $strukSlogan = 'Pusat Sparepart & Servis HP';

    public string $strukFooter = 'Terima kasih atas kunjungan Anda!';

    // Step 2: Modal Kas & Bank Awal
    public string $saldoKas = '0';

    public string $saldoBank = '0';

    public string $namaBank = 'Bank BCA Operasional';

    public string $tanggalModal = '';

    public string $keteranganModal = 'Inisialisasi Saldo Awal Modal Toko';

    public bool $buatJurnalModal = true;

    // Step 3: Akun & Keamanan Staf
    public string $superAdminNama = 'Super Administrator';

    public string $superAdminEmail = 'superadmin@uteparts.id';

    public string $superAdminPassword = '';

    public string $superAdminPasswordConfirmation = '';

    public bool $aturKasir = true;

    public string $kasirNama = 'Kasir Cabang Pusat';

    public string $kasirEmail = 'kasir@uteparts.id';

    public string $kasirPassword = '';

    public bool $aturTeknisi = true;

    public string $teknisiNama = 'Teknisi Utama';

    public string $teknisiEmail = 'teknisi@uteparts.id';

    public string $teknisiPassword = '';

    // Step 4: Migrasi Produk & Stok Awal (Opsional)
    public $fileMigrasi = null;

    public string $formatMigrasi = 'standar'; // standar | sid_retail

    public bool $skipMigrasi = false;

    public bool $sudahPreview = false;

    public string $storedFilePath = '';

    public array $previewSummary = [
        'total_baris' => 0,
        'total_valid' => 0,
        'total_invalid' => 0,
        'total_sku' => 0,
        'estimasi_nilai' => 0,
    ];

    // Step 5: Konfigurasi POS & QRIS
    public string $lebarKertas = '58mm';

    public bool $aktifkanQris = false;

    public string $qrisMerchantName = '';

    public string $qrisMerchantCity = 'JAKARTA';

    public string $qrisPayloadStatic = '';

    public string $qrisNmid = '';

    public bool $aktifkanPpn = false;

    public int $ppnPersen = 11;

    // State pemrosesan akhir
    public bool $isSaving = false;

    public ?string $errorMessage = null;

    public function mount(KonfigurasiService $config): void
    {
        $this->tanggalModal = now()->format('Y-m-d');
        $this->isAlreadyCompleted = (bool) $config->get('app_setup_completed', false);
        $this->completedAt = $config->get('app_setup_completed_at');

        // Load data Cabang default
        $cabangId = session('cabang_id');
        $cabang = $cabangId ? Cabang::find($cabangId) : Cabang::first();
        if ($cabang) {
            $this->namaCabang = $cabang->nama;
            $this->kodeCabang = $cabang->kode;
            $this->alamatToko = $cabang->alamat ?? '';
            $this->teleponToko = $cabang->telepon ?? '';
            $this->namaToko = $cabang->nama;
            $this->qrisMerchantName = $cabang->nama;
        }

        // Load Struk Config
        $struk = $config->get('struk_config', []);
        if (! empty($struk)) {
            $this->strukHeader = (string) ($struk['header_title'] ?? $struk['nama_toko'] ?? $this->strukHeader);
            $this->strukSlogan = (string) ($struk['header_subtitle'] ?? $struk['slogan'] ?? $this->strukSlogan);
            $this->strukFooter = (string) ($struk['footer_text'] ?? $this->strukFooter);
            $this->lebarKertas = (string) ($struk['default_width'] ?? '58mm');
        }

        // Load Akun Super Admin
        $user = auth()->user();
        if ($user) {
            $this->superAdminNama = $user->name;
            $this->superAdminEmail = $user->email;
        }

        // Load Akun Kasir
        $kasir = User::where('email', 'kasir@uteparts.id')->first();
        if ($kasir) {
            $this->kasirNama = $kasir->name;
            $this->kasirEmail = $kasir->email;
        }

        // Load Akun Teknisi
        $teknisi = User::where('email', 'teknisi@uteparts.id')->first();
        if ($teknisi) {
            $this->teknisiNama = $teknisi->name;
            $this->teknisiEmail = $teknisi->email;
        }

        // Load QRIS Cabang
        if ($cabang) {
            $qris = QrisCabang::where('cabang_id', $cabang->id)->first();
            if ($qris) {
                $this->aktifkanQris = (bool) $qris->is_active;
                $this->qrisMerchantName = $qris->merchant_name ?: $this->namaToko;
                $this->qrisMerchantCity = $qris->merchant_city ?: 'JAKARTA';
                $this->qrisPayloadStatic = $qris->payload_static ?: '';
                $this->qrisNmid = $qris->nmid ?: '';
            }
        }

        $this->aktifkanPpn = (bool) $config->get('pajak_enabled', false);
        $this->ppnPersen = (int) $config->get('ppn_persen', 11);
    }

    public function nextStep(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if ($this->currentStep === 1) {
            $this->validate([
                'namaToko' => 'required|string|max:100',
                'namaCabang' => 'required|string|max:100',
                'teleponToko' => 'nullable|string|max:30',
                'alamatToko' => 'nullable|string|max:255',
                'strukHeader' => 'required|string|max:60',
                'strukSlogan' => 'nullable|string|max:100',
                'strukFooter' => 'nullable|string|max:120',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'tanggalModal' => 'required|date',
                'keteranganModal' => 'required|string|max:150',
            ]);
        } elseif ($this->currentStep === 3) {
            $rules = [
                'superAdminNama' => 'required|string|max:100',
            ];
            if (! empty($this->superAdminPassword)) {
                $rules['superAdminPassword'] = 'min:8|same:superAdminPasswordConfirmation';
            }
            if ($this->aturKasir) {
                $rules['kasirNama'] = 'required|string|max:100';
                if (! empty($this->kasirPassword)) {
                    $rules['kasirPassword'] = 'min:8';
                }
            }
            if ($this->aturTeknisi) {
                $rules['teknisiNama'] = 'required|string|max:100';
                if (! empty($this->teknisiPassword)) {
                    $rules['teknisiPassword'] = 'min:8';
                }
            }
            $this->validate($rules);
        } elseif ($this->currentStep === 4) {
            // Migrasi bersifat opsional
            // Lanjut ke step 5
        } elseif ($this->currentStep === 5) {
            $rules = [
                'lebarKertas' => 'required|in:58mm,80mm',
            ];
            if ($this->aktifkanQris) {
                $rules['qrisMerchantName'] = 'required|string|max:100';
                $rules['qrisPayloadStatic'] = 'required|string';
            }
            $this->validate($rules);
        }

        if ($this->currentStep < $this->totalSteps) {
            $this->currentStep++;
        }
    }

    public function previousStep(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;

        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= $this->totalSteps) {
            $this->currentStep = $step;
        }
    }

    public function downloadTemplate(): mixed
    {
        return Excel::download(
            new ImportProdukTemplateExport,
            'template_import_produk_uteparts_'.date('Ymd_His').'.xlsx'
        );
    }

    public function updatedFileMigrasi(): void
    {
        $this->validate([
            'fileMigrasi' => 'required|file|mimes:xlsx,xls,csv|max:25600',
        ]);

        $this->sudahPreview = false;
        $this->skipMigrasi = false;
        $this->errorMessage = null;

        try {
            $filename = 'wizard_import_'.uniqid().'.'.$this->fileMigrasi->getClientOriginalExtension();
            $path = $this->fileMigrasi->storeAs('temp-import', $filename, 'local');
            $this->storedFilePath = $path;

            $fullPath = Storage::disk('local')->path($path);
            $service = app(ImportProdukService::class);
            $cabangId = session('cabang_id') ?? auth()->user()?->cabang_id ?? 1;

            $preview = $service->preview($fullPath, 99, [], [], [], $cabangId);

            $this->previewSummary = [
                'total_baris' => $preview['total_baris'] ?? 0,
                'total_valid' => $preview['valid'] ?? 0,
                'total_invalid' => $preview['invalid'] ?? 0,
                'total_sku' => count($preview['hasil'] ?? []),
                'estimasi_nilai' => $this->hitungEstimasiNilai($preview['hasil'] ?? []),
            ];

            $this->sudahPreview = true;
        } catch (Throwable $e) {
            $this->errorMessage = 'Gagal memproses file Excel: '.$e->getMessage();
            Log::error('Wizard file preview error: '.$e->getMessage());
        }
    }

    private function hitungEstimasiNilai(array $hasilRows): float
    {
        $total = 0.0;
        foreach ($hasilRows as $item) {
            $data = $item['data'] ?? [];
            $stok = (float) ($data['stok_awal'] ?? $data['stok'] ?? 0);
            $hpp = (float) ($data['harga_beli'] ?? $data['hpp'] ?? 0);
            $total += ($stok * $hpp);
        }

        return $total;
    }

    public function lewatiMigrasi(): void
    {
        $this->skipMigrasi = true;
        $this->fileMigrasi = null;
        $this->storedFilePath = '';
        $this->sudahPreview = false;
        $this->currentStep = 5;
    }

    public function parseNominal(string $val): float
    {
        $clean = preg_replace('/[^0-9]/', '', $val);

        return (float) ($clean ?: 0);
    }

    public function selesaikanSetup(): mixed
    {
        $this->isSaving = true;
        $this->errorMessage = null;

        try {
            DB::transaction(function () {
                $cabangId = session('cabang_id');
                $cabang = $cabangId ? Cabang::find($cabangId) : Cabang::first();
                if (! $cabang) {
                    $cabang = Cabang::create([
                        'kode' => $this->kodeCabang ?: 'CBG-01',
                        'nama' => $this->namaCabang,
                        'alamat' => $this->alamatToko,
                        'telepon' => $this->teleponToko,
                        'is_active' => true,
                    ]);
                } else {
                    $cabang->update([
                        'nama' => $this->namaCabang,
                        'alamat' => $this->alamatToko,
                        'telepon' => $this->teleponToko,
                        'is_active' => true,
                    ]);
                }

                session([
                    'cabang_id' => $cabang->id,
                    'cabang_nama' => $cabang->nama,
                ]);

                $config = app(KonfigurasiService::class);

                // 1. Simpan konfigurasi struk
                $strukData = [
                    'nama_toko' => $this->namaToko,
                    'slogan' => $this->strukSlogan,
                    'alamat' => $this->alamatToko,
                    'telepon' => $this->teleponToko,
                    'header_title' => $this->strukHeader,
                    'header_subtitle' => $this->strukSlogan,
                    'footer_text' => $this->strukFooter,
                    'default_width' => $this->lebarKertas,
                    'auto_print' => false,
                ];
                $config->set('struk_config', $strukData, 'Pengaturan default struk transaksi kasir');

                // 2. Update Akun Super Admin
                $admin = auth()->user() ?? User::where('email', $this->superAdminEmail)->first();
                if ($admin) {
                    $adminUpdate = ['name' => $this->superAdminNama];
                    if (! empty($this->superAdminPassword)) {
                        $adminUpdate['password'] = Hash::make($this->superAdminPassword);
                    }
                    $admin->update($adminUpdate);
                }

                // 3. Update Akun Kasir
                if ($this->aturKasir) {
                    $kasir = User::where('email', $this->kasirEmail)->first();
                    if ($kasir) {
                        $kasirUpdate = ['name' => $this->kasirNama];
                        if (! empty($this->kasirPassword)) {
                            $kasirUpdate['password'] = Hash::make($this->kasirPassword);
                        }
                        $kasir->update($kasirUpdate);
                        if (! $kasir->cabangs()->where('cabang_id', $cabang->id)->exists()) {
                            $kasir->cabangs()->attach($cabang->id, ['is_default' => true]);
                        }
                    }
                }

                // 4. Update Akun Teknisi
                if ($this->aturTeknisi) {
                    $teknisi = User::where('email', $this->teknisiEmail)->first();
                    if ($teknisi) {
                        $teknisiUpdate = ['name' => $this->teknisiNama];
                        if (! empty($this->teknisiPassword)) {
                            $teknisiUpdate['password'] = Hash::make($this->teknisiPassword);
                        }
                        $teknisi->update($teknisiUpdate);
                        if (! $teknisi->cabangs()->where('cabang_id', $cabang->id)->exists()) {
                            $teknisi->cabangs()->attach($cabang->id, ['is_default' => true]);
                        }
                    }
                }

                // 5. Inisialisasi Jurnal Saldo Kas & Bank Awal
                $kasNominal = $this->parseNominal($this->saldoKas);
                $bankNominal = $this->parseNominal($this->saldoBank);
                $totalModal = $kasNominal + $bankNominal;

                if ($this->buatJurnalModal && $totalModal > 0) {
                    $jurnalService = app(JurnalService::class);
                    $noJurnal = $jurnalService->generateNoJurnal('manual', $cabang->id);
                    $lines = [];

                    if ($kasNominal > 0) {
                        $lines[] = [
                            'akun_kode' => '110-01',
                            'debit' => $kasNominal,
                            'kredit' => 0,
                        ];
                    }

                    if ($bankNominal > 0) {
                        $lines[] = [
                            'akun_kode' => '110-02',
                            'debit' => $bankNominal,
                            'kredit' => 0,
                        ];
                    }

                    $lines[] = [
                        'akun_kode' => '310-01',
                        'debit' => 0,
                        'kredit' => $totalModal,
                    ];

                    $jurnalService->post(
                        noJurnal: $noJurnal,
                        tanggal: $this->tanggalModal ?: now()->format('Y-m-d'),
                        sumber: 'manual',
                        lines: $lines,
                        deskripsi: $this->keteranganModal ?: 'Inisialisasi Saldo Awal Modal Toko',
                        cabangId: $cabang->id,
                        userId: auth()->id()
                    );
                }

                // 6. Commit Migrasi Excel (bila file ada dan tidak dilewati)
                if (! $this->skipMigrasi && ! empty($this->storedFilePath) && Storage::disk('local')->exists($this->storedFilePath)) {
                    $totalBaris = (int) ($this->previewSummary['total_baris'] ?? 0);
                    $log = ImportLog::create([
                        'tipe' => $this->formatMigrasi === 'sid_retail' ? 'produk_sid_retail' : 'produk_excel',
                        'nama_file' => basename($this->storedFilePath),
                        'status' => 'proses',
                        'user_id' => auth()->id(),
                        'total_baris' => $totalBaris,
                    ]);

                    if ($totalBaris <= 100) {
                        ImportProdukExcelJob::dispatchSync(
                            $log->id,
                            $this->storedFilePath,
                            auth()->id(),
                            $this->formatMigrasi === 'sid_retail' ? 'sid_retail' : 'standar',
                            $cabang->id
                        );
                    } else {
                        ImportProdukExcelJob::dispatch(
                            $log->id,
                            $this->storedFilePath,
                            auth()->id(),
                            $this->formatMigrasi === 'sid_retail' ? 'sid_retail' : 'standar',
                            $cabang->id
                        );
                    }
                }

                // 7. Konfigurasi QRIS & POS
                if ($this->aktifkanQris && ! empty($this->qrisPayloadStatic)) {
                    QrisCabang::updateOrCreate(
                        ['cabang_id' => $cabang->id],
                        [
                            'payload_static' => $this->qrisPayloadStatic,
                            'merchant_name' => $this->qrisMerchantName ?: $this->namaToko,
                            'merchant_city' => $this->qrisMerchantCity ?: 'JAKARTA',
                            'postal_code' => null,
                            'nmid' => $this->qrisNmid,
                            'is_active' => true,
                        ]
                    );
                }

                $config->set('pajak_enabled', $this->aktifkanPpn, 'Aktifkan PPN di Kasir');
                $config->set('ppn_persen', $this->ppnPersen, 'Tarif PPN');

                // 8. Tandai Setup Wizard Selesai
                $config->set('app_setup_completed', true, 'Inisialisasi sistem Setup Wizard selesai');
                $config->set('app_setup_completed_at', now()->toIso8601String(), 'Waktu selesai setup wizard');
            });

            session()->flash('success', 'Setup Toko Selesai! Selamat bertransaksi di POS Kasir Ute Parts.');

            return redirect()->route('pos');
        } catch (Throwable $e) {
            $this->isSaving = false;
            $this->errorMessage = 'Gagal menyimpan konfigurasi setup awal: '.$e->getMessage();
            Log::error('Wizard finish error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
    }

    public function render()
    {
        $kasNominal = $this->parseNominal($this->saldoKas);
        $bankNominal = $this->parseNominal($this->saldoBank);
        $totalModalNominal = $kasNominal + $bankNominal;

        return view('modules.rbac.livewire.setup-wizard', [
            'totalModalNominal' => $totalModalNominal,
            'kasNominal' => $kasNominal,
            'bankNominal' => $bankNominal,
        ]);
    }
}
