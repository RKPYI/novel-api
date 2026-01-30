<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheHelper
{
    /**
     * Check if the current cache driver supports tags
     */
    public static function supportsTags(): bool
    {
        $driver = config('cache.default');
        $supportedDrivers = ['redis', 'memcached', 'octane'];
        
        return in_array($driver, $supportedDrivers);
    }

    /**
     * Remember cache with tag support fallback
     */
    public static function remember($key, $ttl, $callback, $tags = [])
    {
        if (self::supportsTags() && !empty($tags)) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Flush cache with tag support fallback
     */
    public static function flush($tags = [], $keys = [])
    {
        try {
            if (self::supportsTags() && !empty($tags)) {
                Cache::tags($tags)->flush();
            } else {
                // Fallback: forget specific keys
                foreach ($keys as $key) {
                    Cache::forget($key);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Cache flush failed', [
                'tags' => $tags,
                'keys' => $keys,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Forget a specific cache key
     */
    public static function forget($key)
    {
        try {
            Cache::forget($key);
        } catch (\Exception $e) {
            Log::warning('Cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all novel-related caches
     */
    public static function clearNovelCaches($novelId = null, $novelSlug = null)
    {
        $keys = [
            'novels_popular',
            'novels_latest',
            'novels_recently_updated',
            'novels_recommendations',
            'genres_all'
        ];

        if ($novelSlug) {
            $keys[] = "novel_{$novelSlug}";
        }

        if ($novelId) {
            $keys[] = "chapters_novel_{$novelId}";
        }

        // Also need to clear paginated index caches, but we can't know all possible combinations
        // So we'll just clear the main ones
        
        self::flush(
            ['novels', 'novels-index', 'novels-search', 'novels-popular', 'novels-updated', 'novels-recommendations', 'novels-latest'],
            $keys
        );
    }

    /**
     * Clear chapter-related caches
     */
    public static function clearChapterCaches($novelId, $chapterNumber = null, $novelSlug = null)
    {
        $keys = [
            "chapters_novel_{$novelId}"
        ];

        if ($chapterNumber !== null) {
            $keys[] = "chapter_{$novelId}_{$chapterNumber}";
        }

        if ($novelSlug) {
            $keys[] = "novel_{$novelSlug}";
        }

        self::flush(
            ["chapters_novel_{$novelId}", "novel_{$novelSlug}"],
            $keys
        );
    }
}
