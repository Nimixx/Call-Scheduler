<?php
/**
 * Team Member New Booking Notification Email
 *
 * Sent to team members when a customer books a call with them.
 *
 * Available variables:
 * @var string $customerName   - Customer's name
 * @var string $customerEmail  - Customer's email address
 * @var string $bookingDate    - Formatted booking date
 * @var string $bookingTime    - Booking time (HH:MM)
 * @var string $siteName       - Website name
 * @var string $adminEmail     - Admin email address
 * @var string $logoUrl        - Logo image URL
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load partials
include_once __DIR__ . '/partials/info-card.php';

// Email metadata
$email_title = 'Nová rezervace';
$accentColor = '#6366f1'; // Indigo for notifications

// Email content (rendered inside base layout)
$email_content = function () use ($customerName, $customerEmail, $bookingDate, $bookingTime): void {
    ?>
    <h1 style="
        margin: 0 0 24px 0;
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    ">Nová rezervace</h1>

    <p style="margin: 0 0 24px 0; color: #4b5563;">
        Máte novou rezervaci hovoru od zákazníka.
    </p>

    <?php
    echo email_info_card([
        'Zákazník' => $customerName,
        'Email'    => $customerEmail,
        'Datum'    => $bookingDate,
        'Čas'      => $bookingTime,
    ], '#6366f1');
    ?>

    <p style="margin: 24px 0 0 0; color: #4b5563;">
        Nezapomeňte si hovor poznamenat do kalendáře.
    </p>
    <?php
};

// Render with base layout
include __DIR__ . '/layouts/base.php';
