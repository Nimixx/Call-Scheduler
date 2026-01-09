<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

final class Seeder
{
    public static function run(): void
    {
        $user_id = self::createTeamMember();
        $consultant = self::ensureConsultant($user_id);
        self::createAvailability($consultant->id, $user_id);
        self::createBookings($consultant->id, $user_id);
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
            $user = get_user_by('login', 'team_member');
            if ($user === false) {
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

    private static function ensureConsultant(int $user_id): Consultant
    {
        $repository = new ConsultantRepository();
        $consultant = $repository->findByWpUserId($user_id);

        if ($consultant === null) {
            $consultant = $repository->createForUser($user_id, 'Obchodní konzultant', 'Zkušený konzultant pro vaše potřeby.');
        }

        return $consultant;
    }

    private static function createAvailability(int $consultant_id, int $user_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_availability';

        // Clear existing
        $wpdb->delete($table, ['consultant_id' => $consultant_id]);

        // Mon-Fri: 08:00-18:00, Wed: 09:00-14:00
        $schedule = [
            1 => ['08:00:00', '18:00:00'],
            2 => ['08:00:00', '18:00:00'],
            3 => ['09:00:00', '14:00:00'],
            4 => ['08:00:00', '18:00:00'],
            5 => ['08:00:00', '18:00:00'],
        ];

        foreach ($schedule as $day => $times) {
            $wpdb->insert($table, [
                'consultant_id' => $consultant_id,
                'user_id' => $user_id,
                'day_of_week' => $day,
                'start_time' => $times[0],
                'end_time' => $times[1],
            ]);
        }
    }

    private static function createBookings(int $consultant_id, int $user_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // Clear existing seed bookings
        $wpdb->query("DELETE FROM {$table} WHERE customer_email LIKE '%@example.com'");

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
                'consultant_id' => $consultant_id,
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
            ]));
        }
    }
}
