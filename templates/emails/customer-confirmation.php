<?php
/**
 * Customer Booking Confirmation Email
 *
 * Sent to customers after successfully booking a call.
 *
 * Available variables:
 * @var string $customerName    - Customer's name
 * @var string $bookingDate     - Formatted booking date
 * @var string $bookingTime     - Booking time (HH:MM)
 * @var string $teamMemberName  - Team member's display name
 * @var string $siteName        - Website name
 * @var string $adminEmail      - Admin email address
 * @var string $logoUrl         - Logo image URL
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load partials
include_once __DIR__ . '/partials/info-card.php';

// Email metadata
$email_title = 'Potvrzení rezervace';
$accentColor = '#10b981'; // Green for confirmation

// Email content (rendered inside base layout)
$email_content = function () use ($customerName, $bookingDate, $bookingTime, $teamMemberName): void {
    ?>
    <h1 style="
        margin: 0 0 24px 0;
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    ">Vaše rezervace je potvrzena</h1>

    <p style="margin: 0 0 16px 0; color: #4b5563;">
        Dobrý den, <?php echo esc_html($customerName); ?>,
    </p>

    <p style="margin: 0 0 24px 0; color: #4b5563;">
        děkujeme za Vaši rezervaci. Níže najdete detaily Vašeho hovoru:
    </p>

    <?php
    echo email_info_card([
        'Datum'  => $bookingDate,
        'Čas'    => $bookingTime,
        'S kým'  => $teamMemberName,
    ], '#10b981');
    ?>

    <p style="margin: 24px 0 0 0; color: #4b5563;">
        Těšíme se na Váš hovor!
    </p>
    <?php
};

// Render with base layout
include __DIR__ . '/layouts/base.php';
