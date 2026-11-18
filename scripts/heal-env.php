<?php

/**
 * Self-healing utility for Laravel .env configuration.
 * Detects and repairs:
 * 1. Corrupted shell injections (e.g. '; echo', '\;', 'd=\;')
 * 2. Malformed escape sequences in double-quoted strings
 * 3. Dropping unparseable lines that crash phpdotenv with unexpected escape sequence
 */
$dir = $argv[1] ?? getcwd();
$envFile = $dir.'/.env';
$exampleFile = $dir.'/.env.example';

if (! file_exists($envFile)) {
    if (file_exists($exampleFile)) {
        copy($exampleFile, $envFile);
        echo "Created missing .env from .env.example\n";
    } else {
        echo "No .env or .env.example found in {$dir}\n";
        exit(0);
    }
}

$content = file_get_contents($envFile);
$lines = explode("\n", $content);
$sanitized = [];
$modified = false;

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
        $sanitized[] = $line;

        continue;
    }

    // 1. Detect and drop shell injections, command chains, or stray bash artifacts
    if (preg_match('/(?:;|\&|\|)\s*(?:d=|echo|curl|sh|bash|chmod|wget)/i', $line) ||
        preg_match('/\\\\;/', $line) ||
        str_contains($line, 'unas-setup.ps1')) {
        $modified = true;
        echo 'Dropped corrupted/injected .env line: '.substr($trimmed, 0, 50)."...\n";

        continue;
    }

    // 2. Fix invalid dotenv escape sequences in double-quoted values
    // Dotenv only allows: \n, \r, \t, \v, \f, \", \\, \$
    if (preg_match('/^([A-Za-z0-9_]+)=\"(.*)\"$/s', $trimmed, $m)) {
        $key = $m[1];
        $val = $m[2];
        $cleaned = preg_replace('/\\\\([^nrtvf\"\\\\\\$])/', '\\\\\\\\$1', $val);
        if ($cleaned !== $val) {
            $line = "{$key}=\"{$cleaned}\"";
            $modified = true;
            echo "Fixed unescaped backslash in .env key: {$key}\n";
        }
    }

    $sanitized[] = $line;
}

if ($modified) {
    @copy($envFile, $dir.'/.env.bak.'.date('Ymd_His'));
    file_put_contents($envFile, implode("\n", $sanitized));
}

// 3. Verify against Dotenv if autoloader is available
$autoloadPath = $dir.'/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    try {
        Dotenv\Dotenv::createImmutable($dir)->load();
        echo "✓ .env successfully verified by Dotenv\n";
    } catch (Throwable $e) {
        echo '! Dotenv reported syntax error: '.$e->getMessage()."\n";
        echo "  Isolating valid lines via line-by-line verification...\n";
        $safeLines = [];
        $probeFile = $dir.'/.env.probe';
        foreach ($sanitized as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $safeLines[] = $line;

                continue;
            }
            file_put_contents($probeFile, $trimmed."\n");
            try {
                Dotenv\Dotenv::createImmutable($dir, '.env.probe')->load();
                $safeLines[] = $line;
            } catch (Throwable $err) {
                echo '  Dropping invalid line: '.substr($trimmed, 0, 40)."...\n";
            }
            @unlink($probeFile);
        }
        file_put_contents($envFile, implode("\n", $safeLines));
        echo "✓ .env repaired and stabilized.\n";
    }
}
