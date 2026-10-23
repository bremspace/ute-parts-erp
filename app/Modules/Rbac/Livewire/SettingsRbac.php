<?php

namespace App\Modules\Rbac\Livewire;

use App\Models\User;
use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Services\PajakService;
use App\Modules\Crm\Models\TierMembership;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Rbac\Models\Cabang;
use App\Modules\Rbac\Services\AuditService;
use App\Modules\Reseller\Models\SkemaKomisi;
use App\Modules\Servis\Models\JenisServis;
use App\Modules\Wms\Models\Brand;
use App\Modules\Wms\Models\Gudang;
use App\Modules\Wms\Models\KategoriProduk;
use App\Modules\Wms\Models\KualitasProduk;
use App\Modules\Wms\Models\Produk;
use App\Modules\Wms\Models\Rak;
use App\Modules\Wms\Models\SatuanUnit;
use App\Modules\Wms\Models\StokItem;
use App\Modules\Wms\Models\TipeHp;
use App\Traits\ParsesNominal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Pengaturan & RBAC (PRD Frontend §5.9, §5.11):
 * Manajemen user + role + cabang, master data (cabang/gudang/jenis servis/COA).
 */
class SettingsRbac extends Component
{
    use ParsesNominal;
    use WithPagination;

    /**
     * [RBAC] Permission gerbang master data katalog produk. Seeder tidak punya
     * permission per-entitas (kategori/brand/kualitas/satuan/kondisi/tipe HP),
     * jadi dipakai yang sudah ada: `pengaturan.manage` (pemilik halaman) atau
     * permission departemen WMS `wms.create` (staff-gudang).
     *
     * @var string[]
     */
    private const PERM_MASTER_PRODUK = ['pengaturan.manage', 'wms.create'];

    /**
     * [RBAC] Master jenis servis: `pengaturan.manage` (pemilik halaman) atau
     * permission departemen servis `servis.manage` (bagian terkait = admin-toko).
     *
     * @var string[]
     */
    private const PERM_JENIS_SERVIS = ['pengaturan.manage', 'servis.manage'];

    public string $activeTab = 'users'; // users, role, cabang, master, loyalitas, pajak, printer, database

    // [T-22] Strategi Loyalitas (editable tanpa deploy, non-retroaktif)
    public array $loyalitasForm = [
        'poin_earn_persen' => 5,
        'poin_redeem_rupiah' => 100,
        'diskon_silver' => 3,
        'diskon_gold' => 5,
        'diskon_platinum' => 10,
    ];

    public array $skemaKomisiForm = [];

    // Opsi Konfigurasi Pajak (PPN, PPh, PB1 / Tanpa Pajak)
    public array $pajakForm = [
        'target' => 'global',
        'enabled' => false,
        'nama' => 'PPN',
        'percent' => 11.0,
        'mode' => 'exclusive',
    ];

    // Konfigurasi Printer Label QR Produk & Struk Kasir
    public string $printerSubTab = 'struk'; // 'struk' | 'label'

    public array $printerForm = [
        'label_preset' => '33x15',
        'label_width' => 33.0,
        'label_height' => 15.0,
        'label_margin_top' => 1.0,
        'label_margin_bottom' => 1.0,
        'label_margin_left' => 1.5,
        'label_margin_right' => 1.5,
        'label_show_brand' => true,
        'label_show_price' => true,
        'label_show_sku' => true,
        'struk_default_width' => '80mm',
        'struk_footer' => 'Terima kasih atas kunjungan Anda',
    ];

    public array $strukForm = [
        'nama_toko' => 'UTE PARTS',
        'slogan' => 'Pusat Sparepart & Servis HP',
        'alamat' => 'Jl. Veteran No. 12',
        'telepon' => '0812-3456-7890',
        'website_medsos' => 'www.uteparts.id',
        'show_cabang' => true,
        'show_kasir' => true,
        'show_pelanggan' => true,
        'show_waktu' => true,
        'show_subtotal_breakdown' => true,
        'show_qr_retur' => true,
        'qr_size' => 80,
        'qr_caption' => 'Scan QR ini untuk retur & klaim garansi',
        'footer_text' => 'Terima kasih atas kunjungan Anda!',
        'kebijakan_retur' => 'Maksimal klaim retur / garansi 3 hari dengan membawa struk ber-QR ini.',
        'catatan_kaki' => 'Segel toko wajib utuh & fisik barang tidak cacat.',
        'default_width' => '80mm',
        'font_size' => 'normal',
        'auto_print' => false,
    ];

    public array $integrasiForm = [
        // WhatsApp Gateway
        'wa_provider' => 'fonnte', // fonnte, wablas, custom
        'wa_url' => 'https://api.fonnte.com/send',
        'wa_token' => '',
        'wa_device_id' => '',
        'wa_test_number' => '',
        'wa_auto_notif_transaksi' => true,
        'wa_auto_notif_servis' => true,

        // Duitku Payment Gateway
        'duitku_mode' => 'sandbox',
        'duitku_merchant_code' => '',
        'duitku_api_key' => '',
        'duitku_merchant_key' => '',
        'duitku_expiry_period' => 1440,

        // Biteship Logistics Gateway
        'biteship_api_key' => '',
        'biteship_origin_type' => 'closest_branch',
    ];

    public ?string $waTestStatus = null;

    public function mount(): void
    {
        if (request()->has('tab')) {
            $tab = (string) request('tab');
            if (in_array($tab, ['users', 'role', 'cabang', 'master_produk', 'master', 'loyalitas', 'pajak', 'printer', 'integrasi', 'database'], true)) {
                $this->activeTab = $tab;
            }
        }

        if (request()->has('sub')) {
            $sub = (string) request('sub');
            if (in_array($sub, ['kategori', 'brand', 'kualitas', 'satuan', 'kondisi', 'tipe_hp'], true)) {
                $this->masterProdukSubTab = $sub;
            } elseif (in_array($sub, ['struk', 'label'], true)) {
                $this->printerSubTab = $sub;
            }
        }

        $config = app(KonfigurasiService::class);

        // Nilai TERSIMPAN dari tabel konfigurasi, fallback default bila kosong
        foreach (array_keys($this->loyalitasForm) as $kunci) {
            $this->loyalitasForm[$kunci] = (float) ($config->get($kunci) ?? $config->defaults()[$kunci]);
        }

        // Skema komisi default (tabel skema_komisi)
        $this->skemaKomisiForm = SkemaKomisi::orderBy('id')
            ->get(['id', 'nama', 'kategori', 'tipe', 'nilai', 'is_active'])
            ->toArray();

        $this->loadPajakConfig();
        $this->loadPrinterConfig();
        $this->loadIntegrasiConfig();
    }

    public function updatedPajakFormTarget(): void
    {
        $this->loadPajakConfig();
    }

    public function loadPajakConfig(): void
    {
        $pajakService = app(PajakService::class);
        $cabangId = $this->pajakForm['target'] === 'global' ? null : (int) $this->pajakForm['target'];
        $cfg = $pajakService->getConfig($cabangId);

        $this->pajakForm['enabled'] = (bool) $cfg['enabled'];
        $this->pajakForm['nama'] = (string) $cfg['nama'];
        $this->pajakForm['percent'] = (float) $cfg['percent'];
        $this->pajakForm['mode'] = (string) $cfg['mode'];
    }

    public function simpanPajak(): void
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi pajak.')) {
            return;
        }

        $this->validate([
            'pajakForm.enabled' => 'required|boolean',
            'pajakForm.nama' => 'required|string|max:50',
            'pajakForm.percent' => 'required|numeric|min:0|max:100',
            'pajakForm.mode' => 'required|in:exclusive,inclusive',
        ]);

        $cabangId = $this->pajakForm['target'] === 'global' ? null : (int) $this->pajakForm['target'];
        $pajakService = app(PajakService::class);

        $pajakService->saveConfig($cabangId, [
            'enabled' => (bool) $this->pajakForm['enabled'],
            'nama' => $this->pajakForm['nama'],
            'percent' => (float) $this->pajakForm['percent'],
            'mode' => $this->pajakForm['mode'],
        ]);

        $targetText = $cabangId ? "Cabang #{$cabangId}" : 'Global (Semua Cabang)';
        $statusText = $this->pajakForm['enabled']
            ? "Aktif ({$this->pajakForm['nama']} {$this->pajakForm['percent']}%, {$this->pajakForm['mode']})"
            : 'Nonaktif (Tanpa Pajak)';

        app(AuditService::class)->catat('Konfigurasi', 'update', null, "Konfigurasi Pajak {$targetText} diubah: {$statusText}");

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => "Pengaturan pajak {$targetText} berhasil disimpan ({$statusText})",
        ]);
    }

    public function loadPrinterConfig(): void
    {
        $config = app(KonfigurasiService::class);
        $labelCfg = $config->getLabelQrConfig();
        $strukCfg = $config->getStrukConfig();

        $this->strukForm = array_merge($this->strukForm, $strukCfg);

        $this->printerForm = [
            'label_preset' => (string) ($labelCfg['preset'] ?? '33x15'),
            'label_width' => (float) ($labelCfg['width'] ?? 33.0),
            'label_height' => (float) ($labelCfg['height'] ?? 15.0),
            'label_margin_top' => (float) ($labelCfg['margin_top'] ?? 1.0),
            'label_margin_bottom' => (float) ($labelCfg['margin_bottom'] ?? 1.0),
            'label_margin_left' => (float) ($labelCfg['margin_left'] ?? 1.5),
            'label_margin_right' => (float) ($labelCfg['margin_right'] ?? 1.5),
            'label_show_brand' => (bool) ($labelCfg['show_brand'] ?? true),
            'label_show_price' => (bool) ($labelCfg['show_price'] ?? true),
            'label_show_sku' => (bool) ($labelCfg['show_sku'] ?? true),
            'struk_default_width' => (string) ($strukCfg['default_width'] ?? '80mm'),
            'struk_footer' => (string) ($strukCfg['footer_text'] ?? 'Terima kasih atas kunjungan Anda'),
        ];
    }

    public function updatedPrinterFormLabelPreset(string $preset): void
    {
        match ($preset) {
            '33x15' => [
                $this->printerForm['label_width'] = 33.0,
                $this->printerForm['label_height'] = 15.0,
                $this->printerForm['label_margin_top'] = 1.0,
                $this->printerForm['label_margin_bottom'] = 1.0,
                $this->printerForm['label_margin_left'] = 1.5,
                $this->printerForm['label_margin_right'] = 1.5,
            ],
            '40x20' => [
                $this->printerForm['label_width'] = 40.0,
                $this->printerForm['label_height'] = 20.0,
                $this->printerForm['label_margin_top'] = 1.5,
                $this->printerForm['label_margin_bottom'] = 1.5,
                $this->printerForm['label_margin_left'] = 2.0,
                $this->printerForm['label_margin_right'] = 2.0,
            ],
            '50x30' => [
                $this->printerForm['label_width'] = 50.0,
                $this->printerForm['label_height'] = 30.0,
                $this->printerForm['label_margin_top'] = 2.0,
                $this->printerForm['label_margin_bottom'] = 2.0,
                $this->printerForm['label_margin_left'] = 2.5,
                $this->printerForm['label_margin_right'] = 2.5,
            ],
            '58x40' => [
                $this->printerForm['label_width'] = 58.0,
                $this->printerForm['label_height'] = 40.0,
                $this->printerForm['label_margin_top'] = 3.0,
                $this->printerForm['label_margin_bottom'] = 3.0,
                $this->printerForm['label_margin_left'] = 3.0,
                $this->printerForm['label_margin_right'] = 3.0,
            ],
            default => null,
        };
    }

    public function simpanPengaturanPrinter(): void
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi printer.')) {
            return;
        }

        $this->validate([
            'printerForm.label_width' => 'required|numeric|min:15|max:150',
            'printerForm.label_height' => 'required|numeric|min:10|max:150',
            'printerForm.label_margin_top' => 'required|numeric|min:0|max:20',
            'printerForm.label_margin_bottom' => 'required|numeric|min:0|max:20',
            'printerForm.label_margin_left' => 'required|numeric|min:0|max:20',
            'printerForm.label_margin_right' => 'required|numeric|min:0|max:20',
            'printerForm.struk_default_width' => 'required|in:80mm,58mm,a4',
        ]);

        $config = app(KonfigurasiService::class);

        $labelData = [
            'preset' => $this->printerForm['label_preset'],
            'width' => (float) $this->printerForm['label_width'],
            'height' => (float) $this->printerForm['label_height'],
            'margin_top' => (float) $this->printerForm['label_margin_top'],
            'margin_bottom' => (float) $this->printerForm['label_margin_bottom'],
            'margin_left' => (float) $this->printerForm['label_margin_left'],
            'margin_right' => (float) $this->printerForm['label_margin_right'],
            'show_brand' => (bool) $this->printerForm['label_show_brand'],
            'show_price' => (bool) $this->printerForm['label_show_price'],
            'show_sku' => (bool) $this->printerForm['label_show_sku'],
        ];

        $strukData = array_merge($config->getStrukConfig(), [
            'default_width' => $this->printerForm['struk_default_width'],
            'footer_text' => $this->printerForm['struk_footer'],
        ]);

        $config->set('label_qr_config', $labelData, 'Ukuran dan margin cetak label QR produk');
        $config->set('struk_config', $strukData, 'Pengaturan default struk transaksi kasir POS');

        // Sinkronkan ke strukForm
        $this->strukForm['default_width'] = $this->printerForm['struk_default_width'];
        $this->strukForm['footer_text'] = $this->printerForm['struk_footer'];

        app(AuditService::class)->catat(
            'Konfigurasi',
            'update',
            null,
            "Pengaturan Printer & Label QR diperbarui (Label: {$labelData['width']}x{$labelData['height']}mm, Struk: {$strukData['default_width']})"
        );

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Pengaturan Printer & Label QR berhasil disimpan.',
        ]);
    }

    public function simpanTemplateStruk(): void
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi template struk.')) {
            return;
        }

        $this->validate([
            'strukForm.nama_toko' => 'required|string|max:100',
            'strukForm.slogan' => 'nullable|string|max:150',
            'strukForm.alamat' => 'nullable|string|max:250',
            'strukForm.telepon' => 'nullable|string|max:50',
            'strukForm.website_medsos' => 'nullable|string|max:100',
            'strukForm.qr_caption' => 'nullable|string|max:150',
            'strukForm.footer_text' => 'required|string|max:200',
            'strukForm.kebijakan_retur' => 'nullable|string|max:500',
            'strukForm.catatan_kaki' => 'nullable|string|max:300',
            'strukForm.default_width' => 'required|in:80mm,58mm,a4',
            'strukForm.qr_size' => 'required|numeric|min:40|max:150',
        ]);

        $config = app(KonfigurasiService::class);
        $config->set('struk_config', $this->strukForm, 'Pengaturan kustomisasi template struk transaksi kasir');

        // Sinkronkan ke printerForm
        $this->printerForm['struk_default_width'] = $this->strukForm['default_width'];
        $this->printerForm['struk_footer'] = $this->strukForm['footer_text'];

        app(AuditService::class)->catat(
            'Konfigurasi',
            'update',
            null,
            "Template struk & faktur diperbarui (Lebar: {$this->strukForm['default_width']}, QR Retur: ".($this->strukForm['show_qr_retur'] ? 'Aktif' : 'Nonaktif').')'
        );

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Kustomisasi template struk & faktur kasir berhasil disimpan.',
        ]);
    }

    public function loadIntegrasiConfig(): void
    {
        $config = app(KonfigurasiService::class);
        $savedWa = $config->get('wa_gateway_config');
        if (is_array($savedWa)) {
            $this->integrasiForm['wa_provider'] = $savedWa['provider'] ?? 'fonnte';
            $this->integrasiForm['wa_url'] = $savedWa['url'] ?? 'https://api.fonnte.com/send';
            $this->integrasiForm['wa_token'] = $savedWa['token'] ?? '';
            $this->integrasiForm['wa_device_id'] = $savedWa['device_id'] ?? '';
            $this->integrasiForm['wa_auto_notif_transaksi'] = (bool) ($savedWa['auto_notif_transaksi'] ?? true);
            $this->integrasiForm['wa_auto_notif_servis'] = (bool) ($savedWa['auto_notif_servis'] ?? true);
        } else {
            // Fallback ke config('services.wa')
            $envWa = config('services.wa', []);
            if (is_array($envWa)) {
                $this->integrasiForm['wa_url'] = $envWa['url'] ?: 'https://api.fonnte.com/send';
                $this->integrasiForm['wa_token'] = $envWa['token'] ?: '';
                $this->integrasiForm['wa_device_id'] = $envWa['device_id'] ?: '';
            }
        }

        $savedDuitku = $config->get('duitku_gateway_config');
        if (is_array($savedDuitku)) {
            $this->integrasiForm['duitku_mode'] = $savedDuitku['mode'] ?? 'sandbox';
            $this->integrasiForm['duitku_merchant_code'] = $savedDuitku['merchant_code'] ?? '';
            $this->integrasiForm['duitku_api_key'] = $savedDuitku['api_key'] ?? '';
            $this->integrasiForm['duitku_merchant_key'] = $savedDuitku['merchant_key'] ?? '';
            $this->integrasiForm['duitku_expiry_period'] = (int) ($savedDuitku['expiry_period'] ?? 1440);
        } else {
            $this->integrasiForm['duitku_mode'] = config('duitku.sandbox') ? 'sandbox' : 'production';
            $this->integrasiForm['duitku_merchant_code'] = (string) config('duitku.merchant_code', '');
            $this->integrasiForm['duitku_api_key'] = (string) config('duitku.api_key', '');
            $this->integrasiForm['duitku_merchant_key'] = (string) config('duitku.merchant_key', '');
            $this->integrasiForm['duitku_expiry_period'] = (int) config('duitku.expiry_period', 1440);
        }

        $savedBiteship = $config->get('biteship_gateway_config');
        if (is_array($savedBiteship)) {
            $this->integrasiForm['biteship_api_key'] = $savedBiteship['api_key'] ?? '';
            $this->integrasiForm['biteship_origin_type'] = $savedBiteship['origin_type'] ?? 'closest_branch';
        } else {
            $this->integrasiForm['biteship_api_key'] = (string) env('BITESHIP_API_KEY', '');
        }
    }

    public function updatedIntegrasiFormWaProvider(): void
    {
        if ($this->integrasiForm['wa_provider'] === 'fonnte') {
            $this->integrasiForm['wa_url'] = 'https://api.fonnte.com/send';
        } elseif ($this->integrasiForm['wa_provider'] === 'wablas') {
            $this->integrasiForm['wa_url'] = 'https://kudus.wablas.com/api/send-message';
        }
    }

    public function simpanIntegrasi(): void
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi integrasi gateway.')) {
            return;
        }

        $this->validate([
            'integrasiForm.wa_provider' => 'required|in:fonnte,wablas,custom',
            'integrasiForm.wa_url' => 'required|url',
            'integrasiForm.wa_token' => 'nullable|string|max:255',
            'integrasiForm.wa_device_id' => 'nullable|string|max:100',
            'integrasiForm.duitku_mode' => 'required|in:sandbox,production',
            'integrasiForm.duitku_merchant_code' => 'nullable|string|max:100',
            'integrasiForm.duitku_api_key' => 'nullable|string|max:255',
            'integrasiForm.duitku_merchant_key' => 'nullable|string|max:255',
            'integrasiForm.duitku_expiry_period' => 'required|integer|min:15|max:10080',
            'integrasiForm.biteship_api_key' => 'nullable|string|max:255',
        ]);

        $config = app(KonfigurasiService::class);

        // 1. WhatsApp Config
        $config->set('wa_gateway_config', [
            'provider' => $this->integrasiForm['wa_provider'],
            'url' => $this->integrasiForm['wa_url'],
            'token' => $this->integrasiForm['wa_token'],
            'device_id' => $this->integrasiForm['wa_device_id'],
            'auto_notif_transaksi' => (bool) $this->integrasiForm['wa_auto_notif_transaksi'],
            'auto_notif_servis' => (bool) $this->integrasiForm['wa_auto_notif_servis'],
        ], 'Konfigurasi WhatsApp Gateway');

        // 2. Duitku Config
        $config->set('duitku_gateway_config', [
            'mode' => $this->integrasiForm['duitku_mode'],
            'merchant_code' => $this->integrasiForm['duitku_merchant_code'],
            'api_key' => $this->integrasiForm['duitku_api_key'],
            'merchant_key' => $this->integrasiForm['duitku_merchant_key'],
            'expiry_period' => (int) $this->integrasiForm['duitku_expiry_period'],
        ], 'Konfigurasi Payment Gateway Duitku');

        // 3. Biteship Config
        $config->set('biteship_gateway_config', [
            'api_key' => $this->integrasiForm['biteship_api_key'],
            'origin_type' => $this->integrasiForm['biteship_origin_type'],
        ], 'Konfigurasi Ekspedisi Biteship');

        app(AuditService::class)->catat(
            'Konfigurasi',
            'update',
            null,
            "Pengaturan integrasi gateway eksternal diperbarui (WA: {$this->integrasiForm['wa_provider']}, Duitku: {$this->integrasiForm['duitku_mode']})"
        );

        $this->dispatch('alert', [
            'type' => 'success',
            'message' => 'Konfigurasi integrasi gateway (WhatsApp, Duitku, Biteship) berhasil disimpan.',
        ]);
    }

    public function testKirimWa(): void
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin menguji gateway WhatsApp.')) {
            return;
        }

        $this->validate([
            'integrasiForm.wa_token' => 'required|string',
            'integrasiForm.wa_url' => 'required|url',
            'integrasiForm.wa_test_number' => 'required|string|min:9|max:20',
        ], [
            'integrasiForm.wa_test_number.required' => 'Nomor WhatsApp tujuan tes wajib diisi.',
            'integrasiForm.wa_token.required' => 'Token / API Key WhatsApp wajib diisi sebelum melakukan tes.',
        ]);

        $target = preg_replace('/[^0-9]/', '', $this->integrasiForm['wa_test_number']);
        if (str_starts_with($target, '0')) {
            $target = '62'.substr($target, 1);
        }

        $pesan = "🔔 *Uji Coba WhatsApp Gateway Ute Parts ERP*\n\nKoneksi WhatsApp Gateway berhasil terhubung dengan sistem Ute Parts.\n\n📅 Waktu: ".now()->format('d M Y H:i:s')."\n👤 Diuji oleh: ".auth()->user()->name."\n\n_Pesan ini dikirim otomatis oleh sistem._";

        try {
            $response = Http::timeout(12)->withHeaders([
                'Authorization' => $this->integrasiForm['wa_token'],
            ])->post($this->integrasiForm['wa_url'], [
                'target' => $target,
                'message' => $pesan,
            ]);

            if ($response->successful()) {
                $this->waTestStatus = 'success';
                $this->dispatch('alert', [
                    'type' => 'success',
                    'message' => "Pesan tes WhatsApp berhasil dikirim ke {$target}!",
                ]);
            } else {
                $this->waTestStatus = 'error';
                $this->dispatch('alert', [
                    'type' => 'error',
                    'message' => "Gagal kirim WA (HTTP {$response->status()}): {$response->body()}",
                ]);
            }
        } catch (\Throwable $e) {
            $this->waTestStatus = 'error';
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => 'Koneksi ke gateway WA gagal: '.$e->getMessage(),
            ]);
        }
    }

    /** [T-22] Simpan strategi loyalitas → tabel konfigurasi + diskon tier + skema komisi default. Non-retroaktif: hanya berlaku utk transaksi baru. */
    public function simpanLoyalitas()
    {
        if (! $this->boleh('pengaturan.manage', 'Anda tidak memiliki izin mengubah konfigurasi loyalitas.')) {
            return;
        }

        $this->loyalitasForm['poin_redeem_rupiah'] = $this->parseNominal($this->loyalitasForm['poin_redeem_rupiah'] ?? 0);

        foreach ($this->skemaKomisiForm as $k => $row) {
            if (($row['tipe'] ?? '') === 'nominal') {
                $this->skemaKomisiForm[$k]['nilai'] = $this->parseNominal($row['nilai'] ?? 0);
            }
        }

        $this->validate([
            'loyalitasForm.poin_earn_persen' => 'required|numeric|min:0|max:100',
            'loyalitasForm.poin_redeem_rupiah' => 'required|numeric|min:0',
            'loyalitasForm.diskon_silver' => 'required|numeric|min:0|max:100',
            'loyalitasForm.diskon_gold' => 'required|numeric|min:0|max:100',
            'loyalitasForm.diskon_platinum' => 'required|numeric|min:0|max:100',
            'skemaKomisiForm.*.tipe' => 'required|in:persen,nominal',
            'skemaKomisiForm.*.nilai' => 'required|numeric|min:0',
        ]);

        $config = app(KonfigurasiService::class);
        foreach ($this->loyalitasForm as $kunci => $nilai) {
            $config->set($kunci, (float) $nilai, 'Strategi loyalitas');
        }

        // Terapkan diskon tier (sama dengan PUT /api/crm/config)
        foreach (['silver', 'gold', 'platinum'] as $kode) {
            TierMembership::where('kode', $kode)
                ->update(['diskon_persen' => (float) $this->loyalitasForm["diskon_{$kode}"]]);
        }

        // Skema komisi default
        foreach ($this->skemaKomisiForm as $row) {
            SkemaKomisi::findOrFail($row['id'])->update([
                'tipe' => $row['tipe'],
                'nilai' => (float) $row['nilai'],
            ]);
        }

        app(AuditService::class)->catat('Konfigurasi', 'update', null, 'Strategi loyalitas diperbarui (poin/diskon/skema komisi) — non-retroaktif');

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Strategi loyalitas tersimpan (berlaku utk transaksi baru)']);
    }

    // User CRUD
    public bool $showUserModal = false;

    public array $userForm = [
        'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
        'role' => '', 'cabang_ids' => [], 'cabang_default_id' => null, 'is_active' => true,
    ];

    // Cabang CRUD
    public bool $showCabangModal = false;

    public array $cabangForm = ['id' => null, 'nama' => '', 'kode' => '', 'alamat' => '', 'telepon' => '', 'is_active' => true];

    // Gudang CRUD
    public bool $showGudangModal = false;

    public array $gudangForm = ['id' => null, 'cabang_id' => null, 'nama' => '', 'kode' => '', 'is_active' => true];

    // Rak CRUD
    public bool $showRakModal = false;

    public array $rakForm = ['id' => null, 'gudang_id' => null, 'nama' => '', 'kode' => '', 'zona' => '', 'is_active' => true];

    // Master Produk & Katalog Sub-Tab & Modals
    public string $masterProdukSubTab = 'kategori'; // kategori, brand, kualitas, satuan, kondisi, tipe_hp

    // Batch Selection untuk Master Atribut Produk & Part
    public array $selectedKategoriIds = [];

    public array $selectedBrandIds = [];

    public array $selectedKualitasIds = [];

    public array $selectedSatuanIds = [];

    public array $selectedKondisiKodes = [];

    public array $selectedTipeHpIds = [];

    // Modal Batch Edit Master Atribut
    public bool $showBatchEditModal = false;

    public array $batchEditForm = [
        'ubah_status' => false,
        'status' => true,
        'ubah_parent' => false,
        'parent_id' => null,
        'ubah_brand' => false,
        'brand_id' => null,
        'ubah_merk' => false,
        'merk' => '',
    ];

    public bool $showKategoriModal = false;

    public array $kategoriForm = ['id' => null, 'nama' => '', 'parent_id' => null, 'icon' => null, 'urutan' => 0, 'is_active' => true];

    public bool $showBrandModal = false;

    public array $brandForm = ['id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];

    public bool $showKualitasModal = false;

    public array $kualitasForm = ['id' => null, 'brand_id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];

    public bool $showSatuanModal = false;

    public array $satuanForm = ['id' => null, 'kode' => '', 'nama' => '', 'is_active' => true];

    public bool $showKondisiModal = false;

    public array $kondisiForm = ['kode' => '', 'nama' => '', 'warna' => 'emerald', 'hex' => '#10b981', 'keterangan' => '', 'is_active' => true, 'is_edit' => false];

    public bool $showTipeHpModal = false;

    public ?string $tipeHpSelectedMerk = '';

    public ?string $tipeHpSelectedModel = '';

    public array $tipeHpForm = ['id' => null, 'merk' => '', 'model' => '', 'nama' => '', 'is_active' => true];

    // [T-16] JenisServis CRUD editable
    public bool $showJenisServisModal = false;

    public array $jenisServisForm = [
        'id' => null, 'nama' => '', 'kode' => '', 'kategori' => 'hardware',
        'estimasi_durasi' => 120, 'durasi_garansi_hari' => 30, 'butuh_part' => true,
        'is_part_original' => false, 'is_active' => true,
    ];

    // [T-25] RBAC fleksibel: role baru + permission matrix per role
    public bool $showRoleModal = false;

    public string $roleBaruNama = '';

    public array $roleBaruPermissions = [];

    public ?int $editRoleId = null;

    public array $editRolePermissions = [];

    public function getPermissionsListProperty()
    {
        return Permission::orderBy('name')->get();
    }

    public static function getPermissionMetadata(): array
    {
        return [
            // Kasir & POS
            'pos.create' => [
                'modul' => 'Kasir & Penjualan (POS)',
                'label' => 'Buka & Buat Transaksi Kasir',
                'deskripsi' => 'Membuka layar kasir dan membuat transaksi penjualan baru.',
            ],
            'pos.view' => [
                'modul' => 'Kasir & Penjualan (POS)',
                'label' => 'Lihat Seluruh Transaksi Kasir',
                'deskripsi' => 'Melihat riwayat transaksi kasir dari seluruh staf di cabang.',
            ],
            'pos.view-own' => [
                'modul' => 'Kasir & Penjualan (POS)',
                'label' => 'Lihat Transaksi Kasir Sendiri',
                'deskripsi' => 'Hanya melihat riwayat penjualan yang diproses akun kasir sendiri.',
            ],
            'pos.void' => [
                'modul' => 'Kasir & Penjualan (POS)',
                'label' => 'Batalkan / Void Transaksi Kasir',
                'deskripsi' => 'Membatalkan transaksi kasir yang telah dibayar.',
            ],

            // Gudang & Inventaris (WMS)
            'wms.view' => [
                'modul' => 'Gudang & Inventaris (WMS)',
                'label' => 'Lihat Stok & Master Produk',
                'deskripsi' => 'Melihat katalog produk, posisi stok gudang, dan riwayat mutasi.',
            ],
            'wms.create' => [
                'modul' => 'Gudang & Inventaris (WMS)',
                'label' => 'Tambah & Edit Produk',
                'deskripsi' => 'Menambah produk baru, impor Excel, dan edit master barang.',
            ],
            'wms.transfer' => [
                'modul' => 'Gudang & Inventaris (WMS)',
                'label' => 'Transfer Antar Gudang',
                'deskripsi' => 'Membuat dan mengirim mutasi barang antar gudang/cabang.',
            ],
            'wms.opname' => [
                'modul' => 'Gudang & Inventaris (WMS)',
                'label' => 'Input Stok Opname & Cycle Count',
                'deskripsi' => 'Melakukan penghitungan fisik stok berkala di gudang.',
            ],
            'wms.approve-opname' => [
                'modul' => 'Gudang & Inventaris (WMS)',
                'label' => 'Persetujuan Hasil Opname',
                'deskripsi' => 'Menyetujui selisih penyesuaian stok opname.',
            ],
            'wms.receive-po' => [
                'modul' => 'Gudang & Inventaris (WMS)',
                'label' => 'Penerimaan Barang Supplier (PO/GRN)',
                'deskripsi' => 'Memproses penerimaan barang masuk dari purchase order supplier.',
            ],

            // Servis & Reparasi
            'servis.view' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Lihat Papan & Tiket Servis',
                'deskripsi' => 'Melihat kanban antrean servis, status unit, dan riwayat perbaikan.',
            ],
            'servis.create' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Terima Unit & Buat Tiket Servis',
                'deskripsi' => 'Menerima perangkat pelanggan dan menerbitkan tanda terima servis.',
            ],
            'servis.update-status' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Ubah Status Pengerjaan Servis',
                'deskripsi' => 'Mengupdate progres servis (pemeriksaan, pengerjaan teknisi, selesai).',
            ],
            'servis.input-sparepart' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Pasang Sparepart & Jasa Servis',
                'deskripsi' => 'Menambahkan komponen sparepart dan biaya jasa ke tiket servis.',
            ],
            'servis.approve-estimasi' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Persetujuan Estimasi Biaya Servis',
                'deskripsi' => 'Menyetujui estimasi rincian biaya setelah konfirmasi pelanggan.',
            ],
            'servis.override-status' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Override / Batalkan Status Servis',
                'deskripsi' => 'Mengubah paksa atau membatalkan tahapan pengerjaan tiket servis.',
            ],
            'servis.manage' => [
                'modul' => 'Servis & Reparasi',
                'label' => 'Kelola Master Jenis Servis',
                'deskripsi' => 'Mengatur katalog jenis perbaikan, estimasi biaya, dan durasi.',
            ],

            // Pelanggan & CRM
            'crm.view' => [
                'modul' => 'Pelanggan & CRM',
                'label' => 'Lihat Data Pelanggan',
                'deskripsi' => 'Melihat profil pelanggan, riwayat transaksi belanja, dan kontak.',
            ],
            'crm.create' => [
                'modul' => 'Pelanggan & CRM',
                'label' => 'Tambah Pelanggan Baru',
                'deskripsi' => 'Mendaftarkan data profil pelanggan baru ke sistem.',
            ],
            'crm.edit' => [
                'modul' => 'Pelanggan & CRM',
                'label' => 'Ubah Data Pelanggan',
                'deskripsi' => 'Memperbarui nomor kontak, alamat, dan data pelanggan.',
            ],
            'crm.delete' => [
                'modul' => 'Pelanggan & CRM',
                'label' => 'Hapus Pelanggan',
                'deskripsi' => 'Menghapus akun data pelanggan dari sistem.',
            ],
            'crm.broadcast' => [
                'modul' => 'Pelanggan & CRM',
                'label' => 'Kirim Broadcast Pesan / Promo',
                'deskripsi' => 'Mengirim pesan promosi massal via WhatsApp atau Email.',
            ],
            'tier.manage' => [
                'modul' => 'Pelanggan & CRM',
                'label' => 'Kelola Tier Level Pelanggan',
                'deskripsi' => 'Mengatur level harga tier (Retail, Reseller, Grosir) dan rekalkulasi.',
            ],

            // Kemitraan & Reseller
            'reseller.view' => [
                'modul' => 'Kemitraan & Reseller',
                'label' => 'Lihat Mitra Reseller',
                'deskripsi' => 'Melihat daftar mitra agen/reseller dan performa penjualan.',
            ],
            'reseller.manage' => [
                'modul' => 'Kemitraan & Reseller',
                'label' => 'Kelola Pendaftaran Reseller',
                'deskripsi' => 'Menerima, memverifikasi, dan mengatur komisi mitra reseller.',
            ],
            'komisi.view' => [
                'modul' => 'Kemitraan & Reseller',
                'label' => 'Lihat Laporan Komisi',
                'deskripsi' => 'Melihat rekap perolehan komisi staf, teknisi, dan reseller.',
            ],
            'komisi.approve' => [
                'modul' => 'Kemitraan & Reseller',
                'label' => 'Setujui Pembayaran Komisi',
                'deskripsi' => 'Menyetujui pencairan dan pembayaran komisi.',
            ],

            // Keuangan & Akuntansi
            'akunting.view' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Lihat Jurnal & Buku Besar',
                'deskripsi' => 'Melihat laporan jurnal umum, neraca saldo, dan laba rugi.',
            ],
            'akunting.create' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Buat Jurnal & Kas Masuk/Keluar',
                'deskripsi' => 'Mencatat transaksi keuangan kas, bank, dan biaya operasional.',
            ],
            'akunting.edit' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Koreksi Jurnal Keuangan',
                'deskripsi' => 'Mengubah entri jurnal sebelum proses tutup buku.',
            ],
            'akunting.approve' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Tutup Buku & Setujui Jurnal',
                'deskripsi' => 'Menyetujui penutupan periode akuntansi dan penguncian laporan.',
            ],
            'piutang.view' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Lihat Piutang Pelanggan',
                'deskripsi' => 'Melihat saldo tagihan jatuh tempo pelanggan.',
            ],
            'piutang.manage' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Terima Pembayaran Piutang',
                'deskripsi' => 'Mencatat pelunasan atau cicilan piutang pelanggan.',
            ],
            'utang.view' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Lihat Utang Supplier',
                'deskripsi' => 'Melihat tagihan faktur utang pembelian ke supplier.',
            ],
            'utang.manage' => [
                'modul' => 'Keuangan & Akuntansi',
                'label' => 'Bayar Tagihan Utang Supplier',
                'deskripsi' => 'Mencatat pengeluaran kas/bank untuk pelunasan utang supplier.',
            ],

            // Laporan
            'laporan.cabang' => [
                'modul' => 'Laporan & Analitik',
                'label' => 'Lihat Laporan Operasional Cabang',
                'deskripsi' => 'Mengakses laporan laba rugi, penjualan, dan stok cabang aktif.',
            ],
            'laporan.konsolidasi' => [
                'modul' => 'Laporan & Analitik',
                'label' => 'Lihat Laporan Konsolidasi Seluruh Cabang',
                'deskripsi' => 'Melihat laporan gabungan seluruh cabang secara terpusat.',
            ],

            // Pengguna & Cabang
            'user.view' => [
                'modul' => 'Pengguna & Cabang',
                'label' => 'Lihat Daftar Pengguna / Staf',
                'deskripsi' => 'Melihat daftar akun staf, role akses, dan cabang penugasan.',
            ],
            'user.create' => [
                'modul' => 'Pengguna & Cabang',
                'label' => 'Tambah Pengguna / Staf Baru',
                'deskripsi' => 'Membuat akun staf baru dan menentukan peran akses.',
            ],
            'user.edit' => [
                'modul' => 'Pengguna & Cabang',
                'label' => 'Edit Akun & Role Pengguna',
                'deskripsi' => 'Mengubah password, cabang, dan peran akses staf.',
            ],
            'user.delete' => [
                'modul' => 'Pengguna & Cabang',
                'label' => 'Nonaktifkan / Hapus Pengguna',
                'deskripsi' => 'Menonaktifkan akses staf yang sudah tidak bertugas.',
            ],
            'cabang.view' => [
                'modul' => 'Pengguna & Cabang',
                'label' => 'Lihat Struktur Cabang & Gudang',
                'deskripsi' => 'Melihat daftar cabang toko fisik, gudang, dan rak penyimpanan.',
            ],
            'cabang.manage' => [
                'modul' => 'Pengguna & Cabang',
                'label' => 'Kelola Cabang, Gudang, & Rak',
                'deskripsi' => 'Menambah atau mengedit cabang baru, lokasi gudang, dan rak.',
            ],

            // Pengaturan Sistem & Keamanan
            'pengaturan.manage' => [
                'modul' => 'Sistem & Keamanan',
                'label' => 'Kelola Pengaturan Sistem & Toko',
                'deskripsi' => 'Mengatur profil toko, struk kasir, printer, dan konfigurasi pajak.',
            ],
            'omnichannel.view' => [
                'modul' => 'Sistem & Keamanan',
                'label' => 'Lihat Integrasi Marketplace',
                'deskripsi' => 'Melihat channel e-commerce yang terhubung (Shopee, Tokopedia, dll).',
            ],
            'omnichannel.manage' => [
                'modul' => 'Sistem & Keamanan',
                'label' => 'Kelola Integrasi Marketplace',
                'deskripsi' => 'Menghubungkan akun toko marketplace dan sinkronisasi pesanan.',
            ],
            'approve-workflow' => [
                'modul' => 'Sistem & Keamanan',
                'label' => 'Persetujuan Workflow & Otorisasi',
                'deskripsi' => 'Otorisasi pengajuan dokumen bertingkat (PO besar, payroll, dll).',
            ],
            'lihat-audit-log' => [
                'modul' => 'Sistem & Keamanan',
                'label' => 'Lihat Log Audit Jejak Aktivitas',
                'deskripsi' => 'Memantau seluruh jejak riwayat aktivitas staf pada data sistem.',
            ],
            'kelola-sesi' => [
                'modul' => 'Sistem & Keamanan',
                'label' => 'Kelola Sesi & Perangkat Login',
                'deskripsi' => 'Melihat dan memutuskan sesi login aktif pada perangkat lain.',
            ],

            // Akses Data Sensitif (Keuangan Barang)
            'lihat.harga_beli' => [
                'modul' => 'Akses Data Sensitif (Keuangan Barang)',
                'label' => 'Lihat Harga Beli Modal (HPP)',
                'deskripsi' => 'Menampilkan nominal modal harga beli pada tabel produk dan transaksi.',
            ],
            'lihat.margin' => [
                'modul' => 'Akses Data Sensitif (Keuangan Barang)',
                'label' => 'Lihat Margin Keuntungan',
                'deskripsi' => 'Menampilkan nilai margin persentase laba di laporan dan kasir.',
            ],

            // SDM, Absensi & Payroll
            'kelola-hr' => [
                'modul' => 'SDM, Absensi & Penggajian (HR)',
                'label' => 'Kelola Data Karyawan & Shift Kerja',
                'deskripsi' => 'Mengatur jadwal kerja shift, absensi staf, dan data kepegawaian.',
            ],
            'kelola-payroll' => [
                'modul' => 'SDM, Absensi & Penggajian (HR)',
                'label' => 'Kelola Sistem Penggajian (Payroll)',
                'deskripsi' => 'Mengatur komponen gaji pokok, tunjangan, dan skema potongan.',
            ],
            'payroll.view' => [
                'modul' => 'SDM, Absensi & Penggajian (HR)',
                'label' => 'Lihat Rekap Gaji Karyawan',
                'deskripsi' => 'Melihat rekap perhitungan dan slip gaji seluruh karyawan.',
            ],
            'payroll.create' => [
                'modul' => 'SDM, Absensi & Penggajian (HR)',
                'label' => 'Buat & Hitung Periode Penggajian',
                'deskripsi' => 'Membuat draf slip penggajian bulanan staf.',
            ],
            'payroll.approve' => [
                'modul' => 'SDM, Absensi & Penggajian (HR)',
                'label' => 'Setujui Pembayaran Gaji',
                'deskripsi' => 'Menyetujui pencairan dan transfer pembayaran gaji karyawan.',
            ],
            'hr.lihat-sendiri' => [
                'modul' => 'SDM, Absensi & Penggajian (HR)',
                'label' => 'Lihat Absensi & Slip Gaji Sendiri',
                'deskripsi' => 'Staf dapat melihat rekap kehadiran, KPI, dan slip gaji milik sendiri.',
            ],
        ];
    }

    public function getGroupedPermissionsProperty(): array
    {
        $meta = self::getPermissionMetadata();
        $permissions = Permission::orderBy('name')->get();

        $grouped = [];
        foreach ($permissions as $perm) {
            $info = $meta[$perm->name] ?? [
                'modul' => 'Lainnya / Tambahan',
                'label' => ucwords(str_replace(['.', '-', '_'], ' ', $perm->name)),
                'deskripsi' => 'Hak akses sistem untuk '.$perm->name,
            ];

            $modul = $info['modul'];
            if (! isset($grouped[$modul])) {
                $grouped[$modul] = [];
            }

            $grouped[$modul][] = [
                'id' => $perm->id,
                'name' => $perm->name,
                'label' => $info['label'],
                'deskripsi' => $info['deskripsi'],
            ];
        }

        return $grouped;
    }

    // Role: semua role + permission (recompute)
    public function getRolesFullProperty()
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function openRoleModal()
    {
        $this->roleBaruNama = '';
        $this->roleBaruPermissions = [];
        $this->showRoleModal = true;
    }

    public function saveRoleBaru()
    {
        if (! $this->boleh('user.create', 'Anda tidak memiliki izin membuat role baru.')) {
            return;
        }

        $this->validate(['roleBaruNama' => 'required|string|max:255|unique:roles,name']);

        $role = Role::create(['name' => $this->roleBaruNama]);
        if (! empty($this->roleBaruPermissions)) {
            $role->syncPermissions($this->roleBaruPermissions);
        }

        app(AuditService::class)->catat('Role', 'create', $role->id, "Role {$role->name} dibuat");

        $this->showRoleModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Role baru dibuat']);
    }

    public function openEditRole(int $roleId)
    {
        $role = Role::with('permissions')->findOrFail($roleId);
        $this->editRoleId = $roleId;
        $this->editRolePermissions = $role->permissions->pluck('name')->toArray();
    }

    public function saveEditRolePermissions()
    {
        if (! $this->boleh('user.edit', 'Anda tidak memiliki izin mengubah hak akses role.')) {
            return;
        }

        $role = Role::findOrFail($this->editRoleId);

        // Guardrail: super-admin dan owner tidak boleh dikosongkan (anti lockout)
        if (in_array($role->name, ['super-admin', 'owner'], true) && empty($this->editRolePermissions)) {
            $this->dispatch('alert', ['type' => 'error', 'message' => "Role {$role->name} wajib memiliki minimal 1 permission (anti-lockout)"]);

            return;
        }

        $role->syncPermissions($this->editRolePermissions);
        app(AuditService::class)->catat('Role', 'update', $role->id, "Permission role {$role->name} diperbarui");

        $this->editRoleId = null;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Permission role diperbarui']);
    }

    public function getRolesProperty()
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function getCabangsProperty()
    {
        return Cabang::withCount('gudang')->withCount('users')->get();
    }

    public function getGudangsProperty()
    {
        return Gudang::with('cabang')->get();
    }

    public function getRaksProperty()
    {
        $cabangId = session('cabang_id');

        return Rak::with('gudang.cabang')
            ->when($cabangId, fn ($q) => $q->whereHas('gudang', fn ($g) => $g->where('cabang_id', $cabangId)))
            ->orderBy('gudang_id')
            ->orderBy('kode')
            ->get();
    }

    public function getUsersProperty()
    {
        return User::with('roles', 'cabangs')->latest()->paginate(15);
    }

    public function getJenisServisProperty()
    {
        return JenisServis::all();
    }

    /** [T-16] open modal edit/tambah jenis servis */
    public function openJenisServisModal(?int $id = null)
    {
        if (! $this->bolehBuka(self::PERM_JENIS_SERVIS)) {
            return;
        }

        if ($id) {
            $j = JenisServis::findOrFail($id);
            $this->jenisServisForm = $j->toArray();
            $this->jenisServisForm['biaya_jasa'] = (float) ($j->biaya_jasa ?? 0);
        } else {
            $this->jenisServisForm = [
                'id' => null, 'nama' => '', 'kode' => '', 'kategori' => 'hardware',
                'estimasi_durasi' => 120, 'durasi_garansi_hari' => 30, 'biaya_jasa' => 0, 'butuh_part' => true,
                'is_part_original' => false, 'is_active' => true,
            ];
        }
        $this->showJenisServisModal = true;
    }

    public function simpanJenisServis()
    {
        if (! $this->bolehKatalog(self::PERM_JENIS_SERVIS, 'Anda tidak memiliki izin mengelola master jenis servis.')) {
            return;
        }

        $this->jenisServisForm['biaya_jasa'] = $this->parseNominal($this->jenisServisForm['biaya_jasa'] ?? 0);

        $this->validate([
            'jenisServisForm.nama' => 'required|string|max:255',
            'jenisServisForm.kode' => 'required|string|max:20|unique:jenis_servis,kode,'.($this->jenisServisForm['id'] ?? 'NULL'),
            'jenisServisForm.biaya_jasa' => 'nullable|numeric|min:0',
        ]);

        $data = $this->jenisServisForm;
        $data['biaya_jasa'] = (float) ($data['biaya_jasa'] ?? 0);

        if ($this->jenisServisForm['id']) {
            JenisServis::findOrFail($this->jenisServisForm['id'])->update($data);
        } else {
            JenisServis::create($data);
        }

        $this->showJenisServisModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Jenis servis disimpan']);
    }

    /**
     * [T-16] Hapus master jenis servis. Ticket & estimasi yang sudah memakai
     * jenis servis ini adalah riwayat layanan — tidak dihapus permanen, hanya
     * dinonaktifkan (pola sama dengan hapusKategori/hapusKualitas).
     */
    public function hapusJenisServis(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_JENIS_SERVIS, 'Anda tidak memiliki izin menghapus jenis servis.')) {
            return;
        }

        $jenis = JenisServis::findOrFail($id);

        $referensi = DB::table('tiket_servis')->where('jenis_servis_id', $id)->count()
            + DB::table('tiket_servis_estimasi_item')->where('jenis_servis_id', $id)->count();

        if ($referensi > 0) {
            $jenis->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Jenis servis '{$jenis->kode}' dipakai oleh {$referensi} data tiket servis/estimasi. Status dinonaktifkan (tidak dihapus permanen).",
            ]);

            return;
        }

        $kode = $jenis->kode;
        $jenis->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Jenis servis '{$kode}' berhasil dihapus"]);
    }

    public function getCoaListProperty()
    {
        return AkunCOA::orderBy('kode')->get();
    }

    // ===== USER =====
    public function openUserModal(?int $id = null)
    {
        if ($id) {
            $u = User::with('roles', 'cabangs')->findOrFail($id);
            $defaultCabang = $u->cabangs->first(fn ($c) => (bool) $c->pivot->is_default);
            $this->userForm = [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'password' => '',
                'phone' => $u->phone ?? '', 'role' => $u->roles->first()?->name ?? '',
                'cabang_ids' => $u->cabangs->pluck('id')->toArray(),
                'cabang_default_id' => $defaultCabang?->id ?? $u->cabangs->first()?->id,
                'is_active' => (bool) $u->is_active,
            ];
        } else {
            $this->userForm = [
                'id' => null, 'name' => '', 'email' => '', 'password' => '', 'phone' => '',
                'role' => 'kasir', 'cabang_ids' => [], 'cabang_default_id' => null, 'is_active' => true,
            ];
        }
        $this->showUserModal = true;
    }

    public function saveUser()
    {
        $perm = ! empty($this->userForm['id']) ? 'user.edit' : 'user.create';
        if (! $this->boleh($perm, 'Anda tidak memiliki izin mengelola data pengguna.')) {
            return;
        }

        $this->validate([
            'userForm.name' => 'required|string|max:255',
            'userForm.email' => 'required|email|unique:users,email,'.($this->userForm['id'] ?? 'NULL'),
        ]);

        if (! $this->userForm['id']) {
            $this->validate(['userForm.password' => 'required|string|min:6']);

            $user = User::create([
                'name' => $this->userForm['name'],
                'email' => $this->userForm['email'],
                'phone' => $this->userForm['phone'],
                'password' => Hash::make($this->userForm['password']),
                'is_active' => $this->userForm['is_active'],
            ]);
            $aksi = 'create';
        } else {
            $user = User::findOrFail($this->userForm['id']);

            // Guardrail anti self-lockout
            if ($user->id === auth()->id()) {
                if (! $this->userForm['is_active']) {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri (anti-lockout).']);

                    return;
                }
                if ($user->hasRole('super-admin') && $this->userForm['role'] !== 'super-admin') {
                    $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak dapat mencabut role super-admin dari akun Anda sendiri (anti-lockout).']);

                    return;
                }
            }

            $user->update([
                'name' => $this->userForm['name'],
                'email' => $this->userForm['email'],
                'phone' => $this->userForm['phone'],
                'is_active' => $this->userForm['is_active'],
            ]);
            if ($this->userForm['password']) {
                $user->update(['password' => Hash::make($this->userForm['password'])]);
            }
            $aksi = 'update';
        }

        if ($this->userForm['role']) {
            $user->syncRoles([$this->userForm['role']]);
        }

        $cabangIds = array_map('intval', $this->userForm['cabang_ids'] ?? []);
        $defaultId = ! empty($this->userForm['cabang_default_id']) ? (int) $this->userForm['cabang_default_id'] : null;

        if (empty($cabangIds)) {
            $firstCabang = Cabang::where('is_active', true)->first();
            if ($firstCabang) {
                $cabangIds = [(int) $firstCabang->id];
                $defaultId = (int) $firstCabang->id;
            }
        } elseif (! in_array($defaultId, $cabangIds, true)) {
            $defaultId = $cabangIds[0];
        }

        $syncData = [];
        foreach ($cabangIds as $cid) {
            $syncData[$cid] = ['is_default' => ($cid === $defaultId)];
        }
        $user->cabangs()->sync($syncData);

        app(AuditService::class)->catat('User', $aksi, $user->id, "User {$user->name} dikelola ({$this->userForm['role']})");

        $this->showUserModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'User berhasil disimpan']);
    }

    public function toggleUserActive(int $id)
    {
        if (! $this->boleh('user.edit', 'Anda tidak memiliki izin mengubah status pengguna.')) {
            return;
        }

        if ($id === (int) auth()->id()) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri (anti-lockout).']);

            return;
        }

        $u = User::findOrFail($id);
        $u->update(['is_active' => ! $u->is_active]);
        app(AuditService::class)->catat('User', 'update', $u->id, "Status user {$u->name} → ".($u->is_active ? 'aktif' : 'nonaktif'));
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status user diubah']);
    }

    // ===== CABANG =====
    public function openCabangModal(?int $id = null)
    {
        if ($id) {
            $c = Cabang::findOrFail($id);
            $this->cabangForm = $c->toArray();
        } else {
            $this->cabangForm = ['id' => null, 'nama' => '', 'kode' => '', 'alamat' => '', 'telepon' => '', 'is_active' => true];
        }
        $this->showCabangModal = true;
    }

    public function saveCabang()
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin mengelola cabang.')) {
            return;
        }

        $this->validate([
            'cabangForm.nama' => 'required|string|max:255',
            'cabangForm.kode' => 'required|string|max:20|unique:cabang,kode,'.($this->cabangForm['id'] ?? 'NULL'),
        ]);

        if ($this->cabangForm['id']) {
            Cabang::findOrFail($this->cabangForm['id'])->update($this->cabangForm);
        } else {
            Cabang::create($this->cabangForm);
        }

        $this->showCabangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Cabang disimpan']);
    }

    /**
     * Hapus cabang. Cabang hampir selalu punya referensi (transaksi, jurnal,
     * tiket servis, user) — semua itu riwayat akuntansi/layanan, jadi kalau
     * masih direferensikan cabang hanya dinonaktifkan, tidak dihapus permanen
     * (pola sama dengan hapusKategori / hapusKualitas).
     */
    public function hapusCabang(int $id): void
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin menghapus cabang.')) {
            return;
        }

        $cabang = Cabang::findOrFail($id);

        $gudang = DB::table('gudang')->where('cabang_id', $id)->count();
        $user = DB::table('user_cabang')->where('cabang_id', $id)->count();
        $transaksi = DB::table('transaksi')->where('cabang_id', $id)->count();
        $piutang = DB::table('piutang')->where('cabang_id', $id)->count();
        $utang = DB::table('utang')->where('cabang_id', $id)->count();
        $jurnal = DB::table('jurnal_akuntansi')->where('cabang_id', $id)->count();
        $tiket = DB::table('tiket_servis')->where('cabang_id', $id)->count();

        $total = $gudang + $user + $transaksi + $piutang + $utang + $jurnal + $tiket;

        if ($total > 0) {
            $cabang->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Cabang '{$cabang->kode}' masih memiliki {$total} data terkait ({$gudang} gudang, {$user} user, {$transaksi} transaksi, {$piutang} piutang, {$utang} utang, {$jurnal} jurnal, {$tiket} tiket servis). Status dinonaktifkan (tidak dihapus permanen).",
            ]);

            return;
        }

        $kode = $cabang->kode;
        $cabang->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Cabang '{$kode}' berhasil dihapus"]);
    }

    // ===== GUDANG =====
    public function openGudangModal(?int $id = null)
    {
        if ($id) {
            $g = Gudang::findOrFail($id);
            $this->gudangForm = $g->toArray();
        } else {
            $this->gudangForm = ['id' => null, 'cabang_id' => null, 'nama' => '', 'kode' => '', 'is_active' => true];
        }
        $this->showGudangModal = true;
    }

    public function saveGudang()
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin mengelola gudang.')) {
            return;
        }

        $this->validate([
            'gudangForm.cabang_id' => 'required|exists:cabang,id',
            'gudangForm.nama' => 'required|string|max:255',
            'gudangForm.kode' => 'required|string|max:20|unique:gudang,kode,'.($this->gudangForm['id'] ?? 'NULL'),
        ]);

        if ($this->gudangForm['id']) {
            Gudang::findOrFail($this->gudangForm['id'])->update($this->gudangForm);
        } else {
            Gudang::create($this->gudangForm);
        }

        $this->showGudangModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Gudang disimpan']);
    }

    /**
     * Hapus gudang. Hampir semua tabel di bawah memakai cascadeOnDelete
     * (lihat migration: stok_items, stok_log, stok_opname, rak, PO, transfer),
     * jadi hard delete gudang yang pernah dipakai = menghapus riwayat stok
     * tanpa jejak. Kalau ada baris stok/riwayat, gudang hanya dinonaktifkan.
     */
    public function hapusGudang(int $id): void
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin menghapus gudang.')) {
            return;
        }

        $gudang = Gudang::findOrFail($id);

        // Baris stok_items = jejak stok fisik. Satu baris pun (walau jumlah 0)
        // sudah berarti gudang pernah dipakai → hard delete tidak aman.
        $stok = DB::table('stok_items')->where('gudang_id', $id)->count();
        $rak = DB::table('rak')->where('gudang_id', $id)->count();
        $po = DB::table('purchase_order')->where('gudang_tujuan_id', $id)->count();
        $transfer = DB::table('stok_transfer')
            ->where('gudang_asal_id', $id)
            ->orWhere('gudang_tujuan_id', $id)
            ->count();
        $opname = DB::table('stok_opname')->where('gudang_id', $id)->count();
        $mutasi = DB::table('stock_mutation_log')->where('gudang_id', $id)->count();
        // grn memakai FK tanpa onDelete (RESTRICT) — hard delete akan error SQL.
        $grn = DB::table('grn')->where('gudang_id', $id)->count();
        $sparepart = DB::table('servis_sparepart')->where('gudang_id', $id)->count();
        $transaksi = DB::table('transaksi')->where('gudang_id', $id)->count();

        $total = $stok + $rak + $po + $transfer + $opname + $mutasi + $grn + $sparepart + $transaksi;

        if ($total > 0) {
            $gudang->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Gudang '{$gudang->kode}' memiliki {$total} data terkait ({$stok} baris stok, {$mutasi} mutasi, {$po} PO, {$transfer} transfer, {$opname} opname, {$rak} rak, {$grn} GRN, {$sparepart} sparepart servis, {$transaksi} transaksi). Status dinonaktifkan (tidak dihapus permanen).",
            ]);

            return;
        }

        $kode = $gudang->kode;
        $gudang->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Gudang '{$kode}' berhasil dihapus"]);
    }

    // ===== RAK =====
    public function openRakModal(?int $id = null): void
    {
        if ($id) {
            $r = Rak::findOrFail($id);
            $this->rakForm = [
                'id' => $r->id,
                'gudang_id' => $r->gudang_id,
                'nama' => $r->nama,
                'kode' => $r->kode,
                'zona' => $r->zona ?? '',
                'is_active' => (bool) ($r->is_active ?? true),
            ];
        } else {
            $cabangId = session('cabang_id');
            $defaultGudangId = $cabangId ? Gudang::where('cabang_id', $cabangId)->where('is_active', true)->value('id') : null;
            $this->rakForm = [
                'id' => null,
                'gudang_id' => $defaultGudangId,
                'nama' => '',
                'kode' => '',
                'zona' => '',
                'is_active' => true,
            ];
        }
        $this->showRakModal = true;
    }

    public function saveRak(): void
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin mengelola rak.')) {
            return;
        }

        $this->validate([
            'rakForm.gudang_id' => 'required|exists:gudang,id',
            'rakForm.nama' => 'required|string|max:255',
            'rakForm.kode' => 'required|string|max:20|unique:rak,kode,'.($this->rakForm['id'] ?: 'NULL'),
            'rakForm.zona' => 'nullable|string|max:50',
            'rakForm.is_active' => 'boolean',
        ]);

        $gudang = Gudang::find($this->rakForm['gudang_id']);
        $user = auth()->user();
        if ($user && ! $user->hasRole(['super-admin', 'owner'])) {
            $cabangId = session('cabang_id') ?? $user->cabang_id;
            if ($cabangId && $gudang && (int) $gudang->cabang_id !== (int) $cabangId) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda hanya dapat mengelola rak di gudang cabang Anda.']);

                return;
            }
        }

        if ($this->rakForm['id']) {
            Rak::findOrFail($this->rakForm['id'])->update($this->rakForm);
        } else {
            Rak::create($this->rakForm);
        }

        $this->showRakModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Rak berhasil disimpan']);
    }

    public function hapusRak(int $id): void
    {
        if (! $this->boleh('cabang.manage', 'Anda tidak memiliki izin menghapus rak.')) {
            return;
        }

        $rak = Rak::find($id);
        if (! $rak) {
            return;
        }

        $user = auth()->user();
        if ($user && ! $user->hasRole(['super-admin', 'owner'])) {
            $cabangId = session('cabang_id') ?? $user->cabang_id;
            if ($cabangId && $rak->gudang && (int) $rak->gudang->cabang_id !== (int) $cabangId) {
                $this->dispatch('alert', ['type' => 'error', 'message' => 'Anda hanya dapat menghapus rak di gudang cabang Anda.']);

                return;
            }
        }

        if (StokItem::where('rak_id', $id)->where('jumlah', '>', 0)->exists()) {
            $this->dispatch('alert', [
                'type' => 'error',
                'message' => "Rak '{$rak->nama}' masih berisi stok aktif (jumlah > 0). Pindahkan stok ke rak lain terlebih dahulu.",
            ]);

            return;
        }

        $nama = $rak->nama;
        $rak->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => "Rak '{$nama}' berhasil dihapus"]);
    }

    // ===== MASTER DATA PRODUK & KATALOG =====

    // 1. Kategori
    public function openKategoriModal(?int $id = null): void
    {
        if (! $this->bolehBuka(self::PERM_MASTER_PRODUK)) {
            return;
        }

        if ($id) {
            $kat = KategoriProduk::findOrFail($id);
            $this->kategoriForm = [
                'id' => $kat->id,
                'nama' => $kat->nama,
                'parent_id' => $kat->parent_id,
                'icon' => $kat->icon,
                'urutan' => $kat->urutan,
                'is_active' => (bool) $kat->is_active,
            ];
        } else {
            $this->kategoriForm = [
                'id' => null,
                'nama' => '',
                'parent_id' => null,
                'icon' => null,
                'urutan' => 0,
                'is_active' => true,
            ];
        }
        $this->showKategoriModal = true;
    }

    public function simpanKategori(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengelola kategori produk.')) {
            return;
        }

        $this->validate([
            'kategoriForm.nama' => 'required|string|max:255',
            'kategoriForm.parent_id' => 'nullable|exists:kategori_produk,id',
            'kategoriForm.urutan' => 'nullable|integer',
        ]);

        if ($this->kategoriForm['id'] && $this->kategoriForm['parent_id'] == $this->kategoriForm['id']) {
            $this->dispatch('alert', ['type' => 'error', 'message' => 'Kategori tidak bisa menjadi induk untuk dirinya sendiri']);

            return;
        }

        $nama = trim($this->kategoriForm['nama']);
        $parentId = $this->kategoriForm['parent_id'] ? (int) $this->kategoriForm['parent_id'] : null;
        $id = $this->kategoriForm['id'] ? (int) $this->kategoriForm['id'] : null;

        $duplikat = KategoriProduk::whereRaw('LOWER(TRIM(nama)) = ?', [strtolower($nama)])
            ->where(function ($q) use ($parentId) {
                if ($parentId === null) {
                    $q->whereNull('parent_id');
                } else {
                    $q->where('parent_id', $parentId);
                }
            })
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplikat) {
            $this->addError('kategoriForm.nama', "Kategori '{$nama}' sudah ada pada jenjang ini.");
            $this->dispatch('alert', ['type' => 'error', 'message' => "Kategori '{$nama}' sudah ada pada jenjang ini"]);

            return;
        }

        if ($this->kategoriForm['id']) {
            $kat = KategoriProduk::findOrFail($this->kategoriForm['id']);
            $kat->update([
                'nama' => $this->kategoriForm['nama'],
                'parent_id' => $this->kategoriForm['parent_id'] ?: null,
                'icon' => $this->kategoriForm['icon'] ?: null,
                'urutan' => (int) ($this->kategoriForm['urutan'] ?? 0),
                'is_active' => (bool) $this->kategoriForm['is_active'],
            ]);
        } else {
            KategoriProduk::create([
                'nama' => $this->kategoriForm['nama'],
                'parent_id' => $this->kategoriForm['parent_id'] ?: null,
                'icon' => $this->kategoriForm['icon'] ?: null,
                'urutan' => (int) ($this->kategoriForm['urutan'] ?? 0),
                'is_active' => (bool) $this->kategoriForm['is_active'],
            ]);
        }

        $this->showKategoriModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kategori produk disimpan']);
    }

    public function toggleKategoriStatus(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status kategori produk.')) {
            return;
        }

        $kat = KategoriProduk::findOrFail($id);
        $kat->update(['is_active' => ! $kat->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status kategori diubah']);
    }

    public function hapusKategori(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus kategori produk.')) {
            return;
        }

        $kat = KategoriProduk::withCount(['produk', 'children'])->findOrFail($id);

        if ($kat->produk_count > 0 || $kat->children_count > 0) {
            $kat->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Kategori memiliki {$kat->produk_count} produk / {$kat->children_count} sub-kategori. Status dinonaktifkan (tidak dihapus permanen).",
            ]);

            return;
        }

        $kat->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kategori berhasil dihapus']);
    }

    // 2. Brand
    public function openBrandModal(?int $id = null): void
    {
        if (! $this->bolehBuka(self::PERM_MASTER_PRODUK)) {
            return;
        }

        if ($id) {
            $brand = Brand::findOrFail($id);
            $this->brandForm = [
                'id' => $brand->id,
                'nama' => $brand->nama,
                'keterangan' => $brand->keterangan ?? '',
                'is_active' => (bool) $brand->is_active,
            ];
        } else {
            $this->brandForm = ['id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];
        }
        $this->showBrandModal = true;
    }

    public function simpanBrand(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengelola brand produk.')) {
            return;
        }

        $this->validate([
            'brandForm.nama' => 'required|string|max:255',
        ]);

        $nama = trim($this->brandForm['nama']);
        $id = $this->brandForm['id'] ? (int) $this->brandForm['id'] : null;

        $duplikat = Brand::whereRaw('LOWER(TRIM(nama)) = ?', [strtolower($nama)])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplikat) {
            $this->addError('brandForm.nama', "Brand part '{$nama}' sudah terdaftar.");
            $this->dispatch('alert', ['type' => 'error', 'message' => "Brand part '{$nama}' sudah terdaftar"]);

            return;
        }

        if ($this->brandForm['id']) {
            Brand::findOrFail($this->brandForm['id'])->update([
                'nama' => $this->brandForm['nama'],
                'keterangan' => $this->brandForm['keterangan'] ?: null,
                'is_active' => (bool) $this->brandForm['is_active'],
            ]);
        } else {
            Brand::create([
                'nama' => $this->brandForm['nama'],
                'keterangan' => $this->brandForm['keterangan'] ?: null,
                'is_active' => (bool) $this->brandForm['is_active'],
            ]);
        }

        $this->showBrandModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Brand disimpan']);
    }

    public function toggleBrandStatus(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status brand.')) {
            return;
        }

        $brand = Brand::findOrFail($id);
        $brand->update(['is_active' => ! $brand->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status brand diubah']);
    }

    public function hapusBrand(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus brand.')) {
            return;
        }

        $brand = Brand::withCount('produk')->findOrFail($id);
        if ($brand->produk_count > 0) {
            $brand->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Brand terkait dengan {$brand->produk_count} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $brand->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Brand berhasil dihapus']);
    }

    // 3. Kualitas
    public function openKualitasModal(?int $id = null): void
    {
        if (! $this->bolehBuka(self::PERM_MASTER_PRODUK)) {
            return;
        }

        if ($id) {
            $k = KualitasProduk::findOrFail($id);
            $this->kualitasForm = [
                'id' => $k->id,
                'brand_id' => $k->brand_id,
                'nama' => $k->nama,
                'keterangan' => $k->keterangan ?? '',
                'is_active' => (bool) $k->is_active,
            ];
        } else {
            $this->kualitasForm = ['id' => null, 'brand_id' => null, 'nama' => '', 'keterangan' => '', 'is_active' => true];
        }
        $this->showKualitasModal = true;
    }

    public function simpanKualitas(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengelola tingkat kualitas produk.')) {
            return;
        }

        $this->validate([
            'kualitasForm.nama' => 'required|string|max:255',
            'kualitasForm.brand_id' => 'nullable|exists:brands,id',
        ]);

        $nama = trim($this->kualitasForm['nama']);
        $brandId = ! empty($this->kualitasForm['brand_id']) ? (int) $this->kualitasForm['brand_id'] : null;
        $id = $this->kualitasForm['id'] ? (int) $this->kualitasForm['id'] : null;

        $duplikat = KualitasProduk::whereRaw('LOWER(TRIM(nama)) = ?', [strtolower($nama)])
            ->where(function ($q) use ($brandId) {
                if ($brandId === null) {
                    $q->whereNull('brand_id');
                } else {
                    $q->where('brand_id', $brandId);
                }
            })
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplikat) {
            $msg = $brandId
                ? "Tingkat kualitas '{$nama}' untuk brand ini sudah terdaftar."
                : "Tingkat kualitas '{$nama}' (Universal) sudah terdaftar.";
            $this->addError('kualitasForm.nama', $msg);
            $this->dispatch('alert', ['type' => 'error', 'message' => $msg]);

            return;
        }

        $data = [
            'nama' => $this->kualitasForm['nama'],
            'brand_id' => $brandId,
            'keterangan' => $this->kualitasForm['keterangan'] ?: null,
            'is_active' => (bool) $this->kualitasForm['is_active'],
        ];

        if ($this->kualitasForm['id']) {
            KualitasProduk::findOrFail($this->kualitasForm['id'])->update($data);
        } else {
            KualitasProduk::create($data);
        }

        $this->showKualitasModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Tingkat kualitas disimpan']);
    }

    public function toggleKualitasStatus(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status tingkat kualitas.')) {
            return;
        }

        $k = KualitasProduk::findOrFail($id);
        $k->update(['is_active' => ! $k->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status kualitas diubah']);
    }

    public function hapusKualitas(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus tingkat kualitas.')) {
            return;
        }

        $k = KualitasProduk::withCount('produk')->findOrFail($id);
        if ($k->produk_count > 0) {
            $k->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Kualitas terkait dengan {$k->produk_count} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $k->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kualitas berhasil dihapus']);
    }

    // 4. Satuan
    public function openSatuanModal(?int $id = null): void
    {
        if (! $this->bolehBuka(self::PERM_MASTER_PRODUK)) {
            return;
        }

        if ($id) {
            $s = SatuanUnit::findOrFail($id);
            $this->satuanForm = [
                'id' => $s->id,
                'kode' => $s->kode,
                'nama' => $s->nama,
                'is_active' => (bool) $s->is_active,
            ];
        } else {
            $this->satuanForm = ['id' => null, 'kode' => '', 'nama' => '', 'is_active' => true];
        }
        $this->showSatuanModal = true;
    }

    public function simpanSatuan(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengelola satuan unit.')) {
            return;
        }

        $id = $this->satuanForm['id'] ? (int) $this->satuanForm['id'] : null;
        $this->validate([
            'satuanForm.kode' => 'required|string|max:20',
            'satuanForm.nama' => 'required|string|max:100',
        ]);

        $kode = strtolower(trim($this->satuanForm['kode']));
        $nama = trim($this->satuanForm['nama']);

        $duplikatKode = SatuanUnit::whereRaw('LOWER(TRIM(kode)) = ?', [$kode])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();
        if ($duplikatKode) {
            $this->addError('satuanForm.kode', "Kode satuan '{$kode}' sudah digunakan.");
            $this->dispatch('alert', ['type' => 'error', 'message' => "Kode satuan '{$kode}' sudah digunakan"]);

            return;
        }

        $duplikatNama = SatuanUnit::whereRaw('LOWER(TRIM(nama)) = ?', [strtolower($nama)])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();
        if ($duplikatNama) {
            $this->addError('satuanForm.nama', "Nama satuan '{$nama}' sudah terdaftar.");
            $this->dispatch('alert', ['type' => 'error', 'message' => "Nama satuan '{$nama}' sudah terdaftar"]);

            return;
        }

        if ($this->satuanForm['id']) {
            SatuanUnit::findOrFail($this->satuanForm['id'])->update([
                'kode' => $kode,
                'nama' => $this->satuanForm['nama'],
                'is_active' => (bool) $this->satuanForm['is_active'],
            ]);
        } else {
            SatuanUnit::create([
                'kode' => $kode,
                'nama' => $this->satuanForm['nama'],
                'is_active' => (bool) $this->satuanForm['is_active'],
            ]);
        }

        $this->showSatuanModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Satuan unit disimpan']);
    }

    public function toggleSatuanStatus(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status satuan unit.')) {
            return;
        }

        $s = SatuanUnit::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status satuan diubah']);
    }

    public function hapusSatuan(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus satuan unit.')) {
            return;
        }

        $s = SatuanUnit::findOrFail($id);
        $terpakai = Produk::where('satuan_kode', $s->kode)->count();
        if ($terpakai > 0) {
            $s->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Satuan '{$s->kode}' digunakan oleh {$terpakai} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $s->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Satuan berhasil dihapus']);
    }

    // 5. Kondisi (JSON via KonfigurasiService)
    public function openKondisiModal(?string $kode = null): void
    {
        if (! $this->bolehBuka(self::PERM_MASTER_PRODUK)) {
            return;
        }

        if ($kode) {
            $list = $this->getKondisiList();
            $item = collect($list)->firstWhere('kode', $kode);
            if ($item) {
                $this->kondisiForm = [
                    'kode' => $item['kode'],
                    'nama' => $item['nama'],
                    'warna' => $item['warna'] ?? 'emerald',
                    'hex' => $item['hex'] ?? '#10b981',
                    'keterangan' => $item['keterangan'] ?? '',
                    'is_active' => (bool) ($item['is_active'] ?? true),
                    'is_edit' => true,
                ];
            }
        } else {
            $this->kondisiForm = [
                'kode' => '',
                'nama' => '',
                'warna' => 'emerald',
                'hex' => '#10b981',
                'keterangan' => '',
                'is_active' => true,
                'is_edit' => false,
            ];
        }
        $this->showKondisiModal = true;
    }

    public function simpanKondisi(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengelola kondisi produk.')) {
            return;
        }

        $this->validate([
            'kondisiForm.nama' => 'required|string|max:100',
        ]);

        $warnaMap = [
            'emerald' => '#10b981',
            'blue' => '#3b82f6',
            'cyan' => '#06b6d4',
            'amber' => '#f59e0b',
            'rose' => '#ef4444',
            'purple' => '#a855f7',
            'slate' => '#64748b',
        ];
        $warna = $this->kondisiForm['warna'] ?? 'emerald';
        $hex = $warnaMap[$warna] ?? ($this->kondisiForm['hex'] ?? '#10b981');
        $keterangan = trim($this->kondisiForm['keterangan'] ?? '');

        $list = $this->getKondisiList();
        $nama = trim($this->kondisiForm['nama']);
        $isEdit = (bool) ($this->kondisiForm['is_edit'] ?? false);
        $kode = $isEdit
            ? $this->kondisiForm['kode']
            : (Str::slug($this->kondisiForm['kode'] ?: $nama, '_') ?: 'k_'.time());

        foreach ($list as $item) {
            if (! $isEdit && strtolower($item['kode']) === strtolower($kode)) {
                $this->addError('kondisiForm.kode', "Kode kondisi '{$kode}' sudah ada.");
                $this->dispatch('alert', ['type' => 'error', 'message' => "Kode kondisi '{$kode}' sudah ada"]);

                return;
            }
            if ((! $isEdit || $item['kode'] !== $kode) && strtolower(trim($item['nama'])) === strtolower($nama)) {
                $this->addError('kondisiForm.nama', "Kondisi dengan nama '{$nama}' sudah ada.");
                $this->dispatch('alert', ['type' => 'error', 'message' => "Kondisi dengan nama '{$nama}' sudah ada"]);

                return;
            }
        }

        $ada = false;
        foreach ($list as &$item) {
            if ($item['kode'] === $kode) {
                $item['nama'] = $nama;
                $item['warna'] = $warna;
                $item['hex'] = $hex;
                $item['keterangan'] = $keterangan;
                $item['is_active'] = (bool) $this->kondisiForm['is_active'];
                $ada = true;
                break;
            }
        }
        if (! $ada) {
            $list[] = [
                'kode' => $kode,
                'nama' => $nama,
                'warna' => $warna,
                'hex' => $hex,
                'keterangan' => $keterangan,
                'is_active' => (bool) $this->kondisiForm['is_active'],
            ];
        }

        app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($list));

        // [RBAC] Kondisi produk disimpan sebagai JSON di KonfigurasiService —
        // tidak ada model Eloquent, jadi LogsActivity tidak bisa men-log.
        // Catat manual lewat AuditService (helper yang sudah dipakai komponen ini).
        app(AuditService::class)->catat(
            'KondisiProduk',
            $ada ? 'update' : 'create',
            null,
            "Kondisi produk '{$kode}' ({$nama}) disimpan",
            null,
            ['kode' => $kode, 'nama' => $nama, 'warna' => $warna, 'keterangan' => $keterangan, 'is_active' => (bool) $this->kondisiForm['is_active']]
        );

        $this->showKondisiModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kondisi produk disimpan']);
    }

    public function toggleKondisiStatus(string $kode): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status kondisi produk.')) {
            return;
        }

        $list = $this->getKondisiList();
        $statusBaru = null;
        foreach ($list as &$item) {
            if ($item['kode'] === $kode) {
                $item['is_active'] = ! ($item['is_active'] ?? true);
                $statusBaru = (bool) $item['is_active'];
                break;
            }
        }
        app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($list));

        if ($statusBaru !== null) {
            app(AuditService::class)->catat(
                'KondisiProduk',
                'update',
                null,
                "Kondisi produk '{$kode}' → ".($statusBaru ? 'aktif' : 'nonaktif')
            );
        }

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status kondisi diubah']);
    }

    public function hapusKondisi(string $kode): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus kondisi produk.')) {
            return;
        }

        $terpakai = Produk::where('kondisi', $kode)->count();
        if ($terpakai > 0) {
            $this->setKondisiAktif($kode, false);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Kondisi '{$kode}' dipakai pada {$terpakai} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $list = collect($this->getKondisiList())->reject(fn ($i) => $i['kode'] === $kode)->values()->all();
        app(KonfigurasiService::class)->set('master_produk_kondisi_list', $list);

        app(AuditService::class)->catat('KondisiProduk', 'delete', null, "Kondisi produk '{$kode}' dihapus");

        $this->dispatch('alert', ['type' => 'success', 'message' => 'Kondisi berhasil dihapus']);
    }

    /**
     * [RBAC] Set status satu kondisi tanpa dispatch alert — dipakai hapusKondisi
     * supaya hanya satu pesan (warning) terkirim, bukan dua.
     */
    private function setKondisiAktif(string $kode, bool $aktif): void
    {
        $list = [];
        $ditemukan = false;
        foreach ($this->getKondisiList() as $item) {
            if ($item['kode'] === $kode) {
                $item['is_active'] = $aktif;
                $ditemukan = true;
            }
            $list[] = $item;
        }

        app(KonfigurasiService::class)->set('master_produk_kondisi_list', $list);

        if ($ditemukan) {
            app(AuditService::class)->catat(
                'KondisiProduk',
                'update',
                null,
                "Kondisi produk '{$kode}' → ".($aktif ? 'aktif' : 'nonaktif')
            );
        }
    }

    private function getKondisiList(): array
    {
        $config = app(KonfigurasiService::class);
        $saved = $config->get('master_produk_kondisi_list');

        $defaultKondisi = [
            ['kode' => 'baru', 'nama' => 'Baru', 'warna' => 'emerald', 'hex' => '#10b981', 'keterangan' => 'Baru (Segel Pabrik / Fresh Stock)', 'is_active' => true],
            ['kode' => 'oem', 'nama' => 'OEM', 'warna' => 'blue', 'hex' => '#3b82f6', 'keterangan' => 'OEM (Original Equipment Manufacturer)', 'is_active' => true],
            ['kode' => 'compatible', 'nama' => 'Compatible', 'warna' => 'cyan', 'hex' => '#06b6d4', 'keterangan' => 'Compatible (Part Pihak Ketiga Berkualitas)', 'is_active' => true],
            ['kode' => 'bekas', 'nama' => 'Bekas / Copotan', 'warna' => 'amber', 'hex' => '#f59e0b', 'keterangan' => 'Bekas / Copotan Unit Mesin Normal', 'is_active' => true],
            ['kode' => 'rusak', 'nama' => 'Rusak / Kanibal', 'warna' => 'rose', 'hex' => '#ef4444', 'keterangan' => 'Rusak / Bahan Kanibal Part Teknisi', 'is_active' => true],
        ];

        $defMap = collect($defaultKondisi)->keyBy('kode');

        return (is_array($saved) && ! empty($saved))
            ? array_values(array_map(function ($k) use ($defMap) {
                $def = $defMap->get($k['kode'] ?? '');

                return [
                    'kode' => $k['kode'] ?? 'baru',
                    'nama' => $k['nama'] ?? 'Baru',
                    'warna' => $k['warna'] ?? ($def['warna'] ?? 'emerald'),
                    'hex' => $k['hex'] ?? ($def['hex'] ?? '#10b981'),
                    'keterangan' => $k['keterangan'] ?? ($def['keterangan'] ?? 'Kondisi Suku Cadang'),
                    'is_active' => (bool) ($k['is_active'] ?? true),
                ];
            }, $saved))
            : $defaultKondisi;
    }

    // 6. Tipe HP
    public function openTipeHpModal(?int $id = null): void
    {
        if (! $this->bolehBuka(self::PERM_MASTER_PRODUK)) {
            return;
        }

        if ($id) {
            $t = TipeHp::findOrFail($id);
            $this->tipeHpForm = [
                'id' => $t->id,
                'merk' => $t->merk,
                'model' => $t->model,
                'nama' => $t->nama ?? '',
                'is_active' => (bool) $t->is_active,
            ];
            $this->tipeHpSelectedMerk = $t->merk;
            $this->tipeHpSelectedModel = $t->model;
        } else {
            $this->tipeHpForm = ['id' => null, 'merk' => '', 'model' => '', 'nama' => '', 'is_active' => true];
            $this->tipeHpSelectedMerk = '';
            $this->tipeHpSelectedModel = '';
        }
        $this->showTipeHpModal = true;
    }

    public function updatedTipeHpSelectedMerk($value): void
    {
        if ($value && $value !== '__custom__') {
            $this->tipeHpForm['merk'] = $value;
        } elseif ($value === '__custom__') {
            $this->tipeHpForm['merk'] = '';
        }
        $this->tipeHpSelectedModel = '';
        $this->tipeHpForm['model'] = '';
    }

    public function updatedTipeHpSelectedModel($value): void
    {
        if ($value && $value !== '__custom__') {
            $this->tipeHpForm['model'] = $value;
        } elseif ($value === '__custom__') {
            $this->tipeHpForm['model'] = '';
        }
    }

    public function simpanTipeHp(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengelola tipe HP.')) {
            return;
        }

        if (! empty($this->tipeHpSelectedMerk) && $this->tipeHpSelectedMerk !== '__custom__' && empty($this->tipeHpForm['merk'])) {
            $this->tipeHpForm['merk'] = $this->tipeHpSelectedMerk;
        }
        if (! empty($this->tipeHpSelectedModel) && $this->tipeHpSelectedModel !== '__custom__' && empty($this->tipeHpForm['model'])) {
            $this->tipeHpForm['model'] = $this->tipeHpSelectedModel;
        }

        $this->validate([
            'tipeHpForm.merk' => 'required|string|max:100',
            'tipeHpForm.model' => 'required|string|max:100',
        ]);

        $merk = trim($this->tipeHpForm['merk']);
        $model = trim($this->tipeHpForm['model']);
        $id = $this->tipeHpForm['id'] ? (int) $this->tipeHpForm['id'] : null;

        $duplikat = TipeHp::whereRaw('LOWER(TRIM(merk)) = ? AND LOWER(TRIM(model)) = ?', [strtolower($merk), strtolower($model)])
            ->when($id, fn ($q) => $q->where('id', '!=', $id))
            ->exists();

        if ($duplikat) {
            $this->addError('tipeHpForm.model', "Tipe HP '{$merk} {$model}' sudah terdaftar.");
            $this->dispatch('alert', ['type' => 'error', 'message' => "Tipe HP '{$merk} {$model}' sudah terdaftar"]);

            return;
        }

        $nama = trim($this->tipeHpForm['nama']) ?: "{$merk} {$model}";

        if ($this->tipeHpForm['id']) {
            TipeHp::findOrFail($this->tipeHpForm['id'])->update([
                'merk' => $this->tipeHpForm['merk'],
                'model' => $this->tipeHpForm['model'],
                'nama' => $nama,
                'is_active' => (bool) $this->tipeHpForm['is_active'],
            ]);
        } else {
            TipeHp::create([
                'merk' => $this->tipeHpForm['merk'],
                'model' => $this->tipeHpForm['model'],
                'nama' => $nama,
                'is_active' => (bool) $this->tipeHpForm['is_active'],
            ]);
        }

        $this->showTipeHpModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Tipe HP berhasil disimpan']);
    }

    public function toggleTipeHpStatus(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status tipe HP.')) {
            return;
        }

        $t = TipeHp::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Status tipe HP diubah']);
    }

    public function hapusTipeHp(int $id): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus tipe HP.')) {
            return;
        }

        $t = TipeHp::withCount('produk')->findOrFail($id);
        if ($t->produk_count > 0) {
            $t->update(['is_active' => false]);
            $this->dispatch('alert', [
                'type' => 'warning',
                'message' => "Tipe HP ini terhubung ke {$t->produk_count} produk. Status dinonaktifkan.",
            ]);

            return;
        }

        $t->delete();
        $this->dispatch('alert', ['type' => 'success', 'message' => 'Tipe HP berhasil dihapus']);
    }

    // -------------------------------------------------------------
    // Batch Aksi Master Atribut Produk & Part (T-Batch)
    // -------------------------------------------------------------

    public function toggleSelectAll(string $subTab, array $allKeys): void
    {
        switch ($subTab) {
            case 'kategori':
                $allInt = array_values(array_map('intval', $allKeys));
                $this->selectedKategoriIds = count($this->selectedKategoriIds) === count($allInt) ? [] : $allInt;
                break;
            case 'brand':
                $allInt = array_values(array_map('intval', $allKeys));
                $this->selectedBrandIds = count($this->selectedBrandIds) === count($allInt) ? [] : $allInt;
                break;
            case 'kualitas':
                $allInt = array_values(array_map('intval', $allKeys));
                $this->selectedKualitasIds = count($this->selectedKualitasIds) === count($allInt) ? [] : $allInt;
                break;
            case 'satuan':
                $allInt = array_values(array_map('intval', $allKeys));
                $this->selectedSatuanIds = count($this->selectedSatuanIds) === count($allInt) ? [] : $allInt;
                break;
            case 'kondisi':
                $allStr = array_values(array_map('strval', $allKeys));
                $this->selectedKondisiKodes = count($this->selectedKondisiKodes) === count($allStr) ? [] : $allStr;
                break;
            case 'tipe_hp':
                $allInt = array_values(array_map('intval', $allKeys));
                $this->selectedTipeHpIds = count($this->selectedTipeHpIds) === count($allInt) ? [] : $allInt;
                break;
        }
    }

    public function clearSelection(): void
    {
        $this->selectedKategoriIds = [];
        $this->selectedBrandIds = [];
        $this->selectedKualitasIds = [];
        $this->selectedSatuanIds = [];
        $this->selectedKondisiKodes = [];
        $this->selectedTipeHpIds = [];
    }

    public function batchSetStatus(bool $status): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengubah status atribut.')) {
            return;
        }

        $sub = $this->masterProdukSubTab;
        $count = 0;

        switch ($sub) {
            case 'kategori':
                $count = KategoriProduk::whereIn('id', $this->selectedKategoriIds)->update(['is_active' => $status]);
                $this->selectedKategoriIds = [];
                break;
            case 'brand':
                $count = Brand::whereIn('id', $this->selectedBrandIds)->update(['is_active' => $status]);
                $this->selectedBrandIds = [];
                break;
            case 'kualitas':
                $count = KualitasProduk::whereIn('id', $this->selectedKualitasIds)->update(['is_active' => $status]);
                $this->selectedKualitasIds = [];
                break;
            case 'satuan':
                $count = SatuanUnit::whereIn('id', $this->selectedSatuanIds)->update(['is_active' => $status]);
                $this->selectedSatuanIds = [];
                break;
            case 'kondisi':
                $list = $this->getKondisiList();
                foreach ($list as &$item) {
                    if (in_array($item['kode'], $this->selectedKondisiKodes, true)) {
                        $item['is_active'] = $status;
                        $count++;
                    }
                }
                app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($list));
                $this->selectedKondisiKodes = [];
                break;
            case 'tipe_hp':
                $count = TipeHp::whereIn('id', $this->selectedTipeHpIds)->update(['is_active' => $status]);
                $this->selectedTipeHpIds = [];
                break;
        }

        $label = $status ? 'Aktif' : 'Nonaktif';
        $this->dispatch('alert', ['type' => 'success', 'message' => "{$count} item berhasil diset {$label}"]);
    }

    public function batchHapus(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin menghapus master atribut.')) {
            return;
        }

        $sub = $this->masterProdukSubTab;
        $deleted = 0;
        $deactivated = 0;

        switch ($sub) {
            case 'kategori':
                $kats = KategoriProduk::withCount(['produk', 'children'])->whereIn('id', $this->selectedKategoriIds)->get();
                foreach ($kats as $kat) {
                    if ($kat->produk_count > 0 || $kat->children_count > 0) {
                        $kat->update(['is_active' => false]);
                        $deactivated++;
                    } else {
                        $kat->delete();
                        $deleted++;
                    }
                }
                $this->selectedKategoriIds = [];
                break;

            case 'brand':
                $brands = Brand::withCount('produk')->whereIn('id', $this->selectedBrandIds)->get();
                foreach ($brands as $brand) {
                    if ($brand->produk_count > 0) {
                        $brand->update(['is_active' => false]);
                        $deactivated++;
                    } else {
                        $brand->delete();
                        $deleted++;
                    }
                }
                $this->selectedBrandIds = [];
                break;

            case 'kualitas':
                $listK = KualitasProduk::withCount('produk')->whereIn('id', $this->selectedKualitasIds)->get();
                foreach ($listK as $k) {
                    if ($k->produk_count > 0) {
                        $k->update(['is_active' => false]);
                        $deactivated++;
                    } else {
                        $k->delete();
                        $deleted++;
                    }
                }
                $this->selectedKualitasIds = [];
                break;

            case 'satuan':
                $satuans = SatuanUnit::whereIn('id', $this->selectedSatuanIds)->get();
                foreach ($satuans as $s) {
                    $terpakai = Produk::where('satuan_kode', $s->kode)->count();
                    if ($terpakai > 0) {
                        $s->update(['is_active' => false]);
                        $deactivated++;
                    } else {
                        $s->delete();
                        $deleted++;
                    }
                }
                $this->selectedSatuanIds = [];
                break;

            case 'kondisi':
                $list = $this->getKondisiList();
                $newList = [];
                foreach ($list as $item) {
                    if (in_array($item['kode'], $this->selectedKondisiKodes, true)) {
                        $terpakai = Produk::where('kondisi', $item['kode'])->count();
                        if ($terpakai > 0) {
                            $item['is_active'] = false;
                            $newList[] = $item;
                            $deactivated++;
                        } else {
                            $deleted++;
                        }
                    } else {
                        $newList[] = $item;
                    }
                }
                app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($newList));
                $this->selectedKondisiKodes = [];
                break;

            case 'tipe_hp':
                $tipes = TipeHp::withCount('produk')->whereIn('id', $this->selectedTipeHpIds)->get();
                foreach ($tipes as $t) {
                    if ($t->produk_count > 0) {
                        $t->update(['is_active' => false]);
                        $deactivated++;
                    } else {
                        $t->delete();
                        $deleted++;
                    }
                }
                $this->selectedTipeHpIds = [];
                break;
        }

        $pesan = "Batch hapus selesai: {$deleted} item dihapus permanen";
        if ($deactivated > 0) {
            $pesan .= ", {$deactivated} dinonaktifkan (karena terkait data produk)";
        }
        $this->dispatch('alert', ['type' => $deleted > 0 ? 'success' : 'warning', 'message' => $pesan]);
    }

    public function openBatchEditModal(): void
    {
        $this->batchEditForm = [
            'ubah_status' => false,
            'status' => true,
            'ubah_parent' => false,
            'parent_id' => null,
            'ubah_brand' => false,
            'brand_id' => null,
            'ubah_merk' => false,
            'merk' => '',
        ];
        $this->showBatchEditModal = true;
    }

    public function simpanBatchEdit(): void
    {
        if (! $this->bolehKatalog(self::PERM_MASTER_PRODUK, 'Anda tidak memiliki izin mengedit master atribut.')) {
            return;
        }

        $sub = $this->masterProdukSubTab;
        $updated = 0;

        switch ($sub) {
            case 'kategori':
                $kats = KategoriProduk::whereIn('id', $this->selectedKategoriIds)->get();
                foreach ($kats as $kat) {
                    $updates = [];
                    if (! empty($this->batchEditForm['ubah_status'])) {
                        $updates['is_active'] = (bool) $this->batchEditForm['status'];
                    }
                    if (! empty($this->batchEditForm['ubah_parent'])) {
                        $parentId = $this->batchEditForm['parent_id'] ? (int) $this->batchEditForm['parent_id'] : null;
                        if ($parentId !== (int) $kat->id) {
                            $updates['parent_id'] = $parentId;
                        }
                    }
                    if (! empty($updates)) {
                        $kat->update($updates);
                        $updated++;
                    }
                }
                $this->selectedKategoriIds = [];
                break;

            case 'brand':
                if (! empty($this->batchEditForm['ubah_status'])) {
                    $updated = Brand::whereIn('id', $this->selectedBrandIds)
                        ->update(['is_active' => (bool) $this->batchEditForm['status']]);
                }
                $this->selectedBrandIds = [];
                break;

            case 'kualitas':
                $listK = KualitasProduk::whereIn('id', $this->selectedKualitasIds)->get();
                foreach ($listK as $k) {
                    $updates = [];
                    if (! empty($this->batchEditForm['ubah_status'])) {
                        $updates['is_active'] = (bool) $this->batchEditForm['status'];
                    }
                    if (! empty($this->batchEditForm['ubah_brand'])) {
                        $updates['brand_id'] = $this->batchEditForm['brand_id'] ? (int) $this->batchEditForm['brand_id'] : null;
                    }
                    if (! empty($updates)) {
                        $k->update($updates);
                        $updated++;
                    }
                }
                $this->selectedKualitasIds = [];
                break;

            case 'satuan':
                if (! empty($this->batchEditForm['ubah_status'])) {
                    $updated = SatuanUnit::whereIn('id', $this->selectedSatuanIds)
                        ->update(['is_active' => (bool) $this->batchEditForm['status']]);
                }
                $this->selectedSatuanIds = [];
                break;

            case 'kondisi':
                $list = $this->getKondisiList();
                foreach ($list as &$item) {
                    if (in_array($item['kode'], $this->selectedKondisiKodes, true)) {
                        if (! empty($this->batchEditForm['ubah_status'])) {
                            $item['is_active'] = (bool) $this->batchEditForm['status'];
                            $updated++;
                        }
                    }
                }
                app(KonfigurasiService::class)->set('master_produk_kondisi_list', array_values($list));
                $this->selectedKondisiKodes = [];
                break;

            case 'tipe_hp':
                $tipes = TipeHp::whereIn('id', $this->selectedTipeHpIds)->get();
                foreach ($tipes as $t) {
                    $updates = [];
                    if (! empty($this->batchEditForm['ubah_status'])) {
                        $updates['is_active'] = (bool) $this->batchEditForm['status'];
                    }
                    if (! empty($this->batchEditForm['ubah_merk']) && ! empty(trim($this->batchEditForm['merk']))) {
                        $merkBaru = trim($this->batchEditForm['merk']);
                        $updates['merk'] = $merkBaru;
                        $updates['nama'] = "{$merkBaru} {$t->model}";
                    }
                    if (! empty($updates)) {
                        $t->update($updates);
                        $updated++;
                    }
                }
                $this->selectedTipeHpIds = [];
                break;
        }

        $this->showBatchEditModal = false;
        $this->dispatch('alert', ['type' => 'success', 'message' => "Batch edit berhasil: {$updated} item diperbarui"]);
    }

    protected function boleh(string $permission, string $pesan = 'Anda tidak memiliki hak akses untuk tindakan ini.'): bool
    {
        if (auth()->user()?->can($permission)) {
            return true;
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
    }

    /**
     * [RBAC] Gerbang master data katalog (kategori, brand, kualitas, satuan,
     * kondisi, tipe HP, jenis servis).
     *
     * Seeder tidak punya permission per-entitas master, jadi memakai fallback
     * yang sudah ada: `pengaturan.manage` (pemilik halaman pengaturan).
     * Super-admin & owner selalu lolos lewat Gate::before di AppServiceProvider
     * sehingga `can()` selalu true untuk mereka.
     *
     * @param  string[]  $permissions
     */
    protected function bolehKatalog(array $permissions, string $pesan): bool
    {
        $user = auth()->user();

        if ($user !== null) {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }
        }

        $this->dispatch('alert', ['type' => 'error', 'message' => $pesan]);

        return false;
    }

    /**
     * [RBAC] Versi senyap dari bolehKatalog() — untuk aksi pembuka modal
     * (form read-ish). Menolak tanpa mengirim alert agar tidak spam toast.
     *
     * @param  string[]  $permissions
     */
    protected function bolehBuka(array $permissions): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function render()
    {
        return view('modules.rbac.livewire.settings-rbac', [
            'roles' => $this->roles,
            'rolesFull' => $this->rolesFull,
            'permissionsList' => $this->permissionsList,
            'groupedPermissions' => $this->groupedPermissions,
            'cabangs' => $this->cabangs,
            'gudangs' => $this->gudangs,
            'raks' => $this->raks,
            'users' => $this->users,
            'jenisServis' => $this->jenisServis,
            'coaList' => $this->coaList,
            // Master Produk & Katalog
            'kategoriList' => KategoriProduk::with('parent')->withCount('produk', 'children')->orderBy('urutan')->orderBy('nama')->get(),
            'kategoriTree' => KategoriProduk::getTree(),
            'brandList' => Brand::withCount('produk')->orderBy('nama')->get(),
            'kualitasList' => KualitasProduk::with('brand')->withCount('produk')->orderByRaw('brand_id IS NULL ASC')->orderBy('nama')->get(),
            'satuanList' => SatuanUnit::orderBy('kode')->get(),
            'kondisiList' => $this->getKondisiList(),
            'tipeHpList' => TipeHp::withCount('produk')->orderBy('merk')->orderBy('model')->get(),
            'merekHpOptions' => TipeHp::where('is_active', true)->distinct()->orderBy('merk')->pluck('merk')->filter()->values()->all(),
            'existingModelsForSelectedMerk' => (! empty($this->tipeHpForm['merk']) && $this->tipeHpForm['merk'] !== '__custom__')
                ? TipeHp::where('is_active', true)
                    ->where('merk', $this->tipeHpForm['merk'])
                    ->distinct()
                    ->orderBy('model')
                    ->pluck('model')
                    ->filter()
                    ->values()
                    ->all()
                : [],
        ])->layout('layouts.backoffice', ['header' => 'Pengaturan & RBAC']);
    }
}
