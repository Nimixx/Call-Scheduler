<?php
/**
 * Plugin Name: Call Scheduler
 * Plugin URI: https://github.com/Nimixx/WP-booking
 * Description: Simple booking system for sales calls
 * Version: 1.0.0
 * Author: Call Scheduler
 * Author URI: https://github.com/Nimixx/WP-booking
 * Text Domain: call-scheduler
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

declare(strict_types=1);

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CS_VERSION', '1.0.0');
define('CS_PLUGIN_FILE', __FILE__);
define('CS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'CallScheduler\\';
    $base_dir = CS_PLUGIN_DIR . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Boot the plugin
add_action('plugins_loaded', function (): void {
    CallScheduler\Plugin::instance()->boot();
});

// Activation hook
register_activation_hook(__FILE__, function (): void {
    CallScheduler\Installer::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function (): void {
    flush_rewrite_rules();
});
