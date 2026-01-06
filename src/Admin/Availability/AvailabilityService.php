<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Availability;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles business logic for availability management
 */
final class AvailabilityService
{
    private AvailabilityRepository $repository;

    public function __construct(AvailabilityRepository $repository)
    {
        $this->repository = $repository;
    }

    public function prepareData(): array
    {
        $team_members = $this->repository->getTeamMembers();
        $selected_user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

        // Auto-select first team member if none selected
        if ($selected_user_id === 0 && !empty($team_members)) {
            $selected_user_id = $team_members[0]->ID;
        }

        return [
            'team_members' => $team_members,
            'selected_user_id' => $selected_user_id,
            'selected_member' => $selected_user_id > 0 ? get_user_by('ID', $selected_user_id) : null,
            'availability' => $selected_user_id > 0 ? $this->repository->getAvailability($selected_user_id) : [],
            'show_success' => isset($_GET['updated']) && $_GET['updated'] === '1',
            'show_error' => isset($_GET['error']) && $_GET['error'] === '1',
        ];
    }

    public function saveAvailability(): void
    {
        if (!isset($_POST['cs_availability_nonce']) || !wp_verify_nonce($_POST['cs_availability_nonce'], 'cs_save_availability')) {
            wp_die(__('Security check failed.', 'call-scheduler'));
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if ($user_id === 0) {
            return;
        }

        // Delete existing availability
        $this->repository->deleteAvailability($user_id);

        // Insert new availability
        $days = isset($_POST['days']) ? $_POST['days'] : [];
        $has_error = false;

        foreach ($days as $day_num => $day_data) {
            if (empty($day_data['enabled'])) {
                continue;
            }

            $start_time = sanitize_text_field($day_data['start_time']);
            $end_time = sanitize_text_field($day_data['end_time']);

            // Validate time format
            if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
                continue;
            }

            $result = $this->repository->insertAvailability($user_id, absint($day_num), $start_time, $end_time);
            if ($result === false) {
                $has_error = true;
            }
        }

        // Redirect to avoid form resubmission
        $redirect_args = ['user_id' => $user_id];
        if ($has_error) {
            $redirect_args['error'] = '1';
        } else {
            $redirect_args['updated'] = '1';
        }

        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php?page=cs-availability')));
        exit;
    }

    public function calculateHours(string $start, string $end): string
    {
        $start_parts = explode(':', $start);
        $end_parts = explode(':', $end);

        $start_minutes = (int)$start_parts[0] * 60 + (int)$start_parts[1];
        $end_minutes = (int)$end_parts[0] * 60 + (int)$end_parts[1];

        $diff_minutes = $end_minutes - $start_minutes;

        // Handle overnight shifts (end <= start means it wraps to next day)
        $is_overnight = $diff_minutes <= 0;
        if ($is_overnight) {
            $diff_minutes += 1440; // Add 24 hours (24 * 60 minutes)
        }

        $hours = floor($diff_minutes / 60);
        $minutes = $diff_minutes % 60;

        $time_text = $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'm' : '');
        return $is_overnight ? $time_text . ' (overnight)' : $time_text;
    }
}
