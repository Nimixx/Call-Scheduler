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
 */
final class Webhook
{
    /**
     * Send webhook for booking.created event
     *
     * @param array $booking Booking data
     * @return bool True if webhook was dispatched (not necessarily delivered)
     */
    public function sendBookingCreated(array $booking): bool
    {
        $payload = $this->buildPayload('booking.created', [
            'booking' => [
                'id' => $booking['id'] ?? null,
                'user_id' => $booking['user_id'],
                'customer_name' => $booking['customer_name'],
                'customer_email' => $booking['customer_email'],
                'booking_date' => $booking['booking_date'],
                'booking_time' => $booking['booking_time'],
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

        $json_payload = wp_json_encode($payload);
        if ($json_payload === false) {
            return false;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-CS-Event' => $payload['event'],
            'X-CS-Timestamp' => $payload['timestamp'],
        ];

        // Add HMAC signature if secret is configured
        $secret = $options['webhook_secret'] ?? '';
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

        // Allow filtering of webhook args
        $args = apply_filters('cs_webhook_args', $args, $payload);

        $response = wp_remote_post($url, $args);

        // Log errors for debugging (non-blocking failures)
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Call Scheduler Webhook Error: %s',
                    $response->get_error_message()
                ));
            }
            return false;
        }

        return true;
    }

    /**
     * Check if webhooks are enabled
     */
    public static function isEnabled(): bool
    {
        $options = get_option('cs_options', []);
        return !empty($options['webhook_enabled']) && !empty($options['webhook_url']);
    }
}
