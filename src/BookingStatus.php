<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking status constants
 */
final class BookingStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CANCELLED = 'cancelled';
    public const STORNO = 'storno';

    /**
     * Get all valid statuses
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::CANCELLED,
            self::STORNO,
        ];
    }

    /**
     * Get statuses that block time slots
     *
     * @return array<string>
     */
    public static function blocking(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
        ];
    }

    /**
     * Check if status is valid
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    /**
     * Get human-readable label for status
     */
    public static function label(string $status): string
    {
        $labels = [
            self::PENDING => __('Čekající', 'call-scheduler'),
            self::CONFIRMED => __('Potvrzené', 'call-scheduler'),
            self::CANCELLED => __('Zrušené', 'call-scheduler'),
            self::STORNO => __('Stornováno', 'call-scheduler'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Get color for status badge
     *
     * Matches CSS variables in assets/css/base/variables.css
     */
    public static function color(string $status): string
    {
        $colors = [
            self::PENDING => '#ea580c',    // Orange - warning state
            self::CONFIRMED => '#0073aa',  // Blue - confirmed/success state
            self::CANCELLED => '#646970',  // Gray - neutral/cancelled state
            self::STORNO => '#7c3aed',     // Purple - refunded/reversed state
        ];

        return $colors[$status] ?? '#646970';
    }
}
