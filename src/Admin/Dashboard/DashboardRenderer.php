<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Dashboard;

use CallScheduler\Admin\Components\StatusBadgeRenderer;
use CallScheduler\Admin\Components\FilterTabsRenderer;
use CallScheduler\Admin\Components\NoticeRenderer;
use CallScheduler\Admin\Components\RowActionsRenderer;
use CallScheduler\Admin\Bookings\DateFormatter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the dashboard overview page
 */
final class DashboardRenderer
{
    public function renderPage(array $data): void
    {
        ?>
        <div class="wrap cs-dashboard-page">
            <h1><?php esc_html_e('Přehled', 'call-scheduler'); ?></h1>

            <?php $this->renderNotices($data); ?>

            <div class="cs-stats-section">
                <h2><?php esc_html_e('Statistiky', 'call-scheduler'); ?></h2>

                <div class="cs-dashboard-widgets">
                <!-- Pending Reservations Widget -->
                <div class="cs-widget cs-widget-pending">
                    <div class="cs-widget-inner">
                        <div class="cs-widget-number"><?php echo absint($data['stats']['pending']); ?></div>
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
                        <div class="cs-widget-number"><?php echo absint($data['stats']['confirmed']); ?></div>
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
                        <div class="cs-widget-number"><?php echo absint($data['stats']['total']); ?></div>
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

            <?php $this->renderMyBookings($data); ?>
        </div>
        <?php
    }

    public function renderInstallationError(): void
    {
        ?>
        <div class="wrap cs-dashboard-page">
            <?php
            NoticeRenderer::error(
                esc_html__('Zásuvný modul není nainstalován. Prosím aktivujte jej.', 'call-scheduler'),
                false
            );
            ?>
        </div>
        <?php
    }

    private function renderNotices(array $data): void
    {
        if ($data['show_success'] && $data['success_message']) {
            NoticeRenderer::success(esc_html($data['success_message']));
        }

        if ($data['show_error']) {
            NoticeRenderer::error(
                esc_html__('Při zpracování požadavku došlo k chybě. Zkuste to prosím znovu.', 'call-scheduler')
            );
        }
    }

    /**
     * Render "My Bookings" section with filters and pagination
     *
     * @param array $data
     * @return void
     */
    private function renderMyBookings(array $data): void
    {
        $baseUrl = admin_url('admin.php?page=cs-dashboard');
        ?>
        <div class="cs-my-bookings">
            <h2><?php esc_html_e('Moje rezervace', 'call-scheduler'); ?></h2>

            <?php
            FilterTabsRenderer::render(
                $baseUrl,
                'dashboard_status',
                $data['current_status'],
                null, // No counts for dashboard tabs
                'Vše'
            );
            ?>

            <form method="get">
                <input type="hidden" name="page" value="cs-dashboard" />
                <?php if ($data['current_status']): ?>
                    <input type="hidden" name="dashboard_status" value="<?php echo esc_attr($data['current_status']); ?>" />
                <?php endif; ?>

                <?php $this->renderFilters($data); ?>
            </form>

            <form method="post" id="cs-dashboard-bookings-form">
                <?php wp_nonce_field('cs_bookings_action', 'cs_bookings_nonce'); ?>
                <input type="hidden" name="cs_action" value="bulk" />

                <?php $this->renderTable($data); ?>
            </form>
        </div>
        <?php
    }

    private function renderFilters(array $data): void
    {
        ?>
        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="date_from" class="screen-reader-text">
                    <?php echo esc_html__('Od data', 'call-scheduler'); ?>
                </label>
                <input type="date"
                       id="date_from"
                       name="date_from"
                       value="<?php echo esc_attr($data['date_from'] ?? ''); ?>" />

                <label for="date_to" class="screen-reader-text">
                    <?php echo esc_html__('Do data', 'call-scheduler'); ?>
                </label>
                <input type="date"
                       id="date_to"
                       name="date_to"
                       value="<?php echo esc_attr($data['date_to'] ?? ''); ?>" />

                <input type="submit"
                       class="button"
                       value="<?php echo esc_attr__('Filtrovat', 'call-scheduler'); ?>" />

                <?php if ($data['date_from'] || $data['date_to']): ?>
                    <a href="<?php echo esc_url($this->getClearFiltersUrl($data)); ?>" class="button">
                        <?php echo esc_html__('Zrušit filtr', 'call-scheduler'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php $this->renderPagination($data); ?>
            <br class="clear" />
        </div>
        <?php
    }

    private function renderTable(array $data): void
    {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <?php $this->renderTableHeader(); ?>
            </thead>
            <tbody>
                <?php if (empty($data['bookings'])): ?>
                    <tr>
                        <td colspan="6">
                            <?php echo esc_html__('Nebyly nalezeny žádné rezervace.', 'call-scheduler'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data['bookings'] as $booking): ?>
                        <?php $this->renderTableRow($booking); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <?php $this->renderTableHeader(); ?>
            </tfoot>
        </table>

        <div class="tablenav bottom">
            <?php $this->renderPagination($data); ?>
            <br class="clear" />
        </div>
        <?php
    }

    private function renderTableHeader(): void
    {
        ?>
        <tr>
            <th><?php esc_html_e('Zákazník', 'call-scheduler'); ?></th>
            <th><?php esc_html_e('Email', 'call-scheduler'); ?></th>
            <th><?php esc_html_e('Datum', 'call-scheduler'); ?></th>
            <th><?php esc_html_e('Čas', 'call-scheduler'); ?></th>
            <th><?php esc_html_e('Stav', 'call-scheduler'); ?></th>
            <th class="cs-col-actions"><?php esc_html_e('Akce', 'call-scheduler'); ?></th>
        </tr>
        <?php
    }

    private function renderTableRow(object $booking): void
    {
        ?>
        <tr>
            <td>
                <strong><?php echo esc_html($booking->customer_name); ?></strong>
                <?php echo RowActionsRenderer::render((int) $booking->id, $booking->status); ?>
            </td>
            <td>
                <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                    <?php echo esc_html($booking->customer_email); ?>
                </a>
            </td>
            <td>
                <strong><?php echo esc_html(DateFormatter::date($booking->booking_date)); ?></strong>
            </td>
            <td>
                <span class="description"><?php echo esc_html(DateFormatter::time($booking->booking_time)); ?></span>
            </td>
            <td>
                <?php echo StatusBadgeRenderer::render($booking->status); ?>
            </td>
            <td class="cs-col-actions">
                <?php echo RowActionsRenderer::renderDeleteButton((int) $booking->id); ?>
            </td>
        </tr>
        <?php
    }

    private function renderPagination(array $data): void
    {
        if ($data['total_pages'] <= 1) {
            return;
        }

        $current = $data['current_page'];
        $total = $data['total_pages'];
        $items = $data['total_items'];

        ?>
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php printf(_n('%s položka', '%s položek', $items, 'call-scheduler'), number_format_i18n($items)); ?>
            </span>
            <span class="pagination-links">
                <?php echo $this->getPaginationLink($data, 1, '&laquo;', __('První stránka', 'call-scheduler'), $current > 1); ?>
                <?php echo $this->getPaginationLink($data, $current - 1, '&lsaquo;', __('Předchozí stránka', 'call-scheduler'), $current > 1); ?>

                <span class="paging-input">
                    <span class="tablenav-paging-text">
                        <?php echo esc_html($current); ?> <?php echo esc_html__('z', 'call-scheduler'); ?>
                        <span class="total-pages"><?php echo esc_html($total); ?></span>
                    </span>
                </span>

                <?php echo $this->getPaginationLink($data, $current + 1, '&rsaquo;', __('Další stránka', 'call-scheduler'), $current < $total); ?>
                <?php echo $this->getPaginationLink($data, $total, '&raquo;', __('Poslední stránka', 'call-scheduler'), $current < $total); ?>
            </span>
        </div>
        <?php
    }

    private function getPaginationLink(array $data, int $page, string $symbol, string $label, bool $enabled): string
    {
        if (!$enabled) {
            return '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">' . $symbol . '</span>';
        }

        return sprintf(
            '<a class="button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
            esc_url($this->getPaginationUrl($data, $page)),
            esc_html($label),
            $symbol
        );
    }

    private function getPaginationUrl(array $data, int $page): string
    {
        $args = ['page' => 'cs-dashboard'];

        if ($data['current_status']) {
            $args['dashboard_status'] = $data['current_status'];
        }
        if ($data['date_from']) {
            $args['date_from'] = $data['date_from'];
        }
        if ($data['date_to']) {
            $args['date_to'] = $data['date_to'];
        }
        if ($page > 1) {
            $args['paged'] = $page;
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

    private function getClearFiltersUrl(array $data): string
    {
        $args = ['page' => 'cs-dashboard'];

        if ($data['current_status']) {
            $args['dashboard_status'] = $data['current_status'];
        }

        return add_query_arg($args, admin_url('admin.php'));
    }

}
