<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Components;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable status filter tabs renderer
 *
 * Renders WordPress "subsubsub" filter tabs for booking status filtering.
 * Used on both Bookings and Dashboard pages.
 */
final class FilterTabsRenderer
{
    /**
     * Render status filter tabs
     *
     * @param string $baseUrl Base admin URL (e.g., admin.php?page=cs-bookings)
     * @param string $queryParam Query parameter name for status (e.g., 'status' or 'dashboard_status')
     * @param string|null $currentStatus Currently selected status (null = all)
     * @param array|null $counts Optional counts array with keys: 'all', 'pending', 'confirmed', 'cancelled'
     * @param string $allLabel Label for "All" tab (default: 'Vše')
     * @return void
     */
    public static function render(
        string $baseUrl,
        string $queryParam,
        ?string $currentStatus,
        ?array $counts = null,
        string $allLabel = 'Vše'
    ): void {
        ?>
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url($baseUrl); ?>" <?php echo $currentStatus === null ? 'class="current"' : ''; ?>>
                    <?php echo esc_html__($allLabel, 'call-scheduler'); ?>
                    <?php if ($counts !== null): ?>
                        <span class="count">(<?php echo esc_html($counts['all']); ?>)</span>
                    <?php endif; ?>
                </a> |
            </li>
            <?php
            $statuses = [BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::CANCELLED, BookingStatus::STORNO];
            $lastStatus = end($statuses);
            foreach ($statuses as $status):
            ?>
                <li>
                    <a href="<?php echo esc_url(add_query_arg($queryParam, $status, $baseUrl)); ?>"
                       <?php echo $currentStatus === $status ? 'class="current"' : ''; ?>>
                        <?php echo esc_html(BookingStatus::label($status)); ?>
                        <?php if ($counts !== null): ?>
                            <span class="count">(<?php echo esc_html($counts[$status] ?? 0); ?>)</span>
                        <?php endif; ?>
                    </a><?php echo $status !== $lastStatus ? ' |' : ''; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}
