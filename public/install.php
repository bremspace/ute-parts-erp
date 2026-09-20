<?php
/**
 * Ute Parts — Browser Installer (no SSH required)
 *
 * Arahkan ke: https://domain.com/install.php
 * Cek otomatis: jika .env sudah terisi + key ada → redirect ke /app/login
 */

use Illuminate\Support\Facades\Process;

// --- Boot Laravel minimal untuk akses artisan ---
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$isConfigured = (
    trim(env('APP_KEY', '')) !== '' &&
    trim(env('DB_DATABASE', '')) !== '' &&
    trim(env('DB_USERNAME', '')) !== ''
);

if ($isConfigured) {
    header('Location: /app/login');
    exit;
}

// --- Proses form submission ---
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = trim($_POST['db_password'] ?? '');
    $appName = trim($_POST['app_name'] ?? 'Ute Parts');
    $appUrl = trim($_POST['app_url'] ?? '');

    if (empty($dbName) || empty($dbUser)) {
        $error = 'Database name dan username wajib diisi.';
    } else {
        // 1. Test koneksi
        try {
            $pdo = new PDO(
                "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}",
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('SELECT 1');
        } catch (PDOException $e) {
            $error = 'Koneksi database gagal: ' . $e->getMessage();
        }

        if (!$error) {
            // 2. Tulis .env
            $envPath = __DIR__ . '/../.env';
            $envContent = <<<EOF
APP_NAME={$appName}
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL={$appUrl}
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
BCRYPT_ROUNDS=10
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_DATABASE={$dbName}
DB_USERNAME={$dbUser}
DB_PASSWORD={$dbPass}
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
CACHE_PREFIX=ute
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=auto
SANCTUM_STATEFUL_DOMAINS=auto
MAIL_MAILER=log
DUITKU_SANDBOX=true
DUITKU_MERCHANT_CODE=
DUITKU_API_KEY=
DUITKU_MERCHANT_KEY=
BITESHIP_API_KEY=
VITE_APP_NAME={$appName}
EOF;
            file_put_contents($envPath, $envContent);

            // 3. Generate APP_KEY
            $keyProc = Process::run([
                $this->getPhpBinary(), 'artisan', 'key:generate', '--force',
            ], cwd: __DIR__ . '/..');

            if (!$keyProc->successful()) {
                $error = 'Gagal generate APP_KEY: ' . $keyProc->errorOutput();
            } else {
                // 4. Migrate
                $migProc = Process::run([
                    $this->getPhpBinary(), 'artisan', 'migrate', '--force',
                ], cwd: __DIR__ . '/..');

                if (!$migProc->successful()) {
                    $error = 'Migrate gagal: ' . $migProc->errorOutput();
                } else {
                    // 5. Seed
                    Process::run([$this->getPhpBinary(), 'artisan', 'db:seed', '--force'], cwd: __DIR__ . '/..');

                    // 6. Cache
                    Process::run([$this->getPhpBinary(), 'artisan', 'config:cache'], cwd: __DIR__ . '/..');
                    Process::run([$this->getPhpBinary(), 'artisan', 'route:cache'], cwd: __DIR__ . '/..');
                    Process::run([$this->getPhpBinary(), 'artisan', 'view:cache'], cwd: __DIR__ . '/..');
                    Process::run([$this->getPhpBinary(), 'artisan', 'storage:link'], cwd: __DIR__ . '/..');

                    $success = 'Setup berhasil! Redirect ke login dalam 3 detik...';
                    header('Refresh:3;url=/app/login');
                }
            }
        }
    }
}

function getPhpBinary(): string
{
    // Deteksi PHP binary di CyberPanel
    $candidates = [
        '/usr/local/lsws/lsphp85/bin/php',
        '/usr/local/lsws/lsphp84/bin/php',
        '/usr/local/lsws/lsphp83/bin/php',
        '/usr/bin/php',
        'php',
    ];
    foreach ($candidates as $php) {
        if (is_executable($php) || $php === 'php') {
            return $php;
        }
    }
    return 'php';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalasi Ute Parts ERP</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#f0f4ff 0%,#e0e7ff 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem}
        .card{background:#fff;border-radius:1.25rem;box-shadow:0 25px 50px -12px rgba(0,0,0,.1);width:100%;max-width:540px;padding:2.5rem}
        h1{font-size:1.5rem;font-weight:700;color:#1e293b;margin-bottom:.25rem}
        p.sub{font-size:.8rem;color:#64748b;margin-bottom:1.5rem}
        label{display:block;font-size:.75rem;font-weight:600;color:#475569;margin-bottom:.375rem}
        input[type=text],input[type=password],input[type=number]{width:100%;padding:.625rem .75rem;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.875rem;transition:border-color .15s}
        input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,.1)}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
        button{width:100%;padding:.75rem;border-radius:.5rem;font-weight:700;font-size:.875rem;cursor:pointer;border:none;transition:opacity .15s}
        button.primary{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff}
        button.primary:hover{opacity:.9}
        .msg{padding:.75rem 1rem;border-radius:.5rem;font-size:.8rem;margin-bottom:1rem}
        .msg.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
        .msg.success{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
        .note{background:#eff6ff;border-radius:.5rem;padding:.75rem 1rem;font-size:.7rem;color:#1e40af;line-height:1.4}
    </style>
</head>
<body>
    <div class="card">
        <div style="text-align:center;margin-bottom:1rem">
            <div style="width:3.5rem;height:3.5rem;border-radius:.75rem;background:linear-gradient(135deg,#6366f1,#ec4899);display:inline-flex;align-items:center;justify-content:center;margin-bottom:.75rem">
                <svg width="24" height="24" fill="white" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
        </div>

        <h1 style="text-align:center">Instalasi Ute Parts ERP</h1>
        <p class="sub" style="text-align:center">Buat database dulu di CyberPanel → isi data di bawah → Install</p>

        <?php if ($success): ?>
            <div class="msg success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="msg error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$isConfigured): ?>
        <div class="note">
            <strong>Persiapan sebelumnya:</strong> Buka CyberPanel → Websites → <b>YourDomain</b> → <b>Manage Database</b> → Create Database. Catat nama database, username, dan password-nya. Lalu isi di bawah.
        </div>
        <form method="POST" style="margin-top:1.25rem">
            <div class="row">
                <div>
                    <label>DB Host</label>
                    <input type="text" name="db_host" value="127.0.0.1" required>
                </div>
                <div>
                    <label>DB Port</label>
                    <input type="text" name="db_port" value="3306" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>DB Database *</label>
                    <input type="text" name="db_name" placeholder="nama_database" required>
                </div>
                <div>
                    <label>DB Username *</label>
                    <input type="text" name="db_user" placeholder="username_database" required>
                </div>
            </div>
            <div>
                <label>DB Password</label>
                <input type="password" name="db_password" placeholder="password_database">
            </div>
            <div>
                <label>Nama Aplikasi</label>
                <input type="text" name="app_name" value="Ute Parts" required>
            </div>
            <div>
                <label>APP_URL (https://domainanda.com)</label>
                <input type="text" name="app_url" placeholder="https://domainanda.com" required>
            </div>
            <button type="submit" class="primary" style="margin-top:1rem">
                ⚡ Install & Jalankan
            </button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>