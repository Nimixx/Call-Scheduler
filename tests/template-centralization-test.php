<?php
/**
 * Template centralization test
 * Verifies that templates are loaded from email-manager plugin only
 * Run with: php tests/template-centralization-test.php
 */

declare(strict_types=1);

define('ABSPATH', true);
// Get plugins directory: tests -> call-scheduler -> plugins
define('WP_PLUGIN_DIR', dirname(dirname(__DIR__)));

echo "Template Centralization Verification\n";
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

// Test 1: Templates exist in email-manager plugin (in bookings folder)
echo "Test Group 1: Templates Location\n";

$customer_confirm_path = WP_PLUGIN_DIR . '/email-manager/templates/emails/bookings/customer-confirmation.php';
$team_notification_path = WP_PLUGIN_DIR . '/email-manager/templates/emails/bookings/team-member-notification.php';
$base_layout_path = WP_PLUGIN_DIR . '/email-manager/templates/emails/layouts/base.php';

test("customer-confirmation.php exists in email-manager/bookings/", file_exists($customer_confirm_path));
test("team-member-notification.php exists in email-manager/bookings/", file_exists($team_notification_path));
test("base.php master layout exists", file_exists($base_layout_path));

// Test 2: Templates DO NOT exist in call-scheduler
echo "\n";
echo "Test Group 2: Templates Removed from Booking Plugin\n";

$old_templates_path = WP_PLUGIN_DIR . '/call-scheduler/templates';
test("templates directory removed from call-scheduler", !is_dir($old_templates_path));

// Test 3: Template content validation
echo "\n";
echo "Test Group 3: Template Architecture\n";

// Check for base layout
if (file_exists($base_layout_path)) {
    $content = file_get_contents($base_layout_path);
    test("Base layout has header", str_contains($content, 'email-header'));
    test("Base layout has footer", str_contains($content, 'email-footer'));
    test("Base layout supports variables", str_contains($content, '$email_title'));
}

// Check customer confirmation uses base layout
if (file_exists($customer_confirm_path)) {
    $content = file_get_contents($customer_confirm_path);
    test("Customer template uses base layout", str_contains($content, 'layouts/base.php'));
    test("Customer template escapes content", str_contains($content, 'esc_html('));
}

// Check team notification uses base layout
if (file_exists($team_notification_path)) {
    $content = file_get_contents($team_notification_path);
    test("Team template uses base layout", str_contains($content, 'layouts/base.php'));
    test("Team template escapes content", str_contains($content, 'esc_html('));
}

// Test 4: TemplateLoader points to email-manager
echo "\n";
echo "Test Group 4: TemplateLoader Configuration\n";

$template_loader_path = WP_PLUGIN_DIR . '/call-scheduler/src/TemplateLoader.php';
if (file_exists($template_loader_path)) {
    $loader_content = file_get_contents($template_loader_path);
    test("TemplateLoader loads from 'email-manager' plugin", str_contains($loader_content, "'email-manager'"));
    test("TemplateLoader checks email-manager path", str_contains($loader_content, '/email-manager/templates/emails/'));
    test("TemplateLoader is NOT using call-scheduler path", !str_contains($loader_content, '/call-scheduler/templates/'));
}

// Summary
echo "\n";
echo "====================================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";
echo "\n";

if ($tests_failed === 0) {
    echo "✓ All tests passed!\n";
    echo "\nModern Email Architecture:\n";
    echo "- Master Layout: email-manager/templates/emails/layouts/base.php\n";
    echo "- Partials: email-manager/templates/emails/partials/\n";
    echo "- Templates: email-manager/templates/emails/bookings/ (plugin namespaced)\n";
    echo "- Email Class: call-scheduler/src/Email.php\n";
    echo "- TemplateLoader: call-scheduler/src/TemplateLoader.php\n";
    echo "- Result: Consistent, scalable, multi-plugin email system\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
