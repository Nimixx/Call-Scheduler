<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Dashboard;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the dashboard overview page
 */
final class DashboardRenderer
{
    /**
     * @param array{total: int, pending: int, confirmed: int, cancelled: int} $stats
     * @param array $bookings
     * @param string|null $statusFilter
     */
    public function renderPage(array $stats, array $bookings, ?string $statusFilter): void
    {
        // Validate stats structure and log warnings if invalid
        if (!$this->isValidStats($stats)) {
            $this->logInvalidStats($stats);
        }

        ?>
        <div class="wrap cs-dashboard-page">
            <h1><?php esc_html_e('Přehled', 'call-scheduler'); ?></h1>

            <?php $this->renderDataIntegrityNotice($stats); ?>

            <div class="cs-dashboard-widgets">
                <!-- Pending Reservations Widget -->
                <div class="cs-widget cs-widget-pending">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($this->getStat($stats, 'pending')); ?></div>
                        <div class="cs-widget-label">
                            <?php esc_html_e('Čekající rezervace', 'call-scheduler'); ?>
                        </div>
                        <div class="cs-widget-action">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cs-bookings&status=pending')); ?>" class="cs-dashboard-btn">
                                <?php esc_html_e('Zobrazit', 'call-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Confirmed Reservations Widget -->
                <div class="cs-widget cs-widget-confirmed">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($this->getStat($stats, 'confirmed')); ?></div>
                        <div class="cs-widget-label">
                            <?php esc_html_e('Potvrzené rezervace', 'call-scheduler'); ?>
                        </div>
                        <div class="cs-widget-action">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cs-bookings&status=confirmed')); ?>" class="cs-dashboard-btn">
                                <?php esc_html_e('Zobrazit', 'call-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Total Reservations Widget -->
                <div class="cs-widget cs-widget-total">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($this->getStat($stats, 'total')); ?></div>
                        <div class="cs-widget-label">
                            <?php esc_html_e('Celkem rezervací', 'call-scheduler'); ?>
                        </div>
                        <div class="cs-widget-action">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cs-bookings')); ?>" class="cs-dashboard-btn">
                                <?php esc_html_e('Zobrazit vše', 'call-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php $this->renderMyBookings($bookings, $statusFilter); ?>
        </div>
        <?php
    }

    public function renderInstallationError(): void
    {
        ?>
        <div class="wrap cs-dashboard-page">
            <div class="notice notice-error">
                <p><?php esc_html_e('Zásuvný modul není nainstalován. Prosím aktivujte jej.', 'call-scheduler'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Safely get a stat value from array with fallback to 0
     *
     * @param array $stats Statistics array
     * @param string $key Key to retrieve
     * @return int Value or 0 if missing
     */
    private function getStat(array $stats, string $key): int
    {
        return (int) ($stats[$key] ?? 0);
    }

    /**
     * Validate stats array has required keys and types
     *
     * @param array $stats Statistics array to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidStats(array $stats): bool
    {
        $required_keys = ['total', 'pending', 'confirmed', 'cancelled'];

        foreach ($required_keys as $key) {
            if (!isset($stats[$key])) {
                return false;
            }

            if (!is_int($stats[$key]) && !is_numeric($stats[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render data integrity notice if stats are invalid
     *
     * Shows admin warning when data structure is corrupted.
     *
     * @param array $stats Statistics array
     * @return void
     */
    private function renderDataIntegrityNotice(array $stats): void
    {
        if ($this->isValidStats($stats)) {
            return;
        }

        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('⚠️ Dashboard Data Issue:', 'call-scheduler'); ?></strong>
                <?php esc_html_e('Dashboard statistics data is incomplete or corrupted. Showing fallback values (0). Please check the debug log for details.', 'call-scheduler'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Log warning when stats structure is invalid
     *
     * @param array $stats Invalid statistics array
     * @return void
     */
    private function logInvalidStats(array $stats): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Call Scheduler Dashboard: Invalid stats structure in renderPage(). Got: %s',
                wp_json_encode($stats)
            ));
        }
    }

    /**
     * Render "My Bookings" section with status filter tabs
     *
     * @param array $bookings
     * @param string|null $statusFilter
     * @return void
     */
    private function renderMyBookings(array $bookings, ?string $statusFilter): void
    {
        $baseUrl = admin_url('admin.php?page=cs-dashboard');
        ?>
        <div class="cs-my-bookings">
            <h2><?php esc_html_e('Moje rezervace', 'call-scheduler'); ?></h2>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url($baseUrl); ?>" <?php echo $statusFilter === null ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Vše', 'call-scheduler'); ?>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('dashboard_status', BookingStatus::PENDING, $baseUrl)); ?>" <?php echo $statusFilter === BookingStatus::PENDING ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Čekající', 'call-scheduler'); ?>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('dashboard_status', BookingStatus::CONFIRMED, $baseUrl)); ?>" <?php echo $statusFilter === BookingStatus::CONFIRMED ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Potvrzené', 'call-scheduler'); ?>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('dashboard_status', BookingStatus::CANCELLED, $baseUrl)); ?>" <?php echo $statusFilter === BookingStatus::CANCELLED ? 'class="current"' : ''; ?>>
                        <?php esc_html_e('Zrušené', 'call-scheduler'); ?>
                    </a>
                </li>
            </ul>

            <?php $this->renderBookingsTable($bookings); ?>
        </div>
        <?php
    }

    /**
     * Render bookings table
     *
     * @param array $bookings
     * @return void
     */
    private function renderBookingsTable(array $bookings): void
    {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Zákazník', 'call-scheduler'); ?></th>
                    <th><?php esc_html_e('Email', 'call-scheduler'); ?></th>
                    <th><?php esc_html_e('Datum', 'call-scheduler'); ?></th>
                    <th><?php esc_html_e('Čas', 'call-scheduler'); ?></th>
                    <th><?php esc_html_e('Stav', 'call-scheduler'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">
                            <?php esc_html_e('Žádné rezervace nenalezeny.', 'call-scheduler'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo esc_html($booking->customer_name); ?></td>
                            <td><?php echo esc_html($booking->customer_email); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($booking->booking_date))); ?></td>
                            <td><?php echo esc_html(substr($booking->booking_time, 0, 5)); ?></td>
                            <td><?php echo $this->renderStatusBadge($booking->status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render status badge
     *
     * @param string $status
     * @return string
     */
    private function renderStatusBadge(string $status): string
    {
        $labels = [
            BookingStatus::PENDING => __('Čekající', 'call-scheduler'),
            BookingStatus::CONFIRMED => __('Potvrzená', 'call-scheduler'),
            BookingStatus::CANCELLED => __('Zrušená', 'call-scheduler'),
        ];

        $label = $labels[$status] ?? $status;

        return sprintf(
            '<span class="cs-status-badge %s" style="background-color: %s;">%s</span>',
            esc_attr($status),
            esc_attr($this->getStatusColor($status)),
            esc_html($label)
        );
    }

    /**
     * Get status color
     *
     * @param string $status
     * @return string
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            BookingStatus::PENDING => '#ea580c',
            BookingStatus::CONFIRMED => '#0073aa',
            BookingStatus::CANCELLED => '#646970',
            default => '#646970',
        };
    }
}
