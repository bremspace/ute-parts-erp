<?php

namespace App\Modules\Sync\Services;

class SyncExecutionGuard
{
    protected static bool $suppressed = false;

    /**
     * Check if sync event dispatch is currently suppressed.
     */
    public static function isSuppressed(): bool
    {
        return self::$suppressed;
    }

    /**
     * Suppress sync event dispatch manually.
     */
    public static function suppress(): void
    {
        self::$suppressed = true;
    }

    /**
     * Unsuppress sync event dispatch manually.
     */
    public static function unsuppress(): void
    {
        self::$suppressed = false;
    }

    /**
     * Run a given callback with sync observers temporarily suppressed (Anti-Echo Loop).
     */
    public static function runWithoutSync(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }
}
