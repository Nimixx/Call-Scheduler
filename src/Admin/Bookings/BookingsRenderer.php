<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Bookings;

use CallScheduler\Admin\Components\StatusBadgeRenderer;
use CallScheduler\Admin\Components\FilterTabsRenderer;
use CallScheduler\Admin\Components\NoticeRenderer;
use CallScheduler\Admin\Components\RowActionsRenderer;
use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles HTML rendering for bookings pages
 */
final class BookingsRenderer
{
    public function renderPage(array $data): void
    {
        ?>
        <div class="wrap">
            <?php $this->renderHeader(); ?>
            <?php $this->renderNotices($data); ?>
            <?php $this->renderStatusTabs($data); ?>

            <form method="get">
                <input type="hidden" name="page" value="cs-bookings" />
                <?php if ($data['current_status']): ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($data['current_status']); ?>" />
                <?php endif; ?>

                <?php $this->renderFilters($data); ?>
            </form>

            <form method="post" id="cs-bookings-form">
                <?php wp_nonce_field('cs_bookings_action', 'cs_bookings_nonce'); ?>
                <input type="hidden" name="cs_action" value="bulk" />

                <?php $this->renderTable($data); ?>
            </form>
        </div>
        <?php
    }

    public function renderInstallationError(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Všechny rezervace', 'call-scheduler'); ?></h1>
            <?php
            NoticeRenderer::error(
                esc_html__('Databázové tabulky pluginu nejsou nainstalovány. Prosím deaktivujte a znovu aktivujte plugin.', 'call-scheduler'),
                false
            );
            ?>
        </div>
        <?php
    }

    private function renderHeader(): void
    {
        ?>
        <h1 class="wp-heading-inline"><?php echo esc_html__('Všechny rezervace', 'call-scheduler'); ?></h1>
        <hr class="wp-header-end">
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

    private function renderStatusTabs(array $data): void
    {
        FilterTabsRenderer::render(
            admin_url('admin.php?page=cs-bookings'),
            'status',
            $data['current_status'],
            $data['status_counts'],
            'Vše'
        );
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
        <div class="tablenav top">
            <?php $this->renderBulkActions('bulk_action'); ?>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <?php $this->renderTableHeader(); ?>
            </thead>
            <tbody>
                <?php if (empty($data['bookings'])): ?>
                    <tr>
                        <td colspan="8">
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
            <?php $this->renderBulkActions('bulk_action2'); ?>
            <?php $this->renderPagination($data); ?>
            <br class="clear" />
        </div>
        <?php
    }

    private function renderBulkActions(string $selectId): void
    {
        ?>
        <div class="alignleft actions bulkactions">
            <label for="<?php echo esc_attr($selectId); ?>" class="screen-reader-text">
                <?php echo esc_html__('Vybrat hromadnou akci', 'call-scheduler'); ?>
            </label>
            <select name="<?php echo esc_attr($selectId); ?>" id="<?php echo esc_attr($selectId); ?>">
                <option value="-1"><?php echo esc_html__('Hromadné akce', 'call-scheduler'); ?></option>
                <option value="<?php echo esc_attr(BookingStatus::CONFIRMED); ?>">
                    <?php echo esc_html__('Potvrdit', 'call-scheduler'); ?>
                </option>
                <option value="<?php echo esc_attr(BookingStatus::CANCELLED); ?>">
                    <?php echo esc_html__('Zrušit', 'call-scheduler'); ?>
                </option>
                <option value="delete"><?php echo esc_html__('Smazat', 'call-scheduler'); ?></option>
            </select>
            <input type="submit" class="button action" value="<?php echo esc_attr__('Použít', 'call-scheduler'); ?>" />
        </div>
        <?php
    }

    private function renderTableHeader(): void
    {
        ?>
        <tr>
            <td class="manage-column column-cb check-column">
                <label for="cb-select-all" class="screen-reader-text">
                    <?php esc_html_e('Vybrat všechny rezervace', 'call-scheduler'); ?>
                </label>
                <input type="checkbox" id="cb-select-all" />
            </td>
            <th scope="col" class="manage-column"><?php echo esc_html__('Zákazník', 'call-scheduler'); ?></th>
            <th scope="col" class="manage-column"><?php echo esc_html__('Email', 'call-scheduler'); ?></th>
            <th scope="col" class="manage-column"><?php echo esc_html__('Člen týmu', 'call-scheduler'); ?></th>
            <th scope="col" class="manage-column"><?php echo esc_html__('Termín', 'call-scheduler'); ?></th>
            <th scope="col" class="manage-column"><?php echo esc_html__('Stav', 'call-scheduler'); ?></th>
            <th scope="col" class="manage-column"><?php echo esc_html__('Vytvořeno', 'call-scheduler'); ?></th>
            <th scope="col" class="manage-column cs-col-actions"><?php echo esc_html__('Akce', 'call-scheduler'); ?></th>
        </tr>
        <?php
    }

    private function renderTableRow(object $booking): void
    {
        ?>
        <tr>
            <th scope="row" class="check-column">
                <label for="booking_<?php echo esc_attr($booking->id); ?>" class="screen-reader-text">
                    <?php esc_html_e('Vybrat tuto rezervaci', 'call-scheduler'); ?>
                </label>
                <input type="checkbox"
                       id="booking_<?php echo esc_attr($booking->id); ?>"
                       name="booking_ids[]"
                       value="<?php echo esc_attr($booking->id); ?>" />
            </th>
            <td>
                <strong><?php echo esc_html($booking->customer_name); ?></strong>
                <?php $this->renderRowActions($booking); ?>
            </td>
            <td>
                <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>">
                    <?php echo esc_html($booking->customer_email); ?>
                </a>
            </td>
            <td><?php echo esc_html($booking->team_member_name ?? '-'); ?></td>
            <td>
                <strong><?php echo esc_html(DateFormatter::date($booking->booking_date)); ?></strong>
                <br>
                <span class="description"><?php echo esc_html(DateFormatter::time($booking->booking_time)); ?></span>
            </td>
            <td>
                <?php echo StatusBadgeRenderer::render($booking->status); ?>
            </td>
            <td>
                <span class="description"><?php echo esc_html(DateFormatter::dateTimeFromUtc($booking->created_at)); ?></span>
            </td>
            <td class="cs-col-actions">
                <?php echo RowActionsRenderer::renderDeleteButton((int) $booking->id); ?>
            </td>
        </tr>
        <?php
    }

    private function renderRowActions(object $booking): void
    {
        echo RowActionsRenderer::render((int) $booking->id, $booking->status);
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
        $args = ['page' => 'cs-bookings'];

        if ($data['current_status']) {
            $args['status'] = $data['current_status'];
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
        $args = ['page' => 'cs-bookings'];

        if ($data['current_status']) {
            $args['status'] = $data['current_status'];
        }

        return add_query_arg($args, admin_url('admin.php'));
    }
}
