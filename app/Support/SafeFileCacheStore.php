<?php

namespace App\Support;

use Illuminate\Cache\FileStore;
use Throwable;

/**
 * FileStore yang aman dipakai multi-UID (www-data FPM/worker + root CLI/artisan).
 * Mencegah direktori `0755` dan file `0644` milik root meracuni web server www-data.
 */
class SafeFileCacheStore extends FileStore
{
    /** @var array{0: int|null, 1: int|null}|null */
    private ?array $resolvedWebOwner = null;

    public function put($key, $value, $seconds)
    {
        try {
            return parent::put($key, $value, $seconds);
        } catch (Throwable $e) {
            $path = $this->path($key);
            if ($this->removeUnwritableNode($path)) {
                return parent::put($key, $value, $seconds);
            }

            throw $e;
        }
    }

    public function add($key, $value, $seconds)
    {
        $path = $this->path($key);
        $this->removeUnwritableNode($path);

        try {
            return parent::add($key, $value, $seconds);
        } catch (Throwable $e) {
            if ($this->removeUnwritableNode($path)) {
                return parent::add($key, $value, $seconds);
            }

            throw $e;
        } finally {
            if ($this->isRunningAsRoot() && is_file($path)) {
                $this->ensurePermissionsAreCorrect($path);
            }
        }
    }

    protected function ensureCacheDirectoryExists($path)
    {
        parent::ensureCacheDirectoryExists($path);

        if ($this->isRunningAsRoot()) {
            $directory = dirname($path);
            foreach ([$directory, dirname($directory)] as $dir) {
                if ($this->isInsideStoreDirectory($dir) && is_dir($dir) && @fileowner($dir) === 0) {
                    $this->ensurePermissionsAreCorrect($dir);
                }
            }
        }
    }

    protected function ensurePermissionsAreCorrect($path)
    {
        if (! $this->isInsideStoreDirectory($path)) {
            return;
        }

        if ($this->isRunningAsRoot()) {
            [$uid, $gid] = $this->targetWebOwner();
            if ($uid !== null && @fileowner($path) !== $uid) {
                @chown($path, $uid);
            }
            if ($gid !== null && @filegroup($path) !== $gid) {
                @chgrp($path, $gid);
            }
        }

        @chmod($path, is_dir($path) ? 0775 : 0664);
    }

    private function isInsideStoreDirectory(string $path): bool
    {
        $base = rtrim($this->directory, '/\\');

        return $path === $base || str_starts_with($path, $base.DIRECTORY_SEPARATOR);
    }

    private function removeUnwritableNode(string $path): bool
    {
        if (! is_file($path) || is_writable($path) || ! is_writable(dirname($path))) {
            return false;
        }

        return @unlink($path);
    }

    private function isRunningAsRoot(): bool
    {
        return function_exists('posix_geteuid') && posix_geteuid() === 0;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function targetWebOwner(): array
    {
        if ($this->resolvedWebOwner !== null) {
            return $this->resolvedWebOwner;
        }

        foreach ([$this->directory, dirname($this->directory)] as $probe) {
            if (is_dir($probe)) {
                $uid = @fileowner($probe);
                $gid = @filegroup($probe);
                if (is_int($uid) && $uid > 0) {
                    return $this->resolvedWebOwner = [$uid, is_int($gid) ? $gid : $uid];
                }
            }
        }

        if (function_exists('posix_getpwnam')) {
            $pw = @posix_getpwnam('www-data');
            if (is_array($pw) && $pw['uid'] > 0) {
                return $this->resolvedWebOwner = [$pw['uid'], $pw['gid']];
            }
        }

        return $this->resolvedWebOwner = [null, null];
    }
}
