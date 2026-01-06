<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Rest;

use WP_REST_Request;
use WP_UnitTestCase;

class BookingsControllerTest extends WP_UnitTestCase
{
    private int $user_id;

    public function set_up(): void
    {
        parent::set_up();
        do_action('rest_api_init');

        $this->user_id = $this->factory->user->create();
        update_user_meta($this->user_id, 'cs_is_team_member', '1');
    }

    public function test_creates_booking_successfully(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Jane Doe');
        $request->set_param('customer_email', 'jane@example.com');
        $request->set_param('booking_date', '2026-01-06');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());

        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($this->user_id, $data['user_id']);
        $this->assertEquals('Jane Doe', $data['customer_name']);
        $this->assertEquals('jane@example.com', $data['customer_email']);
        $this->assertEquals('pending', $data['status']);
    }

    public function test_rejects_invalid_email(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Jane Doe');
        $request->set_param('customer_email', 'not-an-email');
        $request->set_param('booking_date', '2026-01-06');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_rejects_invalid_date_format(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Jane Doe');
        $request->set_param('customer_email', 'jane@example.com');
        $request->set_param('booking_date', '06-01-2026');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_rejects_invalid_time_format(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Jane Doe');
        $request->set_param('customer_email', 'jane@example.com');
        $request->set_param('booking_date', '2026-01-06');
        $request->set_param('booking_time', '9am');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_rejects_duplicate_booking(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'First Customer',
            'customer_email' => 'first@example.com',
            'booking_date' => '2026-01-06',
            'booking_time' => '09:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Second Customer');
        $request->set_param('customer_email', 'second@example.com');
        $request->set_param('booking_date', '2026-01-06');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $this->assertEquals(409, $response->get_status());
    }

    public function test_allows_booking_cancelled_slot(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'Cancelled Customer',
            'customer_email' => 'cancelled@example.com',
            'booking_date' => '2026-01-06',
            'booking_time' => '09:00:00',
            'status' => 'cancelled',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'New Customer');
        $request->set_param('customer_email', 'new@example.com');
        $request->set_param('booking_date', '2026-01-06');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
    }

    public function test_requires_all_fields(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_database_constraint_prevents_duplicate(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // First insert succeeds
        $result1 = $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'customer_name' => 'Customer 1',
            'customer_email' => 'c1@example.com',
            'booking_date' => '2026-01-07',
            'booking_time' => '10:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $this->assertNotFalse($result1);

        // Second insert to same slot fails at DB level
        $result2 = $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'customer_name' => 'Customer 2',
            'customer_email' => 'c2@example.com',
            'booking_date' => '2026-01-07',
            'booking_time' => '10:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $this->assertFalse($result2);
        $this->assertStringContainsString('Duplicate entry', $wpdb->last_error);
    }

    public function test_database_allows_multiple_cancelled_same_slot(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // Multiple cancelled bookings on same slot should be allowed
        $result1 = $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'customer_name' => 'Cancelled 1',
            'customer_email' => 'c1@example.com',
            'booking_date' => '2026-01-08',
            'booking_time' => '11:00:00',
            'status' => 'cancelled',
            'created_at' => current_time('mysql'),
        ]);

        $result2 = $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'customer_name' => 'Cancelled 2',
            'customer_email' => 'c2@example.com',
            'booking_date' => '2026-01-08',
            'booking_time' => '11:00:00',
            'status' => 'cancelled',
            'created_at' => current_time('mysql'),
        ]);

        $this->assertNotFalse($result1);
        $this->assertNotFalse($result2);
    }

    public function test_concurrent_bookings_only_one_succeeds(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';
        $booking_date = '2026-01-09';
        $booking_time = '14:00:00';

        // Simulate 5 concurrent booking attempts
        $results = [];
        for ($i = 1; $i <= 5; $i++) {
            $results[] = $wpdb->insert($table, [
                'user_id' => $this->user_id,
                'customer_name' => "Customer {$i}",
                'customer_email' => "customer{$i}@example.com",
                'booking_date' => $booking_date,
                'booking_time' => $booking_time,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ]);
        }

        // Only first should succeed
        $successful = array_filter($results, fn($r) => $r !== false);
        $this->assertCount(1, $successful);

        // Verify only one booking in DB
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE booking_date = %s AND booking_time = %s AND status != 'cancelled'",
            $booking_date,
            $booking_time
        ));

        $this->assertEquals(1, $count);
    }

    public function test_different_team_members_can_book_same_slot(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        $user2 = $this->factory->user->create();
        update_user_meta($user2, 'cs_is_team_member', '1');

        // Same time, different team members - both should succeed
        $result1 = $wpdb->insert($table, [
            'user_id' => $this->user_id,
            'customer_name' => 'Customer for User 1',
            'customer_email' => 'c1@example.com',
            'booking_date' => '2026-01-10',
            'booking_time' => '15:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $result2 = $wpdb->insert($table, [
            'user_id' => $user2,
            'customer_name' => 'Customer for User 2',
            'customer_email' => 'c2@example.com',
            'booking_date' => '2026-01-10',
            'booking_time' => '15:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $this->assertNotFalse($result1);
        $this->assertNotFalse($result2);
    }

    // Bug #1 Tests: Overnight Shift Support

    public function test_accepts_booking_during_overnight_shift(): void
    {
        global $wpdb;

        // Setup overnight availability: 22:00 - 02:00
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 1, // Monday
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        // Test booking at 23:00 (within overnight shift)
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Night Customer');
        $request->set_param('customer_email', 'night@example.com');
        $request->set_param('booking_date', '2026-01-05'); // Monday
        $request->set_param('booking_time', '23:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status(), 'Should accept booking at 23:00 during overnight shift');
    }

    public function test_accepts_booking_after_midnight_during_overnight_shift(): void
    {
        global $wpdb;

        // Setup overnight availability: 22:00 - 02:00
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 2, // Tuesday
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        // Test booking at 01:00 (after midnight, still in overnight shift)
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Late Night Customer');
        $request->set_param('customer_email', 'latenight@example.com');
        $request->set_param('booking_date', '2026-01-06'); // Tuesday
        $request->set_param('booking_time', '01:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status(), 'Should accept booking at 01:00 during overnight shift');
    }

    public function test_rejects_booking_outside_overnight_shift(): void
    {
        global $wpdb;

        // Setup overnight availability: 22:00 - 02:00
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 3, // Wednesday
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        // Test booking at 21:00 (before overnight shift starts)
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Too Early Customer');
        $request->set_param('customer_email', 'tooearly@example.com');
        $request->set_param('booking_date', '2026-01-07'); // Wednesday
        $request->set_param('booking_time', '21:00');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status(), 'Should reject booking at 21:00 (before overnight shift)');
        $data = $response->get_data();
        $this->assertEquals('outside_hours', $data['code']);
    }

    public function test_rejects_booking_at_overnight_shift_end_boundary(): void
    {
        global $wpdb;

        // Setup overnight availability: 22:00 - 02:00
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 4, // Thursday
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        // Test booking at 02:00 (end boundary - should be rejected)
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Boundary Customer');
        $request->set_param('customer_email', 'boundary@example.com');
        $request->set_param('booking_date', '2026-01-08'); // Thursday
        $request->set_param('booking_time', '02:00');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status(), 'Should reject booking at 02:00 (end boundary)');
    }

    // Bug #2 Tests: Non-Hourly Bookings Validation

    public function test_rejects_non_hourly_booking_with_30_minutes(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Half Hour Customer');
        $request->set_param('customer_email', 'halfhour@example.com');
        $request->set_param('booking_date', '2026-01-11');
        $request->set_param('booking_time', '16:30');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('invalid_time', $data['code']);
        $this->assertStringContainsString('on the hour', $data['message']);
    }

    public function test_rejects_non_hourly_booking_with_15_minutes(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Quarter Hour Customer');
        $request->set_param('customer_email', 'quarter@example.com');
        $request->set_param('booking_date', '2026-01-12');
        $request->set_param('booking_time', '09:15');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('invalid_time', $data['code']);
    }

    public function test_rejects_non_hourly_booking_with_45_minutes(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Odd Time Customer');
        $request->set_param('customer_email', 'oddtime@example.com');
        $request->set_param('booking_date', '2026-01-13');
        $request->set_param('booking_time', '14:45');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_accepts_hourly_booking_at_midnight(): void
    {
        global $wpdb;

        // Setup availability including midnight
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 0, // Sunday
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Midnight Customer');
        $request->set_param('customer_email', 'midnight@example.com');
        $request->set_param('booking_date', '2026-01-04'); // Sunday
        $request->set_param('booking_time', '00:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status(), 'Should accept hourly booking at 00:00');
    }

    public function test_non_hourly_validation_prevents_slot_overlap(): void
    {
        global $wpdb;

        // Setup availability
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 5, // Friday
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ]);

        // Book 16:00 (should succeed)
        $request1 = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request1->set_param('user_id', $this->user_id);
        $request1->set_param('customer_name', 'Customer A');
        $request1->set_param('customer_email', 'customerA@example.com');
        $request1->set_param('booking_date', '2026-01-09'); // Friday
        $request1->set_param('booking_time', '16:00');

        $response1 = rest_do_request($request1);
        $this->assertEquals(201, $response1->get_status());

        // Try to book 16:30 (should be rejected as non-hourly, not as duplicate)
        $request2 = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request2->set_param('user_id', $this->user_id);
        $request2->set_param('customer_name', 'Customer B');
        $request2->set_param('customer_email', 'customerB@example.com');
        $request2->set_param('booking_date', '2026-01-09');
        $request2->set_param('booking_time', '16:30');

        $response2 = rest_do_request($request2);
        $this->assertEquals(400, $response2->get_status());
        $data = $response2->get_data();
        $this->assertEquals('invalid_time', $data['code'], 'Should reject as invalid time, not slot_taken');
    }
}
