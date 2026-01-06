<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Admin;

use CallScheduler\Admin\Availability\AvailabilityService;
use CallScheduler\Admin\Availability\AvailabilityRepository;
use WP_UnitTestCase;

class AvailabilityServiceTest extends WP_UnitTestCase
{
    private AvailabilityService $service;
    private AvailabilityRepository $repository;

    public function set_up(): void
    {
        parent::set_up();
        $this->repository = new AvailabilityRepository();
        $this->service = new AvailabilityService($this->repository);
    }

    // Bug #3 Tests: Availability Hours Calculation

    public function test_calculates_normal_shift_hours(): void
    {
        $result = $this->service->calculateHours('09:00', '17:00');
        $this->assertEquals('8h', $result);
    }

    public function test_calculates_hours_with_minutes(): void
    {
        $result = $this->service->calculateHours('09:00', '17:30');
        $this->assertEquals('8h 30m', $result);
    }

    public function test_calculates_overnight_shift_hours(): void
    {
        $result = $this->service->calculateHours('22:00', '02:00');
        $this->assertEquals('4h (overnight)', $result);
    }

    public function test_calculates_overnight_shift_with_minutes(): void
    {
        $result = $this->service->calculateHours('22:30', '02:15');
        $this->assertEquals('3h 45m (overnight)', $result);
    }

    public function test_calculates_long_overnight_shift(): void
    {
        $result = $this->service->calculateHours('20:00', '06:00');
        $this->assertEquals('10h (overnight)', $result);
    }

    public function test_calculates_full_24_hour_shift(): void
    {
        $result = $this->service->calculateHours('00:00', '00:00');
        $this->assertEquals('24h (overnight)', $result);
    }

    public function test_calculates_one_hour_shift(): void
    {
        $result = $this->service->calculateHours('09:00', '10:00');
        $this->assertEquals('1h', $result);
    }

    public function test_calculates_45_minute_shift(): void
    {
        $result = $this->service->calculateHours('09:00', '09:45');
        $this->assertEquals('0h 45m', $result);
    }

    public function test_same_start_end_treated_as_overnight_24h(): void
    {
        $result = $this->service->calculateHours('12:00', '12:00');
        $this->assertEquals('24h (overnight)', $result);
    }

    public function test_calculates_early_morning_overnight_shift(): void
    {
        $result = $this->service->calculateHours('23:00', '01:00');
        $this->assertEquals('2h (overnight)', $result);
    }

    // Bug #4 Tests: Silent Failure Error Handling

    public function test_save_availability_sets_error_flag_on_insert_failure(): void
    {
        $user_id = $this->factory->user->create();
        update_user_meta($user_id, 'cs_is_team_member', '1');

        // Create a mock repository that returns false on insert
        $mock_repository = $this->createMock(AvailabilityRepository::class);
        $mock_repository->method('getTeamMembers')->willReturn([get_user_by('ID', $user_id)]);
        $mock_repository->method('deleteAvailability')->willReturn(true);
        $mock_repository->method('insertAvailability')->willReturn(false); // Simulate failure

        $service = new AvailabilityService($mock_repository);

        // Simulate form submission
        $_POST['cs_availability_nonce'] = wp_create_nonce('cs_save_availability');
        $_POST['user_id'] = $user_id;
        $_POST['days'] = [
            1 => [
                'enabled' => '1',
                'start_time' => '09:00',
                'end_time' => '17:00',
            ],
        ];

        // Capture redirect URL
        add_filter('wp_redirect', function($location) {
            // Extract query string
            $parts = parse_url($location);
            parse_str($parts['query'] ?? '', $query);

            // Should have error=1 when insert fails
            $this->assertEquals('1', $query['error'] ?? '', 'Should set error=1 on insert failure');

            // Prevent actual redirect
            return false;
        });

        // This will try to redirect, but our filter will catch it
        try {
            $service->saveAvailability();
        } catch (\Exception $e) {
            // wp_redirect calls exit(), which we can't prevent
        }

        unset($_POST['cs_availability_nonce']);
        unset($_POST['user_id']);
        unset($_POST['days']);
    }

    public function test_prepare_data_includes_show_error_flag(): void
    {
        $user_id = $this->factory->user->create();
        update_user_meta($user_id, 'cs_is_team_member', '1');

        // Simulate error query parameter
        $_GET['error'] = '1';
        $_GET['user_id'] = $user_id;

        $data = $this->service->prepareData();

        $this->assertTrue($data['show_error'], 'Should set show_error to true when error=1 in query');

        unset($_GET['error']);
        unset($_GET['user_id']);
    }

    public function test_prepare_data_includes_show_success_flag(): void
    {
        $user_id = $this->factory->user->create();
        update_user_meta($user_id, 'cs_is_team_member', '1');

        // Simulate success query parameter
        $_GET['updated'] = '1';
        $_GET['user_id'] = $user_id;

        $data = $this->service->prepareData();

        $this->assertTrue($data['show_success'], 'Should set show_success to true when updated=1 in query');

        unset($_GET['updated']);
        unset($_GET['user_id']);
    }

    public function test_prepare_data_flags_are_false_without_query_params(): void
    {
        $user_id = $this->factory->user->create();
        update_user_meta($user_id, 'cs_is_team_member', '1');

        $_GET['user_id'] = $user_id;

        $data = $this->service->prepareData();

        $this->assertFalse($data['show_error'], 'show_error should be false without error parameter');
        $this->assertFalse($data['show_success'], 'show_success should be false without updated parameter');

        unset($_GET['user_id']);
    }
}
