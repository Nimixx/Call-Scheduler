<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Bookings;

use CallScheduler\BookingStatus;
use CallScheduler\Cache;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for bookings data
 *
 * Caching strategy:
 * - Status counts: cached for 5 minutes, invalidated on any booking change
 * - Booking lists: NOT cached (pagination + filters = too many variations)
 * - Single booking: NOT cached (rarely accessed repeatedly)
 */
final class BookingsRepository
{
    private const PER_PAGE = 20;
    private const CACHE_KEY_COUNTS = 'bookings_status_counts';
    private const CACHE_TTL_COUNTS = 5 * MINUTE_IN_SECONDS;

    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
    }

    public function getBookings(
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $page = 1
    ): array {
        global $wpdb;

        $where = $this->buildWhereClause($status, $dateFrom, $dateTo);
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = "SELECT b.*, u.display_name as team_member_name
                FROM {$wpdb->prefix}cs_bookings b
                LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
                {$where}
                ORDER BY b.booking_date DESC, b.booking_time DESC
                LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($sql, self::PER_PAGE, $offset));
    }

    public function countBookings(
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        global $wpdb;

        $where = $this->buildWhereClause($status, $dateFrom, $dateTo);

        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}cs_bookings b {$where}";

        return (int) $wpdb->get_var($sql);
    }

    public function countByStatus(): array
    {
        return $this->cache->remember(
            self::CACHE_KEY_COUNTS,
            function () {
                global $wpdb;

                $results = $wpdb->get_results(
                    "SELECT status, COUNT(*) as count
                     FROM {$wpdb->prefix}cs_bookings
                     GROUP BY status"
                );

                $counts = [
                    'all' => 0,
                    BookingStatus::PENDING => 0,
                    BookingStatus::CONFIRMED => 0,
                    BookingStatus::CANCELLED => 0,
                ];

                foreach ($results as $row) {
                    $counts[$row->status] = (int) $row->count;
                    $counts['all'] += (int) $row->count;
                }

                return $counts;
            },
            self::CACHE_TTL_COUNTS
        );
    }

    public function getBooking(int $id): ?object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, u.display_name as team_member_name
             FROM {$wpdb->prefix}cs_bookings b
             LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
             WHERE b.id = %d",
            $id
        ));
    }

    public function updateStatus(int $id, string $newStatus): bool
    {
        if (!BookingStatus::isValid($newStatus)) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'cs_bookings',
            ['status' => $newStatus],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->invalidateCache();
        }

        return $result !== false;
    }

    public function deleteBooking(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'cs_bookings',
            ['id' => $id],
            ['%d']
        );

        if ($result !== false) {
            $this->invalidateCache();
        }

        return $result !== false;
    }

    public function bulkUpdateStatus(array $ids, string $newStatus): int
    {
        if (empty($ids) || !BookingStatus::isValid($newStatus)) {
            return 0;
        }

        global $wpdb;

        $ids = array_map('absint', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}cs_bookings SET status = %s WHERE id IN ({$placeholders})",
            array_merge([$newStatus], $ids)
        );

        $wpdb->query($sql);
        $affected = $wpdb->rows_affected;

        if ($affected > 0) {
            $this->invalidateCache();
        }

        return $affected;
    }

    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;

        $ids = array_map('absint', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}cs_bookings WHERE id IN ({$placeholders})",
            $ids
        );

        $wpdb->query($sql);
        $affected = $wpdb->rows_affected;

        if ($affected > 0) {
            $this->invalidateCache();
        }

        return $affected;
    }

    public function isPluginInstalled(): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);

        return $wpdb->get_var($query) === $table;
    }

    private function buildWhereClause(
        ?string $status,
        ?string $dateFrom,
        ?string $dateTo
    ): string {
        global $wpdb;

        $conditions = [];

        if ($status !== null && BookingStatus::isValid($status)) {
            $conditions[] = $wpdb->prepare('b.status = %s', $status);
        }

        if ($dateFrom !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $conditions[] = $wpdb->prepare('b.booking_date >= %s', $dateFrom);
        }

        if ($dateTo !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $conditions[] = $wpdb->prepare('b.booking_date <= %s', $dateTo);
        }

        if (empty($conditions)) {
            return '';
        }

        return 'WHERE ' . implode(' AND ', $conditions);
    }

    public function getPerPage(): int
    {
        return self::PER_PAGE;
    }

    /**
     * Invalidate bookings cache
     *
     * Called automatically on create/update/delete operations.
     * Can also be called manually when needed.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY_COUNTS);

        // Notify other repositories that need cache invalidation
        do_action('cs_bookings_cache_invalidated');
    }
}
