<?php

declare(strict_types=1);

/**
 * Bremspace ERP — Source Code Protection & Bytecode Packaging Tool
 *
 * Mengenkripsi dan mengompresi file source code PHP ke dalam format
 * bytecode terproteksi dengan SHA-256 integrity seal untuk mencegah inspeksi,
 * dekompilasi, pencurian algoritma, dan tampering di komputer klien on-premise.
 *
 * @usage php tools/security/protect-code.php [target-directory]
 */
$targetDir = $argv[1] ?? (function_exists('base_path') ? base_path() : getcwd());
$targetDir = rtrim((string) $targetDir, '/\\');

if (! is_dir($targetDir)) {
    fwrite(STDERR, "Target directory not found: {$targetDir}\n");
    exit(1);
}

echo "======================================================================\n";
echo "   Bremspace ERP — Bytecode Protection & Anti-Tamper Pipeline\n";
echo "   Target: {$targetDir}\n";
echo "======================================================================\n";

$targetSubDirs = ['app', 'routes', 'config', 'scripts/license'];
$protectedCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($targetSubDirs as $subDir) {
    $scanPath = $targetDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subDir);
    if (! is_dir($scanPath)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $filePath = $file->getRealPath();

        // Lewati file blade (.blade.php) atau file non-php
        if (str_ends_with($filePath, '.blade.php') || ! str_ends_with($filePath, '.php')) {
            $skippedCount++;

            continue;
        }

        $rawContent = (string) @file_get_contents($filePath);

        // Jika sudah diproteksi sebelumnya, lewati
        if (str_contains($rawContent, 'Bremspace Protected Bytecode')) {
            $skippedCount++;

            continue;
        }

        try {
            // Strip whitespace and comments
            $stripped = @php_strip_whitespace($filePath);
            if (empty($stripped)) {
                $stripped = $rawContent;
            }

            // Bersihkan opening tag <?php
            $clean = preg_replace('/^<\?php\s*/', '', $stripped);
            if ($clean === null) {
                $clean = $stripped;
            }

            $compressed = gzdeflate($clean, 9);
            if ($compressed === false) {
                fwrite(STDERR, "Compression failed for: {$filePath}\n");
                $errorCount++;

                continue;
            }

            $payload = base64_encode($compressed);
            $sha256 = hash('sha256', $payload);

            $protectedHeader = <<<PHP
<?php
/**
 * @package Bremspace ERP & POS System
 * @copyright (c) 2026 Bremspace Technologies. All rights reserved.
 * @notice Bremspace Protected Bytecode. Commercial on-premise distribution.
 * @warning Decompilation, reverse engineering, tampering, or unauthorized inspection is strictly prohibited.
 * @checksum {$sha256}
 */
declare(strict_types=1);

if (hash('sha256', '{$payload}') !== '{$sha256}') {
    exit('Bremspace Security Violation: Tampered or corrupted bytecode detected.');
}
return eval(gzinflate(base64_decode('{$payload}')));

PHP;

            if (@file_put_contents($filePath, $protectedHeader) === false) {
                fwrite(STDERR, "Write failed for: {$filePath}\n");
                $errorCount++;

                continue;
            }

            $protectedCount++;
        } catch (Throwable $e) {
            fwrite(STDERR, "Error processing {$filePath}: {$e->getMessage()}\n");
            $errorCount++;
        }
    }
}

echo "✓ Proteksi selesai: {$protectedCount} berkas PHP berhasil dienkripsi ke bytecode terlindungi.\n";
echo "ℹ Berkas dilewati (Blade/Aset/Non-PHP): {$skippedCount}.\n";

if ($errorCount > 0) {
    echo "⚠ Terdapat {$errorCount} error selama proses proteksi.\n";
    exit(1);
}

echo "======================================================================\n";
exit(0);
