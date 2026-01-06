<?php

declare(strict_types=1);

namespace CallScheduler;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

final class Seeder
{
    public static function run(): void
    {
        $user_id = self::createTeamMember();
        self::createAvailability($user_id);
        self::createBookings($user_id);
    }

    private static function createTeamMember(): int
    {
        $existing = get_users([
            'meta_key' => 'cs_is_team_member',
            'meta_value' => '1',
            'number' => 1,
        ]);

        if (!empty($existing)) {
            return $existing[0]->ID;
        }

        $user_id = wp_insert_user([
            'user_login' => 'team_member',
            'user_email' => 'team@example.com',
            'user_pass' => wp_generate_password(),
            'display_name' => 'Jan Novák',
            'role' => 'editor',
        ]);

        if (is_wp_error($user_id)) {
            // User might already exist - try to get it
            $user = get_user_by('login', 'team_member');
            if ($user === false) {
                // Fallback: get first admin user
                $admins = get_users(['role' => 'administrator', 'number' => 1]);
                if (empty($admins)) {
                    wp_die('Cannot create or find a user for seeding.');
                }
                $user_id = $admins[0]->ID;
            } else {
                $user_id = $user->ID;
            }
        }

        update_user_meta($user_id, 'cs_is_team_member', '1');

        return $user_id;
    }

    private static function createAvailability(int $user_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_availability';

        // Clear existing
        $wpdb->delete($table, ['user_id' => $user_id]);

        // PO-PA (Mon-Fri): 08:00-18:00, ST (Wed): 09:00-14:00
        $schedule = [
            1 => ['08:00:00', '18:00:00'], // Pondělí
            2 => ['08:00:00', '18:00:00'], // Úterý
            3 => ['09:00:00', '14:00:00'], // Středa
            4 => ['08:00:00', '18:00:00'], // Čtvrtek
            5 => ['08:00:00', '18:00:00'], // Pátek
        ];

        foreach ($schedule as $day => $times) {
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'day_of_week' => $day,
                'start_time' => $times[0],
                'end_time' => $times[1],
            ]);
        }
    }

    private static function createBookings(int $user_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // Clear existing seed bookings
        $wpdb->query("DELETE FROM {$table} WHERE customer_email LIKE '%@example.com'");

        // Find next Monday
        $next_monday = date('Y-m-d', strtotime('next monday'));

        $bookings = [
            [
                'customer_name' => 'Petr Svoboda',
                'customer_email' => 'petr@example.com',
                'booking_date' => $next_monday,
                'booking_time' => '09:00:00',
                'status' => BookingStatus::CONFIRMED,
            ],
            [
                'customer_name' => 'Marie Dvořáková',
                'customer_email' => 'marie@example.com',
                'booking_date' => $next_monday,
                'booking_time' => '14:00:00',
                'status' => BookingStatus::PENDING,
            ],
            [
                'customer_name' => 'Tomáš Horák',
                'customer_email' => 'tomas@example.com',
                'booking_date' => date('Y-m-d', strtotime($next_monday . ' +1 day')),
                'booking_time' => '10:00:00',
                'status' => BookingStatus::CONFIRMED,
            ],
            [
                'customer_name' => 'Eva Marková',
                'customer_email' => 'eva@example.com',
                'booking_date' => date('Y-m-d', strtotime($next_monday . ' +2 day')),
                'booking_time' => '11:00:00',
                'status' => BookingStatus::PENDING,
            ],
            [
                'customer_name' => 'Zrušený Klient',
                'customer_email' => 'cancelled@example.com',
                'booking_date' => $next_monday,
                'booking_time' => '16:00:00',
                'status' => BookingStatus::CANCELLED,
            ],
        ];

        foreach ($bookings as $booking) {
            $wpdb->insert($table, array_merge($booking, [
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
            ]));
        }
    }
}
