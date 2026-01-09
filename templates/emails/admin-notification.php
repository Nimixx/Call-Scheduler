<?php
/**
 * Admin/Team Member New Booking Notification Email (Generic/Unbranded)
 *
 * Sent to team members when a customer books an appointment.
 *
 * Available variables:
 * @var string $customerName   - Customer's name
 * @var string $customerEmail  - Customer's email address
 * @var string $bookingDate    - Formatted booking date
 * @var string $bookingTime    - Formatted booking time
 * @var string $bookingId      - Booking reference ID (optional)
 * @var string $siteName       - Website name
 * @var string $adminEmail     - Admin email address
 * @var string $logoUrl        - Logo image URL (optional)
 * @var string $dashboardUrl   - URL to admin bookings page (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load partials
include_once __DIR__ . '/partials/info-card.php';
include_once __DIR__ . '/partials/button.php';

// Email metadata
$email_title = sprintf(
    /* translators: %s: customer name */
    __('New Booking: %s', 'call-scheduler'),
    $customerName ?? __('New Customer', 'call-scheduler')
);

$preheader = sprintf(
    /* translators: %1$s: customer name, %2$s: date, %3$s: time */
    __('New booking from %1$s on %2$s at %3$s', 'call-scheduler'),
    $customerName ?? '',
    $bookingDate ?? '',
    $bookingTime ?? ''
);

$accentColor = '#6366f1'; // Indigo for notifications

// Email content (rendered inside base layout)
$email_content = function () use ($customerName, $customerEmail, $bookingDate, $bookingTime, $bookingId, $dashboardUrl): void {
    ?>
    <h1 style="
        margin: 0 0 24px 0;
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    " class="email-text"><?php echo esc_html__('New Booking Received', 'call-scheduler'); ?></h1>

    <p style="margin: 0 0 24px 0; color: #4b5563;" class="email-text">
        <?php echo esc_html__('You have a new booking. Here are the details:', 'call-scheduler'); ?>
    </p>

    <?php
    $details = [
        __('Customer', 'call-scheduler') => $customerName,
        __('Email', 'call-scheduler')    => $customerEmail,
        __('Date', 'call-scheduler')     => $bookingDate,
        __('Time', 'call-scheduler')     => $bookingTime,
    ];

    if (!empty($bookingId)) {
        $details[__('Reference', 'call-scheduler')] = '#' . $bookingId;
    }

    echo email_info_card($details, '#6366f1');
    ?>

    <?php if (!empty($dashboardUrl)): ?>
        <?php echo email_button(
            __('View in Dashboard', 'call-scheduler'),
            $dashboardUrl,
            '#6366f1'
        ); ?>
    <?php endif; ?>

    <p style="margin: 24px 0 0 0; color: #4b5563;" class="email-text">
        <?php echo esc_html__('Please add this appointment to your calendar.', 'call-scheduler'); ?>
    </p>
    <?php
};

// Render with base layout
include __DIR__ . '/layouts/base.php';
