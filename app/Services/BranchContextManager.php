<?php

namespace App\Services;

use App\Modules\Rbac\Models\Cabang;
use Illuminate\Support\Facades\Auth;

/**
 * BranchContextManager
 *
 * Mengelola context cabang aktif di runtime PHP process.
 * Berfungsi di HTTP request (sinkron dengan session) dan
 * di CLI / Queue context (dimana session HTTP tidak tersedia).
 */
class BranchContextManager
{
    protected ?int $activeCabangId = null;

    protected bool $bypassed = false;

    public function setActiveCabangId(?int $cabangId): self
    {
        $this->activeCabangId = $cabangId !== null ? (int) $cabangId : null;

        return $this;
    }

    public function getActiveCabangId(): ?int
    {
        if ($this->bypassed) {
            return null;
        }

        if ($this->activeCabangId !== null) {
            return $this->activeCabangId;
        }

        // Cek HTTP session jika runtime dalam web request dan memiliki session
        try {
            if (function_exists('session') && session()->isStarted() && session()->has('cabang_id')) {
                $sessId = session('cabang_id');
                if ($sessId !== null && is_numeric($sessId)) {
                    return (int) $sessId;
                }
            }
        } catch (\Throwable) {
            // Session mungkin belum siap atau di CLI
        }

        // Cek authenticated user cabang default bila ada auth context
        if (Auth::check()) {
            $user = Auth::user();
            if ($user && method_exists($user, 'cabangDefault')) {
                $cabang = $user->cabangDefault();
                if ($cabang) {
                    return (int) $cabang->id;
                }
            }

            if ($user && method_exists($user, 'cabangs')) {
                $firstCabangId = $user->cabangs()->value('cabang.id');
                if ($firstCabangId) {
                    return (int) $firstCabangId;
                }
            }
        }

        return null;
    }

    public function getActiveCabang(): ?Cabang
    {
        $id = $this->getActiveCabangId();

        return $id ? Cabang::find($id) : null;
    }

    /**
     * Jalankan callback dalam context cabang tertentu dan kembalikan state semula sesudahnya.
     * Sangat berguna di Job queue antrean atau CLI command multi-cabang.
     */
    public function runInBranchContext(int $cabangId, callable $callback): mixed
    {
        $previous = $this->activeCabangId;
        $prevBypass = $this->bypassed;

        $this->activeCabangId = $cabangId;
        $this->bypassed = false;

        try {
            return $callback();
        } finally {
            $this->activeCabangId = $previous;
            $this->bypassed = $prevBypass;
        }
    }

    /**
     * Jalankan callback tanpa scope cabang (bypass all branch scopes).
     */
    public function withoutScope(callable $callback): mixed
    {
        $prevBypass = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $prevBypass;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    public function clear(): void
    {
        $this->activeCabangId = null;
        $this->bypassed = false;
    }
}
