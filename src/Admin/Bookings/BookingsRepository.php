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

    /**
     * Get booking counts grouped by status
     *
     * Returns array with keys: all, pending, confirmed, cancelled
     * Validates cache integrity and regenerates if corrupted.
     *
     * @return array{all: int, pending: int, confirmed: int, cancelled: int}
     */
    public function countByStatus(): array
    {
        // Try to get from cache first
        $cached = $this->cache->get(self::CACHE_KEY_COUNTS);

        // If cache exists and is valid, return it
        if ($cached !== null && $this->isValidCounts($cached)) {
            return $cached;
        }

        // Cache missing or corrupted - log if corrupted
        if ($cached !== null && !$this->isValidCounts($cached)) {
            $this->logCacheCorruption($cached);
            // Delete corrupted cache so we don't use it again
            $this->cache->delete(self::CACHE_KEY_COUNTS);
        }

        // Regenerate counts from database
        $counts = $this->generateCountsByStatus();

        // Validate before caching
        if (!$this->isValidCounts($counts)) {
            $this->logDataIntegrityError($counts);
            // Return safe defaults rather than corrupted data
            return $this->getDefaultCounts();
        }

        // Cache the valid counts
        $this->cache->set(self::CACHE_KEY_COUNTS, $counts, self::CACHE_TTL_COUNTS);

        return $counts;
    }

    /**
     * Generate booking counts from database
     *
     * @return array Counts with keys: all, pending, confirmed, cancelled
     */
    private function generateCountsByStatus(): array
    {
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
    }

    /**
     * Validate counts array structure and values
     *
     * Expected format:
     * - 'all': integer >= 0
     * - 'pending': integer >= 0
     * - 'confirmed': integer >= 0
     * - 'cancelled': integer >= 0
     *
     * @param mixed $counts Data to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidCounts(mixed $counts): bool
    {
        // Must be array
        if (!is_array($counts)) {
            return false;
        }

        $required_keys = ['all', BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::CANCELLED];

        // Check all required keys exist and have valid values
        foreach ($required_keys as $key) {
            if (!isset($counts[$key])) {
                return false;
            }

            // Must be integer or numeric
            if (!is_int($counts[$key]) && !is_numeric($counts[$key])) {
                return false;
            }

            // Must be >= 0 (counts can't be negative)
            if ((int) $counts[$key] < 0) {
                return false;
            }
        }

        // Consistency check: all should equal sum of statuses
        $sum = (int) $counts[BookingStatus::PENDING]
            + (int) $counts[BookingStatus::CONFIRMED]
            + (int) $counts[BookingStatus::CANCELLED];

        if ((int) $counts['all'] !== $sum) {
            return false;
        }

        return true;
    }

    /**
     * Get default/safe counts when data is corrupted
     *
     * @return array Safe defaults with all zeros
     */
    private function getDefaultCounts(): array
    {
        return [
            'all' => 0,
            BookingStatus::PENDING => 0,
            BookingStatus::CONFIRMED => 0,
            BookingStatus::CANCELLED => 0,
        ];
    }

    /**
     * Log warning when cache is corrupted
     *
     * @param mixed $cached Corrupted cached data
     * @return void
     */
    private function logCacheCorruption(mixed $cached): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Call Scheduler BookingsRepository: Cache corruption detected in countByStatus(). Cached data: %s',
                wp_json_encode($cached)
            ));
        }
    }

    /**
     * Log warning when database query returns invalid data
     *
     * @param mixed $counts Invalid counts from database
     * @return void
     */
    private function logDataIntegrityError(mixed $counts): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Call Scheduler BookingsRepository: Data integrity error in generateCountsByStatus(). Data: %s',
                wp_json_encode($counts)
            ));
        }
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
    }
}
