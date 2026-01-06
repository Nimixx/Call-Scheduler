<?php
/**
 * Configuration and slot duration tests
 * Run with: php tests/config-test.php
 */

declare(strict_types=1);

define('ABSPATH', true);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

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

// Test 2: Valid slot times (60-minute slots)
echo "Test Group 2: Valid Slot Times (60-minute slots)\n";
test("09:00 is valid", Config::isValidSlotTime('09:00'));
test("10:00 is valid", Config::isValidSlotTime('10:00'));
test("00:00 is valid", Config::isValidSlotTime('00:00'));
test("23:00 is valid", Config::isValidSlotTime('23:00'));
test("09:30 is invalid", !Config::isValidSlotTime('09:30'));
test("09:15 is invalid", !Config::isValidSlotTime('09:15'));
test("09:45 is invalid", !Config::isValidSlotTime('09:45'));
echo "\n";

// Test 3: 30-minute slot configuration
echo "Test Group 3: 30-Minute Slot Configuration\n";
define('CS_SLOT_DURATION', 30);

test("Configured slot duration is 30 minutes", Config::getSlotDuration() === 30);
test("09:00 is valid", Config::isValidSlotTime('09:00'));
test("09:30 is valid", Config::isValidSlotTime('09:30'));
test("10:00 is valid", Config::isValidSlotTime('10:00'));
test("09:15 is invalid", !Config::isValidSlotTime('09:15'));
test("09:45 is invalid", !Config::isValidSlotTime('09:45'));
test("Slot duration text is '30 minutes'", Config::getSlotDurationText() === '30 minutes');
echo "\n";

// Test 4: Buffer time configuration
echo "Test Group 4: Buffer Time Configuration\n";
define('CS_BUFFER_TIME', 15);

test("Configured buffer time is 15 minutes", Config::getBufferTime() === 15);
test("Buffer time in seconds is 900", Config::getBufferTimeSeconds() === 900);
echo "\n";

// Test 5: Configuration summary
echo "Test Group 5: Configuration Summary\n";
$summary = Config::getConfigSummary();
test("Summary contains slot_duration_minutes", isset($summary['slot_duration_minutes']));
test("Summary contains buffer_time_minutes", isset($summary['buffer_time_minutes']));
test("Summary contains max_booking_days", isset($summary['max_booking_days']));
test("Summary shows 2 slots per hour (30min)", $summary['valid_times_per_hour'] === 2);

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

// Test 7: Multiple bookings with buffer
echo "Test Group 7: Multiple Bookings with Buffer\n";

// Bookings: 09:00, 10:00, 11:00 (each 30min + 15min buffer)
$bookings = [
    ['time' => '09:00:00', 'blocks_until' => '09:45'],
    ['time' => '10:00:00', 'blocks_until' => '10:45'],
    ['time' => '11:00:00', 'blocks_until' => '11:45'],
];

$slots_to_check = ['09:00', '09:30', '09:45', '10:00', '10:30', '10:45', '11:00', '11:30', '11:45', '12:00'];
$expected_available = ['09:45', '10:45', '11:45', '12:00'];

$blocked_periods = [];
foreach ($bookings as $booking) {
    $start = strtotime($booking['time']);
    $end = $start + $slot_duration + $buffer_time;
    $blocked_periods[] = ['start' => $start, 'end' => $end];
}

foreach ($slots_to_check as $slot) {
    $slot_time = strtotime($slot . ':00');
    $is_blocked = false;

    foreach ($blocked_periods as $period) {
        if ($slot_time >= $period['start'] && $slot_time < $period['end']) {
            $is_blocked = true;
            break;
        }
    }

    $should_be_available = in_array($slot, $expected_available);
    $is_correct = ($is_blocked !== $should_be_available);

    $status = $is_blocked ? 'blocked' : 'available';
    test("$slot is correctly $status", $is_correct);
}

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
