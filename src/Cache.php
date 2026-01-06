<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache manager using WordPress Transients API
 *
 * Provides a simple interface for caching with automatic scaling:
 * - Uses Redis/Memcached if available on the server
 * - Falls back to database transients if not
 * - No configuration needed - works out of the box
 */
final class Cache
{
    /** Cache key prefix to avoid conflicts */
    private const PREFIX = 'cs_cache_';

    /** Default cache duration: 1 hour */
    private const DEFAULT_TTL = HOUR_IN_SECONDS;

    /**
     * Get cached value
     *
     * @param string $key Cache key (will be prefixed automatically)
     * @return mixed|null Cached value or null if not found/expired
     */
    public function get(string $key): mixed
    {
        $value = get_transient($this->makeKey($key));

        // WordPress returns false for missing/expired transients
        return $value !== false ? $value : null;
    }

    /**
     * Set cached value
     *
     * @param string $key Cache key (will be prefixed automatically)
     * @param mixed $value Value to cache (must be serializable)
     * @param int|null $ttl Time to live in seconds (null = default 1 hour)
     * @return bool True on success, false on failure
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;

        return set_transient($this->makeKey($key), $value, $ttl);
    }

    /**
     * Delete cached value
     *
     * @param string $key Cache key (will be prefixed automatically)
     * @return bool True if deleted, false if not found
     */
    public function delete(string $key): bool
    {
        return delete_transient($this->makeKey($key));
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if key exists and not expired
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Get or set cache value (read-through cache)
     *
     * If key exists, returns cached value.
     * If key doesn't exist, executes callback, caches result, and returns it.
     *
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached or generated value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Flush all plugin caches
     *
     * Deletes all cached values with our prefix.
     * Note: This queries the database for all transients with our prefix.
     *
     * @return int Number of cache keys deleted
     */
    public function flush(): int
    {
        global $wpdb;

        $prefix = $wpdb->esc_like('_transient_' . self::PREFIX) . '%';

        // Get all transient keys with our prefix
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix
        ));

        $deleted = 0;
        foreach ($keys as $key) {
            // Remove '_transient_' prefix to get the actual transient name
            $transient_name = str_replace('_transient_', '', $key);
            if (delete_transient($transient_name)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Delete multiple cache keys matching a pattern
     *
     * @param string $pattern Pattern to match (e.g., 'availability_*')
     * @return int Number of cache keys deleted
     */
    public function deletePattern(string $pattern): int
    {
        global $wpdb;

        $search = $wpdb->esc_like('_transient_' . self::PREFIX . str_replace('*', '', $pattern)) . '%';

        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $search
        ));

        $deleted = 0;
        foreach ($keys as $key) {
            $transient_name = str_replace('_transient_', '', $key);
            if (delete_transient($transient_name)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Generate full cache key with prefix
     *
     * @param string $key User-provided key
     * @return string Full cache key with prefix
     */
    private function makeKey(string $key): string
    {
        return self::PREFIX . $key;
    }

    /**
     * Get cache statistics (for debugging)
     *
     * @return array Statistics about cached items
     */
    public function getStats(): array
    {
        global $wpdb;

        $prefix = $wpdb->esc_like('_transient_' . self::PREFIX) . '%';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            $prefix
        ));

        return [
            'total_keys' => (int) $count,
            'prefix' => self::PREFIX,
            'default_ttl' => self::DEFAULT_TTL,
        ];
    }
}
