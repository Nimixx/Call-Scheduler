<?php

declare(strict_types=1);

namespace CallScheduler\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class TeamMembersController extends RestController
{
    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/team-members', [
            'methods' => 'GET',
            'callback' => [$this, 'getTeamMembers'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getTeamMembers(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $error = $this->checkReadRateLimit('team-members');
        if ($error) {
            return $error;
        }

        global $wpdb;

        $users = get_users([
            'meta_key' => 'cs_is_team_member',
            'meta_value' => '1',
        ]);

        $data = array_map(function ($user) use ($wpdb) {
            $available_days = $wpdb->get_col($wpdb->prepare(
                "SELECT day_of_week FROM {$wpdb->prefix}cs_availability WHERE user_id = %d",
                $user->ID
            ));

            return [
                'id' => $user->ID,
                'name' => $user->display_name,
                'available_days' => array_map('intval', $available_days),
            ];
        }, $users);

        return $this->successResponse($data, 'team-members');
    }
}
