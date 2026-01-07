<?php
/**
 * Webhook Integration Tests
 * Run with: php tests/webhook-test.php
 */

declare(strict_types=1);

define('ABSPATH', true);

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option(string $key, $default = []) {
        global $mock_options;
        return $mock_options[$key] ?? $default;
    }
}

if (!function_exists('home_url')) {
    function home_url(): string {
        return 'https://example.com';
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by(string $field, $value) {
        if ($value === 1) {
            return (object) [
                'ID' => 1,
                'display_name' => 'Test User',
                'user_email' => 'test@example.com',
            ];
        }
        return false;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args) {
        global $mock_webhook_calls;
        $mock_webhook_calls[] = ['url' => $url, 'args' => $args];
        return ['response' => ['code' => 200]];
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return false;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('error_log')) {
    function error_log(string $message): void {
        global $mock_error_log;
        $mock_error_log[] = $message;
    }
}

// Load classes
require_once __DIR__ . '/../src/BookingStatus.php';
require_once __DIR__ . '/../src/Webhook.php';

use CallScheduler\Webhook;
use CallScheduler\BookingStatus;

// Global mocks
$mock_options = [];
$mock_webhook_calls = [];
$mock_error_log = [];

echo "Webhook Integration Tests\n";
echo "=========================\n\n";

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

function reset_mocks(): void {
    global $mock_options, $mock_webhook_calls, $mock_error_log;
    $mock_options = [];
    $mock_webhook_calls = [];
    $mock_error_log = [];
}

// =============================================================================
// Test Group 1: Webhook Disabled by Default
// =============================================================================
echo "Test Group 1: Webhook Disabled by Default\n";
reset_mocks();

$webhook = new Webhook();
test("isEnabled() returns false when no options set", Webhook::isEnabled() === false);

$mock_options['cs_options'] = ['webhook_enabled' => false];
test("isEnabled() returns false when explicitly disabled", Webhook::isEnabled() === false);

$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => ''];
test("isEnabled() returns false when URL is empty", Webhook::isEnabled() === false);

$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => 'https://example.com/webhook'];
test("isEnabled() returns true when enabled with URL", Webhook::isEnabled() === true);

echo "\n";

// =============================================================================
// Test Group 2: Secret Management
// =============================================================================
echo "Test Group 2: Secret Management\n";
reset_mocks();

test("getSecret() returns empty when constant not defined", Webhook::getSecret() === '');
test("hasSecret() returns false when constant not defined", Webhook::hasSecret() === false);

// Define the constant for subsequent tests
if (!defined('CS_WEBHOOK_SECRET')) {
    define('CS_WEBHOOK_SECRET', 'test-secret-key-12345');
}

test("getSecret() returns value when constant defined", Webhook::getSecret() === 'test-secret-key-12345');
test("hasSecret() returns true when constant defined", Webhook::hasSecret() === true);

echo "\n";

// =============================================================================
// Test Group 3: Input Validation
// =============================================================================
echo "Test Group 3: Input Validation\n";
reset_mocks();
$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => 'https://webhook.site/test'];
define('WP_DEBUG', true);

$webhook = new Webhook();

// Missing required fields
$result = $webhook->sendBookingCreated([]);
test("Rejects empty booking array", $result === false);

$result = $webhook->sendBookingCreated(['user_id' => 1]);
test("Rejects booking missing customer_name", $result === false);

$result = $webhook->sendBookingCreated([
    'user_id' => 1,
    'customer_name' => 'Test',
]);
test("Rejects booking missing customer_email", $result === false);

$result = $webhook->sendBookingCreated([
    'user_id' => 1,
    'customer_name' => 'Test',
    'customer_email' => 'test@test.com',
]);
test("Rejects booking missing booking_date", $result === false);

$result = $webhook->sendBookingCreated([
    'user_id' => 1,
    'customer_name' => 'Test',
    'customer_email' => 'test@test.com',
    'booking_date' => '2026-01-15',
]);
test("Rejects booking missing booking_time", $result === false);

// Valid booking
$result = $webhook->sendBookingCreated([
    'id' => 123,
    'user_id' => 1,
    'customer_name' => 'Test Customer',
    'customer_email' => 'test@test.com',
    'booking_date' => '2026-01-15',
    'booking_time' => '10:00',
    'status' => BookingStatus::PENDING,
]);
test("Accepts valid booking with all required fields", $result === true);

echo "\n";

// =============================================================================
// Test Group 4: HTTPS Enforcement
// =============================================================================
echo "Test Group 4: HTTPS Enforcement\n";
reset_mocks();

$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => 'http://example.com/webhook'];
$webhook = new Webhook();

$result = $webhook->sendBookingCreated([
    'id' => 1,
    'user_id' => 1,
    'customer_name' => 'Test',
    'customer_email' => 'test@test.com',
    'booking_date' => '2026-01-15',
    'booking_time' => '10:00',
]);
test("Rejects HTTP URLs", $result === false);

echo "\n";

// =============================================================================
// Test Group 5: SSRF Protection
// =============================================================================
echo "Test Group 5: SSRF Protection\n";

$ssrf_urls = [
    'https://localhost/webhook' => 'localhost',
    'https://127.0.0.1/webhook' => '127.0.0.1',
    'https://10.0.0.1/webhook' => '10.x.x.x range',
    'https://172.16.0.1/webhook' => '172.16.x.x range',
    'https://192.168.1.1/webhook' => '192.168.x.x range',
    'https://internal.local/webhook' => '.local domain',
    'https://api.internal/webhook' => '.internal domain',
    'https://test.localhost/webhook' => '.localhost domain',
];

foreach ($ssrf_urls as $url => $description) {
    reset_mocks();
    $mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => $url];
    $webhook = new Webhook();

    $result = $webhook->sendBookingCreated([
        'id' => 1,
        'user_id' => 1,
        'customer_name' => 'Test',
        'customer_email' => 'test@test.com',
        'booking_date' => '2026-01-15',
        'booking_time' => '10:00',
    ]);
    test("Blocks $description", $result === false);
}

echo "\n";

// =============================================================================
// Test Group 6: Payload Structure
// =============================================================================
echo "Test Group 6: Payload Structure\n";
reset_mocks();
$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => 'https://webhook.site/test'];

$webhook = new Webhook();
$webhook->sendBookingCreated([
    'id' => 42,
    'user_id' => 1,
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'booking_date' => '2026-01-20',
    'booking_time' => '14:30',
    'status' => BookingStatus::CONFIRMED,
]);

test("Webhook was dispatched", count($mock_webhook_calls) === 1);

if (count($mock_webhook_calls) > 0) {
    $call = $mock_webhook_calls[0];
    $payload = json_decode($call['args']['body'], true);

    test("Payload has event field", isset($payload['event']));
    test("Event is booking.created", $payload['event'] === 'booking.created');
    test("Payload has timestamp field", isset($payload['timestamp']));
    test("Payload has data field", isset($payload['data']));
    test("Payload has meta field", isset($payload['meta']));

    test("Booking ID is correct", $payload['data']['booking']['id'] === 42);
    test("Customer name is correct", $payload['data']['booking']['customer_name'] === 'John Doe');
    test("Customer email is correct", $payload['data']['booking']['customer_email'] === 'john@example.com');
    test("Booking date is correct", $payload['data']['booking']['booking_date'] === '2026-01-20');
    test("Booking time is correct", $payload['data']['booking']['booking_time'] === '14:30');
    test("Status is correct", $payload['data']['booking']['status'] === BookingStatus::CONFIRMED);

    test("Team member data included", isset($payload['data']['team_member']));
    test("Team member ID is correct", $payload['data']['team_member']['id'] === 1);
    test("Team member name is correct", $payload['data']['team_member']['display_name'] === 'Test User');

    test("Meta has plugin_version", isset($payload['meta']['plugin_version']));
    test("Meta has site_url", $payload['meta']['site_url'] === 'https://example.com');
}

echo "\n";

// =============================================================================
// Test Group 7: Headers
// =============================================================================
echo "Test Group 7: Headers\n";
reset_mocks();
$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => 'https://webhook.site/test'];

$webhook = new Webhook();
$webhook->sendBookingCreated([
    'id' => 1,
    'user_id' => 1,
    'customer_name' => 'Test',
    'customer_email' => 'test@test.com',
    'booking_date' => '2026-01-15',
    'booking_time' => '10:00',
]);

if (count($mock_webhook_calls) > 0) {
    $headers = $mock_webhook_calls[0]['args']['headers'];

    test("Content-Type header is application/json", $headers['Content-Type'] === 'application/json');
    test("X-CS-Event header is set", isset($headers['X-CS-Event']));
    test("X-CS-Event is booking.created", $headers['X-CS-Event'] === 'booking.created');
    test("X-CS-Timestamp header is set", isset($headers['X-CS-Timestamp']));
    test("X-CS-Signature header is set (secret configured)", isset($headers['X-CS-Signature']));

    // Verify signature
    $payload = $mock_webhook_calls[0]['args']['body'];
    $expected_signature = hash_hmac('sha256', $payload, 'test-secret-key-12345');
    test("Signature is valid HMAC-SHA256", $headers['X-CS-Signature'] === $expected_signature);
}

echo "\n";

// =============================================================================
// Test Group 8: Non-Blocking Configuration
// =============================================================================
echo "Test Group 8: Non-Blocking Configuration\n";
reset_mocks();
$mock_options['cs_options'] = ['webhook_enabled' => true, 'webhook_url' => 'https://webhook.site/test'];

$webhook = new Webhook();
$webhook->sendBookingCreated([
    'id' => 1,
    'user_id' => 1,
    'customer_name' => 'Test',
    'customer_email' => 'test@test.com',
    'booking_date' => '2026-01-15',
    'booking_time' => '10:00',
]);

if (count($mock_webhook_calls) > 0) {
    $args = $mock_webhook_calls[0]['args'];

    test("blocking is false (fire-and-forget)", $args['blocking'] === false);
    test("timeout is very short", $args['timeout'] <= 1);
    test("sslverify is true (secure)", $args['sslverify'] === true);
}

echo "\n";

// =============================================================================
// Summary
// =============================================================================
echo "=========================\n";
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
