<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Components;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable status badge renderer
 *
 * Provides consistent status badge rendering across all admin pages.
 * Uses BookingStatus::color() and BookingStatus::label() as single source of truth.
 */
final class StatusBadgeRenderer
{
    /**
     * Render status badge HTML
     *
     * @param string $status Booking status constant
     * @return string HTML for status badge
     */
    public static function render(string $status): string
    {
        return sprintf(
            '<span class="cs-status-badge %s" style="background-color: %s;">%s</span>',
            esc_attr($status),
            esc_attr(BookingStatus::color($status)),
            esc_html(BookingStatus::label($status))
        );
    }
}
