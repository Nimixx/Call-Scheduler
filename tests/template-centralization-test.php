<?php
/**
 * Email Template Architecture Test
 *
 * Verifies the email template system structure and rendering.
 * Run with: php tests/template-centralization-test.php
 */

declare(strict_types=1);

define('ABSPATH', true);
define('CS_PLUGIN_DIR', dirname(__DIR__) . '/');

echo "Email Template Architecture Test\n";
echo "=================================\n\n";

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

// ============================================
// Test Group 1: Directory Structure
// ============================================
echo "1. Directory Structure\n";

$templates_dir = CS_PLUGIN_DIR . 'templates/emails';
$layouts_dir   = $templates_dir . '/layouts';
$partials_dir  = $templates_dir . '/partials';

test("templates/emails/ exists", is_dir($templates_dir));
test("templates/emails/layouts/ exists", is_dir($layouts_dir));
test("templates/emails/partials/ exists", is_dir($partials_dir));

// ============================================
// Test Group 2: Core Files Exist
// ============================================
echo "\n2. Core Files\n";

test("layouts/base.php exists", file_exists($layouts_dir . '/base.php'));
test("partials/info-card.php exists", file_exists($partials_dir . '/info-card.php'));
test("partials/button.php exists", file_exists($partials_dir . '/button.php'));
test("customer-confirmation.php exists", file_exists($templates_dir . '/customer-confirmation.php'));
test("team-member-notification.php exists", file_exists($templates_dir . '/team-member-notification.php'));

// ============================================
// Test Group 3: Base Layout Quality
// ============================================
echo "\n3. Base Layout Quality\n";

$base_content = file_get_contents($layouts_dir . '/base.php');
test("Base has DOCTYPE", str_contains($base_content, '<!DOCTYPE html>'));
test("Base has table-based layout", str_contains($base_content, 'role="presentation"'));
test("Base has inline styles", str_contains($base_content, 'style="'));
test("Base has Outlook conditional", str_contains($base_content, '<!--[if mso]>'));
test("Base calls \$email_content closure", str_contains($base_content, '($email_content)()'));
test("Base has design tokens", str_contains($base_content, "\$colors = ["));

// ============================================
// Test Group 4: Partials Quality
// ============================================
echo "\n4. Partials Quality\n";

$card_content = file_get_contents($partials_dir . '/info-card.php');
test("Info card defines email_info_card function", str_contains($card_content, 'function email_info_card'));
test("Info card uses esc_html", str_contains($card_content, 'esc_html('));
test("Info card returns HTML string", str_contains($card_content, 'return <<<HTML'));

$button_content = file_get_contents($partials_dir . '/button.php');
test("Button defines email_button function", str_contains($button_content, 'function email_button'));
test("Button uses esc_url", str_contains($button_content, 'esc_url('));

// ============================================
// Test Group 5: Templates Use Layout System
// ============================================
echo "\n5. Templates Use Layout System\n";

$customer_content = file_get_contents($templates_dir . '/customer-confirmation.php');
test("Customer template includes base layout", str_contains($customer_content, "include __DIR__ . '/layouts/base.php'"));
test("Customer template includes info-card partial", str_contains($customer_content, "include_once __DIR__ . '/partials/info-card.php'"));
test("Customer template defines \$email_content closure", str_contains($customer_content, '$email_content = function'));
test("Customer template sets \$email_title", str_contains($customer_content, '$email_title'));

$team_content = file_get_contents($templates_dir . '/team-member-notification.php');
test("Team template includes base layout", str_contains($team_content, "include __DIR__ . '/layouts/base.php'"));
test("Team template includes info-card partial", str_contains($team_content, "include_once __DIR__ . '/partials/info-card.php'"));
test("Team template defines \$email_content closure", str_contains($team_content, '$email_content = function'));

// ============================================
// Test Group 6: TemplateLoader
// ============================================
echo "\n6. TemplateLoader\n";

require_once CS_PLUGIN_DIR . 'src/TemplateLoader.php';

test("TemplateLoader::exists('customer-confirmation')", \CallScheduler\TemplateLoader::exists('customer-confirmation'));
test("TemplateLoader::exists('team-member-notification')", \CallScheduler\TemplateLoader::exists('team-member-notification'));
test("TemplateLoader::exists('layouts/base')", \CallScheduler\TemplateLoader::exists('layouts/base'));

// ============================================
// Test Group 7: Template Rendering
// ============================================
echo "\n7. Template Rendering\n";

// Mock WordPress functions
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}

$test_data = [
    'customerName'   => 'Jan Novak',
    'customerEmail'  => 'jan@example.com',
    'bookingDate'    => '15. ledna 2026',
    'bookingTime'    => '14:00',
    'teamMemberName' => 'Marie Svobodova',
    'siteName'       => 'Test Site',
    'adminEmail'     => 'admin@example.com',
    'logoUrl'        => '',
];

$customer_html = \CallScheduler\TemplateLoader::load('customer-confirmation', $test_data);
test("Customer renders HTML (length > 500)", strlen($customer_html) > 500);
test("Customer contains customer name", str_contains($customer_html, 'Jan Novak'));
test("Customer contains booking date", str_contains($customer_html, '15. ledna 2026'));
test("Customer contains team member name", str_contains($customer_html, 'Marie Svobodova'));
test("Customer contains site name in footer", str_contains($customer_html, 'Test Site'));
test("Customer has table-based layout", str_contains($customer_html, 'role="presentation"'));

$team_html = \CallScheduler\TemplateLoader::load('team-member-notification', $test_data);
test("Team renders HTML (length > 500)", strlen($team_html) > 500);
test("Team contains customer name", str_contains($team_html, 'Jan Novak'));
test("Team contains customer email", str_contains($team_html, 'jan@example.com'));
test("Team has table-based layout", str_contains($team_html, 'role="presentation"'));

// ============================================
// Summary
// ============================================
echo "\n=================================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n\n";

if ($tests_failed === 0) {
    echo "All tests passed!\n\n";
    echo "Architecture:\n";
    echo "  templates/emails/\n";
    echo "  ├── layouts/\n";
    echo "  │   └── base.php          # Master layout\n";
    echo "  ├── partials/\n";
    echo "  │   ├── button.php        # CTA button component\n";
    echo "  │   └── info-card.php     # Info card component\n";
    echo "  ├── customer-confirmation.php\n";
    echo "  └── team-member-notification.php\n";
    exit(0);
} else {
    echo "Some tests failed.\n";
    exit(1);
}
