<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Rbac\Models\Cabang;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'is_active', 'theme_preference', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_backup_codes'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_backup_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Role yang wajib mengaktifkan 2FA (G-03 / PRD F1-3).
     * Tanpa setup = TIDAK lock-out (MVP): login tetap jalan + banner/step opsional.
     *
     * @return list<string>
     */
    public static function twoFactorRequiredRoles(): array
    {
        return ['super-admin', 'admin-toko', 'finance'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'theme_preference' => 'string',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_backup_codes' => 'array',
        ];
    }

    public function cabangs()
    {
        return $this->belongsToMany(Cabang::class, 'user_cabang')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    /** [F1-3] 2FA aktif hanya bila secret ada DAN sudah dikonfirmasi. */
    public function hasEnabledTwoFactor(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /** [F1-3] Role ini wajib 2FA (super-admin | admin-toko | finance). */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(self::twoFactorRequiredRoles());
    }

    /**
     * [F1-3] Generate N backup code — plaintext hanya dikembalikan (sekali tampil),
     * disimpan sebagai hash bcrypt di JSON column. Default 8 sesuai PRD.
     *
     * @return list<string> plaintext codes untuk ditampilkan satu kali
     */
    public function generateTwoFactorBackupCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(Str::random(10));
            $plain[] = substr($raw, 0, 5).'-'.substr($raw, 5);
            $hashed[] = Hash::make($raw);
        }

        $this->two_factor_backup_codes = $hashed;
        $this->save();

        return $plain;
    }

    /**
     * [F1-3] Verifikasi + consume-on-use backup code.
     * Input dinormalkan (uppercase, buang non-alnum) agar cocok dgn format XXXXX-XXXXX.
     */
    public function confirmTwoFactorBackupCode(string $code): bool
    {
        $codes = $this->two_factor_backup_codes ?? [];
        if ($codes === []) {
            return false;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        foreach ($codes as $index => $hash) {
            if ($normalized !== '' && Hash::check($normalized, $hash)) {
                unset($codes[$index]);
                $this->two_factor_backup_codes = array_values($codes);
                $this->save();

                return true;
            }
        }

        return false;
    }
}
