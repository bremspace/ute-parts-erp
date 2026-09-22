<?php

// Konfigurasi Duitku payment gateway (PRD §4.7)
return [
    'merchant_code' => env('DUITKU_MERCHANT_CODE', ''),
    'api_key' => env('DUITKU_API_KEY', ''),       // apikey untuk createTransaction
    'merchant_key' => env('DUITKU_MERCHANT_KEY', ''), // sandbox: 56c1b8aff069e5c2dbf4171854708d31
    'sandbox' => env('DUITKU_SANDBOX', true),

    'sandbox_url' => env('DUITKU_SANDBOX_URL', 'https://sandbox.duitku.com/webapi/api/merchant'),
    'production_url' => env('DUITKU_PRODUCTION_URL', 'https://api.duitku.com/webapi/api/merchant'),

    'expiry_period' => env('DUITKU_EXPIRY_PERIOD', 1440), // menit
];
