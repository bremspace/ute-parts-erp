<?php

namespace App\Modules\Wms\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BulkFotoUploadController extends Controller
{
    /**
     * Upload chunk of image files to session temporary directory.
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'max:20480'], // max 20MB per file
            'paths' => ['nullable', 'array'],
            'paths.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $sessionId = (string) $request->input('session_id');
        $authId = (int) auth()->id();
        $targetDirName = "tmp_foto_{$authId}_{$sessionId}";
        $targetPath = Storage::disk('local')->path($targetDirName);

        if (! is_dir($targetPath)) {
            File::makeDirectory($targetPath, 0775, true, true);
        }

        $files = $request->file('files', []);
        $paths = $request->input('paths', []);
        $uploadedCount = 0;

        foreach ($files as $idx => $uploadedFile) {
            if (! $uploadedFile->isValid()) {
                continue;
            }

            $origName = $uploadedFile->getClientOriginalName();
            $relPath = $paths[$idx] ?? $origName;

            // Bersihkan relative path untuk mencegah directory traversal
            $cleanRel = ltrim(str_replace(['../', '..\\', "\0"], '', (string) $relPath), '/\\');
            if ($cleanRel === '') {
                $cleanRel = $origName;
            }

            // Simpan file dengan nama asli dan struktur subfolder asli jika ada
            $subDir = dirname($cleanRel);
            $destDir = $subDir !== '.' && $subDir !== '' ? $targetPath.'/'.$subDir : $targetPath;
            if (! is_dir($destDir)) {
                File::makeDirectory($destDir, 0775, true, true);
            }

            $destFilename = basename($cleanRel);
            $uploadedFile->move($destDir, $destFilename);
            $uploadedCount++;
        }

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'uploaded_count' => $uploadedCount,
        ]);
    }

    /**
     * Clear and delete session temporary directory.
     */
    public function clearSession(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_\-]+$/'],
        ]);

        $sessionId = (string) $request->input('session_id');
        $authId = (int) auth()->id();
        $targetDirName = "tmp_foto_{$authId}_{$sessionId}";

        if (Storage::disk('local')->exists($targetDirName)) {
            Storage::disk('local')->deleteDirectory($targetDirName);
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
