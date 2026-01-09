# Consultant Profiles: Security Abstraction Layer

## Problem

The current REST API exposes WordPress user IDs publicly:

```json
GET /cs/v1/team-members
[{"id": 5, "name": "Jan Novák", "available_days": [1,2,3,4,5]}]
```

This allows attackers to:
- Enumerate WordPress user IDs
- Target other WordPress endpoints (e.g., `/wp-json/wp/v2/users/5`)
- Map employee schedules to real user accounts

## Solution

Create a **consultant profiles** abstraction layer that decouples public booking data from WordPress users.

## Database Schema

### New Table: `wp_cs_consultants`

```sql
CREATE TABLE wp_cs_consultants (
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
);
```

**Fields:**
- `public_id` - 8-char random hash (e.g., `c7f3a2b9`), exposed in API
- `wp_user_id` - Links to WordPress user, never exposed
- `display_name` - Public-facing name (defaults to WP display name)
- `title` - Optional, e.g., "Sales Consultant"
- `bio` - Optional short description
- `is_active` - Soft-delete flag

### Schema Changes

**`wp_cs_availability`:**
```
Before: user_id BIGINT (references wp_users.ID)
After:  consultant_id BIGINT (references wp_cs_consultants.id)
```

**`wp_cs_bookings`:**
```
Before: user_id BIGINT (references wp_users.ID)
After:  consultant_id BIGINT (references wp_cs_consultants.id)
```

## Migration Strategy

1. Create `wp_cs_consultants` table
2. For each user with `cs_is_team_member = '1'`:
   - Generate unique `public_id` via `bin2hex(random_bytes(4))`
   - Create consultant profile with WP user's display name
3. Add `consultant_id` column to `wp_cs_availability` and `wp_cs_bookings`
4. Populate `consultant_id` by joining on `wp_user_id`
5. Drop old `user_id` columns

## Auto-Creation Flow

When `cs_is_team_member` user meta is set to `'1'`:
1. Hook fires on `update_user_meta`
2. Check if consultant profile exists for this `wp_user_id`
3. If not, create new profile with:
   - `public_id` = `bin2hex(random_bytes(4))`
   - `display_name` = WordPress user's display name
   - `title` = NULL
   - `bio` = NULL
   - `is_active` = 1

When `cs_is_team_member` is set to `'0'`:
- Set `is_active = 0` on consultant profile
- Profile is preserved (same `public_id` if re-enabled)

## Admin UI Changes

Add fields to User Profile page (visible when Team Member is checked):

```
☑ Team Member

Consultant Settings:
├── Display Name: [____________] (defaults to WP name)
├── Title:        [____________] (optional)
└── Bio:          [____________] (optional, textarea)
```

## REST API Changes

### GET /cs/v1/team-members

**Before:**
```json
[
  {"id": 5, "name": "Jan Novák", "available_days": [1,2,3,4,5]}
]
```

**After:**
```json
[
  {
    "id": "c7f3a2b9",
    "name": "Jan Novák",
    "title": "Sales Consultant",
    "available_days": [1,2,3,4,5]
  }
]
```

### GET /cs/v1/availability

**Before:**
```
GET /cs/v1/availability?user_id=5&date=2024-01-15
```

**After:**
```
GET /cs/v1/availability?consultant_id=c7f3a2b9&date=2024-01-15
```

### POST /cs/v1/bookings

**Before:**
```json
{
  "user_id": 5,
  "customer_name": "Customer",
  "customer_email": "customer@example.com",
  "booking_date": "2024-01-15",
  "booking_time": "10:00"
}
```

**After:**
```json
{
  "consultant_id": "c7f3a2b9",
  "customer_name": "Customer",
  "customer_email": "customer@example.com",
  "booking_date": "2024-01-15",
  "booking_time": "10:00"
}
```

## Files to Modify

### New Files
- `src/Consultant.php` - Entity class
- `src/ConsultantRepository.php` - Database operations

### Modified Files
- `src/Installer.php` - New table, migration
- `src/Admin/UserProfile.php` - Add consultant fields, auto-create hook
- `src/Rest/TeamMembersController.php` - Return public_id, add title
- `src/Rest/AvailabilityController.php` - Accept consultant_id param
- `src/Rest/BookingsController.php` - Accept consultant_id param
- `src/Rest/RestController.php` - Add validateConsultant() method
- `src/Admin/Availability/*` - Use consultant_id internally
- `src/Admin/Bookings/*` - Use consultant_id internally
- All affected tests

## Security Improvements

| Before | After |
|--------|-------|
| WordPress user ID exposed | Random 8-char hash |
| Sequential IDs (enumerable) | Non-sequential, non-guessable |
| Direct link to WP user system | Abstraction layer |

## Breaking Changes

- Frontend must update from `user_id` to `consultant_id` parameter
- API response `id` field changes from integer to 8-char string
