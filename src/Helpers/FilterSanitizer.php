<?php

declare(strict_types=1);

namespace CallScheduler\Helpers;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter sanitization helper
 *
 * Provides consistent query parameter sanitization and validation.
 */
final class FilterSanitizer
{
    /**
     * Sanitize and validate booking status from query parameter
     *
     * @param string $paramName GET parameter name (e.g., 'status', 'dashboard_status')
     * @return string|null Valid status or null
     */
    public static function sanitizeStatus(string $paramName): ?string
    {
        if (!isset($_GET[$paramName])) {
            return null;
        }

        $status = sanitize_text_field($_GET[$paramName]);

        if (!BookingStatus::isValid($status)) {
            return null;
        }

        return $status;
    }

    /**
     * Sanitize and validate date from query parameter
     *
     * @param string $paramName GET parameter name (e.g., 'date_from', 'date_to')
     * @return string|null Valid date in YYYY-MM-DD format or null
     */
    public static function sanitizeDate(string $paramName): ?string
    {
        if (!isset($_GET[$paramName])) {
            return null;
        }

        $date = sanitize_text_field($_GET[$paramName]);

        // Validate YYYY-MM-DD format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date;
    }

    /**
     * Sanitize text field from POST parameter
     *
     * @param string $paramName POST parameter name
     * @return string Sanitized text or empty string
     */
    public static function sanitizePostText(string $paramName): string
    {
        if (!isset($_POST[$paramName])) {
            return '';
        }

        return sanitize_text_field($_POST[$paramName]);
    }

    /**
     * Sanitize and validate status from POST parameter
     *
     * @param string $paramName POST parameter name
     * @return string|null Valid status or null
     */
    public static function sanitizePostStatus(string $paramName): ?string
    {
        $status = self::sanitizePostText($paramName);

        if ($status === '' || !BookingStatus::isValid($status)) {
            return null;
        }

        return $status;
    }

    /**
     * Sanitize integer from POST parameter
     *
     * @param string $paramName POST parameter name
     * @return int Sanitized integer (0 if invalid/missing)
     */
    public static function sanitizePostInt(string $paramName): int
    {
        if (!isset($_POST[$paramName])) {
            return 0;
        }

        return absint($_POST[$paramName]);
    }

    /**
     * Sanitize integer from GET parameter
     *
     * @param string $paramName GET parameter name
     * @return int Sanitized integer (0 if invalid/missing)
     */
    public static function sanitizeGetInt(string $paramName): int
    {
        if (!isset($_GET[$paramName])) {
            return 0;
        }

        return absint($_GET[$paramName]);
    }
}
