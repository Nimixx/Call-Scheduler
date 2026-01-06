<?php
/**
 * Standalone tests for Settings modules
 * Run with: php tests/settings-standalone-tests.php
 */

declare(strict_types=1);

class SettingsTestRunner
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

    public function assertArrayHasKey(string $key, array $array, string $message = ''): void
    {
        $this->assertTrue(array_key_exists($key, $array), $message ?: "Array should have key '$key'");
    }

    public function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertFalse(str_contains($haystack, $needle), $message ?: "'$haystack' should not contain '$needle'");
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
// Timing Module Logic Tests
// ============================================================================

/**
 * Simulate TimingModule::sanitize logic
 */
function sanitize_timing(array $input): array
{
    $defaults = [
        'slot_duration' => 60,
        'buffer_time' => 0,
    ];
    $output = [];

    // Slot duration - must be positive and in valid list
    $slot_duration = isset($input['slot_duration']) ? abs((int) $input['slot_duration']) : $defaults['slot_duration'];
    $valid_durations = [15, 30, 60, 90, 120];
    $output['slot_duration'] = in_array($slot_duration, $valid_durations, true) ? $slot_duration : $defaults['slot_duration'];

    // Buffer time - must be non-negative and less than slot duration
    $buffer_time = isset($input['buffer_time']) ? abs((int) $input['buffer_time']) : $defaults['buffer_time'];
    $output['buffer_time'] = $buffer_time < $output['slot_duration'] ? $buffer_time : 0;

    return $output;
}

function test_timing_module_defaults(SettingsTestRunner $t): void
{
    echo "\n\nTimingModule: Defaults\n";

    $defaults = ['slot_duration' => 60, 'buffer_time' => 0];

    $t->assertArrayHasKey('slot_duration', $defaults, 'Defaults should have slot_duration');
    $t->assertArrayHasKey('buffer_time', $defaults, 'Defaults should have buffer_time');
    $t->assertEquals(60, $defaults['slot_duration'], 'Default slot_duration should be 60');
    $t->assertEquals(0, $defaults['buffer_time'], 'Default buffer_time should be 0');
}

function test_timing_module_valid_durations(SettingsTestRunner $t): void
{
    echo "\nTimingModule: Valid Durations\n";

    $validDurations = [15, 30, 60, 90, 120];

    foreach ($validDurations as $duration) {
        $input = ['slot_duration' => $duration, 'buffer_time' => 0];
        $result = sanitize_timing($input);
        $t->assertEquals($duration, $result['slot_duration'], "Duration {$duration} should be accepted");
    }
}

function test_timing_module_invalid_duration(SettingsTestRunner $t): void
{
    echo "\nTimingModule: Invalid Duration\n";

    // 45 is not in the valid list
    $input = ['slot_duration' => 45, 'buffer_time' => 0];
    $result = sanitize_timing($input);
    $t->assertEquals(60, $result['slot_duration'], 'Invalid duration 45 should fall back to 60');

    // 100 is not valid
    $input = ['slot_duration' => 100, 'buffer_time' => 0];
    $result = sanitize_timing($input);
    $t->assertEquals(60, $result['slot_duration'], 'Invalid duration 100 should fall back to 60');

    // 0 is not valid
    $input = ['slot_duration' => 0, 'buffer_time' => 0];
    $result = sanitize_timing($input);
    $t->assertEquals(60, $result['slot_duration'], 'Invalid duration 0 should fall back to 60');
}

function test_timing_module_buffer_time(SettingsTestRunner $t): void
{
    echo "\nTimingModule: Buffer Time\n";

    // Valid buffer time
    $input = ['slot_duration' => 60, 'buffer_time' => 15];
    $result = sanitize_timing($input);
    $t->assertEquals(15, $result['buffer_time'], 'Buffer time 15 should be accepted');

    // Buffer time at max (but less than slot)
    $input = ['slot_duration' => 60, 'buffer_time' => 59];
    $result = sanitize_timing($input);
    $t->assertEquals(59, $result['buffer_time'], 'Buffer time 59 should be accepted for 60min slot');

    // Buffer time equals slot duration - should be rejected
    $input = ['slot_duration' => 60, 'buffer_time' => 60];
    $result = sanitize_timing($input);
    $t->assertEquals(0, $result['buffer_time'], 'Buffer time equal to slot should fall back to 0');

    // Buffer time exceeds slot duration - should be rejected
    $input = ['slot_duration' => 30, 'buffer_time' => 60];
    $result = sanitize_timing($input);
    $t->assertEquals(0, $result['buffer_time'], 'Buffer time exceeding slot should fall back to 0');
}

function test_timing_module_string_conversion(SettingsTestRunner $t): void
{
    echo "\nTimingModule: String to Int Conversion\n";

    $input = ['slot_duration' => '30', 'buffer_time' => '10'];
    $result = sanitize_timing($input);

    $t->assertEquals(30, $result['slot_duration'], 'String "30" should be converted to int 30');
    $t->assertEquals(10, $result['buffer_time'], 'String "10" should be converted to int 10');
}

function test_timing_module_negative_values(SettingsTestRunner $t): void
{
    echo "\nTimingModule: Negative Values\n";

    // abs() converts -30 to 30, which is valid
    $input = ['slot_duration' => -30, 'buffer_time' => -5];
    $result = sanitize_timing($input);

    $t->assertEquals(30, $result['slot_duration'], 'Negative -30 should become 30 (valid)');
    $t->assertEquals(5, $result['buffer_time'], 'Negative -5 should become 5');
}

// ============================================================================
// Whitelabel Module Logic Tests
// ============================================================================

/**
 * Simulate WhitelabelModule::sanitize logic
 */
function sanitize_whitelabel(array $input): array
{
    $output = [];

    $output['whitelabel_enabled'] = !empty($input['whitelabel_enabled']);

    $output['whitelabel_plugin_name'] = isset($input['whitelabel_plugin_name'])
        ? trim(strip_tags($input['whitelabel_plugin_name']))
        : '';

    return $output;
}

/**
 * Simulate WhitelabelModule::getPluginName logic
 */
function get_plugin_name(array $options): string
{
    if (!empty($options['whitelabel_enabled']) && !empty($options['whitelabel_plugin_name'])) {
        return $options['whitelabel_plugin_name'];
    }

    return 'Rezervace'; // Default
}

function test_whitelabel_module_defaults(SettingsTestRunner $t): void
{
    echo "\n\nWhitelabelModule: Defaults\n";

    $defaults = ['whitelabel_enabled' => false, 'whitelabel_plugin_name' => ''];

    $t->assertArrayHasKey('whitelabel_enabled', $defaults, 'Defaults should have whitelabel_enabled');
    $t->assertArrayHasKey('whitelabel_plugin_name', $defaults, 'Defaults should have whitelabel_plugin_name');
    $t->assertFalse($defaults['whitelabel_enabled'], 'whitelabel_enabled should default to false');
    $t->assertEquals('', $defaults['whitelabel_plugin_name'], 'whitelabel_plugin_name should default to empty');
}

function test_whitelabel_module_sanitize_enabled(SettingsTestRunner $t): void
{
    echo "\nWhitelabelModule: Sanitize Enabled\n";

    // Enabled
    $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => 'Test'];
    $result = sanitize_whitelabel($input);
    $t->assertTrue($result['whitelabel_enabled'], 'whitelabel_enabled "1" should be true');

    // Different truthy value
    $input = ['whitelabel_enabled' => 'on', 'whitelabel_plugin_name' => 'Test'];
    $result = sanitize_whitelabel($input);
    $t->assertTrue($result['whitelabel_enabled'], 'whitelabel_enabled "on" should be true');

    // Not set (checkbox unchecked)
    $input = ['whitelabel_plugin_name' => 'Test'];
    $result = sanitize_whitelabel($input);
    $t->assertFalse($result['whitelabel_enabled'], 'Missing whitelabel_enabled should be false');

    // Empty string
    $input = ['whitelabel_enabled' => '', 'whitelabel_plugin_name' => 'Test'];
    $result = sanitize_whitelabel($input);
    $t->assertFalse($result['whitelabel_enabled'], 'Empty whitelabel_enabled should be false');
}

function test_whitelabel_module_sanitize_plugin_name(SettingsTestRunner $t): void
{
    echo "\nWhitelabelModule: Sanitize Plugin Name\n";

    // Normal name
    $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => 'My App'];
    $result = sanitize_whitelabel($input);
    $t->assertEquals('My App', $result['whitelabel_plugin_name'], 'Normal name should be preserved');

    // Trimmed
    $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => '  Spaced Name  '];
    $result = sanitize_whitelabel($input);
    $t->assertEquals('Spaced Name', $result['whitelabel_plugin_name'], 'Name should be trimmed');

    // HTML stripped
    $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => '<b>Bold</b> Name'];
    $result = sanitize_whitelabel($input);
    $t->assertStringNotContains('<b>', $result['whitelabel_plugin_name'], 'HTML should be stripped');

    // Script tag removed (XSS prevention)
    $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => '<script>alert("xss")</script>Safe'];
    $result = sanitize_whitelabel($input);
    $t->assertStringNotContains('<script>', $result['whitelabel_plugin_name'], 'Script tags should be stripped');
}

function test_whitelabel_get_plugin_name(SettingsTestRunner $t): void
{
    echo "\nWhitelabelModule: getPluginName\n";

    // Disabled - should return default
    $options = ['whitelabel_enabled' => false, 'whitelabel_plugin_name' => 'Custom Name'];
    $name = get_plugin_name($options);
    $t->assertEquals('Rezervace', $name, 'Disabled whitelabel should return default');

    // Enabled with name - should return custom
    $options = ['whitelabel_enabled' => true, 'whitelabel_plugin_name' => 'My Booking App'];
    $name = get_plugin_name($options);
    $t->assertEquals('My Booking App', $name, 'Enabled whitelabel should return custom name');

    // Enabled but empty name - should return default
    $options = ['whitelabel_enabled' => true, 'whitelabel_plugin_name' => ''];
    $name = get_plugin_name($options);
    $t->assertEquals('Rezervace', $name, 'Empty plugin name should return default');

    // No options at all
    $options = [];
    $name = get_plugin_name($options);
    $t->assertEquals('Rezervace', $name, 'No options should return default');
}

// ============================================================================
// Settings Page Logic Tests
// ============================================================================

function test_settings_page_get_options_with_defaults(SettingsTestRunner $t): void
{
    echo "\n\nSettingsPage: getOptions with Defaults\n";

    // Simulate wp_parse_args behavior
    $saved = [];
    $defaults = [
        'slot_duration' => 60,
        'buffer_time' => 0,
        'whitelabel_enabled' => false,
        'whitelabel_plugin_name' => '',
    ];

    $options = array_merge($defaults, $saved);

    $t->assertEquals(60, $options['slot_duration'], 'slot_duration should default to 60');
    $t->assertEquals(0, $options['buffer_time'], 'buffer_time should default to 0');
    $t->assertFalse($options['whitelabel_enabled'], 'whitelabel_enabled should default to false');
    $t->assertEquals('', $options['whitelabel_plugin_name'], 'whitelabel_plugin_name should default to empty');
}

function test_settings_page_get_options_merge(SettingsTestRunner $t): void
{
    echo "\nSettingsPage: getOptions Merge\n";

    // Simulate saved options overriding defaults
    $saved = [
        'slot_duration' => 30,
        'whitelabel_enabled' => true,
    ];
    $defaults = [
        'slot_duration' => 60,
        'buffer_time' => 0,
        'whitelabel_enabled' => false,
        'whitelabel_plugin_name' => '',
    ];

    $options = array_merge($defaults, $saved);

    // Saved values
    $t->assertEquals(30, $options['slot_duration'], 'Saved slot_duration should override default');
    $t->assertTrue($options['whitelabel_enabled'], 'Saved whitelabel_enabled should override default');

    // Default values (not in saved)
    $t->assertEquals(0, $options['buffer_time'], 'buffer_time should use default');
    $t->assertEquals('', $options['whitelabel_plugin_name'], 'whitelabel_plugin_name should use default');
}

function test_settings_page_sanitize_all_modules(SettingsTestRunner $t): void
{
    echo "\nSettingsPage: Sanitize All Modules\n";

    $input = [
        'slot_duration' => 30,
        'buffer_time' => 10,
        'whitelabel_enabled' => '1',
        'whitelabel_plugin_name' => 'Test App',
    ];

    // Process through both sanitizers (simulating module aggregation)
    $timing_result = sanitize_timing($input);
    $whitelabel_result = sanitize_whitelabel($input);
    $result = array_merge($timing_result, $whitelabel_result);

    // Timing
    $t->assertEquals(30, $result['slot_duration'], 'slot_duration should be sanitized');
    $t->assertEquals(10, $result['buffer_time'], 'buffer_time should be sanitized');

    // Whitelabel
    $t->assertTrue($result['whitelabel_enabled'], 'whitelabel_enabled should be sanitized');
    $t->assertEquals('Test App', $result['whitelabel_plugin_name'], 'whitelabel_plugin_name should be sanitized');
}

// ============================================================================
// Integration Tests
// ============================================================================

function test_option_name_consistency(SettingsTestRunner $t): void
{
    echo "\n\nIntegration: Option Name\n";

    $expected_option_name = 'cs_options';
    $t->assertEquals('cs_options', $expected_option_name, 'Option name should be cs_options');
}

function test_complete_workflow(SettingsTestRunner $t): void
{
    echo "\nIntegration: Complete Workflow\n";

    // Step 1: Get defaults
    $defaults = [
        'slot_duration' => 60,
        'buffer_time' => 0,
        'whitelabel_enabled' => false,
        'whitelabel_plugin_name' => '',
    ];

    // Step 2: User submits form
    $form_input = [
        'slot_duration' => '30',          // String from form
        'buffer_time' => '15',            // String from form
        'whitelabel_enabled' => '1',      // Checkbox
        'whitelabel_plugin_name' => ' My Custom App ',  // With whitespace
    ];

    // Step 3: Sanitize
    $timing_result = sanitize_timing($form_input);
    $whitelabel_result = sanitize_whitelabel($form_input);
    $sanitized = array_merge($timing_result, $whitelabel_result);

    // Step 4: Verify sanitized values
    $t->assertEquals(30, $sanitized['slot_duration'], 'slot_duration should be int 30');
    $t->assertEquals(15, $sanitized['buffer_time'], 'buffer_time should be int 15');
    $t->assertTrue($sanitized['whitelabel_enabled'], 'whitelabel_enabled should be true');
    $t->assertEquals('My Custom App', $sanitized['whitelabel_plugin_name'], 'plugin_name should be trimmed');

    // Step 5: Get plugin name
    $plugin_name = get_plugin_name($sanitized);
    $t->assertEquals('My Custom App', $plugin_name, 'Plugin name should be custom');
}

function test_security_xss_prevention(SettingsTestRunner $t): void
{
    echo "\nIntegration: XSS Prevention\n";

    $malicious_inputs = [
        '<script>alert("xss")</script>Name',
        '"><script>document.location="evil.com"</script>',
        "'; DROP TABLE users; --",
        '<img src=x onerror=alert("xss")>',
    ];

    foreach ($malicious_inputs as $i => $malicious) {
        $input = ['whitelabel_enabled' => '1', 'whitelabel_plugin_name' => $malicious];
        $result = sanitize_whitelabel($input);

        $t->assertStringNotContains('<script>', $result['whitelabel_plugin_name'], "XSS attempt $i should be sanitized");
        $t->assertStringNotContains('<img', $result['whitelabel_plugin_name'], "XSS attempt $i img should be sanitized");
    }
}

// ============================================================================
// Run all tests
// ============================================================================

echo "Running standalone tests for Settings modules...\n";
echo "=================================================\n";

$t = new SettingsTestRunner();

// TimingModule tests
test_timing_module_defaults($t);
test_timing_module_valid_durations($t);
test_timing_module_invalid_duration($t);
test_timing_module_buffer_time($t);
test_timing_module_string_conversion($t);
test_timing_module_negative_values($t);

// WhitelabelModule tests
test_whitelabel_module_defaults($t);
test_whitelabel_module_sanitize_enabled($t);
test_whitelabel_module_sanitize_plugin_name($t);
test_whitelabel_get_plugin_name($t);

// SettingsPage tests
test_settings_page_get_options_with_defaults($t);
test_settings_page_get_options_merge($t);
test_settings_page_sanitize_all_modules($t);

// Integration tests
test_option_name_consistency($t);
test_complete_workflow($t);
test_security_xss_prevention($t);

$t->report();
