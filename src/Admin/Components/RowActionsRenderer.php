<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Components;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders row action links for status changes
 *
 * Consolidates duplicate status action rendering logic from BookingsRenderer
 * and DashboardRenderer into a single, reusable component.
 */
final class RowActionsRenderer
{
    /**
     * Render row action links (status changes only)
     *
     * @param int $bookingId Booking ID
     * @param string $currentStatus Current booking status
     * @return string HTML for row actions
     */
    public static function render(int $bookingId, string $currentStatus): string
    {
        $availableStatuses = array_filter(
            BookingStatus::all(),
            fn($status) => $status !== $currentStatus
        );

        if (empty($availableStatuses)) {
            return '';
        }

        $links = [];
        foreach ($availableStatuses as $status) {
            $links[] = sprintf(
                '<a href="#" '
                . 'class="cs-row-action-status" '
                . 'data-booking-id="%d" '
                . 'data-new-status="%s" '
                . 'role="button" '
                . 'aria-label="%s" '
                . 'tabindex="0">%s</a>',
                $bookingId,
                esc_attr($status),
                esc_attr(sprintf(__('Změnit stav na: %s', 'call-scheduler'), BookingStatus::label($status))),
                esc_html(BookingStatus::label($status))
            );
        }

        ob_start();
        ?>
        <div class="row-actions">
            <span class="status"><?php echo implode(' | ', $links); ?></span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render delete button for standalone column
     *
     * @param int $bookingId Booking ID
     * @return string HTML for delete button
     */
    public static function renderDeleteButton(int $bookingId): string
    {
        ob_start();
        ?>
        <a href="#"
           class="cs-row-action-delete submitdelete"
           data-booking-id="<?php echo esc_attr($bookingId); ?>"
           role="button"
           aria-label="<?php esc_attr_e('Smazat tuto rezervaci', 'call-scheduler'); ?>"
           tabindex="0">
            <?php esc_html_e('Smazat', 'call-scheduler'); ?>
        </a>
        <?php
        return ob_get_clean();
    }
}
