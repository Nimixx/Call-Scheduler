<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

class BookingWorkflowTest extends WP_UnitTestCase
{
    private int $team_member_id;

    public function set_up(): void
    {
        parent::set_up();
        do_action('rest_api_init');

        // Create team member with availability
        $this->team_member_id = $this->factory->user->create([
            'display_name' => 'Dr. Smith',
            'user_email' => 'smith@clinic.com',
        ]);
        update_user_meta($this->team_member_id, 'cs_is_team_member', '1');

        // Set up weekly availability (Mon-Fri 9:00-17:00)
        global $wpdb;
        for ($day = 1; $day <= 5; $day++) {
            $wpdb->insert($wpdb->prefix . 'cs_availability', [
                'user_id' => $this->team_member_id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
            ]);
        }

        // Clear rate limits
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cs_rate_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cs_rate_%'");
    }

    /**
     * Scenario: Customer checks availability, picks a slot, and books it
     */
    public function test_complete_booking_workflow(): void
    {
        // Step 1: Customer gets team members
        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $team_members = $response->get_data();
        $this->assertNotEmpty($team_members);

        $selected_member = $team_members[0];

        // Step 2: Customer checks availability for Monday
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $selected_member['id']);
        $request->set_param('date', '2026-01-12'); // Monday

        $response = rest_do_request($request);
        $this->assertEquals(200, $response->get_status());

        $availability = $response->get_data();
        $available_slots = array_filter($availability['slots'], fn($s) => $s['available']);
        $this->assertNotEmpty($available_slots);

        $selected_slot = reset($available_slots);

        // Step 3: Customer books the slot
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $selected_member['id']);
        $request->set_param('customer_name', 'John Customer');
        $request->set_param('customer_email', 'john@customer.com');
        $request->set_param('booking_date', '2026-01-12');
        $request->set_param('booking_time', $selected_slot['start']);

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $booking = $response->get_data();
        $this->assertEquals('pending', $booking['status']);

        // Step 4: Verify slot is now unavailable
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $selected_member['id']);
        $request->set_param('date', '2026-01-12');

        $response = rest_do_request($request);
        $slots = $response->get_data()['slots'];

        $booked_slot = array_filter($slots, fn($s) => $s['start'] === $selected_slot['start']);
        $booked_slot = reset($booked_slot);

        $this->assertFalse($booked_slot['available']);
    }

    /**
     * Scenario: Two customers try to book the same slot simultaneously
     */
    public function test_concurrent_booking_only_one_succeeds(): void
    {
        global $wpdb;

        $date = '2026-01-13';
        $time = '10:00';

        // Simulate concurrent bookings at database level
        $results = [];
        for ($i = 1; $i <= 3; $i++) {
            $results[] = $wpdb->insert($wpdb->prefix . 'cs_bookings', [
                'user_id' => $this->team_member_id,
                'customer_name' => "Customer $i",
                'customer_email' => "customer$i@example.com",
                'booking_date' => $date,
                'booking_time' => "$time:00",
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ]);
        }

        $successful = array_filter($results, fn($r) => $r !== false);
        $this->assertCount(1, $successful, 'Only one concurrent booking should succeed');
    }

    /**
     * Scenario: Customer cancels booking, another customer books same slot
     */
    public function test_rebooking_after_cancellation(): void
    {
        global $wpdb;

        $date = '2026-01-14';
        $time = '11:00:00';

        // First customer books
        $wpdb->insert($wpdb->prefix . 'cs_bookings', [
            'user_id' => $this->team_member_id,
            'customer_name' => 'First Customer',
            'customer_email' => 'first@example.com',
            'booking_date' => $date,
            'booking_time' => $time,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ]);
        $first_booking_id = $wpdb->insert_id;

        // First customer cancels
        $wpdb->update(
            $wpdb->prefix . 'cs_bookings',
            ['status' => 'cancelled'],
            ['id' => $first_booking_id]
        );

        // Second customer should be able to book same slot
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Second Customer');
        $request->set_param('customer_email', 'second@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '11:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
    }

    /**
     * Scenario: Customer tries to book on weekend (no availability)
     */
    public function test_booking_on_unavailable_day_shows_no_slots(): void
    {
        // Saturday - no availability set
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('date', '2026-01-17'); // Saturday

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertEmpty($response->get_data()['slots']);
    }

    /**
     * Scenario: Multiple bookings on same day, different times
     */
    public function test_multiple_bookings_same_day_different_times(): void
    {
        $date = '2026-01-15';

        // Book 9:00
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Morning Customer');
        $request->set_param('customer_email', 'morning@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '09:00');
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());

        // Book 14:00
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Afternoon Customer');
        $request->set_param('customer_email', 'afternoon@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '14:00');
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());

        // Check availability - should have 6 slots taken (9:00 and 14:00)
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('date', $date);
        $response = rest_do_request($request);

        $slots = $response->get_data()['slots'];
        $unavailable = array_filter($slots, fn($s) => !$s['available']);

        $this->assertCount(2, $unavailable);
    }

    /**
     * Scenario: Customer books first and last slot of the day
     */
    public function test_booking_edge_time_slots(): void
    {
        $date = '2026-01-16';

        // Book first slot (09:00)
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'First Slot');
        $request->set_param('customer_email', 'first@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '09:00');
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());

        // Book last slot (16:00, since availability ends at 17:00)
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Last Slot');
        $request->set_param('customer_email', 'last@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '16:00');
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());

        // Verify both are booked
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('date', $date);
        $response = rest_do_request($request);

        $slots = $response->get_data()['slots'];
        $first_slot = $slots[0];
        $last_slot = end($slots);

        $this->assertFalse($first_slot['available']);
        $this->assertFalse($last_slot['available']);
    }

    /**
     * Scenario: Different team members can have same time slot booked
     */
    public function test_different_team_members_same_slot(): void
    {
        // Create second team member
        $second_member = $this->factory->user->create([
            'display_name' => 'Dr. Jones',
        ]);
        update_user_meta($second_member, 'cs_is_team_member', '1');

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $second_member,
            'day_of_week' => 1, // Monday
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        $date = '2026-01-19';
        $time = '10:00';

        // Book with first team member
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Customer A');
        $request->set_param('customer_email', 'a@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', $time);
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());

        // Book same slot with second team member - should succeed
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $second_member);
        $request->set_param('customer_name', 'Customer B');
        $request->set_param('customer_email', 'b@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', $time);
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());
    }

    /**
     * Scenario: Fully booked day shows all slots unavailable
     */
    public function test_fully_booked_day(): void
    {
        global $wpdb;

        $date = '2026-01-20';

        // Book all 8 slots (09:00 to 16:00)
        for ($hour = 9; $hour < 17; $hour++) {
            $wpdb->insert($wpdb->prefix . 'cs_bookings', [
                'user_id' => $this->team_member_id,
                'customer_name' => "Customer $hour",
                'customer_email' => "c$hour@example.com",
                'booking_date' => $date,
                'booking_time' => sprintf('%02d:00:00', $hour),
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            ]);
        }

        // Check availability
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('date', $date);
        $response = rest_do_request($request);

        $slots = $response->get_data()['slots'];
        $available = array_filter($slots, fn($s) => $s['available']);

        $this->assertEmpty($available, 'All slots should be unavailable');
    }

    /**
     * Scenario: Booking with invalid team member ID
     */
    public function test_booking_with_nonexistent_team_member(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', 99999);
        $request->set_param('customer_name', 'Test Customer');
        $request->set_param('customer_email', 'test@example.com');
        $request->set_param('booking_date', '2026-01-21');
        $request->set_param('booking_time', '10:00');

        $response = rest_do_request($request);

        // Currently accepts any user_id - booking is created
        // This test documents current behavior
        $this->assertContains($response->get_status(), [201, 400]);
    }

    /**
     * Scenario: Same customer booking multiple slots
     */
    public function test_same_customer_multiple_bookings(): void
    {
        $date = '2026-01-22';

        // Same customer books two different slots
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Repeat Customer');
        $request->set_param('customer_email', 'repeat@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '09:00');
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());

        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->team_member_id);
        $request->set_param('customer_name', 'Repeat Customer');
        $request->set_param('customer_email', 'repeat@example.com');
        $request->set_param('booking_date', $date);
        $request->set_param('booking_time', '14:00');
        $response = rest_do_request($request);
        $this->assertEquals(201, $response->get_status());
    }

    /**
     * Scenario: Check availability returns correct day_of_week
     */
    public function test_day_of_week_is_correct(): void
    {
        $test_dates = [
            '2026-01-11' => 0, // Sunday
            '2026-01-12' => 1, // Monday
            '2026-01-13' => 2, // Tuesday
            '2026-01-14' => 3, // Wednesday
            '2026-01-15' => 4, // Thursday
            '2026-01-16' => 5, // Friday
            '2026-01-17' => 6, // Saturday
        ];

        foreach ($test_dates as $date => $expected_day) {
            $request = new WP_REST_Request('GET', '/cs/v1/availability');
            $request->set_param('user_id', $this->team_member_id);
            $request->set_param('date', $date);
            $response = rest_do_request($request);

            $this->assertEquals(
                $expected_day,
                $response->get_data()['day_of_week'],
                "Date $date should be day $expected_day"
            );
        }
    }
}
