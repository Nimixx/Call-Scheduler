# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Call Scheduler is a WordPress plugin for booking sales calls.

**Requirements:** PHP 8.0+, WordPress 6.0+

## Configuration

### Booking Slots (Recommended: 30-minute setup)

Add to `wp-config.php`:

```php
// Call Scheduler Configuration
define('CS_SLOT_DURATION', 30);  // 30-minute bookings
define('CS_BUFFER_TIME', 15);    // 15-minute buffer between meetings
define('CS_MAX_BOOKING_DAYS', 30);
```

See `config-example.php` and `SLOT-CONFIGURATION.md` for details.

## Architecture

```
call-scheduler.php  → Plugin entry point, constants, PSR-4 autoloader
src/
├── Plugin.php            → Singleton main class, hooks registration
├── Installer.php         → Database table creation on activation
└── Rest/
    ├── RestController.php           → Base REST controller
    ├── TeamMembersController.php    → GET /cs/v1/team-members
    ├── AvailabilityController.php   → GET /cs/v1/availability
    └── BookingsController.php       → POST /cs/v1/bookings
```

**Namespace:** `CallScheduler\`

**Database Tables:**
- `wp_cs_availability` - Team member availability (user_id, day_of_week, start_time, end_time)
- `wp_cs_bookings` - Booking reservations (user_id, customer_name, customer_email, booking_date, booking_time, status)

**Team Members:** WordPress users with `cs_is_team_member` user meta set to `1`

## REST API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/cs/v1/team-members` | List team members |
| GET | `/wp-json/cs/v1/availability?user_id=X&date=Y` | Get available slots |
| POST | `/wp-json/cs/v1/bookings` | Create booking |

## Development

```bash
composer install          # Install dependencies
composer test             # Run tests
```

**Adding new classes:** Place in `src/` following PSR-4. `CallScheduler\Foo` → `src/Foo.php`

## Testing

```bash
# First time setup (requires MySQL)
bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run tests
composer test
```

Tests are in `tests/` directory using PHPUnit with WordPress test suite.

## Code Standards

- Strict types: `declare(strict_types=1)`
- Security: `if (!defined('ABSPATH')) exit;`
- Translations: `esc_html__('text', 'call-scheduler')`
