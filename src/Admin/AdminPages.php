<?php

declare(strict_types=1);

namespace CallScheduler\Admin;

use CallScheduler\Admin\Availability\AvailabilityPage;
use CallScheduler\Admin\Bookings\BookingsPage;
use CallScheduler\Admin\Dashboard\DashboardPage;
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
        $dashboardPage = new DashboardPage();
        $bookingsPage = new BookingsPage();
        $availabilityPage = new AvailabilityPage();
        $settingsPage = new SettingsPage();

        // Register hooks for asset enqueuing
        $dashboardPage->register();
        $bookingsPage->register();
        $availabilityPage->register();
        $settingsPage->register();

        // Get plugin name (whitelabel or default)
        $plugin_name = WhitelabelModule::getPluginName();

        // Main menu page - Dashboard
        add_menu_page(
            __('Přehled', 'call-scheduler'),              // Page title
            $plugin_name,                                      // Menu title (whitelabel)
            'manage_options',                                  // Capability
            'cs-dashboard',                                    // Menu slug
            [$dashboardPage, 'render'],                        // Callback
            'dashicons-calendar-alt',                          // Icon
            30                                                 // Position
        );

        // Submenu page - All Bookings
        add_submenu_page(
            'cs-dashboard',                                    // Parent slug
            __('Všechny rezervace', 'call-scheduler'),   // Page title
            __('Všechny rezervace', 'call-scheduler'),   // Menu title
            'manage_options',                                  // Capability
            'cs-bookings',                                     // Menu slug
            [$bookingsPage, 'render']                          // Callback
        );

        // Submenu page - Availability setup
        add_submenu_page(
            'cs-dashboard',                                    // Parent slug
            __('Dostupnost', 'call-scheduler'),          // Page title
            __('Dostupnost', 'call-scheduler'),          // Menu title
            'manage_options',                                  // Capability
            'cs-availability',                                 // Menu slug
            [$availabilityPage, 'render']                      // Callback
        );

        // Submenu page - Settings
        add_submenu_page(
            'cs-dashboard',                                    // Parent slug
            __('Nastavení', 'call-scheduler'),           // Page title
            __('Nastavení', 'call-scheduler'),           // Menu title
            'manage_options',                                  // Capability
            'cs-settings',                                     // Menu slug
            [$settingsPage, 'render']                          // Callback
        );
    }
}
