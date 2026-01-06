<?php

declare(strict_types=1);

namespace CallScheduler\Admin;

use CallScheduler\Admin\Availability\AvailabilityPage;
use CallScheduler\Admin\Bookings\BookingsPage;
use CallScheduler\Admin\Settings\SettingsPage;
use CallScheduler\Admin\Settings\Modules\WhitelabelModule;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminPages
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenuPages']);
    }

    public function registerMenuPages(): void
    {
        $bookingsPage = new BookingsPage();
        $availabilityPage = new AvailabilityPage();
        $settingsPage = new SettingsPage();

        // Register hooks for asset enqueuing
        $bookingsPage->register();
        $availabilityPage->register();
        $settingsPage->register();

        // Get plugin name (whitelabel or default)
        $plugin_name = WhitelabelModule::getPluginName();

        // Main menu page - Bookings list
        add_menu_page(
            __('Vsechny rezervace', 'call-scheduler'),   // Page title
            $plugin_name,                                      // Menu title (whitelabel)
            'manage_options',                                  // Capability
            'cs-bookings',                                     // Menu slug
            [$bookingsPage, 'render'],                         // Callback
            'dashicons-calendar-alt',                          // Icon
            30                                                 // Position
        );

        // Submenu page - Availability setup
        add_submenu_page(
            'cs-bookings',                                     // Parent slug
            __('Dostupnost', 'call-scheduler'),          // Page title
            __('Dostupnost', 'call-scheduler'),          // Menu title
            'manage_options',                                  // Capability
            'cs-availability',                                 // Menu slug
            [$availabilityPage, 'render']                      // Callback
        );

        // Submenu page - Settings
        add_submenu_page(
            'cs-bookings',                                     // Parent slug
            __('Nastaveni', 'call-scheduler'),           // Page title
            __('Nastaveni', 'call-scheduler'),           // Menu title
            'manage_options',                                  // Capability
            'cs-settings',                                     // Menu slug
            [$settingsPage, 'render']                          // Callback
        );

        // Rename first submenu item to "All Bookings"
        global $submenu;
        if (isset($submenu['cs-bookings'][0])) {
            $submenu['cs-bookings'][0][0] = __('Vsechny rezervace', 'call-scheduler');
        }
    }
}
