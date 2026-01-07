<?php
/**
 * Configuration and slot duration tests
 * Run with: php tests/config-test.php
 */

declare(strict_types=1);

define('ABSPATH', true);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($key, $default = []) {
        return $default; // Return default (no admin settings)
    }
}

// Load Config class
require_once __DIR__ . '/../src/Config.php';

use CallScheduler\Config;

echo "Configuration & Slot Duration Tests\n";
echo "====================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test(string $name, bool $result): void {
    global $tests_passed, $tests_failed;

    if ($result) {
        echo "✓ $name\n";
        $tests_passed++;
    } else {
        echo "✗ FAIL: $name\n";
        $tests_failed++;
    }
}

// Test 1: Default configuration
echo "Test Group 1: Default Configuration (60-minute slots)\n";
test("Default slot duration is 60 minutes", Config::getSlotDuration() === 60);
test("Default buffer time is 0 minutes", Config::getBufferTime() === 0);
test("Default max booking days is 30", Config::getMaxBookingDays() === 30);
test("Slot duration in seconds is 3600", Config::getSlotDurationSeconds() === 3600);
test("Buffer time in seconds is 0", Config::getBufferTimeSeconds() === 0);
echo "\n";

// Test 2: Slot interval (60-minute slots, no buffer)
echo "Test Group 2: Slot Interval (60-minute slots, no buffer)\n";
test("Slot interval is 60 minutes (60 + 0)", Config::getSlotInterval() === 60);
test("Slot interval in seconds is 3600", Config::getSlotIntervalSeconds() === 3600);
echo "\n";

// Test 3: 30-minute slot configuration
echo "Test Group 3: 30-Minute Slot Configuration\n";
define('CS_SLOT_DURATION', 30);

test("Configured slot duration is 30 minutes", Config::getSlotDuration() === 30);
test("Slot duration text is '30 minutes'", Config::getSlotDurationText() === '30 minutes');
test("Slot interval is still 30 (no buffer yet)", Config::getSlotInterval() === 30);
echo "\n";

// Test 4: Buffer time configuration
echo "Test Group 4: Buffer Time Configuration\n";
define('CS_BUFFER_TIME', 15);

test("Configured buffer time is 15 minutes", Config::getBufferTime() === 15);
test("Buffer time in seconds is 900", Config::getBufferTimeSeconds() === 900);
test("Slot interval is now 45 (30 + 15)", Config::getSlotInterval() === 45);
test("Slot interval in seconds is 2700", Config::getSlotIntervalSeconds() === 2700);
echo "\n";

// Test 5: Configuration summary
echo "Test Group 5: Configuration Summary\n";
$summary = Config::getConfigSummary();
test("Summary contains slot_duration_minutes", isset($summary['slot_duration_minutes']));
test("Summary contains buffer_time_minutes", isset($summary['buffer_time_minutes']));
test("Summary contains slot_interval_minutes", isset($summary['slot_interval_minutes']));
test("Summary contains max_booking_days", isset($summary['max_booking_days']));
test("Summary shows 45-minute slot interval", $summary['slot_interval_minutes'] === 45);

echo "\n";
echo "Configuration Summary:\n";
foreach ($summary as $key => $value) {
    echo "  $key: $value\n";
}
echo "\n";

// Test 6: Buffer time blocking simulation
echo "Test Group 6: Buffer Time Blocking Simulation\n";

// Simulate: 30-minute slot at 09:00 with 15-minute buffer
$slot_duration = 30 * 60; // 1800 seconds
$buffer_time = 15 * 60;   // 900 seconds

$booking_time = strtotime('09:00');
$blocked_until = $booking_time + $slot_duration + $buffer_time;

$blocked_until_time = date('H:i', $blocked_until);

test("09:00 booking (30min) + 15min buffer blocks until 09:45", $blocked_until_time === '09:45');

// Check if 09:30 slot would be blocked
$next_slot = strtotime('09:30');
$is_09_30_blocked = $next_slot >= $booking_time && $next_slot < $blocked_until;
test("09:30 slot is blocked by 09:00 booking + buffer", $is_09_30_blocked);

// Check if 10:00 slot would be available
$slot_10_00 = strtotime('10:00');
$is_10_00_available = !($slot_10_00 >= $booking_time && $slot_10_00 < $blocked_until);
test("10:00 slot is available after 09:00 booking + buffer", $is_10_00_available);

echo "\n";

// Test 7: Slot generation with 45-minute intervals (30 slot + 15 buffer)
echo "Test Group 7: Slot Generation with 45-minute Intervals\n";

// With 30-minute slots and 15-minute buffer, slots should be at 45-minute intervals
// Starting from 09:00: 09:00, 09:45, 10:30, 11:15, 12:00, 12:45...
$interval = Config::getSlotIntervalSeconds(); // 2700 seconds = 45 minutes
$start = strtotime('09:00');

$expected_slots = ['09:00', '09:45', '10:30', '11:15', '12:00', '12:45'];
$generated_slots = [];

$current = $start;
for ($i = 0; $i < 6; $i++) {
    $generated_slots[] = date('H:i', $current);
    $current += $interval;
}

for ($i = 0; $i < count($expected_slots); $i++) {
    test("Slot $i is {$expected_slots[$i]}", $generated_slots[$i] === $expected_slots[$i]);
}

echo "\n";

// Test 8: Booking blocks next slot correctly
echo "Test Group 8: Booking Blocking with New Interval\n";

// If 09:00 is booked (30 min slot + 15 min buffer), blocks until 09:45
// Next available slot in 45-minute grid is 09:45 (which is exactly when buffer ends)
$booking_at_0900 = strtotime('09:00');
$blocked_until = $booking_at_0900 + $slot_duration + $buffer_time;

test("09:00 booking blocks until 09:45", date('H:i', $blocked_until) === '09:45');

// The next slot in the 45-minute grid is 09:45
$next_slot_in_grid = $booking_at_0900 + $interval;
test("Next slot in grid is 09:45", date('H:i', $next_slot_in_grid) === '09:45');

// 09:45 should NOT be blocked (it starts exactly when buffer ends)
$is_0945_blocked = $next_slot_in_grid >= $booking_at_0900 && $next_slot_in_grid < $blocked_until;
test("09:45 slot is available (buffer just ended)", !$is_0945_blocked);

echo "\n";

// Summary
echo "====================================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";
echo "\n";

if ($tests_failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
