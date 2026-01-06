<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplateLoader
{
    private const TEMPLATE_NAMESPACE = 'bookings';

    /**
     * Load and render email template from email-manager plugin using Blade
     *
     * @param string $template Template name (e.g., 'customer-confirmation')
     * @param array $data Data to pass to template
     * @return string Rendered template HTML
     */
    public static function load(string $template, array $data): string
    {
        // Check if email-manager plugin is active
        if (!self::isEmailManagerActive()) {
            return '';
        }

        // Load BladeRenderer from email-manager plugin
        $path = WP_PLUGIN_DIR . '/email-manager/src/BladeRenderer.php';
        if (!file_exists($path)) {
            return '';
        }

        // Ensure Composer autoload is loaded for BladeOne
        $autoload = WP_PLUGIN_DIR . '/email-manager/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        require_once $path;

        // Load template using Blade dot notation
        // Example: 'customer-confirmation' becomes 'emails.bookings.customer-confirmation'
        $template_path = 'emails.' . self::TEMPLATE_NAMESPACE . '.' . $template;
        return \EmailManager\BladeRenderer::render($template_path, $data);
    }

    /**
     * Check if email-manager plugin is active
     *
     * @return bool True if plugin is active
     */
    private static function isEmailManagerActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('email-manager/email-manager.php');
    }

    /**
     * Check if template exists in email-manager plugin
     *
     * @param string $template Template name
     * @return bool True if template exists
     */
    public static function exists(string $template): bool
    {
        $template_path = self::TEMPLATE_NAMESPACE . '/' . $template;
        $path = WP_PLUGIN_DIR . '/email-manager/templates/emails/' . $template_path . '.blade.php';
        return file_exists($path);
    }
}
