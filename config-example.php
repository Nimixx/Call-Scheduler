<?php
/**
 * Call Scheduler - Configuration Example
 *
 * INSTRUCTIONS:
 * 1. Copy the configuration lines below
 * 2. Paste them into your wp-config.php file
 * 3. Place them BEFORE the line that says "That's all, stop editing!"
 *
 * Location: /wp-config.php (in your WordPress root directory)
 */

// ============================================================================
// CALL SCHEDULER CONFIGURATION
// ============================================================================

/**
 * Slot Duration (in minutes)
 *
 * How long each booking lasts.
 *
 * Common values:
 * - 15  Quick support calls
 * - 30  Standard meetings (RECOMMENDED)
 * - 60  Hour-long consultations (default if not set)
 * - 90  Extended sessions
 */
define('CS_SLOT_DURATION', 30);

/**
 * Buffer Time (in minutes)
 *
 * Time blocked after each booking for preparation, notes, or travel.
 * Prevents back-to-back bookings with no break.
 *
 * Common values:
 * - 0   No buffer (back-to-back bookings)
 * - 10  Quick notes/transition
 * - 15  Standard prep time (RECOMMENDED with 30min slots)
 * - 30  Extended preparation
 *
 * Note: Buffer must be less than slot duration
 */
define('CS_BUFFER_TIME', 15);

/**
 * Maximum Booking Days (in days)
 *
 * How far in advance customers can book appointments.
 *
 * Default: 30 days
 */
define('CS_MAX_BOOKING_DAYS', 30);

// ============================================================================
// WEBHOOK CONFIGURATION (Optional)
// ============================================================================

/**
 * Webhook Secret Key (for HMAC-SHA256 signature)
 *
 * Used to sign webhook payloads so the receiving endpoint can verify
 * the request came from your WordPress site.
 *
 * SECURITY: This is stored here (not in database) to prevent exposure
 * via SQL injection, database backups, or other plugin access.
 *
 * Generate a secure random key:
 *   - Linux/Mac: openssl rand -hex 32
 *   - Or use: https://randomkeygen.com (256-bit WEP Keys)
 *
 * The webhook URL is configured in: Settings > Call Scheduler > Webhooks
 */
// define('CS_WEBHOOK_SECRET', 'your-secret-key-here');

// ============================================================================
// END CALL SCHEDULER CONFIGURATION
// ============================================================================

/*
 * RESULT WITH ABOVE CONFIGURATION:
 *
 * Slot Duration:   30 minutes (meeting length)
 * Buffer Time:     15 minutes (break after each meeting)
 * Slot Interval:   45 minutes (time between consecutive bookings)
 *
 * Example Schedule (9:00-17:00):
 * ─────────────────────────────────────────────────────────────
 * 09:00-09:30  Meeting 1
 * 09:30-09:45  Buffer (prep time, notes, break)
 * 09:45-10:15  Meeting 2
 * 10:15-10:30  Buffer
 * 10:30-11:00  Meeting 3
 * 11:00-11:15  Buffer
 * 11:15-11:45  Meeting 4
 * ... and so on
 *
 * Available booking times (45-minute intervals):
 * 09:00, 09:45, 10:30, 11:15, 12:00, 12:45, 13:30, 14:15, 15:00, 15:45, 16:30
 *
 * Capacity: ~10-11 bookings per 8-hour day
 */
