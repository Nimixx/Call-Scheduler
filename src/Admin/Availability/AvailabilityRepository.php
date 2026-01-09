<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Availability;

use CallScheduler\Cache;
use CallScheduler\ConsultantRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles database operations for availability data
 */
final class AvailabilityRepository
{
    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
    }

    public function getTeamMembers(): array
    {
        return $this->cache->remember(
            'team_members',
            fn() => get_users([
                'meta_key' => 'cs_is_team_member',
                'meta_value' => '1',
            ]),
            12 * HOUR_IN_SECONDS
        );
    }

    public function getAvailability(int $user_id): array
    {
        return $this->cache->remember(
            "availability_{$user_id}",
            function () use ($user_id) {
                global $wpdb;

                $consultant = $this->getConsultantForUser($user_id);
                if ($consultant === null) {
                    return [];
                }

                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT day_of_week, start_time, end_time
                     FROM {$wpdb->prefix}cs_availability
                     WHERE consultant_id = %d",
                    $consultant->id
                ));

                $availability = [];
                foreach ($results as $row) {
                    $availability[(int) $row->day_of_week] = $row;
                }

                return $availability;
            },
            DAY_IN_SECONDS
        );
    }

    public function deleteAvailability(int $user_id): void
    {
        global $wpdb;

        $consultant = $this->getConsultantForUser($user_id);
        if ($consultant !== null) {
            $wpdb->delete($wpdb->prefix . 'cs_availability', ['consultant_id' => $consultant->id]);
        }

        $this->cache->delete("availability_{$user_id}");
    }

    public function insertAvailability(int $user_id, int $day_num, string $start_time, string $end_time): bool
    {
        global $wpdb;

        $consultant = $this->getConsultantForUser($user_id);
        if ($consultant === null) {
            return false;
        }

        $result = $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'consultant_id' => $consultant->id,
            'user_id' => $user_id,
            'day_of_week' => $day_num,
            'start_time' => $start_time . ':00',
            'end_time' => $end_time . ':00',
        ]);

        if ($result !== false) {
            $this->cache->delete("availability_{$user_id}");
        }

        return $result !== false;
    }

    private function getConsultantForUser(int $user_id): ?\CallScheduler\Consultant
    {
        $repository = new ConsultantRepository();
        return $repository->findByWpUserId($user_id);
    }

    public function isPluginInstalled(): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_availability';
        $query = $wpdb->prepare('SHOW TABLES LIKE %s', $table);

        return $wpdb->get_var($query) === $table;
    }

    public function invalidateTeamMembersCache(): void
    {
        $this->cache->delete('team_members');
    }
}
