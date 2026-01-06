<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Bookings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility class for date and time formatting
 */
final class DateFormatter
{
    /**
     * Format date for display (e.g., "6. 1. 2026")
     */
    public static function date(string $date): string
    {
        $timestamp = strtotime($date);
        return date_i18n('j. n. Y', $timestamp);
    }

    /**
     * Format time for display (e.g., "09:00")
     */
    public static function time(string $time): string
    {
        return substr($time, 0, 5);
    }

    /**
     * Format datetime from UTC to local timezone (e.g., "6. 1. 2026 11:15")
     */
    public static function dateTimeFromUtc(string $datetime): string
    {
        $timestamp = strtotime($datetime . ' UTC');
        return wp_date('j. n. Y H:i', $timestamp);
    }
}
