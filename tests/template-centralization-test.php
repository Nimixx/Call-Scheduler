<?php
/**
 * Template centralization test
 * Verifies that email templates are defined within the call-scheduler plugin
 * Run with: php tests/template-centralization-test.php
 */

declare(strict_types=1);

define('ABSPATH', true);
define('CS_PLUGIN_DIR', dirname(__DIR__) . '/');

echo "Email Template Verification\n";
echo "============================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test(string $name, bool $result): void {
    global $tests_passed, $tests_failed;

    if ($result) {
        echo "  PASS: $name\n";
        $tests_passed++;
    } else {
        echo "  FAIL: $name\n";
        $tests_failed++;
    }
}

// Test 1: Templates directory exists
echo "Test Group 1: Template Directory Structure\n";

$templates_dir = CS_PLUGIN_DIR . 'templates/emails';
test("templates/emails directory exists", is_dir($templates_dir));

// Test 2: Required templates exist
echo "\nTest Group 2: Email Templates Exist\n";

$customer_confirm_path = $templates_dir . '/customer-confirmation.php';
$team_notification_path = $templates_dir . '/team-member-notification.php';

test("customer-confirmation.php exists", file_exists($customer_confirm_path));
test("team-member-notification.php exists", file_exists($team_notification_path));

// Test 3: Template content validation
echo "\nTest Group 3: Template Content Quality\n";

if (file_exists($customer_confirm_path)) {
    $content = file_get_contents($customer_confirm_path);
    test("Customer template has DOCTYPE", str_contains($content, '<!DOCTYPE html>'));
    test("Customer template has proper escaping", str_contains($content, 'esc_html('));
    test("Customer template has ABSPATH check", str_contains($content, "defined('ABSPATH')"));
    test("Customer template uses customerName variable", str_contains($content, '$customerName'));
    test("Customer template uses bookingDate variable", str_contains($content, '$bookingDate'));
    test("Customer template uses bookingTime variable", str_contains($content, '$bookingTime'));
}

if (file_exists($team_notification_path)) {
    $content = file_get_contents($team_notification_path);
    test("Team template has DOCTYPE", str_contains($content, '<!DOCTYPE html>'));
    test("Team template has proper escaping", str_contains($content, 'esc_html('));
    test("Team template has ABSPATH check", str_contains($content, "defined('ABSPATH')"));
    test("Team template uses customerEmail variable", str_contains($content, '$customerEmail'));
}

// Test 4: TemplateLoader configuration
echo "\nTest Group 4: TemplateLoader Configuration\n";

$template_loader_path = CS_PLUGIN_DIR . 'src/TemplateLoader.php';
if (file_exists($template_loader_path)) {
    $loader_content = file_get_contents($template_loader_path);
    test("TemplateLoader uses CS_PLUGIN_DIR", str_contains($loader_content, 'CS_PLUGIN_DIR'));
    test("TemplateLoader points to templates/emails", str_contains($loader_content, 'templates/emails'));
    test("TemplateLoader does NOT depend on email-manager", !str_contains($loader_content, 'email-manager'));
    test("TemplateLoader has load() method", str_contains($loader_content, 'public static function load'));
    test("TemplateLoader has exists() method", str_contains($loader_content, 'public static function exists'));
}

// Test 5: TemplateLoader functionality
echo "\nTest Group 5: TemplateLoader Functionality\n";

require_once $template_loader_path;

test("TemplateLoader::exists('customer-confirmation') returns true", \CallScheduler\TemplateLoader::exists('customer-confirmation'));
test("TemplateLoader::exists('team-member-notification') returns true", \CallScheduler\TemplateLoader::exists('team-member-notification'));
test("TemplateLoader::exists('nonexistent') returns false", !\CallScheduler\TemplateLoader::exists('nonexistent'));

// Test 6: Template rendering
echo "\nTest Group 6: Template Rendering\n";

// Mock WordPress functions for testing
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}

$test_data = [
    'customerName' => 'Jan Novak',
    'customerEmail' => 'jan@example.com',
    'bookingDate' => '15. ledna 2026',
    'bookingTime' => '14:00',
    'teamMemberName' => 'Marie Svobodova',
    'siteName' => 'Test Site',
    'adminEmail' => 'admin@example.com',
    'logoUrl' => 'https://example.com/logo.png',
];

$customer_html = \CallScheduler\TemplateLoader::load('customer-confirmation', $test_data);
test("Customer template renders HTML", strlen($customer_html) > 100);
test("Customer template contains customer name", str_contains($customer_html, 'Jan Novak'));
test("Customer template contains booking date", str_contains($customer_html, '15. ledna 2026'));
test("Customer template contains booking time", str_contains($customer_html, '14:00'));

$team_html = \CallScheduler\TemplateLoader::load('team-member-notification', $test_data);
test("Team template renders HTML", strlen($team_html) > 100);
test("Team template contains customer name", str_contains($team_html, 'Jan Novak'));
test("Team template contains customer email", str_contains($team_html, 'jan@example.com'));

// Summary
echo "\n============================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";
echo "\n";

if ($tests_failed === 0) {
    echo "All tests passed!\n";
    echo "\nEmail Template Architecture:\n";
    echo "- Templates: call-scheduler/templates/emails/\n";
    echo "- Loader: call-scheduler/src/TemplateLoader.php\n";
    echo "- Email: call-scheduler/src/Email.php\n";
    echo "- No external plugin dependencies\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
