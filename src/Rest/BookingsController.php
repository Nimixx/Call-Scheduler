<?php

declare(strict_types=1);

namespace CallScheduler\Rest;

use CallScheduler\BookingStatus;
use CallScheduler\Config;
use CallScheduler\Email;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class BookingsController extends RestController
{
    private Email $email;

    public function __construct(?Email $email = null)
    {
        $this->email = $email ?? new Email();
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/bookings', [
            'methods' => 'POST',
            'callback' => [$this, 'createBooking'],
            'permission_callback' => '__return_true',
            'args' => [
                'user_id' => [
                    'required' => true,
                    'type' => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'customer_name' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'customer_email' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ],
                'booking_date' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'booking_time' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                // Honeypot field - must be empty (bots fill it, humans don't see it)
                'website' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function createBooking(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Rate limiting
        $error = $this->checkWriteRateLimit('bookings');
        if ($error) {
            return $error;
        }

        // Token verification (if enabled)
        $error = $this->verifyToken($request);
        if ($error) {
            return $error;
        }

        // Honeypot check - if filled, it's a bot
        if (!empty($request->get_param('website'))) {
            return new WP_REST_Response(['id' => 0, 'status' => BookingStatus::PENDING], 201);
        }

        global $wpdb;

        $user_id = $request->get_param('user_id');
        $customer_name = $request->get_param('customer_name');
        $customer_email = $request->get_param('customer_email');
        $booking_date = $request->get_param('booking_date');
        $booking_time = $request->get_param('booking_time');

        // Validate team member
        $error = $this->validateTeamMember($user_id);
        if ($error) {
            return $error;
        }

        // Validate email
        if (!is_email($customer_email)) {
            return $this->errorResponse('invalid_email', 'Invalid email address.');
        }

        // Validate date
        $error = $this->validateDate($booking_date);
        if ($error) {
            return $error;
        }

        // Validate time
        $error = $this->validateTime($booking_time);
        if ($error) {
            return $error;
        }

        // Validate slot is within team member's availability
        $error = $this->validateAvailability($user_id, $booking_date, $booking_time);
        if ($error) {
            return $error;
        }

        // Insert booking - DB unique constraint prevents duplicates
        $result = $wpdb->insert(
            $wpdb->prefix . 'cs_bookings',
            [
                'user_id' => $user_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'booking_date' => $booking_date,
                'booking_time' => $booking_time . ':00',
                'status' => BookingStatus::PENDING,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            if (str_contains($wpdb->last_error, 'Duplicate entry')) {
                return $this->errorResponse('slot_taken', 'This time slot is already booked.', 409);
            }
            return $this->errorResponse('db_error', 'Failed to create booking.', 500);
        }

        $booking_id = $wpdb->insert_id;

        // Fire action for cache invalidation and other hooks
        do_action('cs_booking_created', $booking_id, $user_id, $booking_date);

        // Send confirmation emails (non-blocking)
        $booking_data = [
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'booking_date' => $booking_date,
            'booking_time' => $booking_time,
            'user_id' => $user_id,
        ];
        $this->email->sendCustomerConfirmation($booking_data);
        $this->email->sendTeamMemberNotification($booking_data);

        return $this->successResponse([
            'id' => $booking_id,
            'user_id' => $user_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'booking_date' => $booking_date,
            'booking_time' => $booking_time,
            'status' => BookingStatus::PENDING,
        ], 'bookings', 201, Config::getRateLimitWrite());
    }

    private function validateDate(string $date): ?WP_Error
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->errorResponse('invalid_date', 'Invalid date format. Use YYYY-MM-DD.');
        }

        $date_parts = explode('-', $date);
        if (!checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0])) {
            return $this->errorResponse('invalid_date', 'Invalid date.');
        }

        if ($date < wp_date('Y-m-d')) {
            return $this->errorResponse('past_date', 'Cannot book dates in the past.');
        }

        $max_booking_days = Config::getMaxBookingDays();
        $max_date = wp_date('Y-m-d', strtotime("+{$max_booking_days} days"));
        if ($date > $max_date) {
            return $this->errorResponse('date_too_far', "Cannot book more than {$max_booking_days} days in advance.");
        }

        return null;
    }

    private function validateTime(string $time): ?WP_Error
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $this->errorResponse('invalid_time', 'Invalid time format. Use HH:MM.');
        }

        $time_parts = explode(':', $time);
        $hours = (int) $time_parts[0];
        $minutes = (int) $time_parts[1];
        if ($hours > 23 || $minutes > 59) {
            return $this->errorResponse('invalid_time', 'Invalid time. Hours must be 00-23, minutes 00-59.');
        }

        // Note: Slot boundary validation removed - frontend uses availability API
        // which returns only valid slots. Working hours checked in validateAvailability().

        return null;
    }

    private function validateAvailability(int $user_id, string $date, string $time): ?WP_Error
    {
        global $wpdb;

        $day_of_week = (int) wp_date('w', strtotime($date));

        $availability = $wpdb->get_row($wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}cs_availability
             WHERE user_id = %d AND day_of_week = %d",
            $user_id,
            $day_of_week
        ));

        if (!$availability) {
            return $this->errorResponse('no_availability', 'Team member is not available on this day.');
        }

        $requested_time = strtotime($time);
        $start_time = strtotime(substr($availability->start_time, 0, 5));
        $end_time = strtotime(substr($availability->end_time, 0, 5));

        // Detect overnight shift (end <= start means it wraps to next day)
        $is_overnight = $end_time <= $start_time;

        if ($is_overnight) {
            $is_valid = $requested_time >= $start_time || $requested_time < $end_time;
        } else {
            $is_valid = $requested_time >= $start_time && $requested_time < $end_time;
        }

        if (!$is_valid) {
            return $this->errorResponse(
                'outside_hours',
                sprintf(
                    'Requested time is outside working hours (%s - %s).',
                    substr($availability->start_time, 0, 5),
                    substr($availability->end_time, 0, 5)
                )
            );
        }

        return null;
    }

}
