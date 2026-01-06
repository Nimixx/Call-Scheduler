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
// END CALL SCHEDULER CONFIGURATION
// ============================================================================

/*
 * RESULT WITH ABOVE CONFIGURATION:
 *
 * Slot Duration: 30 minutes
 * Buffer Time:   15 minutes
 * Total Block:   45 minutes per booking
 *
 * Example Schedule (9:00-17:00):
 * ─────────────────────────────────
 * 09:00-09:30  Meeting 1
 * 09:30-09:45  Buffer (blocked)
 * 09:45-10:15  Meeting 2
 * 10:15-10:30  Buffer (blocked)
 * 10:30-11:00  Meeting 3
 * 11:00-11:15  Buffer (blocked)
 * 11:15-11:45  Meeting 4
 * ... and so on
 *
 * Capacity: ~10 bookings per day (vs 8 with 60-minute slots)
 *
 * Available booking times:
 * 09:00, 09:45, 10:30, 11:15, 12:00, 12:45, 13:30, 14:15, 15:00, 15:45, 16:30
 */
