<?php
/**
 * Customer Booking Confirmation Email (Generic/Unbranded)
 *
 * Sent to customers after successfully booking an appointment.
 *
 * Available variables:
 * @var string $customerName    - Customer's name
 * @var string $bookingDate     - Formatted booking date
 * @var string $bookingTime     - Formatted booking time
 * @var string $teamMemberName  - Team member's display name
 * @var string $bookingId       - Booking reference ID (optional)
 * @var string $siteName        - Website name
 * @var string $adminEmail      - Admin email address
 * @var string $logoUrl         - Logo image URL (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load partials
include_once __DIR__ . '/partials/info-card.php';

// Email metadata
$email_title = sprintf(
    /* translators: %s: site name */
    __('Booking Confirmation - %s', 'call-scheduler'),
    $siteName ?? get_bloginfo('name')
);

$preheader = sprintf(
    /* translators: %1$s: date, %2$s: time */
    __('Your booking on %1$s at %2$s has been received.', 'call-scheduler'),
    $bookingDate ?? '',
    $bookingTime ?? ''
);

$accentColor = '#10b981'; // Green for confirmation

// Email content (rendered inside base layout)
$email_content = function () use ($customerName, $bookingDate, $bookingTime, $teamMemberName, $bookingId): void {
    ?>
    <h1 style="
        margin: 0 0 24px 0;
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    " class="email-text"><?php echo esc_html__('Booking Confirmed', 'call-scheduler'); ?></h1>

    <p style="margin: 0 0 16px 0; color: #4b5563;" class="email-text">
        <?php printf(
            /* translators: %s: customer name */
            esc_html__('Hello %s,', 'call-scheduler'),
            esc_html($customerName)
        ); ?>
    </p>

    <p style="margin: 0 0 24px 0; color: #4b5563;" class="email-text">
        <?php echo esc_html__('Thank you for your booking. Here are your appointment details:', 'call-scheduler'); ?>
    </p>

    <?php
    $details = [
        __('Date', 'call-scheduler') => $bookingDate,
        __('Time', 'call-scheduler') => $bookingTime,
        __('With', 'call-scheduler') => $teamMemberName,
    ];

    if (!empty($bookingId)) {
        $details[__('Reference', 'call-scheduler')] = '#' . $bookingId;
    }

    echo email_info_card($details, '#10b981');
    ?>

    <p style="margin: 24px 0 0 0; color: #4b5563;" class="email-text">
        <?php echo esc_html__('We look forward to speaking with you!', 'call-scheduler'); ?>
    </p>
    <?php
};

// Render with base layout
include __DIR__ . '/layouts/base.php';
