<?php

declare(strict_types=1);

namespace CallScheduler\Helpers;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data validation helper
 *
 * Provides shared validation logic for booking statistics and other data structures.
 */
final class DataValidator
{
    /**
     * Validate booking status counts structure
     *
     * Expected structure:
     * - 'all': integer >= 0
     * - 'pending': integer >= 0
     * - 'confirmed': integer >= 0
     * - 'cancelled': integer >= 0
     *
     * Also validates that 'all' equals the sum of individual statuses.
     *
     * @param mixed $counts Data to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidStatusCounts(mixed $counts): bool
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

        // Consistency check: 'all' should equal sum of statuses
        $sum = (int) $counts[BookingStatus::PENDING]
            + (int) $counts[BookingStatus::CONFIRMED]
            + (int) $counts[BookingStatus::CANCELLED];

        if ((int) $counts['all'] !== $sum) {
            return false;
        }

        return true;
    }

    /**
     * Get default/safe booking counts
     *
     * @return array{all: int, pending: int, confirmed: int, cancelled: int}
     */
    public static function getDefaultStatusCounts(): array
    {
        return [
            'all' => 0,
            BookingStatus::PENDING => 0,
            BookingStatus::CONFIRMED => 0,
            BookingStatus::CANCELLED => 0,
        ];
    }
}
