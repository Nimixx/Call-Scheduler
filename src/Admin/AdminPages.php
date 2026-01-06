<?php

declare(strict_types=1);

namespace CallScheduler\Admin;

use CallScheduler\Admin\Availability\AvailabilityPage;
use CallScheduler\Admin\Bookings\BookingsPage;

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

        // Register hooks for asset enqueuing
        $bookingsPage->register();
        $availabilityPage->register();

        // Main menu page - Bookings list
        add_menu_page(
            __('Všechny rezervace', 'call-scheduler'),   // Page title
            __('Rezervace', 'call-scheduler'),           // Menu title
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

        // Rename first submenu item to "All Bookings"
        global $submenu;
        if (isset($submenu['cs-bookings'][0])) {
            $submenu['cs-bookings'][0][0] = __('Všechny rezervace', 'call-scheduler');
        }
    }
}
