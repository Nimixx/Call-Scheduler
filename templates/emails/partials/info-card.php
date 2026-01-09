<?php
/**
 * Email Info Card Partial (Clean Light Theme)
 *
 * Renders a styled information card with key-value pairs.
 *
 * Usage:
 *   <?php include __DIR__ . '/partials/info-card.php'; ?>
 *   <?php echo email_info_card([
 *       'Date' => 'January 15, 2026',
 *       'Time' => '14:00',
 *   ]); ?>
 *
 * @param array  $items       - Associative array of label => value pairs
 * @param string $accentColor - Left border accent color (default: #2563eb)
 * @return string HTML card markup
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('email_info_card')) {
    function email_info_card(array $items, string $accentColor = '#2563eb'): string
    {
        $rows = '';
        $lastKey = array_key_last($items);

        foreach ($items as $label => $value) {
            $label = esc_html($label);
            $value = esc_html($value);
            $isLast = $label === $lastKey;
            $borderBottom = $isLast ? 'none' : '1px solid #f1f5f9';

            $rows .= <<<HTML
                <tr>
                    <td style="
                        padding: 14px 0;
                        border-bottom: {$borderBottom};
                        color: #64748b;
                        font-size: 14px;
                        width: 90px;
                        vertical-align: top;
                    ">{$label}</td>
                    <td style="
                        padding: 14px 0;
                        border-bottom: {$borderBottom};
                        color: #1e293b;
                        font-weight: 500;
                        font-size: 15px;
                    ">{$value}</td>
                </tr>
            HTML;
        }

        return <<<HTML
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="
            margin: 24px 0;
            background-color: #f8fafc;
            border-radius: 12px;
            border-left: 3px solid {$accentColor};
        ">
            <tr>
                <td style="padding: 8px 24px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                        {$rows}
                    </table>
                </td>
            </tr>
        </table>
        HTML;
    }
}
