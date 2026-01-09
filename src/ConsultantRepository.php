<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository for consultant database operations
 */
final class ConsultantRepository
{
    private Cache $cache;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
    }

    /**
     * Create consultant profile for WordPress user
     */
    public function createForUser(int $wpUserId, ?string $title = null, ?string $bio = null): Consultant
    {
        global $wpdb;

        $user = get_user_by('ID', $wpUserId);
        $displayName = $user ? $user->display_name : 'Unknown';

        $publicId = Consultant::generatePublicId();

        // Ensure unique public_id (regenerate if collision)
        while ($this->findByPublicId($publicId) !== null) {
            $publicId = Consultant::generatePublicId();
        }

        $wpdb->insert(
            $wpdb->prefix . 'cs_consultants',
            [
                'public_id' => $publicId,
                'wp_user_id' => $wpUserId,
                'display_name' => $displayName,
                'title' => $title,
                'bio' => $bio,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%s']
        );

        $this->cache->delete('consultants_active');

        return $this->findById($wpdb->insert_id);
    }

    /**
     * Find consultant by internal ID
     */
    public function findById(int $id): ?Consultant
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cs_consultants WHERE id = %d",
            $id
        ));

        return $row ? Consultant::fromRow($row) : null;
    }

    /**
     * Find consultant by public ID (used in REST API)
     */
    public function findByPublicId(string $publicId): ?Consultant
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cs_consultants WHERE public_id = %s",
            $publicId
        ));

        return $row ? Consultant::fromRow($row) : null;
    }

    /**
     * Find consultant by WordPress user ID
     */
    public function findByWpUserId(int $wpUserId): ?Consultant
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cs_consultants WHERE wp_user_id = %d",
            $wpUserId
        ));

        return $row ? Consultant::fromRow($row) : null;
    }

    /**
     * Get all active consultants
     */
    public function getActiveConsultants(): array
    {
        return $this->cache->remember(
            'consultants_active',
            function () {
                global $wpdb;

                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}cs_consultants WHERE is_active = %d ORDER BY display_name",
                    1
                ));

                return array_map([Consultant::class, 'fromRow'], $rows);
            },
            12 * HOUR_IN_SECONDS
        );
    }

    /**
     * Set consultant active status
     */
    public function setActive(int $id, bool $active): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'cs_consultants',
            ['is_active' => $active ? 1 : 0],
            ['id' => $id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            $this->cache->delete('consultants_active');
        }

        return $result !== false;
    }

    /**
     * Update consultant profile fields
     */
    public function updateProfile(int $id, string $displayName, ?string $title, ?string $bio): bool
    {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'cs_consultants',
            [
                'display_name' => $displayName,
                'title' => $title,
                'bio' => $bio,
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result !== false) {
            $this->cache->delete('consultants_active');
        }

        return $result !== false;
    }

    /**
     * Invalidate cache
     */
    public function invalidateCache(): void
    {
        $this->cache->delete('consultants_active');
    }
}
