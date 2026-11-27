<?php

use App\Modules\Marketplace\Models\WebKonsumenSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Install lama bisa kehilangan setting kontak karena seeder hanya jalan
     * saat storage/.installed belum ada. Isi kembali kunci yang hilang saja,
     * jangan timpa kustomisianCMS yang sudah disimpan pengguna.
     */
    private array $defaults = [
        [
            'group_key' => 'branding',
            'setting_key' => 'store_phone',
            'setting_value' => '62812-9950-5350',
            'value_type' => 'string',
            'deskripsi' => 'No. HP / WhatsApp toko untuk identitas storefront',
        ],
        [
            'group_key' => 'branding',
            'setting_key' => 'store_address',
            'setting_value' => 'Jl. Nasional 3 Cibolang Kaler Sukabumi',
            'value_type' => 'string',
            'deskripsi' => 'Alamat toko untuk identitas storefront',
        ],
        [
            'group_key' => 'contact',
            'setting_key' => 'wa_number',
            'setting_value' => '6281299505350',
            'value_type' => 'string',
            'deskripsi' => 'Nomor WhatsApp resmi Customer Service (sama dengan store_phone)',
        ],
        [
            'group_key' => 'contact',
            'setting_key' => 'wa_default_msg',
            'setting_value' => 'Halo Admin UTEParts, saya ingin konsultasi sparepart...',
            'value_type' => 'string',
            'deskripsi' => 'Template pesan awal WhatsApp; URL halaman asal ditambahkan otomatis',
        ],
        [
            'group_key' => 'contact',
            'setting_key' => 'wa_floating_active',
            'setting_value' => 'true',
            'value_type' => 'boolean',
            'deskripsi' => 'Aktifkan tombol mengapung WhatsApp',
        ],
        [
            'group_key' => 'contact',
            'setting_key' => 'operating_hours',
            'setting_value' => 'Senin - Sabtu: 08.00 - 21.00 WIB',
            'value_type' => 'string',
            'deskripsi' => 'Jam operasional toko',
        ],
    ];

    public function up(): void
    {
        foreach ($this->defaults as $default) {
            WebKonsumenSetting::firstOrCreate(
                ['setting_key' => $default['setting_key']],
                $default
            );
        }
    }

    public function down(): void
    {
        WebKonsumenSetting::whereIn(
            'setting_key',
            array_column($this->defaults, 'setting_key')
        )->delete();
    }
};
