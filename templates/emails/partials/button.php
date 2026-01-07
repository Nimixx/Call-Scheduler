<?php
/**
 * Email Button Partial
 *
 * Renders a styled call-to-action button.
 *
 * Usage:
 *   <?php include __DIR__ . '/partials/button.php'; ?>
 *   <?php echo email_button('Click Here', 'https://example.com'); ?>
 *
 * @param string $text  - Button text
 * @param string $url   - Button link URL
 * @param string $color - Button color (default: #6366f1)
 * @return string HTML button markup
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('email_button')) {
    function email_button(string $text, string $url, string $color = '#6366f1'): string
    {
        $text = esc_html($text);
        $url  = esc_url($url);

        return <<<HTML
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 24px 0;">
            <tr>
                <td align="center" style="
                    background-color: {$color};
                    border-radius: 8px;
                ">
                    <a href="{$url}" target="_blank" style="
                        display: inline-block;
                        padding: 14px 28px;
                        font-size: 16px;
                        font-weight: 600;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 8px;
                    ">{$text}</a>
                </td>
            </tr>
        </table>
        HTML;
    }
}
