<?php

declare(strict_types=1);

/*
 * DIPASANG OLEH INSTALLER docker-client (disalin ke /usr/local/bin di image).
 *
 * Membuat TEPAT SATU akun admin lokal dari env ADMIN_EMAIL / ADMIN_PASSWORD.
 * BUKAN seeder: tidak membuat role, permission, cabang, produk, atau data
 * master. Data kanonik ditarik dari cloud via `php artisan sync:bootstrap`.
 *
 * Dipanggil oleh entrypoint.sh setiap container start (idempoten).
 * Role/cabang hanya DIPASANG bila sudah tersedia di DB lokal (biasanya
 * setelah bootstrap data dari cloud).
 */

use App\Models\User;
use App\Modules\Rbac\Models\Cabang;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

require '/var/www/html/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * @param  string  $message
 */
function client_admin_log(string $message): void
{
    echo '[client-admin] '.$message.PHP_EOL;
}

$email = strtolower(trim((string) (getenv('ADMIN_EMAIL') ?: '')));
$password = (string) (getenv('ADMIN_PASSWORD') ?: '');
$name = trim((string) (getenv('ADMIN_NAME') ?: '')) ?: 'Admin Client';
$branchCode = trim((string) (getenv('ADMIN_BRANCH_CODE') ?: ''));
$force = filter_var(getenv('ADMIN_FORCE') ?: false, FILTER_VALIDATE_BOOLEAN);

if ($email === '' || $password === '') {
    client_admin_log('[PERINGATAN] ADMIN_EMAIL / ADMIN_PASSWORD kosong. Admin lokal tidak dibuat.');
    exit(0);
}

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    client_admin_log('[ERROR] Format ADMIN_EMAIL tidak valid: '.$email);
    exit(1);
}

try {
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        client_admin_log('Admin lokal dibuat: '.$email);
    } else {
        client_admin_log('Admin lokal sudah ada: '.$email.' (password tidak diubah).');

        if ($force) {
            $user->forceFill([
                'password' => Hash::make($password),
                'is_active' => true,
            ])->save();
            client_admin_log('Password diselaraskan dengan ADMIN_PASSWORD.');
        }
    }

    // Role berasal dari data kanonik cloud. Sebelum sync:bootstrap role ini
    // memang belum ada — itu normal dan TIDAK dijadikan alasan untuk seeder.
    try {
        $role = Role::where('name', 'super-admin')
            ->where('guard_name', 'web')
            ->first();

        if ($role === null) {
            client_admin_log('Role super-admin belum ada. Jalankan sync:bootstrap dari cloud, lalu restart container.');
        } elseif (! $user->hasRole($role)) {
            $user->assignRole($role);
            client_admin_log('Role super-admin diberikan.');
        }
    } catch (Throwable $e) {
        client_admin_log('[WARN] Gagal memberi role: '.$e->getMessage());
    }

    try {
        $hasPivot = DB::table('user_cabang')->where('user_id', $user->id)->exists();

        if ($branchCode !== '') {
            $cabang = Cabang::where('kode', $branchCode)->first();
            if ($cabang === null) {
                client_admin_log("[WARN] Cabang '{$branchCode}' belum ada. Setelah sync:bootstrap, jalankan ulang dengan ADMIN_FORCE=true.");
            }
        } elseif ($hasPivot) {
            $cabang = null; // Sudah punya cabang; jangan timpa pilihan pengguna.
        } else {
            $cabang = Cabang::where('is_active', true)->orderBy('id')->first();
            if ($cabang === null) {
                client_admin_log('[WARN] Belum ada cabang di DB lokal (hadir setelah sync:bootstrap).');
            }
        }

        if (isset($cabang) && $cabang !== null) {
            DB::table('user_cabang')
                ->where('user_id', $user->id)
                ->update(['is_default' => false]);

            DB::table('user_cabang')->updateOrInsert(
                ['user_id' => $user->id, 'cabang_id' => $cabang->id],
                [
                    'is_default' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            client_admin_log('Cabang default: '.$cabang->kode.' - '.$cabang->nama);
        }
    } catch (Throwable $e) {
        client_admin_log('[WARN] Belum bisa mengatur cabang: '.$e->getMessage());
    }

    client_admin_log('Selesai. Data awal: php artisan sync:bootstrap');
} catch (Throwable $e) {
    client_admin_log('[ERROR] '.$e->getMessage());
    exit(1);
}
