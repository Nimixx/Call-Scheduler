<?php

declare(strict_types=1);

namespace CallScheduler\Rest;

use CallScheduler\BookingStatus;
use CallScheduler\Config;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class AvailabilityController extends RestController
{
    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/availability', [
            'methods' => 'GET',
            'callback' => [$this, 'getAvailability'],
            'permission_callback' => '__return_true',
            'args' => [
                'consultant_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'date' => [
                    'required' => false,
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function getAvailability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $error = $this->checkReadRateLimit('availability');
        if ($error) {
            return $error;
        }

        global $wpdb;

        $consultant_id = $request->get_param('consultant_id');
        $date = $request->get_param('date') ?: wp_date('Y-m-d');

        // Validate consultant
        $consultant = $this->validateConsultant($consultant_id);
        if ($consultant instanceof WP_Error) {
            return $consultant;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->errorResponse('invalid_date', 'Invalid date format. Use YYYY-MM-DD.');
        }

        // Block dates too far in the future
        $max_booking_days = Config::getMaxBookingDays();
        $max_date = wp_date('Y-m-d', strtotime("+{$max_booking_days} days"));
        if ($date > $max_date) {
            return $this->errorResponse('date_too_far', "Cannot view availability more than {$max_booking_days} days in advance.");
        }

        $day_of_week = (int) wp_date('w', strtotime($date));

        // Get availability for this day
        $availability = $wpdb->get_row($wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}cs_availability
             WHERE consultant_id = %d AND day_of_week = %d",
            $consultant->id,
            $day_of_week
        ));

        if (!$availability) {
            return $this->successResponse([
                'date' => $date,
                'day_of_week' => $day_of_week,
                'slots' => [],
            ], 'availability');
        }

        // Get existing bookings for this date (pending and confirmed block the slot)
        $blocking_statuses = BookingStatus::blocking();
        $status_placeholders = implode(',', array_fill(0, count($blocking_statuses), '%s'));
        $query_args = array_merge([$consultant->id, $date], $blocking_statuses);

        $booked_times = $wpdb->get_col($wpdb->prepare(
            "SELECT booking_time FROM {$wpdb->prefix}cs_bookings
             WHERE consultant_id = %d AND booking_date = %s AND status IN ($status_placeholders)",
            $query_args
        ));

        // Generate hourly slots
        $slots = $this->generateSlots(
            $availability->start_time,
            $availability->end_time,
            $booked_times
        );

        return $this->successResponse([
            'date' => $date,
            'day_of_week' => $day_of_week,
            'slots' => $slots,
        ], 'availability');
    }

    private function generateSlots(string $start, string $end, array $booked): array
    {
        $slots = [];
        $start_time = strtotime($start);
        $end_time = strtotime($end);

        // Detect overnight shift (end <= start means it wraps to next day)
        $is_overnight = $end_time <= $start_time;

        if ($is_overnight) {
            // Add 24 hours to end_time to represent next day
            $end_time += 86400; // 24 * 60 * 60 seconds
        }

        // Get configuration
        $slot_duration = Config::getSlotDurationSeconds();
        $buffer_time = Config::getBufferTimeSeconds();

        // Calculate blocked time periods (booking duration + buffer)
        $blocked_periods = $this->calculateBlockedPeriods($booked, $slot_duration, $buffer_time);

        $current = $start_time;

        while ($current < $end_time) {
            $slot_start = date('H:i', $current);
            $slot_end = date('H:i', $current + $slot_duration);

            $slots[] = [
                'start' => $slot_start,
                'end' => $slot_end,
                'available' => !$this->isSlotBlocked($current, $blocked_periods),
            ];

            // Move to next slot: duration + buffer gives proper spacing
            $current += $slot_duration + $buffer_time;
        }

        return $slots;
    }

    /**
     * Calculate blocked time periods from booked times
     *
     * Each booking blocks: [booking_time] to [booking_time + duration + buffer]
     *
     * @param array $booked Booked times (HH:MM:SS format)
     * @param int $duration Slot duration in seconds
     * @param int $buffer Buffer time in seconds
     * @return array Array of blocked periods ['start' => timestamp, 'end' => timestamp]
     */
    private function calculateBlockedPeriods(array $booked, int $duration, int $buffer): array
    {
        $blocked = [];

        foreach ($booked as $booking_time) {
            // Convert booking time to today's timestamp for comparison
            $booking_start = strtotime($booking_time);

            // Block period: start of booking to end of (booking + buffer)
            $blocked_end = $booking_start + $duration + $buffer;

            $blocked[] = [
                'start' => $booking_start,
                'end' => $blocked_end,
            ];
        }

        return $blocked;
    }

    /**
     * Check if a slot start time falls within any blocked period
     *
     * @param int $slot_start Slot start timestamp
     * @param array $blocked_periods Array of blocked periods
     * @return bool True if slot is blocked
     */
    private function isSlotBlocked(int $slot_start, array $blocked_periods): bool
    {
        foreach ($blocked_periods as $period) {
            // Slot is blocked if it starts within a blocked period
            if ($slot_start >= $period['start'] && $slot_start < $period['end']) {
                return true;
            }
        }

        return false;
    }
}
