<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Akunting\Models\AkunCOA;
use App\Modules\Akunting\Models\JurnalAkuntansi;
use App\Modules\Akunting\Services\JurnalService;
use App\Modules\Crm\Services\KonfigurasiService;
use App\Modules\Marketplace\Models\Pengiriman;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Biteship Shipping (PRD §4.8).
 * - POST rates/couriers: multi-origin (pilih cabang/gudang terdekat dengan stok)
 * - Order API: buat pengiriman setelah pembayaran lunas, simpan tracking_id
 */
class BiteshipService
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $cfg = app(KonfigurasiService::class)->get('biteship_gateway_config');
        $this->apiKey = (is_array($cfg) && filled($cfg['api_key'] ?? null))
            ? (string) $cfg['api_key']
            : (string) env('BITESHIP_API_KEY', '');
        $this->baseUrl = env('BITESHIP_BASE_URL', 'https://api.biteship.com/v1');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    private function withAuth(): array
    {
        return [
            'Authorization' => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Cek tarif kurir dari satu origin ke destination.
     * Origin: collection [lat,long] pilih cabang/gudang terdekat dengan stok tersedia.
     */
    public function getRates(
        array $origins,
        string $destinationPostal,
        array $items = [],
        string $courier = 'jne'
    ): array {
        $payload = [
            'origin_coordinates' => $origins,       // [[lat, lon], ...]
            'destination_postal_code' => $destinationPostal,
            'couriers' => $courier,
            'items' => array_values($items),
        ];

        $response = Http::withHeaders($this->withAuth())
            ->timeout(25)
            ->post($this->baseUrl.'/rates/couriers', $payload);

        if ($response->failed()) {
            throw new \Exception('Biteship: '.($response->json('error') ?? 'Gagal mengambil tarif kurir'));
        }

        return collect($response->json('pricing') ?? [])->map(fn ($r) => [
            'kurir' => $r['courier_name'] ?? null,
            'layanan' => $r['courier_service_name'] ?? null,
            'deskripsi' => $r['description'] ?? null,
            'durasi' => $r['duration'] ?? null,
            'harga' => (float) ($r['price'] ?? 0),
        ])->values()->toArray();
    }

    /**
     * Buat order pengiriman (setelah pembayaran lunas).
     *
     * [H7] Bila $pengiriman diberikan, kurir ditagih saat order Biteship
     * berhasil dibuat → titipan ongkir 210-04 dilepas (Dr 210-04 / Cr 110-02)
     * via catatPembayaranOngkir (idempoten per pengiriman). Kegagalan jurnal
     * TIDAK membatalkan order kurir yang sudah terlanjur dibuat di Biteship —
     * dicatat Log::error dan bisa diposting ulang manual via
     * catatPembayaranOngkir (retry aman).
     */
    public function createOrder(array $payload, ?Pengiriman $pengiriman = null): array
    {
        $response = Http::withHeaders($this->withAuth())
            ->timeout(25)
            ->post($this->baseUrl.'/orders', $payload);

        if ($response->failed()) {
            throw new \Exception('Biteship: '.($response->json('error') ?? 'Gagal membuat order pengiriman'));
        }

        $data = $response->json();

        if ($pengiriman !== null) {
            try {
                $this->catatPembayaranOngkir($pengiriman);
            } catch (\Throwable $e) {
                Log::error('[Biteship] Order kurir berhasil dibuat tapi jurnal pelepasan ongkir 210-04 gagal — posting ulang via catatPembayaranOngkir', [
                    'pengiriman_id' => $pengiriman->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'order_id' => $data['order_id'] ?? null,
            'tracking_id' => $data['tracking_id'] ?? null,
            'waybill_id' => $data['waybill_id'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    /**
     * [H7] Lepas titipan ongkir (210-04) saat kurir ditagih/dibayar.
     * Jurnal: Dr 210-04 Titipan Ongkir / Cr 110-02 Bank sebesar ongkir.
     * Idempoten per pengiriman: pre-check jurnal debit 210-04 ber-referensi
     * pengiriman ini + idempotencyKey `ongkir:{id}` (atomic guard race).
     * Fail-closed: kegagalan jurnal (periode tutup/akun nonaktif) dilempar
     * ke pemanggil — tidak ada pelepasan liabilitas tanpa jurnal.
     *
     * @param  float|null  $nominal  override nominal (default: ongkir pengiriman)
     * @return bool true bila jurnal diposting; false bila sudah pernah / ongkir 0
     */
    public function catatPembayaranOngkir(Pengiriman $pengiriman, ?float $nominal = null): bool
    {
        $nominal = round($nominal ?? (float) $pengiriman->ongkir, 2);
        if ($nominal <= 0) {
            return false;
        }

        $sudah = JurnalAkuntansi::where('referensi_tipe', Pengiriman::class)
            ->where('referensi_id', $pengiriman->id)
            ->whereHas('akun', fn ($q) => $q->where('kode', '210-04'))
            ->where('debit', '>', 0)
            ->exists();
        if ($sudah) {
            return false;
        }

        $cabangId = $pengiriman->transaksi?->cabang_id ?: (currentCabangId() ?? Cabang::value('id'));

        foreach ([
            ['kode' => '210-04', 'nama' => 'Titipan Ongkir', 'tipe' => 'kewajiban', 'kelompok' => 'utang_lain', 'saldo_normal' => 'kredit'],
            ['kode' => '110-02', 'nama' => 'Bank', 'tipe' => 'aset', 'kelompok' => 'kas', 'saldo_normal' => 'debit'],
        ] as $akun) {
            AkunCOA::firstOrCreate(['kode' => $akun['kode']], $akun + ['is_active' => true]);
        }

        $jurnalService = app(JurnalService::class);
        $noJurnal = $jurnalService->generateNoJurnal('pembayaran', $cabangId);
        try {
            $jurnalService->post(
                $noJurnal,
                now(),
                'pembayaran',
                [
                    ['akun_kode' => '210-04', 'debit' => $nominal, 'kredit' => 0],
                    ['akun_kode' => '110-02', 'debit' => 0, 'kredit' => $nominal],
                ],
                "Pembayaran ongkir kurir {$pengiriman->kurir} pengiriman #{$pengiriman->id}",
                $cabangId,
                null,
                Pengiriman::class,
                $pengiriman->id,
                'ongkir:'.$pengiriman->id
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'idempotency guard')) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Tracking status pengiriman.
     */
    public function getTracking(string $trackingId): array
    {
        $response = Http::withHeaders($this->withAuth())
            ->timeout(15)
            ->get($this->baseUrl.'/trackings/'.$trackingId);

        if ($response->failed()) {
            throw new \Exception('Biteship: Gagal mengambil tracking');
        }

        return $response->json();
    }
}
