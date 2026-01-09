<?php
/**
 * Booking Status Change Email (Generic/Unbranded)
 *
 * Sent to customers when their booking status changes.
 *
 * Available variables:
 * @var string $customerName    - Customer's name
 * @var string $bookingDate     - Formatted booking date
 * @var string $bookingTime     - Formatted booking time
 * @var string $teamMemberName  - Team member's display name
 * @var string $bookingId       - Booking reference ID (optional)
 * @var string $newStatus       - New status label (e.g., "Confirmed")
 * @var string $newStatusRaw    - New status value (e.g., "confirmed")
 * @var string $oldStatus       - Previous status label (optional)
 * @var string $statusColor     - Status color hex code
 * @var string $siteName        - Website name
 * @var string $adminEmail      - Admin email address
 * @var string $logoUrl         - Logo image URL (optional)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load partials
include_once __DIR__ . '/partials/info-card.php';
include_once __DIR__ . '/partials/status-badge.php';

// Determine accent color based on status
$statusColors = [
    'pending'   => '#ea580c', // Orange
    'confirmed' => '#10b981', // Green
    'cancelled' => '#ef4444', // Red
];

$accentColor = $statusColor ?? ($statusColors[$newStatusRaw ?? 'pending'] ?? '#6366f1');

// Email metadata
$email_title = sprintf(
    /* translators: %s: status label */
    __('Booking %s', 'call-scheduler'),
    $newStatus ?? __('Updated', 'call-scheduler')
);

// Status-specific preheaders
$preheaderTemplates = [
    'confirmed' => __('Great news! Your booking has been confirmed.', 'call-scheduler'),
    'cancelled' => __('Your booking has been cancelled.', 'call-scheduler'),
    'pending'   => __('Your booking status has been updated.', 'call-scheduler'),
];

$preheader = $preheaderTemplates[$newStatusRaw ?? 'pending'] ??
    sprintf(
        /* translators: %s: status */
        __('Your booking status has been changed to %s.', 'call-scheduler'),
        $newStatus ?? ''
    );

// Status-specific messages
$statusMessages = [
    'confirmed' => __('Great news! Your booking has been confirmed. We look forward to your appointment.', 'call-scheduler'),
    'cancelled' => __('Your booking has been cancelled. If you did not request this cancellation, please contact us.', 'call-scheduler'),
    'pending'   => __('Your booking is pending review and will be confirmed shortly.', 'call-scheduler'),
];

$statusMessage = $statusMessages[$newStatusRaw ?? 'pending'] ??
    sprintf(
        /* translators: %s: status */
        __('Your booking status has been changed to %s.', 'call-scheduler'),
        $newStatus ?? ''
    );

// Email content (rendered inside base layout)
$email_content = function () use (
    $customerName,
    $bookingDate,
    $bookingTime,
    $teamMemberName,
    $bookingId,
    $newStatus,
    $oldStatus,
    $accentColor,
    $statusMessage
): void {
    ?>
    <h1 style="
        margin: 0 0 24px 0;
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    " class="email-text"><?php echo esc_html__('Booking Status Update', 'call-scheduler'); ?></h1>

    <p style="margin: 0 0 16px 0; color: #4b5563;" class="email-text">
        <?php printf(
            /* translators: %s: customer name */
            esc_html__('Hello %s,', 'call-scheduler'),
            esc_html($customerName)
        ); ?>
    </p>

    <!-- Status Badge -->
    <p style="margin: 0 0 16px 0;">
        <?php echo esc_html__('Status:', 'call-scheduler'); ?>
        <?php echo email_status_badge($newStatus, $accentColor); ?>
    </p>

    <p style="margin: 0 0 24px 0; color: #4b5563;" class="email-text">
        <?php echo esc_html($statusMessage); ?>
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

    echo email_info_card($details, $accentColor);
    ?>

    <?php if (!empty($oldStatus)): ?>
    <p style="margin: 24px 0 0 0; font-size: 14px; color: #6b7280;" class="email-text-light">
        <?php printf(
            /* translators: %s: previous status */
            esc_html__('Previous status: %s', 'call-scheduler'),
            esc_html($oldStatus)
        ); ?>
    </p>
    <?php endif; ?>
    <?php
};

// Render with base layout
include __DIR__ . '/layouts/base.php';
