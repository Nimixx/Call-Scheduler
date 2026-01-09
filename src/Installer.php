<?php

declare(strict_types=1);

namespace CallScheduler;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer
{
    public static function activate(): void
    {
        self::createTables();

        // Run migrations (creates consultant profiles for existing team members)
        self::migrateTeamMembersToConsultants();
        self::migrateAvailabilityToConsultantId();
        self::migrateBookingsToConsultantId();

        self::setDbVersion();
        flush_rewrite_rules();

        // Set activation notice (expires in 1 hour)
        set_transient('cs_activation_notice', true, HOUR_IN_SECONDS);
    }

    private static function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create availability table
        $sql_availability = "CREATE TABLE {$wpdb->prefix}cs_availability (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            day_of_week TINYINT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            UNIQUE KEY unique_slot (user_id, day_of_week)
        ) $charset_collate;";

        dbDelta($sql_availability);

        // Create bookings table
        // Indexes optimized for:
        // - Admin list: ORDER BY booking_date DESC, booking_time DESC
        // - Status filter: WHERE status = X ORDER BY booking_date DESC
        // - Date range filter: WHERE booking_date BETWEEN X AND Y
        // - Availability check: WHERE user_id = X AND booking_date = Y AND status IN (...)
        $sql_bookings = "CREATE TABLE {$wpdb->prefix}cs_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            booking_date DATE NOT NULL,
            booking_time TIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_date_status (user_id, booking_date, status),
            KEY idx_status_date (status, booking_date DESC),
            KEY idx_date_time (booking_date DESC, booking_time DESC)
        ) $charset_collate;";

        dbDelta($sql_bookings);

        self::addUniqueBookingConstraint();
        self::createConsultantsTable();
    }

    private static function createConsultantsTable(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$wpdb->prefix}cs_consultants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            public_id VARCHAR(8) NOT NULL,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            display_name VARCHAR(255) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            bio TEXT DEFAULT NULL,
            is_active TINYINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_public_id (public_id),
            UNIQUE KEY unique_wp_user (wp_user_id),
            KEY idx_active (is_active)
        ) $charset_collate;";

        dbDelta($sql);
    }

    private static function addUniqueBookingConstraint(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // Check if column exists
        $column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'is_active'");

        if ($column) {
            return;
        }

        // Add generated column: 1 for active bookings, NULL for cancelled
        // NULL values are ignored in unique indexes
        $cancelled_status = BookingStatus::CANCELLED;
        $wpdb->query("
            ALTER TABLE {$table}
            ADD COLUMN is_active TINYINT UNSIGNED
                GENERATED ALWAYS AS (IF(status = '{$cancelled_status}', NULL, 1)) STORED
        ");

        // Add unique constraint on active bookings only
        $wpdb->query("
            ALTER TABLE {$table}
            ADD UNIQUE KEY unique_active_booking (user_id, booking_date, booking_time, is_active)
        ");
    }

    private static function setDbVersion(): void
    {
        update_option('cs_db_version', CS_VERSION);
    }

    /**
     * Run database upgrades if needed
     *
     * Call this on plugin init to handle upgrades for existing installations
     */
    public static function maybeUpgrade(): void
    {
        $current_version = get_option('cs_db_version', '0.0.0');

        // Skip if already up to date
        if (version_compare($current_version, CS_VERSION, '>=')) {
            return;
        }

        // Create consultants table if needed
        self::createConsultantsTable();

        // Migrate team members to consultants
        self::migrateTeamMembersToConsultants();

        // Add consultant_id to availability and bookings
        self::migrateAvailabilityToConsultantId();
        self::migrateBookingsToConsultantId();

        // Add optimized indexes (safe to run multiple times)
        self::addOptimizedIndexes();

        self::setDbVersion();
    }

    /**
     * Migrate existing team members to consultants table
     */
    private static function migrateTeamMembersToConsultants(): void
    {
        $repository = new ConsultantRepository();

        $team_members = get_users([
            'meta_key' => 'cs_is_team_member',
            'meta_value' => '1',
        ]);

        foreach ($team_members as $user) {
            // Skip if already has consultant profile
            if ($repository->findByWpUserId($user->ID) !== null) {
                continue;
            }

            $repository->createForUser($user->ID);
        }
    }

    /**
     * Add consultant_id column to availability table and migrate data
     */
    private static function migrateAvailabilityToConsultantId(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_availability';

        // Check if consultant_id column exists
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'consultant_id'));

        if ($column) {
            return; // Already migrated
        }

        // Add consultant_id column
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN consultant_id BIGINT UNSIGNED DEFAULT NULL AFTER id");

        // Populate consultant_id from user_id
        $wpdb->query("
            UPDATE {$table} a
            INNER JOIN {$wpdb->prefix}cs_consultants c ON a.user_id = c.wp_user_id
            SET a.consultant_id = c.id
        ");

        // Add index
        $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_consultant (consultant_id)");
    }

    /**
     * Add consultant_id column to bookings table and migrate data
     */
    private static function migrateBookingsToConsultantId(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // Check if consultant_id column exists
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'consultant_id'));

        if ($column) {
            return; // Already migrated
        }

        // Add consultant_id column
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN consultant_id BIGINT UNSIGNED DEFAULT NULL AFTER id");

        // Populate consultant_id from user_id
        $wpdb->query("
            UPDATE {$table} b
            INNER JOIN {$wpdb->prefix}cs_consultants c ON b.user_id = c.wp_user_id
            SET b.consultant_id = c.id
        ");

        // Add index for consultant_id
        $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_consultant (consultant_id)");
    }

    /**
     * Add optimized indexes for better query performance
     *
     * Safe to run multiple times - checks if index exists before adding
     */
    private static function addOptimizedIndexes(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'cs_bookings';

        // Get existing indexes
        $existing = $wpdb->get_results("SHOW INDEX FROM {$table}");
        $existing_keys = array_column($existing, 'Key_name');

        // Add composite index for user + date + status (availability queries)
        if (!in_array('idx_user_date_status', $existing_keys)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_user_date_status (user_id, booking_date, status)");
        }

        // Add composite index for status + date (admin filtering)
        if (!in_array('idx_status_date', $existing_keys)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_status_date (status, booking_date DESC)");
        }

        // Add composite index for date + time (admin sorting)
        if (!in_array('idx_date_time', $existing_keys)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_date_time (booking_date DESC, booking_time DESC)");
        }

        // Remove old single-column indexes if composite ones are added (optional, reduces index overhead)
        if (in_array('idx_user_date_status', $existing_keys)) {
            if (in_array('user_id', $existing_keys)) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX user_id");
            }
            if (in_array('booking_date', $existing_keys)) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX booking_date");
            }
            if (in_array('status', $existing_keys)) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX status");
            }
        }
    }
}
