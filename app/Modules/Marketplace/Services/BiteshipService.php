<?php

namespace App\Modules\Marketplace\Services;

use Illuminate\Support\Facades\Http;

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
        $this->apiKey = (string) env('BITESHIP_API_KEY', '');
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
            ->post($this->baseUrl . '/rates/couriers', $payload);

        if ($response->failed()) {
            throw new \Exception('Biteship: ' . ($response->json('error') ?? 'Gagal mengambil tarif kurir'));
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
     */
    public function createOrder(array $payload): array
    {
        $response = Http::withHeaders($this->withAuth())
            ->timeout(25)
            ->post($this->baseUrl . '/orders', $payload);

        if ($response->failed()) {
            throw new \Exception('Biteship: ' . ($response->json('error') ?? 'Gagal membuat order pengiriman'));
        }

        $data = $response->json();
        return [
            'order_id' => $data['order_id'] ?? null,
            'tracking_id' => $data['tracking_id'] ?? null,
            'waybill_id' => $data['waybill_id'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    /**
     * Tracking status pengiriman.
     */
    public function getTracking(string $trackingId): array
    {
        $response = Http::withHeaders($this->withAuth())
            ->timeout(15)
            ->get($this->baseUrl . '/trackings/' . $trackingId);

        if ($response->failed()) {
            throw new \Exception('Biteship: Gagal mengambil tracking');
        }

        return $response->json();
    }
}