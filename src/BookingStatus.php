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
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Get color for status badge
     */
    public static function color(string $status): string
    {
        $colors = [
            self::PENDING => '#dba617',
            self::CONFIRMED => '#00a32a',
            self::CANCELLED => '#d63638',
        ];

        return $colors[$status] ?? '#666';
    }
}
