<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Rest;

use WP_REST_Request;
use WP_UnitTestCase;

class RateLimiterTest extends WP_UnitTestCase
{
    private int $user_id;

    public function set_up(): void
    {
        parent::set_up();
        do_action('rest_api_init');

        $this->user_id = $this->factory->user->create();
        update_user_meta($this->user_id, 'cs_is_team_member', '1');

        // Clear rate limit transients before each test
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cs_rate_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cs_rate_%'");
    }

    public function test_response_includes_rate_limit_headers(): void
    {
        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $headers = $response->get_headers();
        $this->assertArrayHasKey('X-RateLimit-Limit', $headers);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
        $this->assertArrayHasKey('X-RateLimit-Reset', $headers);

        $this->assertEquals('60', $headers['X-RateLimit-Limit']);
    }

    public function test_rate_limit_remaining_decreases(): void
    {
        $request1 = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response1 = rest_do_request($request1);
        $remaining1 = (int) $response1->get_headers()['X-RateLimit-Remaining'];

        $request2 = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response2 = rest_do_request($request2);
        $remaining2 = (int) $response2->get_headers()['X-RateLimit-Remaining'];

        $this->assertEquals($remaining1 - 1, $remaining2);
    }

    public function test_bookings_endpoint_has_stricter_limit(): void
    {
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Test Customer');
        $request->set_param('customer_email', 'test@example.com');
        $request->set_param('booking_date', '2026-02-01');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $headers = $response->get_headers();
        $this->assertEquals('10', $headers['X-RateLimit-Limit']);
    }

    public function test_rate_limit_blocks_excessive_requests(): void
    {
        // Make 11 booking requests - 11th should be blocked
        for ($i = 1; $i <= 10; $i++) {
            $request = new WP_REST_Request('POST', '/cs/v1/bookings');
            $request->set_param('user_id', $this->user_id);
            $request->set_param('customer_name', "Customer $i");
            $request->set_param('customer_email', "customer$i@example.com");
            $request->set_param('booking_date', '2026-02-02');
            $request->set_param('booking_time', sprintf('%02d:00', 8 + $i));

            $response = rest_do_request($request);
            $this->assertEquals(201, $response->get_status(), "Request $i should succeed");
        }

        // 11th request should be rate limited
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Blocked Customer');
        $request->set_param('customer_email', 'blocked@example.com');
        $request->set_param('booking_date', '2026-02-03');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        $this->assertEquals(429, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals('rate_limit_exceeded', $data['code']);
        $this->assertArrayHasKey('retry_after', $data['data']);
    }

    public function test_different_endpoints_have_separate_limits(): void
    {
        // Exhaust bookings limit
        for ($i = 1; $i <= 10; $i++) {
            $request = new WP_REST_Request('POST', '/cs/v1/bookings');
            $request->set_param('user_id', $this->user_id);
            $request->set_param('customer_name', "Customer $i");
            $request->set_param('customer_email', "c$i@example.com");
            $request->set_param('booking_date', '2026-02-04');
            $request->set_param('booking_time', sprintf('%02d:00', 8 + $i));
            rest_do_request($request);
        }

        // Team members endpoint should still work (different limit counter)
        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
    }

    public function test_availability_endpoint_rate_limited(): void
    {
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('date', '2026-02-05');

        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());

        $headers = $response->get_headers();
        $this->assertEquals('60', $headers['X-RateLimit-Limit']);
        $this->assertArrayHasKey('X-RateLimit-Remaining', $headers);
    }

    // Bug #5 Tests: Rate Limiter Race Condition Fix

    public function test_concurrent_requests_count_accurately(): void
    {
        // Make 10 concurrent-like requests rapidly
        // With the lock fix, all should count correctly
        $remaining_values = [];

        for ($i = 1; $i <= 10; $i++) {
            $request = new WP_REST_Request('POST', '/cs/v1/bookings');
            $request->set_param('user_id', $this->user_id);
            $request->set_param('customer_name', "Concurrent Customer $i");
            $request->set_param('customer_email', "concurrent$i@example.com");
            $request->set_param('booking_date', '2026-02-06');
            $request->set_param('booking_time', sprintf('%02d:00', 8 + $i));

            $response = rest_do_request($request);
            $headers = $response->get_headers();
            $remaining_values[] = (int) $headers['X-RateLimit-Remaining'];
        }

        // With proper locking, remaining should decrease monotonically
        // First request: 9 remaining, second: 8, ..., tenth: 0
        $this->assertEquals(9, $remaining_values[0], 'First request should have 9 remaining');
        $this->assertEquals(0, $remaining_values[9], 'Tenth request should have 0 remaining');

        // Verify it's monotonically decreasing
        for ($i = 1; $i < count($remaining_values); $i++) {
            $this->assertLessThan(
                $remaining_values[$i - 1],
                $remaining_values[$i],
                "Remaining should decrease monotonically at index $i"
            );
        }
    }

    public function test_rate_limit_lock_prevents_count_loss(): void
    {
        // Make exactly 10 requests (the limit)
        for ($i = 1; $i <= 10; $i++) {
            $request = new WP_REST_Request('POST', '/cs/v1/bookings');
            $request->set_param('user_id', $this->user_id);
            $request->set_param('customer_name', "Customer $i");
            $request->set_param('customer_email', "customer$i@example.com");
            $request->set_param('booking_date', '2026-02-07');
            $request->set_param('booking_time', sprintf('%02d:00', 8 + $i));

            rest_do_request($request);
        }

        // 11th request should be blocked
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('user_id', $this->user_id);
        $request->set_param('customer_name', 'Customer 11');
        $request->set_param('customer_email', 'customer11@example.com');
        $request->set_param('booking_date', '2026-02-08');
        $request->set_param('booking_time', '09:00');

        $response = rest_do_request($request);

        // Without lock fix, race condition might allow 11th request through
        // With lock fix, 11th request is guaranteed to be blocked
        $this->assertEquals(429, $response->get_status(), 'Lock should prevent race condition allowing 11th request');
    }

    public function test_rate_limit_lock_releases_after_operation(): void
    {
        // Make a request
        $request1 = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request1->set_param('user_id', $this->user_id);
        $request1->set_param('customer_name', 'Customer 1');
        $request1->set_param('customer_email', 'customer1@example.com');
        $request1->set_param('booking_date', '2026-02-09');
        $request1->set_param('booking_time', '09:00');

        $response1 = rest_do_request($request1);
        $this->assertEquals(201, $response1->get_status());

        // Immediately make another request - should not be blocked by lock
        $request2 = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request2->set_param('user_id', $this->user_id);
        $request2->set_param('customer_name', 'Customer 2');
        $request2->set_param('customer_email', 'customer2@example.com');
        $request2->set_param('booking_date', '2026-02-10');
        $request2->set_param('booking_time', '09:00');

        $response2 = rest_do_request($request2);
        $this->assertEquals(201, $response2->get_status(), 'Lock should be released after first request');
    }

    public function test_rate_limit_accuracy_under_rapid_fire(): void
    {
        global $wpdb;

        // Setup availability for rapid fire test
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'user_id' => $this->user_id,
            'day_of_week' => 1, // Monday
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
        ]);

        $success_count = 0;
        $rate_limited_count = 0;

        // Make 15 rapid requests (limit is 10)
        for ($i = 1; $i <= 15; $i++) {
            $request = new WP_REST_Request('POST', '/cs/v1/bookings');
            $request->set_param('user_id', $this->user_id);
            $request->set_param('customer_name', "Rapid Customer $i");
            $request->set_param('customer_email', "rapid$i@example.com");
            $request->set_param('booking_date', '2026-02-11');
            $request->set_param('booking_time', sprintf('%02d:00', 8 + ($i % 12)));

            $response = rest_do_request($request);

            if ($response->get_status() === 201) {
                $success_count++;
            } elseif ($response->get_status() === 429) {
                $rate_limited_count++;
            }
        }

        // With lock fix: exactly 10 succeed, 5 blocked
        $this->assertEquals(10, $success_count, 'Exactly 10 requests should succeed');
        $this->assertEquals(5, $rate_limited_count, 'Exactly 5 requests should be rate limited');
    }
}

