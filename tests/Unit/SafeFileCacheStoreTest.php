<?php

namespace Tests\Unit;

use App\Support\SafeFileCacheStore;
use ErrorException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SafeFileCacheStoreTest extends TestCase
{
    public function test_driver_file_memakai_safe_file_cache_store(): void
    {
        $this->assertInstanceOf(SafeFileCacheStore::class, Cache::store('file')->getStore());
    }

    public function test_direktori_cache_tetap_executable_dan_file_group_writable(): void
    {
        $root = sys_get_temp_dir().'/safe-cache-test-'.uniqid('', true);
        $files = new Filesystem;
        $store = new SafeFileCacheStore($files, $root);

        try {
            $this->assertTrue($store->put('test-key', ['ok' => true], 60));
            $this->assertSame(['ok' => true], $store->get('test-key'));

            $path = $store->path('test-key');
            $dir = dirname($path);
            $parentDir = dirname($dir);

            $this->assertSame(0775, fileperms($dir) & 0777);
            $this->assertSame(0775, fileperms($parentDir) & 0777);
            $this->assertSame(0664, fileperms($path) & 0777);

            $this->assertTrue($store->add('lock-key', 'token-1', 60));
            $this->assertFalse($store->add('lock-key', 'token-2', 60));
            $this->assertSame(0664, fileperms($store->path('lock-key')) & 0777);

            $lock = $store->lock('lock-resource', 10);
            $this->assertTrue($lock->get());
            $lock->release();
            $this->assertSame(01777, fileperms(sys_get_temp_dir()) & 01777);
        } finally {
            $files->deleteDirectory($root);
        }
    }

    public function test_memulihkan_cache_saat_file_lama_tidak_writable(): void
    {
        $root = sys_get_temp_dir().'/safe-cache-recover-'.uniqid('', true);
        $files = new class extends Filesystem
        {
            public bool $failNextWriteOnReadonly = true;

            public function put($path, $contents, $lock = false)
            {
                if ($this->failNextWriteOnReadonly && is_file($path) && ! is_writable($path)) {
                    $this->failNextWriteOnReadonly = false;
                    throw new ErrorException("file_put_contents({$path}): Failed to open stream: Permission denied");
                }

                return parent::put($path, $contents, $lock);
            }
        };
        $store = new SafeFileCacheStore($files, $root);

        try {
            $this->assertTrue($store->put('stale-key', 'old', 60));
            $path = $store->path('stale-key');
            chmod($path, 0444);

            $this->assertTrue($store->put('stale-key', 'fresh', 60));
            $this->assertSame('fresh', $store->get('stale-key'));
            $this->assertSame(0664, fileperms($path) & 0777);
        } finally {
            $files->deleteDirectory($root);
        }
    }
}
