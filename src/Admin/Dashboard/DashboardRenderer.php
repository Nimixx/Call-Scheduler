<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Dashboard;

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
     */
    public function renderPage(array $stats): void
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
}
