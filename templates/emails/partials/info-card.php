<?php
/**
 * Email Info Card Partial
 *
 * Renders a styled information card with key-value pairs.
 *
 * Usage:
 *   <?php include __DIR__ . '/partials/info-card.php'; ?>
 *   <?php echo email_info_card([
 *       'Datum' => '15. ledna 2026',
 *       'Čas'   => '14:00',
 *   ]); ?>
 *
 * @param array  $items - Associative array of label => value pairs
 * @param string $accentColor - Left border accent color (default: #6366f1)
 * @return string HTML card markup
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('email_info_card')) {
    function email_info_card(array $items, string $accentColor = '#6366f1'): string
    {
        $rows = '';
        foreach ($items as $label => $value) {
            $label = esc_html($label);
            $value = esc_html($value);
            $rows .= <<<HTML
                <tr>
                    <td style="
                        padding: 12px 0;
                        border-bottom: 1px solid #f3f4f6;
                        color: #6b7280;
                        font-size: 14px;
                        width: 100px;
                        vertical-align: top;
                    ">{$label}</td>
                    <td style="
                        padding: 12px 0;
                        border-bottom: 1px solid #f3f4f6;
                        color: #1f2937;
                        font-weight: 500;
                    ">{$value}</td>
                </tr>
            HTML;
        }

        return <<<HTML
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="
            margin: 24px 0;
            background-color: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid {$accentColor};
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                        {$rows}
                    </table>
                </td>
            </tr>
        </table>
        HTML;
    }
}
