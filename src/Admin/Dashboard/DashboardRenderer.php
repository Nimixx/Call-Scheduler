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
        ?>
        <div class="wrap cs-dashboard-page">
            <h1><?php esc_html_e('Přehled', 'call-scheduler'); ?></h1>

            <div class="cs-dashboard-widgets">
                <!-- Pending Reservations Widget -->
                <div class="cs-widget cs-widget-pending">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($stats['pending']); ?></div>
                        <div class="cs-widget-label">
                            <?php esc_html_e('Čekající rezervace', 'call-scheduler'); ?>
                        </div>
                        <div class="cs-widget-action">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cs-bookings&status=pending')); ?>" class="button button-small">
                                <?php esc_html_e('Zobrazit', 'call-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Confirmed Reservations Widget -->
                <div class="cs-widget cs-widget-confirmed">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($stats['confirmed']); ?></div>
                        <div class="cs-widget-label">
                            <?php esc_html_e('Potvrzené rezervace', 'call-scheduler'); ?>
                        </div>
                        <div class="cs-widget-action">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cs-bookings&status=confirmed')); ?>" class="button button-small">
                                <?php esc_html_e('Zobrazit', 'call-scheduler'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Total Reservations Widget -->
                <div class="cs-widget cs-widget-total">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($stats['total']); ?></div>
                        <div class="cs-widget-label">
                            <?php esc_html_e('Celkem rezervací', 'call-scheduler'); ?>
                        </div>
                        <div class="cs-widget-action">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=cs-bookings')); ?>" class="button button-small">
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
}
