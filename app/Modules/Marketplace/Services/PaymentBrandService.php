<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Crm\Services\KonfigurasiService;
use App\Support\NomorTeleponIndonesia;
use Illuminate\Support\Facades\Storage;

/** Identitas toko yang ditampilkan pada halaman pembayaran konsumen. */
class PaymentBrandService
{
    public function __construct(
        protected KonfigurasiService $config,
        protected WebKonsumenService $web
    ) {}

    public function info(): array
    {
        $duitkuCfg = $this->config->get('duitku_gateway_config');
        $strukCfg = $this->config->get('struk_config');
        $all = $this->web->getAll();

        $logo = is_array($duitkuCfg) ? ($duitkuCfg['logo'] ?? null) : null;
        if (empty($logo)) {
            $logo = $all['store_logo_url'] ?? null;
        }

        if (! empty($logo) && ! str_starts_with((string) $logo, 'http')) {
            $logo = str_starts_with((string) $logo, '/')
                ? url((string) $logo)
                : Storage::url((string) $logo);
        }

        $wa = NomorTeleponIndonesia::standar($all['wa_number'] ?? null);

        return [
            'nama' => is_array($strukCfg)
                ? ($strukCfg['nama_toko'] ?? config('app.name', 'UTEParts'))
                : config('app.name', 'UTEParts'),
            'logo' => $logo ?: url('/icons/icon.svg'),
            'sandbox' => is_array($duitkuCfg) && ($duitkuCfg['mode'] ?? 'sandbox') === 'sandbox',
            'whatsapp' => $wa !== '' ? 'https://wa.me/'.$wa : null,
        ];
    }
}
