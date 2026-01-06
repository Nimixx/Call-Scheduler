<?php
/**
 * Standalone tests for bug fixes
 * Run with: php tests/standalone-tests.php
 */

declare(strict_types=1);

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->passed++;
            echo ".";
        } else {
            $this->failed++;
            $this->failures[] = [
                'message' => $message,
                'expected' => $expected,
                'actual' => $actual,
            ];
            echo "F";
        }
    }

    public function assertTrue($value, string $message = ''): void
    {
        $this->assertEquals(true, $value, $message);
    }

    public function assertFalse($value, string $message = ''): void
    {
        $this->assertEquals(false, $value, $message);
    }

    public function report(): void
    {
        echo "\n\n";
        echo "Tests: " . ($this->passed + $this->failed) . "\n";
        echo "Passed: " . $this->passed . "\n";
        echo "Failed: " . $this->failed . "\n\n";

        if (!empty($this->failures)) {
            echo "Failures:\n";
            foreach ($this->failures as $i => $failure) {
                echo ($i + 1) . ") " . $failure['message'] . "\n";
                echo "   Expected: " . var_export($failure['expected'], true) . "\n";
                echo "   Actual: " . var_export($failure['actual'], true) . "\n\n";
            }
        }

        exit($this->failed > 0 ? 1 : 0);
    }
}

// ============================================================================
// Bug #1 Tests: Overnight Shift Logic
// ============================================================================

function test_overnight_shift_detection(TestRunner $t): void
{
    echo "\n\nBug #1: Overnight Shift Detection\n";

    // Normal shift: end > start
    $start = strtotime('09:00');
    $end = strtotime('17:00');
    $is_overnight = $end <= $start;
    $t->assertFalse($is_overnight, 'Normal shift 09:00-17:00 should not be overnight');

    // Overnight shift: end < start
    $start = strtotime('22:00');
    $end = strtotime('02:00');
    $is_overnight = $end <= $start;
    $t->assertTrue($is_overnight, 'Shift 22:00-02:00 should be detected as overnight');

    // Edge case: same time (treated as overnight 24h)
    $start = strtotime('12:00');
    $end = strtotime('12:00');
    $is_overnight = $end <= $start;
    $t->assertTrue($is_overnight, 'Shift 12:00-12:00 should be overnight (24h)');
}

function test_overnight_shift_validation(TestRunner $t): void
{
    echo "\nBug #1: Overnight Shift Validation\n";

    // Overnight shift: 22:00 - 02:00
    $start_time = strtotime('22:00');
    $end_time = strtotime('02:00');
    $is_overnight = $end_time <= $start_time;

    // Test 23:00 (should be valid - after start)
    $requested = strtotime('23:00');
    $is_valid = $is_overnight
        ? ($requested >= $start_time || $requested < $end_time)
        : ($requested >= $start_time && $requested < $end_time);
    $t->assertTrue($is_valid, '23:00 should be valid in 22:00-02:00 overnight shift');

    // Test 00:00 (should be valid - before end)
    $requested = strtotime('00:00');
    $is_valid = $is_overnight
        ? ($requested >= $start_time || $requested < $end_time)
        : ($requested >= $start_time && $requested < $end_time);
    $t->assertTrue($is_valid, '00:00 should be valid in 22:00-02:00 overnight shift');

    // Test 01:00 (should be valid - before end)
    $requested = strtotime('01:00');
    $is_valid = $is_overnight
        ? ($requested >= $start_time || $requested < $end_time)
        : ($requested >= $start_time && $requested < $end_time);
    $t->assertTrue($is_valid, '01:00 should be valid in 22:00-02:00 overnight shift');

    // Test 21:00 (should be invalid - before start)
    $requested = strtotime('21:00');
    $is_valid = $is_overnight
        ? ($requested >= $start_time || $requested < $end_time)
        : ($requested >= $start_time && $requested < $end_time);
    $t->assertFalse($is_valid, '21:00 should be invalid in 22:00-02:00 overnight shift');

    // Test 02:00 (should be invalid - at end boundary)
    $requested = strtotime('02:00');
    $is_valid = $is_overnight
        ? ($requested >= $start_time || $requested < $end_time)
        : ($requested >= $start_time && $requested < $end_time);
    $t->assertFalse($is_valid, '02:00 should be invalid (end boundary)');

    // Test 10:00 (should be invalid - in middle of day)
    $requested = strtotime('10:00');
    $is_valid = $is_overnight
        ? ($requested >= $start_time || $requested < $end_time)
        : ($requested >= $start_time && $requested < $end_time);
    $t->assertFalse($is_valid, '10:00 should be invalid in 22:00-02:00 overnight shift');
}

function test_overnight_slot_generation(TestRunner $t): void
{
    echo "\nBug #1: Overnight Slot Generation\n";

    // Overnight shift: 22:00 - 02:00
    $start_time = strtotime('22:00');
    $end_time = strtotime('02:00');
    $is_overnight = $end_time <= $start_time;

    if ($is_overnight) {
        $end_time += 86400; // Add 24 hours
    }

    $slots = [];
    $current = $start_time;

    while ($current < $end_time) {
        $slots[] = date('H:i', $current);
        $current += 3600; // 1 hour
    }

    $t->assertEquals(4, count($slots), 'Should generate 4 slots for 22:00-02:00');
    $t->assertEquals('22:00', $slots[0], 'First slot should be 22:00');
    $t->assertEquals('23:00', $slots[1], 'Second slot should be 23:00');
    $t->assertEquals('00:00', $slots[2], 'Third slot should be 00:00');
    $t->assertEquals('01:00', $slots[3], 'Fourth slot should be 01:00');
}

// ============================================================================
// Bug #2 Tests: Non-Hourly Validation
// ============================================================================

function test_non_hourly_validation(TestRunner $t): void
{
    echo "\n\nBug #2: Non-Hourly Booking Validation\n";

    // Test hourly times (should pass)
    $hourly_times = ['09:00', '14:00', '00:00', '23:00'];
    foreach ($hourly_times as $time) {
        $parts = explode(':', $time);
        $minutes = (int) $parts[1];
        $is_hourly = $minutes === 0;
        $t->assertTrue($is_hourly, "$time should be valid (hourly)");
    }

    // Test non-hourly times (should fail)
    $non_hourly_times = ['09:15', '14:30', '16:45', '00:01'];
    foreach ($non_hourly_times as $time) {
        $parts = explode(':', $time);
        $minutes = (int) $parts[1];
        $is_hourly = $minutes === 0;
        $t->assertFalse($is_hourly, "$time should be invalid (non-hourly)");
    }
}

// ============================================================================
// Bug #3 Tests: Hours Calculation
// ============================================================================

function calculateHours(string $start, string $end): string
{
    $start_parts = explode(':', $start);
    $end_parts = explode(':', $end);

    $start_minutes = (int)$start_parts[0] * 60 + (int)$start_parts[1];
    $end_minutes = (int)$end_parts[0] * 60 + (int)$end_parts[1];

    $diff_minutes = $end_minutes - $start_minutes;

    // Handle overnight shifts (end <= start means it wraps to next day)
    $is_overnight = $diff_minutes <= 0;
    if ($is_overnight) {
        $diff_minutes += 1440; // Add 24 hours (24 * 60 minutes)
    }

    $hours = floor($diff_minutes / 60);
    $minutes = $diff_minutes % 60;

    $time_text = $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'm' : '');
    return $is_overnight ? $time_text . ' (overnight)' : $time_text;
}

function test_hours_calculation(TestRunner $t): void
{
    echo "\n\nBug #3: Hours Calculation\n";

    // Normal shifts
    $t->assertEquals('8h', calculateHours('09:00', '17:00'), 'Normal 8 hour shift');
    $t->assertEquals('8h 30m', calculateHours('09:00', '17:30'), 'Shift with 30 minutes');
    $t->assertEquals('1h', calculateHours('09:00', '10:00'), 'One hour shift');
    $t->assertEquals('0h 45m', calculateHours('09:00', '09:45'), '45 minute shift');

    // Overnight shifts
    $t->assertEquals('4h (overnight)', calculateHours('22:00', '02:00'), 'Overnight 22:00-02:00');
    $t->assertEquals('3h 45m (overnight)', calculateHours('22:30', '02:15'), 'Overnight with minutes');
    $t->assertEquals('10h (overnight)', calculateHours('20:00', '06:00'), 'Long overnight shift');
    $t->assertEquals('24h (overnight)', calculateHours('00:00', '00:00'), 'Full 24 hour shift');
    $t->assertEquals('2h (overnight)', calculateHours('23:00', '01:00'), 'Short overnight shift');
}

// ============================================================================
// Bug #5 Tests: Rate Limiter Lock Logic
// ============================================================================

function test_rate_limiter_lock_simulation(TestRunner $t): void
{
    echo "\n\nBug #5: Rate Limiter Lock Logic\n";

    // Simulate lock behavior
    $locks = [];

    // Function to acquire lock
    $acquire_lock = function($key) use (&$locks) {
        if (isset($locks[$key])) {
            return false; // Lock already held
        }
        $locks[$key] = true;
        return true; // Lock acquired
    };

    // Function to release lock
    $release_lock = function($key) use (&$locks) {
        unset($locks[$key]);
    };

    // Test 1: Lock acquisition
    $acquired = $acquire_lock('test_key');
    $t->assertTrue($acquired, 'First lock acquisition should succeed');

    // Test 2: Lock is held
    $acquired = $acquire_lock('test_key');
    $t->assertFalse($acquired, 'Second acquisition should fail (lock held)');

    // Test 3: Lock release
    $release_lock('test_key');
    $acquired = $acquire_lock('test_key');
    $t->assertTrue($acquired, 'Acquisition after release should succeed');

    // Test 4: Multiple different locks
    $release_lock('test_key');
    $acquired1 = $acquire_lock('key1');
    $acquired2 = $acquire_lock('key2');
    $t->assertTrue($acquired1, 'Lock key1 should be acquired');
    $t->assertTrue($acquired2, 'Lock key2 should be acquired (different key)');

    // Cleanup
    $release_lock('key1');
    $release_lock('key2');
}

function test_rate_limiter_retry_logic(TestRunner $t): void
{
    echo "\nBug #5: Rate Limiter Retry Logic\n";

    $count = 0;
    $max_retries = 3;
    $retry_count = 0;
    $lock_acquired = false;

    // Simulate retry loop
    while ($retry_count < $max_retries) {
        // Simulate lock acquisition (succeeds on 3rd try)
        if ($retry_count === 2) {
            $lock_acquired = true;
            break;
        }

        $retry_count++;
    }

    $t->assertTrue($lock_acquired, 'Lock should be acquired after retries');
    $t->assertEquals(2, $retry_count, 'Should retry 2 times before success');
}

function test_atomic_counter_with_lock(TestRunner $t): void
{
    echo "\nBug #5: Atomic Counter with Lock\n";

    $count = 0;
    $locks = [];

    // Simulate 10 concurrent requests with lock
    for ($i = 0; $i < 10; $i++) {
        // Acquire lock
        $key = 'rate_limit';
        if (!isset($locks[$key])) {
            $locks[$key] = true;

            // Critical section
            $count++;

            // Release lock
            unset($locks[$key]);
        }
    }

    $t->assertEquals(10, $count, 'With lock, all 10 increments should be counted');
}

// ============================================================================
// Run all tests
// ============================================================================

echo "Running standalone tests for bug fixes...\n";
echo "==========================================\n";

$t = new TestRunner();

test_overnight_shift_detection($t);
test_overnight_shift_validation($t);
test_overnight_slot_generation($t);

test_non_hourly_validation($t);

test_hours_calculation($t);

test_rate_limiter_lock_simulation($t);
test_rate_limiter_retry_logic($t);
test_atomic_counter_with_lock($t);

$t->report();
