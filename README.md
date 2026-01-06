# Call Scheduler

A WordPress plugin for booking sales calls.

## Requirements

- PHP 8.0+
- WordPress 6.0+

## Installation

1. Upload the plugin to `/wp-content/plugins/call-scheduler`
2. Activate the plugin through the WordPress admin
3. Configure team members and availability settings

## Configuration

Add to `wp-config.php`:

```php
define('CS_SLOT_DURATION', 30);    // Booking duration in minutes
define('CS_BUFFER_TIME', 15);      // Buffer between meetings
define('CS_MAX_BOOKING_DAYS', 30); // Days ahead for booking
```

## REST API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/cs/v1/team-members` | List team members |
| GET | `/wp-json/cs/v1/availability?user_id=X&date=Y` | Get available slots |
| POST | `/wp-json/cs/v1/bookings` | Create booking |

## Development

```bash
composer install          # Install dependencies
composer test:standalone  # Run standalone tests (no WordPress required)
./bin/build.sh            # Build production zip
```

## Release

Releases are made from the `main` branch:

```bash
git checkout main
git merge develop
./bin/release.sh 1.0.0   # Bumps version, tags, pushes to GitHub
```

GitHub Actions automatically creates the release with the production zip.

## License

MIT License - see [LICENSE](LICENSE) for details.
