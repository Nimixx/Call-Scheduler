<?php

declare(strict_types=1);

namespace CallScheduler\Rest;

use CallScheduler\Config;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsController extends RestController
{
    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getSettings(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $error = $this->checkReadRateLimit('settings');
        if ($error) {
            return $error;
        }

        $data = [
            'slot_duration' => Config::getSlotDuration(),
            'buffer_time' => Config::getBufferTime(),
        ];

        return $this->successResponse($data, 'settings');
    }
}
