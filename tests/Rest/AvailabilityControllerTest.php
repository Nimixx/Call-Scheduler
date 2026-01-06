<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Rest;

use WP_REST_Request;
use WP_UnitTestCase;

class AvailabilityControllerTest extends WP_UnitTestCase
{
    private int $user_id;

    public function set_up(): void
    {
        parent::set_up();
        do_action('rest_api_init');

        $this->user_id = $this->factory->user->create();
        update_user_meta($this->user_id, 'cs_is_team_member', '1');
    }

    public function test_returns_empty_slots_for_unavailable_day(): void
    {
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-06');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals('2026-01-06', $data['date']);
        $this->assertEmpty($data['slots']);
    }

    public function test_returns_slots_with_availability(): void
    {
        global $wpdb;

        // Monday = 1
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
        ]);

        // 2026-01-05 is Monday
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-05');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertCount(3, $data['slots']); // 09:00, 10:00, 11:00
        $this->assertEquals('09:00', $data['slots'][0]['start']);
        $this->assertTrue($data['slots'][0]['available']);
    }

    public function test_marks_booked_slots_as_unavailable(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'booking_date' => '2026-01-05',
            'booking_time' => '09:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-05');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertFalse($data['slots'][0]['available']); // 09:00 booked
        $this->assertTrue($data['slots'][1]['available']);  // 10:00 free
    }

    public function test_validates_date_format(): void
    {
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', 'invalid-date');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_requires_user_id(): void
    {
        $request = new WP_REST_Request('GET', '/cs/v1/availability');

        $response = rest_do_request($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function test_pending_booking_blocks_slot(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 2, // Tuesday
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'Pending Customer',
            'customer_email' => 'pending@example.com',
            'booking_date' => '2026-01-06', // Tuesday
            'booking_time' => '09:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-06');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertFalse($data['slots'][0]['available'], 'Pending booking should block slot');
    }

    public function test_confirmed_booking_blocks_slot(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 3, // Wednesday
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'Confirmed Customer',
            'customer_email' => 'confirmed@example.com',
            'booking_date' => '2026-01-07', // Wednesday
            'booking_time' => '09:00:00',
            'status' => 'confirmed',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-07');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertFalse($data['slots'][0]['available'], 'Confirmed booking should block slot');
    }

    public function test_cancelled_booking_does_not_block_slot(): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 4, // Thursday
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'Cancelled Customer',
            'customer_email' => 'cancelled@example.com',
            'booking_date' => '2026-01-08', // Thursday
            'booking_time' => '09:00:00',
            'status' => 'cancelled',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-08');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertTrue($data['slots'][0]['available'], 'Cancelled booking should NOT block slot');
    }

    // Bug #1 Tests: Overnight Shift Slot Generation

    public function test_generates_slots_for_overnight_shift(): void
    {
        global $wpdb;

        // Setup overnight availability: 22:00 - 02:00 (4 hours)
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 5, // Friday
            'start_time' => '22:00:00',
            'end_time' => '02:00:00',
        ]);

        // 2026-01-09 is Friday
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-09');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertEquals(200, $response->get_status());
        $this->assertCount(4, $data['slots'], 'Should generate 4 hourly slots for 22:00-02:00');

        // Verify slot times
        $this->assertEquals('22:00', $data['slots'][0]['start']);
        $this->assertEquals('23:00', $data['slots'][0]['end']);

        $this->assertEquals('23:00', $data['slots'][1]['start']);
        $this->assertEquals('00:00', $data['slots'][1]['end']);

        $this->assertEquals('00:00', $data['slots'][2]['start']);
        $this->assertEquals('01:00', $data['slots'][2]['end']);

        $this->assertEquals('01:00', $data['slots'][3]['start']);
        $this->assertEquals('02:00', $data['slots'][3]['end']);

        // All should be available
        foreach ($data['slots'] as $slot) {
            $this->assertTrue($slot['available'], "Slot {$slot['start']}-{$slot['end']} should be available");
        }
    }

    public function test_overnight_shift_marks_booked_midnight_slot_unavailable(): void
    {
        global $wpdb;

        // Setup overnight availability
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 6, // Saturday
            'start_time' => '22:00:00',
            'end_time' => '03:00:00',
        ]);

        // Book midnight slot
        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->user_id,
            'customer_name' => 'Midnight Booker',
            'customer_email' => 'midnight@example.com',
            'booking_date' => '2026-01-10', // Saturday
            'booking_time' => '00:00:00',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);

        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-10');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertCount(5, $data['slots']); // 22:00, 23:00, 00:00, 01:00, 02:00

        // Find midnight slot and verify it's unavailable
        $midnight_slot = array_values(array_filter($data['slots'], fn($s) => $s['start'] === '00:00'))[0];
        $this->assertFalse($midnight_slot['available'], 'Booked midnight slot should be unavailable');

        // Other slots should be available
        $other_slots = array_filter($data['slots'], fn($s) => $s['start'] !== '00:00');
        foreach ($other_slots as $slot) {
            $this->assertTrue($slot['available'], "Slot {$slot['start']} should be available");
        }
    }

    public function test_long_overnight_shift_generates_correct_slot_count(): void
    {
        global $wpdb;

        // Setup long overnight shift: 20:00 - 06:00 (10 hours)
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 0, // Sunday
            'start_time' => '20:00:00',
            'end_time' => '06:00:00',
        ]);

        // 2026-01-11 is Sunday
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-01-11');

        $response = rest_do_request($request);
        $data = $response->get_data();

        $this->assertCount(10, $data['slots'], 'Should generate 10 slots for 20:00-06:00');

        // Verify first and last slots
        $this->assertEquals('20:00', $data['slots'][0]['start']);
        $this->assertEquals('05:00', $data['slots'][9]['start']);
        $this->assertEquals('06:00', $data['slots'][9]['end']);
    }
}
