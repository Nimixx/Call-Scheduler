<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin configuration
 *
 * Centralizes all configurable settings with defaults.
 * Can be overridden via wp-config.php constants.
 *
 * Configuration constants (define in wp-config.php):
 * - CS_SLOT_DURATION: Slot duration in minutes (default: 60)
 * - CS_BUFFER_TIME: Buffer between bookings in minutes (default: 0)
 * - CS_MAX_BOOKING_DAYS: Max days in advance for booking (default: 30)
 * - CS_ALLOWED_ORIGINS: Comma-separated CORS origins (default: home_url())
 * - CS_RATE_LIMIT_READ: Read endpoint limit per minute (default: 60)
 * - CS_RATE_LIMIT_WRITE: Write endpoint limit per minute (default: 5)
 * - CS_BOOKING_SECRET: HMAC secret for token verification (optional)
 * - CS_TRUST_PROXY: Trust X-Forwarded-For header (default: false)
 */
final class Config
{
    // =========================================================================
    // CORS Settings
    // =========================================================================

    /**
     * Get allowed CORS origins
     *
     * @return array<string> List of allowed origins
     */
    public static function getAllowedOrigins(): array
    {
        if (!defined('CS_ALLOWED_ORIGINS')) {
            // Default: same site only (fallback for non-WordPress context)
            return function_exists('home_url') ? [home_url()] : [];
        }

        return array_map('trim', explode(',', CS_ALLOWED_ORIGINS));
    }

    /**
     * Check if origin is allowed
     */
    public static function isOriginAllowed(string $origin): bool
    {
        return in_array($origin, self::getAllowedOrigins(), true);
    }

    // =========================================================================
    // Rate Limiting
    // =========================================================================

    /**
     * Get rate limit for read endpoints (per minute)
     */
    public static function getRateLimitRead(): int
    {
        $limit = defined('CS_RATE_LIMIT_READ') ? CS_RATE_LIMIT_READ : 60;
        return max(1, (int) $limit);
    }

    /**
     * Get rate limit for write endpoints (per minute)
     */
    public static function getRateLimitWrite(): int
    {
        $limit = defined('CS_RATE_LIMIT_WRITE') ? CS_RATE_LIMIT_WRITE : 5;
        return max(1, (int) $limit);
    }

    /**
     * Get rate limit window in seconds
     */
    public static function getRateLimitWindow(): int
    {
        return 60; // 1 minute window
    }

    // =========================================================================
    // Security
    // =========================================================================

    /**
     * Get booking secret for token verification (null if not configured)
     */
    public static function getBookingSecret(): ?string
    {
        if (!defined('CS_BOOKING_SECRET') || empty(CS_BOOKING_SECRET)) {
            return null;
        }
        return CS_BOOKING_SECRET;
    }

    /**
     * Check if token verification is enabled
     */
    public static function isTokenVerificationEnabled(): bool
    {
        return self::getBookingSecret() !== null;
    }

    /**
     * Check if proxy headers should be trusted
     */
    public static function shouldTrustProxy(): bool
    {
        $trust = defined('CS_TRUST_PROXY') && CS_TRUST_PROXY;

        // Allow filter override (only when WordPress is loaded)
        if (function_exists('apply_filters')) {
            return apply_filters('cs_trust_proxy', $trust);
        }

        return $trust;
    }

    // =========================================================================
    // Booking Slots
    // =========================================================================

    /**
     * Get slot duration in minutes
     *
     * Default: 60 minutes (1 hour)
     * Override in wp-config.php: define('CS_SLOT_DURATION', 30);
     *
     * Common values:
     * - 15 minutes (very short consultations)
     * - 30 minutes (standard short meetings)
     * - 60 minutes (standard hour meetings)
     * - 90 minutes (extended consultations)
     *
     * @return int Duration in minutes
     */
    public static function getSlotDuration(): int
    {
        $duration = defined('CS_SLOT_DURATION') ? CS_SLOT_DURATION : 60;

        // Validate: must be positive integer
        if (!is_int($duration) || $duration <= 0) {
            return 60;
        }

        // Validate: must divide evenly into 60 minutes for clean hour boundaries
        // Allowed: 15, 30, 60 (divides into hour)
        // Not allowed: 20, 40 (doesn't divide evenly)
        if (60 % $duration !== 0 && $duration !== 90 && $duration !== 120) {
            trigger_error(
                "CS_SLOT_DURATION ($duration) should divide evenly into 60 minutes. Using 60.",
                E_USER_WARNING
            );
            return 60;
        }

        return $duration;
    }

    /**
     * Get buffer time in minutes (time blocked after each booking)
     *
     * Default: 0 minutes (no buffer)
     * Override in wp-config.php: define('CS_BUFFER_TIME', 15);
     *
     * Use cases:
     * - 0 minutes: Back-to-back bookings
     * - 5-10 minutes: Quick preparation/notes
     * - 15 minutes: Travel time between locations
     * - 30 minutes: Extended preparation
     *
     * @return int Buffer time in minutes
     */
    public static function getBufferTime(): int
    {
        $buffer = defined('CS_BUFFER_TIME') ? CS_BUFFER_TIME : 0;

        // Validate: must be non-negative integer
        if (!is_int($buffer) || $buffer < 0) {
            return 0;
        }

        // Validate: buffer shouldn't exceed slot duration (makes no sense)
        $slot_duration = self::getSlotDuration();
        if ($buffer >= $slot_duration) {
            trigger_error(
                "CS_BUFFER_TIME ($buffer) should be less than CS_SLOT_DURATION ($slot_duration). Using 0.",
                E_USER_WARNING
            );
            return 0;
        }

        return $buffer;
    }

    /**
     * Get maximum days in advance for bookings
     *
     * Default: 30 days
     * Override in wp-config.php: define('CS_MAX_BOOKING_DAYS', 60);
     *
     * @return int Days in advance
     */
    public static function getMaxBookingDays(): int
    {
        $days = defined('CS_MAX_BOOKING_DAYS') ? CS_MAX_BOOKING_DAYS : 30;

        if (!is_int($days) || $days <= 0) {
            return 30;
        }

        return $days;
    }

    /**
     * Get slot duration in seconds (for internal calculations)
     *
     * @return int Duration in seconds
     */
    public static function getSlotDurationSeconds(): int
    {
        return self::getSlotDuration() * 60;
    }

    /**
     * Get buffer time in seconds (for internal calculations)
     *
     * @return int Buffer time in seconds
     */
    public static function getBufferTimeSeconds(): int
    {
        return self::getBufferTime() * 60;
    }

    /**
     * Check if time is on valid slot boundary
     *
     * @param string $time Time in HH:MM format
     * @return bool True if time is on slot boundary
     */
    public static function isValidSlotTime(string $time): bool
    {
        $parts = explode(':', $time);
        if (count($parts) !== 2) {
            return false;
        }

        $minutes = (int) $parts[1];
        $slot_duration = self::getSlotDuration();

        // For 60-minute slots: only 00 is valid
        // For 30-minute slots: 00, 30 are valid
        // For 15-minute slots: 00, 15, 30, 45 are valid
        return $minutes % $slot_duration === 0;
    }

    /**
     * Get human-readable slot duration text
     *
     * @return string Example: "30 minutes", "1 hour", "1 hour 30 minutes"
     */
    public static function getSlotDurationText(): string
    {
        $minutes = self::getSlotDuration();

        if ($minutes < 60) {
            return "$minutes minutes";
        }

        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;

        if ($remaining_minutes === 0) {
            return $hours === 1 ? "1 hour" : "$hours hours";
        }

        return $hours === 1
            ? "1 hour $remaining_minutes minutes"
            : "$hours hours $remaining_minutes minutes";
    }

    /**
     * Get configuration summary (for debugging)
     *
     * @return array Configuration values
     */
    public static function getConfigSummary(): array
    {
        return [
            // Booking slots
            'slot_duration_minutes' => self::getSlotDuration(),
            'slot_duration_text' => self::getSlotDurationText(),
            'buffer_time_minutes' => self::getBufferTime(),
            'max_booking_days' => self::getMaxBookingDays(),
            'valid_times_per_hour' => 60 / self::getSlotDuration(),
            // Security
            'rate_limit_read' => self::getRateLimitRead(),
            'rate_limit_write' => self::getRateLimitWrite(),
            'token_verification_enabled' => self::isTokenVerificationEnabled(),
            'trust_proxy' => self::shouldTrustProxy(),
            // CORS
            'allowed_origins' => self::getAllowedOrigins(),
        ];
    }

    /**
     * Get a config value by key (dot notation supported)
     *
     * @param string $key Config key (e.g., 'slot_duration', 'rate_limit.read')
     * @param mixed $default Default value if not found
     * @return mixed Config value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $map = [
            'slot_duration' => fn() => self::getSlotDuration(),
            'buffer_time' => fn() => self::getBufferTime(),
            'max_booking_days' => fn() => self::getMaxBookingDays(),
            'rate_limit.read' => fn() => self::getRateLimitRead(),
            'rate_limit.write' => fn() => self::getRateLimitWrite(),
            'rate_limit.window' => fn() => self::getRateLimitWindow(),
            'allowed_origins' => fn() => self::getAllowedOrigins(),
            'booking_secret' => fn() => self::getBookingSecret(),
            'trust_proxy' => fn() => self::shouldTrustProxy(),
        ];

        if (isset($map[$key])) {
            return $map[$key]();
        }

        return $default;
    }
}

/**
 * Global config helper function
 *
 * @param string|null $key Config key (null returns Config class)
 * @param mixed $default Default value
 * @return mixed
 */
function config(?string $key = null, mixed $default = null): mixed
{
    if ($key === null) {
        return Config::class;
    }

    return Config::get($key, $default);
}
