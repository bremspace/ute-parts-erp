<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GitDeployWebhookController extends Controller
{
    /**
     * Webhook otomatis untuk deployment saat push ke Git repository (GitHub / GitLab / Gitea).
     *
     * URL: POST /webhook/git-deploy
     * Headers:
     * - X-Hub-Signature-256 (GitHub HMAC SHA256)
     * - atau Header Authorization: Bearer <token>
     * - atau Query / Body param: secret=<token>
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.git_deploy.secret') ?: env('GIT_DEPLOY_SECRET');

        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Git Deploy webhook belum diaktifkan (GIT_DEPLOY_SECRET kosong di .env).',
            ], 403);
        }

        // Verifikasi token via berbagai metode aman
        $authorized = false;

        // 1. Signature GitHub HMAC SHA256 (X-Hub-Signature-256: sha256=...)
        $githubSig = $request->header('X-Hub-Signature-256');
        if ($githubSig && str_starts_with($githubSig, 'sha256=')) {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            if (hash_equals($expected, $githubSig)) {
                $authorized = true;
            }
        }

        // 2. Token header / parameter fallback
        $token = $request->bearerToken()
            ?: $request->header('X-Gitlab-Token')
            ?: $request->input('secret');

        if ($token && hash_equals($secret, $token)) {
            $authorized = true;
        }

        if (! $authorized) {
            Log::warning('Percobaan akses webhook Git Deploy ditolak: kredensial tidak valid.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Secret atau Signature tidak valid.',
            ], 401);
        }

        $branch = env('GIT_DEPLOY_BRANCH', 'main');
        $baseDir = base_path();

        // Jalankan update git & optimize
        Log::info('Git Deploy webhook diterima: Memulai deployment branch '.$branch);

        $cmd = sprintf(
            'cd %s && git pull origin %s 2>&1 && php artisan migrate --force 2>&1 && php artisan optimize 2>&1',
            escapeshellarg($baseDir),
            escapeshellarg($branch)
        );

        $result = Process::path($baseDir)->run($cmd);

        if (! $result->successful()) {
            Log::error('Git Deploy gagal dijalankan:', ['output' => $result->errorOutput() ?: $result->output()]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan perintah deploy: '.($result->errorOutput() ?: $result->output()),
            ], 500);
        }

        Log::info('Git Deploy sukses dijalankan:', ['output' => $result->output()]);

        return response()->json([
            'success' => true,
            'message' => 'Deploy berhasil diproses.',
            'output' => $result->output(),
        ]);
    }
}
