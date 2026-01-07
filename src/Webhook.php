<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook service for sending HTTP notifications to external endpoints
 *
 * Supports:
 * - Fire-and-forget (non-blocking) HTTP POST requests
 * - HMAC-SHA256 signature for payload verification
 * - Extensible event types
 *
 * Security:
 * - Secret key stored in wp-config.php (CS_WEBHOOK_SECRET), not database
 * - HTTPS enforced for webhook URLs
 * - SSRF protection blocks internal URLs
 */
final class Webhook
{
    /**
     * Send webhook for booking.created event
     *
     * @param array $booking Booking data with keys: id, user_id, customer_name, customer_email, booking_date, booking_time, status
     * @return bool True if webhook was dispatched (not necessarily delivered)
     */
    public function sendBookingCreated(array $booking): bool
    {
        // Validate required fields
        $required = ['user_id', 'customer_name', 'customer_email', 'booking_date', 'booking_time'];
        foreach ($required as $field) {
            if (!isset($booking[$field])) {
                $this->logError("Missing required field: {$field}");
                return false;
            }
        }

        $payload = $this->buildPayload('booking.created', [
            'booking' => [
                'id' => $booking['id'] ?? null,
                'user_id' => (int) $booking['user_id'],
                'customer_name' => (string) $booking['customer_name'],
                'customer_email' => (string) $booking['customer_email'],
                'booking_date' => (string) $booking['booking_date'],
                'booking_time' => (string) $booking['booking_time'],
                'status' => $booking['status'] ?? BookingStatus::PENDING,
            ],
            'team_member' => $this->getTeamMemberData((int) $booking['user_id']),
        ]);

        return $this->dispatch($payload);
    }

    /**
     * Build standard webhook payload
     *
     * @param string $event Event type (e.g., 'booking.created')
     * @param array $data Event-specific data
     * @return array Complete payload
     */
    private function buildPayload(string $event, array $data): array
    {
        return [
            'event' => $event,
            'timestamp' => gmdate('c'), // ISO 8601 format
            'data' => $data,
            'meta' => [
                'plugin_version' => defined('CS_VERSION') ? CS_VERSION : '1.0.0',
                'site_url' => home_url(),
            ],
        ];
    }

    /**
     * Get team member data for payload
     *
     * @param int $user_id Team member user ID
     * @return array|null Team member data or null
     */
    private function getTeamMemberData(int $user_id): ?array
    {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
        ];
    }

    /**
     * Dispatch webhook payload to configured URL
     *
     * Uses wp_remote_post with short timeout for non-blocking behavior.
     * Failures are logged but do not interrupt the booking flow.
     *
     * @param array $payload The payload to send
     * @return bool True if request was initiated
     */
    private function dispatch(array $payload): bool
    {
        $options = get_option('cs_options', []);

        if (empty($options['webhook_enabled'])) {
            return false;
        }

        $url = $options['webhook_url'] ?? '';
        if (empty($url)) {
            return false;
        }

        // Security: Enforce HTTPS at dispatch time as well
        if (!str_starts_with($url, 'https://')) {
            $this->logError('Webhook URL must use HTTPS');
            return false;
        }

        // Security: SSRF protection at dispatch time (defense in depth)
        if ($this->isInternalUrl($url)) {
            $this->logError('Webhook URL points to internal address');
            return false;
        }

        $json_payload = wp_json_encode($payload);
        if ($json_payload === false) {
            return false;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-CS-Event' => $payload['event'],
            'X-CS-Timestamp' => $payload['timestamp'],
        ];

        // Security: Get secret from wp-config.php constant, NOT database
        $secret = self::getSecret();
        if (!empty($secret)) {
            $signature = hash_hmac('sha256', $json_payload, $secret);
            $headers['X-CS-Signature'] = $signature;
        }

        // Fire-and-forget: short timeout, don't wait for response
        $args = [
            'body' => $json_payload,
            'headers' => $headers,
            'timeout' => 0.01, // Near-instant timeout for non-blocking
            'blocking' => false, // Don't wait for response
            'sslverify' => true,
        ];

        // Allow filtering of webhook args (for testing)
        $args = apply_filters('cs_webhook_args', $args, $payload);

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->logError($response->get_error_message());
            return false;
        }

        return true;
    }

    /**
     * Get webhook secret from wp-config.php constant
     *
     * Security: Secret is NEVER stored in database to prevent exposure
     * via SQL injection, backups, or other plugin access.
     *
     * @return string Secret key or empty string if not configured
     */
    public static function getSecret(): string
    {
        if (!defined('CS_WEBHOOK_SECRET') || empty(CS_WEBHOOK_SECRET)) {
            return '';
        }

        return CS_WEBHOOK_SECRET;
    }

    /**
     * Check if webhook secret is configured
     */
    public static function hasSecret(): bool
    {
        return !empty(self::getSecret());
    }

    /**
     * Check if webhooks are enabled
     */
    public static function isEnabled(): bool
    {
        $options = get_option('cs_options', []);
        return !empty($options['webhook_enabled']) && !empty($options['webhook_url']);
    }

    /**
     * Check if URL points to internal/private network (SSRF protection)
     */
    private function isInternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return true;
        }

        $host = strtolower($host);

        // Block localhost
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        // Block private IP patterns
        $blocked_patterns = [
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[01])\./',
            '/^192\.168\./',
            '/\.local$/',
            '/\.internal$/',
            '/\.localhost$/',
        ];

        foreach ($blocked_patterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log error for debugging (only when WP_DEBUG is enabled)
     */
    private function logError(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Call Scheduler Webhook Error: %s', $message));
        }
    }
}
