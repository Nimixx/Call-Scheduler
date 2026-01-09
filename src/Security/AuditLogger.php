<?php

declare(strict_types=1);

namespace CallScheduler\Security;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security audit logger
 *
 * Logs security events without storing sensitive data.
 * All identifiable information is hashed or anonymized.
 */
final class AuditLogger
{
    private const LOG_FILE = 'cs-security.log';
    private const MAX_LOG_SIZE = 5 * 1024 * 1024; // 5MB

    /**
     * Log a security event
     *
     * @param string $event Event type (e.g., 'rate_limit', 'invalid_token')
     * @param array $context Additional context (will be sanitized)
     */
    public static function log(string $event, array $context = []): void
    {
        if (!self::isLoggingEnabled()) {
            return;
        }

        $entry = [
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'event' => self::sanitizeEventName($event),
            'request_id' => self::getRequestId(),
            'context' => self::sanitizeContext($context),
        ];

        self::writeLog($entry);

        // Fire action for external integrations (SIEM, etc.)
        do_action('cs_security_event', $event, $entry);
    }

    /**
     * Log rate limit hit
     */
    public static function rateLimitHit(string $endpoint, int $limit): void
    {
        self::log('rate_limit_exceeded', [
            'endpoint' => $endpoint,
            'limit' => $limit,
            'ip_hash' => self::hashIp(),
        ]);
    }

    /**
     * Log invalid token attempt
     */
    public static function invalidToken(string $reason): void
    {
        self::log('invalid_token', [
            'reason' => $reason,
            'ip_hash' => self::hashIp(),
        ]);
    }

    /**
     * Log booking attempt
     */
    public static function bookingAttempt(string $status, array $details = []): void
    {
        self::log('booking_' . $status, array_merge([
            'ip_hash' => self::hashIp(),
        ], $details));
    }

    /**
     * Log honeypot triggered (bot detected)
     */
    public static function honeypotTriggered(): void
    {
        self::log('honeypot_triggered', [
            'ip_hash' => self::hashIp(),
            'user_agent_hash' => self::hashUserAgent(),
        ]);
    }

    /**
     * Log invalid input attempt
     */
    public static function invalidInput(string $field, string $reason): void
    {
        self::log('invalid_input', [
            'field' => $field,
            'reason' => $reason,
            'ip_hash' => self::hashIp(),
        ]);
    }

    /**
     * Log CORS rejection
     */
    public static function corsRejected(string $origin): void
    {
        // Hash the origin domain, keep only TLD structure
        $parsed = parse_url($origin);
        $domain = $parsed['host'] ?? 'unknown';

        self::log('cors_rejected', [
            'origin_hash' => self::hash($domain),
            'ip_hash' => self::hashIp(),
        ]);
    }

    /**
     * Get recent security events for admin display
     *
     * @param int $limit Number of events to return
     * @return array Recent events
     */
    public static function getRecentEvents(int $limit = 50): array
    {
        $logFile = self::getLogPath();
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $lines = array_slice($lines, -$limit);
        $events = [];

        foreach (array_reverse($lines) as $line) {
            $event = json_decode($line, true);
            if ($event) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Get event statistics for dashboard
     *
     * @param int $hours Hours to look back
     * @return array Event counts by type
     */
    public static function getStats(int $hours = 24): array
    {
        $events = self::getRecentEvents(1000);
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($hours * 3600));

        $stats = [
            'rate_limit_exceeded' => 0,
            'invalid_token' => 0,
            'honeypot_triggered' => 0,
            'invalid_input' => 0,
            'cors_rejected' => 0,
            'booking_success' => 0,
            'booking_failed' => 0,
        ];

        foreach ($events as $event) {
            if ($event['timestamp'] < $cutoff) {
                continue;
            }

            $type = $event['event'] ?? '';
            if (isset($stats[$type])) {
                $stats[$type]++;
            } elseif (str_starts_with($type, 'booking_')) {
                $stats['booking_' . (str_contains($type, 'success') ? 'success' : 'failed')]++;
            }
        }

        return $stats;
    }

    /**
     * Clear old log entries
     */
    public static function rotateLogs(): void
    {
        $logFile = self::getLogPath();
        if (!file_exists($logFile)) {
            return;
        }

        $size = filesize($logFile);
        if ($size === false || $size < self::MAX_LOG_SIZE) {
            return;
        }

        // Keep last 1000 lines
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        $lines = array_slice($lines, -1000);
        file_put_contents($logFile, implode("\n", $lines) . "\n");
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private static function isLoggingEnabled(): bool
    {
        // Disable in tests
        if (defined('WP_TESTS_DOMAIN')) {
            return false;
        }

        // Can be disabled via constant
        if (defined('CS_DISABLE_AUDIT_LOG') && CS_DISABLE_AUDIT_LOG) {
            return false;
        }

        return true;
    }

    private static function getLogPath(): string
    {
        $uploadDir = wp_upload_dir();
        $logDir = $uploadDir['basedir'] . '/cs-logs';

        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);

            // Protect directory with .htaccess
            file_put_contents($logDir . '/.htaccess', "Deny from all\n");

            // Add index.php for extra protection
            file_put_contents($logDir . '/index.php', "<?php // Silence is golden\n");
        }

        return $logDir . '/' . self::LOG_FILE;
    }

    private static function writeLog(array $entry): void
    {
        $logFile = self::getLogPath();
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        // Rotate if needed (check every ~100 writes)
        if (mt_rand(1, 100) === 1) {
            self::rotateLogs();
        }
    }

    private static function sanitizeEventName(string $event): string
    {
        return preg_replace('/[^a-z0-9_]/', '', strtolower($event)) ?: 'unknown';
    }

    private static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        $allowedKeys = [
            'endpoint', 'limit', 'reason', 'field', 'status',
            'ip_hash', 'user_agent_hash', 'origin_hash',
            'consultant_id', 'slot_date', 'error_code',
        ];

        foreach ($context as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key)) ?: 'unknown';

            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            // Ensure value is scalar and reasonable length
            if (is_scalar($value)) {
                $sanitized[$key] = substr((string) $value, 0, 100);
            }
        }

        return $sanitized;
    }

    private static function getRequestId(): string
    {
        static $requestId = null;

        if ($requestId === null) {
            $requestId = substr(bin2hex(random_bytes(4)), 0, 8);
        }

        return $requestId;
    }

    private static function hash(string $value): string
    {
        // Use first 8 chars of SHA256 - enough for correlation, not reversible
        return substr(hash('sha256', $value . self::getSalt()), 0, 8);
    }

    private static function hashIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return self::hash($ip);
    }

    private static function hashUserAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return self::hash($ua);
    }

    private static function getSalt(): string
    {
        // Use WordPress auth salt for consistent hashing within installation
        // Changes if site is compromised (good - invalidates old hashes)
        if (defined('AUTH_SALT')) {
            return AUTH_SALT;
        }

        return 'cs-default-salt';
    }
}
