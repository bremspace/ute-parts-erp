<?php

namespace App\Modules\Marketplace\Console\Commands;

use App\Modules\Marketplace\Services\DuitkuService;
use Illuminate\Console\Command;

/**
 * [T-03] Cek kredensial + signature Duitku terhadap API live (sandbox/production).
 *
 * Panggil POST /paymentmethod/getpaymentmethod dgn amount Rp10.000 — bila
 * responseCode '00' berarti merchantCode/apiKey valid dan signature cocok.
 * Pemakaian: php artisan duitku:health
 */
class DuitkuHealthCommand extends Command
{
    protected $signature = 'duitku:health';

    protected $description = 'Cek kredensial & signature Duitku (getpaymentmethod amount 10000) ke sandbox/production';

    public function handle(DuitkuService $duitku): int
    {
        if (! $duitku->isConfigured()) {
            $this->error('Duitku belum dikonfigurasi — set DUITKU_MERCHANT_CODE, DUITKU_API_KEY, DUITKU_MERCHANT_KEY di .env');

            return self::FAILURE;
        }

        $this->info('Mode      : '.($duitku->isSandbox() ? 'SANDBOX' : 'PRODUCTION'));
        $this->info('Signature : '.$duitku->getSignatureMode());

        try {
            $methods = $duitku->getPaymentMethods(10000);
        } catch (\Exception $e) {
            $this->error('GAGAL — kredensial atau signature tidak diterima Duitku: '.$e->getMessage());

            return self::FAILURE;
        }

        if (empty($methods)) {
            $this->warn('OK — signature valid, tetapi tidak ada metode pembayaran aktif di akun Duitku.');

            return self::SUCCESS;
        }

        $this->info('OK — kredensial & signature valid. '.count($methods).' metode aktif:');
        $this->table(
            ['Kode', 'Nama', 'Fee'],
            collect($methods)->map(fn ($m) => [$m['kode'], $m['nama'], $m['fee']])->all()
        );

        return self::SUCCESS;
    }
}
