<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Dashboard;

use CallScheduler\BookingStatus;
use CallScheduler\Cache;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for dashboard stats
 */
final class DashboardRepository
{
    private const CACHE_KEY = 'dashboard_stats';
    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
    }

    /**
     * Get dashboard stats (cached)
     *
     * @return array{total: int, pending: int, confirmed: int, cancelled: int}
     */
    public function getStats(): array
    {
        return $this->cache->remember(
            self::CACHE_KEY,
            function () {
                global $wpdb;

                $results = $wpdb->get_results(
                    "SELECT status, COUNT(*) as count
                     FROM {$wpdb->prefix}cs_bookings
                     GROUP BY status"
                );

                $stats = [
                    'total' => 0,
                    'pending' => 0,
                    'confirmed' => 0,
                    'cancelled' => 0,
                ];

                foreach ($results as $row) {
                    $count = (int) $row->count;
                    $stats['total'] += $count;

                    if ($row->status === BookingStatus::PENDING) {
                        $stats['pending'] = $count;
                    } elseif ($row->status === BookingStatus::CONFIRMED) {
                        $stats['confirmed'] = $count;
                    } elseif ($row->status === BookingStatus::CANCELLED) {
                        $stats['cancelled'] = $count;
                    }
                }

                return $stats;
            },
            self::CACHE_TTL
        );
    }

    public function isPluginInstalled(): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);

        return $wpdb->get_var($query) === $table;
    }

    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
