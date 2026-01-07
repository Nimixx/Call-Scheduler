<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Template loader for email templates
 *
 * Loads PHP templates from the plugin's templates/emails/ directory.
 * Templates receive data as extracted variables.
 */
final class TemplateLoader
{
    private const TEMPLATES_DIR = 'templates/emails';

    /**
     * Load and render email template from plugin templates directory
     *
     * @param string $template Template name without extension (e.g., 'customer-confirmation')
     * @param array $data Data to pass to template as variables
     * @return string Rendered template HTML
     */
    public static function load(string $template, array $data): string
    {
        $template_path = self::getTemplatePath($template);

        if (!file_exists($template_path)) {
            return '';
        }

        // Extract data array to variables for template use
        extract($data, EXTR_SKIP);

        // Capture template output
        ob_start();
        include $template_path;
        return ob_get_clean() ?: '';
    }

    /**
     * Check if template exists in plugin templates directory
     *
     * @param string $template Template name without extension
     * @return bool True if template exists
     */
    public static function exists(string $template): bool
    {
        return file_exists(self::getTemplatePath($template));
    }

    /**
     * Get full path to template file
     *
     * @param string $template Template name without extension
     * @return string Full path to template file
     */
    private static function getTemplatePath(string $template): string
    {
        return CS_PLUGIN_DIR . self::TEMPLATES_DIR . '/' . $template . '.php';
    }
}
