<?php
/**
 * Email Status Badge Partial
 *
 * Renders a styled status badge for email templates.
 *
 * Usage:
 *   <?php include __DIR__ . '/partials/status-badge.php'; ?>
 *   <?php echo email_status_badge('Confirmed', '#10b981'); ?>
 *
 * @param string $statusLabel - Human-readable status text
 * @param string $color       - Badge background color (hex)
 * @return string HTML badge markup
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('email_status_badge')) {
    function email_status_badge(string $statusLabel, string $color): string
    {
        $label = esc_html($statusLabel);

        return <<<HTML
        <span style="
            display: inline-block;
            padding: 6px 12px;
            background-color: {$color};
            color: #ffffff;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 4px;
        ">{$label}</span>
        HTML;
    }
}
