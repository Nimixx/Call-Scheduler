<?php
/**
 * Email functionality tests
 * Run with: php tests/email-demo.php
 */

declare(strict_types=1);

define('ABSPATH', true);

// Mock WordPress functions
function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_html__($text, $domain = 'default') {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function get_bloginfo($show = '') {
    $options = [
        'name' => 'Test Booking Site',
        'description' => 'Professional Booking Platform',
    ];
    return $options[$show] ?? 'Test Site';
}

function get_option($option, $default = false) {
    $options = [
        'admin_email' => 'admin@example.com',
    ];
    return $options[$option] ?? $default;
}

function get_user_by($field, $value) {
    // Mock user data
    $users = [
        1 => (object)[
            'ID' => 1,
            'user_login' => 'john',
            'user_email' => 'john@example.com',
            'display_name' => 'John Smith',
        ],
    ];

    if ($field === 'ID' && isset($users[$value])) {
        return $users[$value];
    }

    return false;
}

// Track sent emails
$GLOBALS['_test_emails'] = [];

function wp_mail($to, $subject, $message, $headers = '') {
    global $_test_emails;

    $_test_emails[] = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'headers' => $headers,
    ];

    return true;
}

// Load Email class
require_once __DIR__ . '/../src/Email.php';

use CallScheduler\Email;

echo "Email Functionality Tests\n";
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

// Test 1: Send customer confirmation email
echo "Test Group 1: Customer Confirmation Email\n";

$email = new Email();
$booking = [
    'customer_name' => 'Jane Doe',
    'customer_email' => 'jane@example.com',
    'booking_date' => '2026-01-15',
    'booking_time' => '14:00',
    'user_id' => 1,
];

$GLOBALS['_test_emails'] = [];
$result = $email->sendCustomerConfirmation($booking);

test("Customer confirmation email sent successfully", $result === true);
test("Email was queued for sending", count($GLOBALS['_test_emails']) === 1);

if (!empty($GLOBALS['_test_emails'])) {
    $sent_email = $GLOBALS['_test_emails'][0];
    test("Email sent to customer", $sent_email['to'] === 'jane@example.com');
    test("Email subject contains 'Confirmed'", str_contains($sent_email['subject'], 'Confirmed'));
    test("Email message contains customer name", str_contains($sent_email['message'], 'Jane Doe'));
    test("Email message contains booking date", str_contains($sent_email['message'], '2026-01-15'));
    test("Email message contains booking time", str_contains($sent_email['message'], '14:00'));
    test("Email is HTML format", str_contains($sent_email['message'], '<html'));
}

echo "\n";

// Test 2: Send team member notification email
echo "Test Group 2: Team Member Notification Email\n";

$GLOBALS['_test_emails'] = [];
$result = $email->sendTeamMemberNotification($booking);

test("Team member notification sent successfully", $result === true);
test("Email was queued for sending", count($GLOBALS['_test_emails']) === 1);

if (!empty($GLOBALS['_test_emails'])) {
    $sent_email = $GLOBALS['_test_emails'][0];
    test("Email sent to team member", $sent_email['to'] === 'john@example.com');
    test("Email subject contains customer name", str_contains($sent_email['subject'], 'Jane Doe'));
    test("Email message contains 'New Booking'", str_contains($sent_email['message'], 'New Booking'));
    test("Email message contains customer email", str_contains($sent_email['message'], 'jane@example.com'));
    test("Email message contains booking date", str_contains($sent_email['message'], '2026-01-15'));
}

echo "\n";

// Test 3: Email structure validation
echo "Test Group 3: Email Structure Validation\n";

$GLOBALS['_test_emails'] = [];
$email->sendCustomerConfirmation($booking);

if (!empty($GLOBALS['_test_emails'])) {
    $sent_email = $GLOBALS['_test_emails'][0];

    // Check for essential HTML structure
    test("Email contains DOCTYPE", str_contains($sent_email['message'], '<!DOCTYPE html'));
    test("Email contains body tag", str_contains($sent_email['message'], '<body'));
    test("Email contains heading", str_contains($sent_email['message'], '<h1'));
    test("Email contains styling", str_contains($sent_email['message'], '<style'));
    test("Email has proper closing tags", str_contains($sent_email['message'], '</html>'));

    // Check headers
    test("Email headers contain HTML content type", str_contains($sent_email['headers'][0], 'text/html'));
    test("Email headers contain charset", str_contains($sent_email['headers'][0], 'UTF-8'));
}

echo "\n";

// Test 4: Multiple bookings sequence
echo "Test Group 4: Multiple Bookings Sequence\n";

$GLOBALS['_test_emails'] = [];

$bookings = [
    [
        'customer_name' => 'Alice',
        'customer_email' => 'alice@example.com',
        'booking_date' => '2026-01-20',
        'booking_time' => '10:00',
        'user_id' => 1,
    ],
    [
        'customer_name' => 'Bob',
        'customer_email' => 'bob@example.com',
        'booking_date' => '2026-01-21',
        'booking_time' => '11:00',
        'user_id' => 1,
    ],
    [
        'customer_name' => 'Charlie',
        'customer_email' => 'charlie@example.com',
        'booking_date' => '2026-01-22',
        'booking_time' => '12:00',
        'user_id' => 1,
    ],
];

foreach ($bookings as $booking) {
    $email->sendCustomerConfirmation($booking);
    $email->sendTeamMemberNotification($booking);
}

test("3 bookings create 6 emails (customer + team member each)", count($GLOBALS['_test_emails']) === 6);

$customer_emails = array_filter($GLOBALS['_test_emails'], function($e) {
    return $e['to'] === 'alice@example.com' || $e['to'] === 'bob@example.com' || $e['to'] === 'charlie@example.com';
});

test("3 customer confirmation emails sent", count($customer_emails) === 3);

$team_emails = array_filter($GLOBALS['_test_emails'], function($e) {
    return $e['to'] === 'john@example.com';
});

test("3 team member notification emails sent", count($team_emails) === 3);

echo "\n";

// Summary
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
