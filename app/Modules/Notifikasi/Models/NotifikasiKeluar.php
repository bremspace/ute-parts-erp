<?php

namespace App\Modules\Notifikasi\Models;

use App\Models\User;
use App\Modules\Crm\Models\KampanyeBroadcast;
use App\Modules\Crm\Models\Pelanggan;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tipe', 'kategori', 'tujuan', 'judul', 'konten', 'payload', 'status', 'error',
    'kampanye_broadcast_id', 'read_at', 'user_id', 'pelanggan_id', 'cabang_id', 'target_role', 'action_url',
])]
class NotifikasiKeluar extends Model
{
    protected $table = 'notifikasi_keluar';

    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
    ];

    public function kampanyeBroadcast(): BelongsTo
    {
        return $this->belongsTo(KampanyeBroadcast::class, 'kampanye_broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'pelanggan_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForPelanggan($query, int $pelangganId)
    {
        return $query->where('pelanggan_id', $pelangganId);
    }

    public function scopeForUserOrCabang($query, ?int $userId = null, ?int $cabangId = null)
    {
        return $query->where(function ($q) use ($userId, $cabangId) {
            $q->where(function ($sub) use ($userId) {
                if ($userId) {
                    $sub->whereNull('user_id')->orWhere('user_id', $userId);
                } else {
                    $sub->whereNull('user_id');
                }
            })->where(function ($sub) use ($cabangId) {
                if ($cabangId) {
                    $sub->whereNull('cabang_id')->orWhere('cabang_id', $cabangId);
                }
            });
        });
    }

    public static function isConsumerZoneUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        foreach (['/account', '/checkout', '/cart', '/shop', '/tracking', '/login-pelanggan', '/daftar-pelanggan'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function safeActionUrlForStaff(?string $url, ?string $kategori = null): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (static::isConsumerZoneUrl($url)) {
            return match ($kategori) {
                'transaksi' => '/app/pos/online-orders',
                'stok' => '/app/wms',
                'servis' => '/app/servis',
                'approval' => '/app/approvals',
                'keuangan' => '/app/akunting',
                default => '/app/dashboard',
            };
        }

        return $url;
    }

    public function scopeForUserRoleAndCategory($query, ?User $user = null, ?int $cabangId = null, array $allowedCategories = [])
    {
        // Backoffice tidak boleh melihat baris yang ditujukan ke pelanggan
        $query->whereNull('pelanggan_id');

        if (! empty($allowedCategories)) {
            $query->whereIn('kategori', $allowedCategories);
        }

        if ($cabangId) {
            $query->where(fn ($q) => $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId));
        }

        if (! $user) {
            return $query;
        }

        if ($user->hasRole('super-admin') || $user->hasRole('owner')) {
            return $query;
        }

        $userRoles = $user->getRoleNames()->toArray();

        return $query->where(function ($q) use ($user, $userRoles) {
            // Ditujukan spesifik ke user ini
            $q->where('user_id', $user->id)
                // Atau broadcast untuk role yang sesuai
                ->orWhere(function ($sub) use ($user, $userRoles) {
                    $sub->whereNull('user_id');
                    if ($user->hasRole('admin-toko')) {
                        // Admin toko menerima operasional toko & role stafnya
                        $sub->where(function ($roleQuery) use ($userRoles) {
                            $roleQuery->whereNull('target_role')
                                ->orWhereIn('target_role', array_merge($userRoles, ['kasir', 'teknisi', 'staff-gudang', 'approval']));
                        });
                    } elseif (! empty($userRoles)) {
                        $sub->where(function ($roleQuery) use ($userRoles) {
                            $roleQuery->whereNull('target_role')
                                ->orWhereIn('target_role', $userRoles);
                        });
                    } else {
                        $sub->whereNull('target_role');
                    }
                });
        });
    }
}
