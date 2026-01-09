# Consultant Profiles Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a security abstraction layer that hides WordPress user IDs from public REST API by introducing consultant profiles.

**Architecture:** New `wp_cs_consultants` table stores public-facing consultant data with 8-char random IDs. Existing tables (`cs_availability`, `cs_bookings`) will reference `consultant_id` instead of `user_id`. Auto-creation on team member enable.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, MySQL

---

## Task 1: Create Consultant Entity Class

**Files:**
- Create: `src/Consultant.php`

**Step 1: Create the entity class**

```php
<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consultant entity - public-facing abstraction over WordPress users
 */
final class Consultant
{
    public readonly int $id;
    public readonly string $publicId;
    public readonly int $wpUserId;
    public readonly string $displayName;
    public readonly ?string $title;
    public readonly ?string $bio;
    public readonly bool $isActive;
    public readonly string $createdAt;

    public function __construct(
        int $id,
        string $publicId,
        int $wpUserId,
        string $displayName,
        ?string $title,
        ?string $bio,
        bool $isActive,
        string $createdAt
    ) {
        $this->id = $id;
        $this->publicId = $publicId;
        $this->wpUserId = $wpUserId;
        $this->displayName = $displayName;
        $this->title = $title;
        $this->bio = $bio;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
    }

    /**
     * Create from database row
     */
    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->id,
            $row->public_id,
            (int) $row->wp_user_id,
            $row->display_name,
            $row->title,
            $row->bio,
            (bool) $row->is_active,
            $row->created_at
        );
    }

    /**
     * Generate unique 8-char public ID
     */
    public static function generatePublicId(): string
    {
        return bin2hex(random_bytes(4));
    }
}
```

**Step 2: Commit**

```bash
git add src/Consultant.php
git commit -m "feat: add Consultant entity class"
```

---

## Task 2: Create ConsultantRepository

**Files:**
- Create: `src/ConsultantRepository.php`
- Create: `tests/ConsultantRepositoryTest.php`

**Step 1: Write failing test**

```php
<?php

declare(strict_types=1);

namespace CallScheduler\Tests;

use CallScheduler\Consultant;
use CallScheduler\ConsultantRepository;
use WP_UnitTestCase;

class ConsultantRepositoryTest extends WP_UnitTestCase
{
    private ConsultantRepository $repository;
    private int $wpUserId;

    public function set_up(): void
    {
        parent::set_up();
        $this->repository = new ConsultantRepository();
        $this->wpUserId = $this->factory->user->create(['display_name' => 'Test User']);
    }

    public function test_creates_consultant_for_user(): void
    {
        $consultant = $this->repository->createForUser($this->wpUserId);

        $this->assertInstanceOf(Consultant::class, $consultant);
        $this->assertEquals($this->wpUserId, $consultant->wpUserId);
        $this->assertEquals('Test User', $consultant->displayName);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}$/', $consultant->publicId);
        $this->assertTrue($consultant->isActive);
    }

    public function test_finds_consultant_by_public_id(): void
    {
        $created = $this->repository->createForUser($this->wpUserId);

        $found = $this->repository->findByPublicId($created->publicId);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_finds_consultant_by_wp_user_id(): void
    {
        $created = $this->repository->createForUser($this->wpUserId);

        $found = $this->repository->findByWpUserId($this->wpUserId);

        $this->assertNotNull($found);
        $this->assertEquals($created->id, $found->id);
    }

    public function test_returns_null_for_nonexistent_public_id(): void
    {
        $found = $this->repository->findByPublicId('nonexist');

        $this->assertNull($found);
    }

    public function test_get_active_consultants(): void
    {
        $this->repository->createForUser($this->wpUserId);

        $user2 = $this->factory->user->create(['display_name' => 'User 2']);
        $this->repository->createForUser($user2);

        $active = $this->repository->getActiveConsultants();

        $this->assertCount(2, $active);
    }

    public function test_deactivates_consultant(): void
    {
        $consultant = $this->repository->createForUser($this->wpUserId);

        $this->repository->setActive($consultant->id, false);

        $found = $this->repository->findByPublicId($consultant->publicId);
        $this->assertFalse($found->isActive);
    }

    public function test_updates_consultant_profile(): void
    {
        $consultant = $this->repository->createForUser($this->wpUserId);

        $this->repository->updateProfile($consultant->id, 'New Name', 'Sales Rep', 'Bio text');

        $found = $this->repository->findByPublicId($consultant->publicId);
        $this->assertEquals('New Name', $found->displayName);
        $this->assertEquals('Sales Rep', $found->title);
        $this->assertEquals('Bio text', $found->bio);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `composer test -- --filter=ConsultantRepositoryTest`
Expected: FAIL with "Class ConsultantRepository not found"

**Step 3: Create ConsultantRepository**

```php
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

                $rows = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}cs_consultants WHERE is_active = 1 ORDER BY display_name"
                );

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
```

**Step 4: Run test to verify it passes**

Run: `composer test -- --filter=ConsultantRepositoryTest`
Expected: Tests will fail because table doesn't exist yet (expected at this stage)

**Step 5: Commit**

```bash
git add src/ConsultantRepository.php tests/ConsultantRepositoryTest.php
git commit -m "feat: add ConsultantRepository with tests"
```

---

## Task 3: Create Consultants Table

**Files:**
- Modify: `src/Installer.php`

**Step 1: Add table creation method**

In `src/Installer.php`, add new method after `createTables()`:

```php
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
```

**Step 2: Call from createTables()**

Add to end of `createTables()` method:

```php
self::createConsultantsTable();
```

**Step 3: Run ConsultantRepository tests**

Run: `composer test -- --filter=ConsultantRepositoryTest`
Expected: PASS (after plugin reactivation in test)

**Step 4: Commit**

```bash
git add src/Installer.php
git commit -m "feat: add wp_cs_consultants table creation"
```

---

## Task 4: Add Migration for Existing Data

**Files:**
- Modify: `src/Installer.php`

**Step 1: Add migration method**

Add to `src/Installer.php`:

```php
/**
 * Migrate existing team members to consultants table
 *
 * Creates consultant profiles for all users with cs_is_team_member = '1'
 * who don't already have a consultant profile.
 */
private static function migrateTeamMembersToConsultants(): void
{
    $repository = new \CallScheduler\ConsultantRepository();

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
```

**Step 2: Add column migration for availability table**

Add method:

```php
/**
 * Add consultant_id column to availability table and migrate data
 */
private static function migrateAvailabilityToConsultantId(): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'cs_availability';

    // Check if consultant_id column exists
    $column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'consultant_id'");

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

    // Note: user_id column kept for now, will be removed in future version
}
```

**Step 3: Add column migration for bookings table**

Add method:

```php
/**
 * Add consultant_id column to bookings table and migrate data
 */
private static function migrateBookingsToConsultantId(): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'cs_bookings';

    // Check if consultant_id column exists
    $column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'consultant_id'");

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

    // Update unique constraint to use consultant_id
    // First drop old constraint if exists
    $wpdb->query("ALTER TABLE {$table} DROP INDEX IF EXISTS unique_active_booking");

    // Re-add with consultant_id
    $cancelled_status = \CallScheduler\BookingStatus::CANCELLED;
    $wpdb->query("
        ALTER TABLE {$table}
        ADD UNIQUE KEY unique_active_booking (consultant_id, booking_date, booking_time, is_active)
    ");
}
```

**Step 4: Update maybeUpgrade() to run migration**

Modify `maybeUpgrade()`:

```php
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
```

**Step 5: Commit**

```bash
git add src/Installer.php
git commit -m "feat: add migration from user_id to consultant_id"
```

---

## Task 5: Update UserProfile for Auto-Creation

**Files:**
- Modify: `src/Admin/UserProfile.php`

**Step 1: Add auto-creation in saveField()**

Update `saveField()` method to create consultant profile when team member is enabled:

```php
public function saveField(int $user_id): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id)) {
        return;
    }

    $is_team_member = isset($_POST['cs_is_team_member']) ? '1' : '0';
    $was_team_member = get_user_meta($user_id, 'cs_is_team_member', true) === '1';

    update_user_meta($user_id, 'cs_is_team_member', $is_team_member);

    // Handle consultant profile
    $repository = new \CallScheduler\ConsultantRepository();
    $consultant = $repository->findByWpUserId($user_id);

    if ($is_team_member === '1') {
        // Create consultant if doesn't exist
        if ($consultant === null) {
            $title = isset($_POST['cs_consultant_title']) ? sanitize_text_field($_POST['cs_consultant_title']) : null;
            $bio = isset($_POST['cs_consultant_bio']) ? sanitize_textarea_field($_POST['cs_consultant_bio']) : null;
            $consultant = $repository->createForUser($user_id, $title, $bio);
        } else {
            // Update existing consultant
            $displayName = isset($_POST['cs_consultant_display_name'])
                ? sanitize_text_field($_POST['cs_consultant_display_name'])
                : $consultant->displayName;
            $title = isset($_POST['cs_consultant_title']) ? sanitize_text_field($_POST['cs_consultant_title']) : null;
            $bio = isset($_POST['cs_consultant_bio']) ? sanitize_textarea_field($_POST['cs_consultant_bio']) : null;

            $repository->updateProfile($consultant->id, $displayName, $title, $bio);

            // Reactivate if was deactivated
            if (!$consultant->isActive) {
                $repository->setActive($consultant->id, true);
            }
        }
    } elseif ($consultant !== null && $was_team_member) {
        // Deactivate consultant when team member disabled
        $repository->setActive($consultant->id, false);
    }

    // Invalidate team members cache when team member status changes
    $this->cache->delete('team_members');
}
```

**Step 2: Add consultant fields to renderField()**

Update `renderField()` to add consultant profile fields:

```php
public function renderField(WP_User $user): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $is_team_member = get_user_meta($user->ID, 'cs_is_team_member', true) === '1';

    $repository = new \CallScheduler\ConsultantRepository();
    $consultant = $repository->findByWpUserId($user->ID);

    $display_name = $consultant ? $consultant->displayName : $user->display_name;
    $title = $consultant ? $consultant->title : '';
    $bio = $consultant ? $consultant->bio : '';
    ?>
    <h3><?php echo esc_html__('Nastavení rezervací', 'call-scheduler'); ?></h3>
    <table class="form-table">
        <tr>
            <th>
                <label for="cs_is_team_member">
                    <?php echo esc_html__('Dostupnost pro rezervace', 'call-scheduler'); ?>
                </label>
            </th>
            <td>
                <label>
                    <input type="checkbox"
                           name="cs_is_team_member"
                           id="cs_is_team_member"
                           value="1"
                           <?php checked($is_team_member); ?> />
                    <?php echo esc_html__('Tento uživatel je dostupný pro rezervace', 'call-scheduler'); ?>
                </label>
                <p class="description">
                    <?php echo esc_html__('Pokud je zaškrtnuto, tento uživatel se zobrazí jako možnost pro rezervace a bude možné nastavit jeho dostupnost.', 'call-scheduler'); ?>
                </p>
            </td>
        </tr>
    </table>

    <div id="cs-consultant-fields" style="<?php echo $is_team_member ? '' : 'display:none;'; ?>">
        <h3><?php echo esc_html__('Profil konzultanta', 'call-scheduler'); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="cs_consultant_display_name">
                        <?php echo esc_html__('Zobrazované jméno', 'call-scheduler'); ?>
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="cs_consultant_display_name"
                           id="cs_consultant_display_name"
                           value="<?php echo esc_attr($display_name); ?>"
                           class="regular-text" />
                    <p class="description">
                        <?php echo esc_html__('Jméno zobrazované zákazníkům při rezervaci.', 'call-scheduler'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="cs_consultant_title">
                        <?php echo esc_html__('Titul / Pozice', 'call-scheduler'); ?>
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="cs_consultant_title"
                           id="cs_consultant_title"
                           value="<?php echo esc_attr($title); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr__('např. Obchodní konzultant', 'call-scheduler'); ?>" />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="cs_consultant_bio">
                        <?php echo esc_html__('Krátký popis', 'call-scheduler'); ?>
                    </label>
                </th>
                <td>
                    <textarea name="cs_consultant_bio"
                              id="cs_consultant_bio"
                              rows="3"
                              class="large-text"><?php echo esc_textarea($bio); ?></textarea>
                </td>
            </tr>
        </table>
    </div>

    <script>
    jQuery(function($) {
        $('#cs_is_team_member').on('change', function() {
            $('#cs-consultant-fields').toggle(this.checked);
        });
    });
    </script>
    <?php
}
```

**Step 3: Commit**

```bash
git add src/Admin/UserProfile.php
git commit -m "feat: add consultant profile fields to user profile"
```

---

## Task 6: Update TeamMembersController

**Files:**
- Modify: `src/Rest/TeamMembersController.php`
- Modify: `tests/Rest/TeamMembersControllerTest.php`

**Step 1: Update test for new response format**

Update `tests/Rest/TeamMembersControllerTest.php`:

```php
public function test_returns_team_members_with_consultant_data(): void
{
    // Create team member with consultant profile
    $user_id = $this->factory->user->create(['display_name' => 'John Doe']);
    update_user_meta($user_id, 'cs_is_team_member', '1');

    $repository = new \CallScheduler\ConsultantRepository();
    $consultant = $repository->createForUser($user_id);
    $repository->updateProfile($consultant->id, 'John Doe', 'Sales Rep', null);

    $request = new WP_REST_Request('GET', '/cs/v1/team-members');
    $response = rest_do_request($request);

    $this->assertEquals(200, $response->get_status());

    $data = $response->get_data();
    $this->assertCount(1, $data);
    $this->assertEquals($consultant->publicId, $data[0]['id']);
    $this->assertEquals('John Doe', $data[0]['name']);
    $this->assertEquals('Sales Rep', $data[0]['title']);
    $this->assertArrayNotHasKey('wp_user_id', $data[0]); // Ensure WP ID not exposed
}
```

**Step 2: Run test to verify it fails**

Run: `composer test -- --filter=TeamMembersControllerTest::test_returns_team_members_with_consultant_data`
Expected: FAIL

**Step 3: Update TeamMembersController**

Replace `getTeamMembers()` method in `src/Rest/TeamMembersController.php`:

```php
public function getTeamMembers(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $error = $this->checkReadRateLimit('team-members');
    if ($error) {
        return $error;
    }

    global $wpdb;

    $repository = new \CallScheduler\ConsultantRepository();
    $consultants = $repository->getActiveConsultants();

    $data = array_map(function ($consultant) use ($wpdb) {
        $available_days = $wpdb->get_col($wpdb->prepare(
            "SELECT day_of_week FROM {$wpdb->prefix}cs_availability WHERE consultant_id = %d",
            $consultant->id
        ));

        return [
            'id' => $consultant->publicId,
            'name' => $consultant->displayName,
            'title' => $consultant->title,
            'available_days' => array_map('intval', $available_days),
        ];
    }, $consultants);

    return $this->successResponse($data, 'team-members');
}
```

**Step 4: Run test**

Run: `composer test -- --filter=TeamMembersControllerTest`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Rest/TeamMembersController.php tests/Rest/TeamMembersControllerTest.php
git commit -m "feat: return consultant public_id in team-members endpoint"
```

---

## Task 7: Update RestController with Consultant Validation

**Files:**
- Modify: `src/Rest/RestController.php`

**Step 1: Add consultant validation method**

Add to `src/Rest/RestController.php`:

```php
/**
 * Validate consultant exists and is active
 *
 * @param string $publicId The consultant's public ID
 * @return \CallScheduler\Consultant|WP_Error
 */
protected function validateConsultant(string $publicId): \CallScheduler\Consultant|WP_Error
{
    $repository = new \CallScheduler\ConsultantRepository();
    $consultant = $repository->findByPublicId($publicId);

    if ($consultant === null) {
        return $this->errorResponse('invalid_consultant', 'Invalid consultant.', 400);
    }

    if (!$consultant->isActive) {
        return $this->errorResponse('consultant_inactive', 'Consultant is not available.', 400);
    }

    return $consultant;
}
```

**Step 2: Commit**

```bash
git add src/Rest/RestController.php
git commit -m "feat: add validateConsultant() to RestController"
```

---

## Task 8: Update AvailabilityController

**Files:**
- Modify: `src/Rest/AvailabilityController.php`
- Modify: `tests/Rest/AvailabilityControllerTest.php`

**Step 1: Update test**

Add to `tests/Rest/AvailabilityControllerTest.php`:

```php
public function test_accepts_consultant_id_parameter(): void
{
    $user_id = $this->factory->user->create();
    update_user_meta($user_id, 'cs_is_team_member', '1');

    $repository = new \CallScheduler\ConsultantRepository();
    $consultant = $repository->createForUser($user_id);

    // Add availability
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'cs_availability', [
        'consultant_id' => $consultant->id,
        'user_id' => $user_id, // Keep for backwards compat during migration
        'day_of_week' => (int) wp_date('w'),
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    $request = new WP_REST_Request('GET', '/cs/v1/availability');
    $request->set_param('consultant_id', $consultant->publicId);
    $request->set_param('date', wp_date('Y-m-d'));

    $response = rest_do_request($request);

    $this->assertEquals(200, $response->get_status());
    $data = $response->get_data();
    $this->assertArrayHasKey('slots', $data);
}

public function test_rejects_invalid_consultant_id(): void
{
    $request = new WP_REST_Request('GET', '/cs/v1/availability');
    $request->set_param('consultant_id', 'invalid1');
    $request->set_param('date', wp_date('Y-m-d'));

    $response = rest_do_request($request);

    $this->assertEquals(400, $response->get_status());
    $this->assertEquals('invalid_consultant', $response->get_data()['code']);
}
```

**Step 2: Update AvailabilityController**

Modify `src/Rest/AvailabilityController.php`:

Change route registration args:
```php
'args' => [
    'consultant_id' => [
        'required' => true,
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ],
    'date' => [
        'required' => false,
        'type' => 'string',
        'default' => '',
        'sanitize_callback' => 'sanitize_text_field',
    ],
],
```

Update `getAvailability()` method:
```php
public function getAvailability(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $error = $this->checkReadRateLimit('availability');
    if ($error) {
        return $error;
    }

    global $wpdb;

    $consultant_id = $request->get_param('consultant_id');
    $date = $request->get_param('date') ?: wp_date('Y-m-d');

    // Validate consultant
    $consultant = $this->validateConsultant($consultant_id);
    if ($consultant instanceof WP_Error) {
        return $consultant;
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $this->errorResponse('invalid_date', 'Invalid date format. Use YYYY-MM-DD.');
    }

    // Block dates too far in the future
    $max_booking_days = Config::getMaxBookingDays();
    $max_date = wp_date('Y-m-d', strtotime("+{$max_booking_days} days"));
    if ($date > $max_date) {
        return $this->errorResponse('date_too_far', "Cannot view availability more than {$max_booking_days} days in advance.");
    }

    $day_of_week = (int) wp_date('w', strtotime($date));

    // Get availability for this day
    $availability = $wpdb->get_row($wpdb->prepare(
        "SELECT start_time, end_time FROM {$wpdb->prefix}cs_availability
         WHERE consultant_id = %d AND day_of_week = %d",
        $consultant->id,
        $day_of_week
    ));

    if (!$availability) {
        return $this->successResponse([
            'date' => $date,
            'day_of_week' => $day_of_week,
            'slots' => [],
        ], 'availability');
    }

    // Get existing bookings for this date (pending and confirmed block the slot)
    $blocking_statuses = BookingStatus::blocking();
    $status_placeholders = implode(',', array_fill(0, count($blocking_statuses), '%s'));
    $query_args = array_merge([$consultant->id, $date], $blocking_statuses);

    $booked_times = $wpdb->get_col($wpdb->prepare(
        "SELECT booking_time FROM {$wpdb->prefix}cs_bookings
         WHERE consultant_id = %d AND booking_date = %s AND status IN ($status_placeholders)",
        $query_args
    ));

    // Generate hourly slots
    $slots = $this->generateSlots(
        $availability->start_time,
        $availability->end_time,
        $booked_times
    );

    return $this->successResponse([
        'date' => $date,
        'day_of_week' => $day_of_week,
        'slots' => $slots,
    ], 'availability');
}
```

**Step 3: Run tests**

Run: `composer test -- --filter=AvailabilityControllerTest`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Rest/AvailabilityController.php tests/Rest/AvailabilityControllerTest.php
git commit -m "feat: use consultant_id in availability endpoint"
```

---

## Task 9: Update BookingsController

**Files:**
- Modify: `src/Rest/BookingsController.php`
- Modify: `tests/Rest/BookingsControllerTest.php`

**Step 1: Update test setup and tests**

Update `tests/Rest/BookingsControllerTest.php` setup and tests to use consultant_id.

**Step 2: Update BookingsController**

Change route registration args from `user_id` to `consultant_id`:
```php
'consultant_id' => [
    'required' => true,
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
],
```

Update `createBooking()` to use consultant:
```php
$consultant_id = $request->get_param('consultant_id');

// Validate consultant
$consultant = $this->validateConsultant($consultant_id);
if ($consultant instanceof WP_Error) {
    return $consultant;
}

// ... rest of validation ...

// Insert booking with consultant_id
$result = $wpdb->insert(
    $wpdb->prefix . 'cs_bookings',
    [
        'consultant_id' => $consultant->id,
        'user_id' => $consultant->wpUserId, // Keep for backwards compat
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'booking_date' => $booking_date,
        'booking_time' => $booking_time . ':00',
        'status' => BookingStatus::PENDING,
        'created_at' => current_time('mysql'),
    ],
    ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
);

// ... response includes consultant_id ...
return $this->successResponse([
    'id' => $booking_id,
    'consultant_id' => $consultant->publicId,
    'customer_name' => $customer_name,
    // ...
], 'bookings', 201, Config::getRateLimitWrite());
```

**Step 3: Update validateAvailability()**

Change method signature and use consultant_id:
```php
private function validateAvailability(int $consultantId, string $date, string $time): ?WP_Error
```

**Step 4: Run tests**

Run: `composer test -- --filter=BookingsControllerTest`
Expected: PASS (after updating all test references)

**Step 5: Commit**

```bash
git add src/Rest/BookingsController.php tests/Rest/BookingsControllerTest.php
git commit -m "feat: use consultant_id in bookings endpoint"
```

---

## Task 10: Update Admin Repositories

**Files:**
- Modify: `src/Admin/Availability/AvailabilityRepository.php`
- Modify: `src/Admin/Bookings/BookingsRepository.php`

**Step 1: Update AvailabilityRepository**

Change methods to use consultant_id internally. Keep accepting user_id for admin UI but look up consultant internally.

**Step 2: Update BookingsRepository**

Update queries to JOIN on consultants table instead of users directly for team_member_name.

**Step 3: Run all tests**

Run: `composer test`
Expected: PASS

**Step 4: Commit**

```bash
git add src/Admin/Availability/AvailabilityRepository.php src/Admin/Bookings/BookingsRepository.php
git commit -m "refactor: update admin repositories to use consultant_id"
```

---

## Task 11: Final Integration Test

**Files:**
- Create: `tests/Integration/ConsultantMigrationTest.php`

**Step 1: Write integration test**

```php
<?php

declare(strict_types=1);

namespace CallScheduler\Tests\Integration;

use CallScheduler\ConsultantRepository;
use WP_REST_Request;
use WP_UnitTestCase;

class ConsultantMigrationTest extends WP_UnitTestCase
{
    public function test_full_booking_workflow_with_consultant(): void
    {
        // 1. Create team member
        $user_id = $this->factory->user->create(['display_name' => 'Test Consultant']);
        update_user_meta($user_id, 'cs_is_team_member', '1');

        // 2. Create consultant profile
        $repository = new ConsultantRepository();
        $consultant = $repository->createForUser($user_id);

        // 3. Set availability
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'cs_availability', [
            'consultant_id' => $consultant->id,
            'user_id' => $user_id,
            'day_of_week' => 1, // Monday
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ]);

        // 4. Get team members (should return public_id)
        $request = new WP_REST_Request('GET', '/cs/v1/team-members');
        $response = rest_do_request($request);
        $members = $response->get_data();

        $this->assertEquals($consultant->publicId, $members[0]['id']);

        // 5. Get availability using consultant_id
        $request = new WP_REST_Request('GET', '/cs/v1/availability');
        $request->set_param('consultant_id', $consultant->publicId);
        $request->set_param('date', '2026-01-05'); // Monday
        $response = rest_do_request($request);

        $this->assertEquals(200, $response->get_status());
        $this->assertNotEmpty($response->get_data()['slots']);

        // 6. Create booking using consultant_id
        $request = new WP_REST_Request('POST', '/cs/v1/bookings');
        $request->set_param('consultant_id', $consultant->publicId);
        $request->set_param('customer_name', 'Customer');
        $request->set_param('customer_email', 'customer@test.com');
        $request->set_param('booking_date', '2026-01-05');
        $request->set_param('booking_time', '10:00');

        $response = rest_do_request($request);

        $this->assertEquals(201, $response->get_status());
        $this->assertEquals($consultant->publicId, $response->get_data()['consultant_id']);
    }
}
```

**Step 2: Run test**

Run: `composer test -- --filter=ConsultantMigrationTest`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Integration/ConsultantMigrationTest.php
git commit -m "test: add integration test for consultant workflow"
```

---

## Task 12: Run Full Test Suite & Final Commit

**Step 1: Run all tests**

Run: `composer test`
Expected: All tests PASS

**Step 2: Final commit**

```bash
git add -A
git commit -m "feat: complete consultant profiles implementation

Security improvement that hides WordPress user IDs from public API.
Introduces wp_cs_consultants table with random 8-char public IDs.

Breaking changes:
- REST API parameter renamed from user_id to consultant_id
- Response id field is now string (8 chars) instead of integer"
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Consultant entity | `src/Consultant.php` |
| 2 | ConsultantRepository | `src/ConsultantRepository.php`, test |
| 3 | Create table | `src/Installer.php` |
| 4 | Migration | `src/Installer.php` |
| 5 | UserProfile auto-create | `src/Admin/UserProfile.php` |
| 6 | TeamMembersController | `src/Rest/TeamMembersController.php` |
| 7 | RestController validation | `src/Rest/RestController.php` |
| 8 | AvailabilityController | `src/Rest/AvailabilityController.php` |
| 9 | BookingsController | `src/Rest/BookingsController.php` |
| 10 | Admin repositories | `src/Admin/*/Repository.php` |
| 11 | Integration test | `tests/Integration/ConsultantMigrationTest.php` |
| 12 | Final verification | All files |
