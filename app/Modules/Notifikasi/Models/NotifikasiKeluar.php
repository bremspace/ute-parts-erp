<?php

namespace App\Modules\Notifikasi\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipe', 'tujuan', 'judul', 'konten', 'payload', 'status', 'error'
])]
class NotifikasiKeluar extends Model
{
    protected $table = 'notifikasi_keluar';

    protected $casts = [
        'payload' => 'array',
    ];
}