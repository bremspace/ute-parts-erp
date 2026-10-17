<?php

// Konfigurasi Duitku payment gateway (PRD §4.7) — docs.duitku.com (30 Sep 2026)
return [
    'merchant_code' => env('DUITKU_MERCHANT_CODE', ''),
    'api_key' => env('DUITKU_API_KEY', ''),       // apikey utk signature HMAC-SHA256
    'merchant_key' => env('DUITKU_MERCHANT_KEY', ''), // sandbox: 56c1b8aff069e5c2dbf4171854708d31
    'sandbox' => env('DUITKU_SANDBOX', true),

    // Base URL docs resmi: sandbox.duitku.com / passport.duitku.com
    'sandbox_url' => env('DUITKU_SANDBOX_URL', 'https://sandbox.duitku.com/webapi/api/merchant'),
    'production_url' => env('DUITKU_PRODUCTION_URL', 'https://passport.duitku.com/webapi/api/merchant'),

    // Masa berlaku invoice (menit) — dipakai createTransaction + duitku:rekon
    'expiry_period' => env('DUITKU_EXPIRY_PERIOD', 1440), // menit

    // Callback URL publik: Wajib port standar 80/443 (Duitku tolak port custom seperti :8080).
    // Bila null, otomatis memakai url('/webhook/duitku') berbasis APP_URL domain production.
    'callback_url' => env('DUITKU_CALLBACK_URL', null),

    // Mode signature (T-01, transisi MD5 → HMAC-SHA256 docs baru):
    //  - 'hmac' — outbound & verifikasi HMAC saja (tujuan akhir)
    //  - 'md5'  — outbound & verifikasi MD5 lama saja (rollback darurat)
    //  - 'dual' (default) — outbound HMAC; verifikasi callback menerima HMAC ATAU MD5
    'signature_mode' => env('DUITKU_SIGNATURE_MODE', 'dual'),
];
