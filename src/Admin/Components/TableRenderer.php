<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Components;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable WordPress table renderer
 *
 * Provides consistent wp-list-table rendering with empty state handling.
 * Uses callbacks for flexible header and row rendering.
 */
final class TableRenderer
{
    /**
     * Render WordPress admin table
     *
     * @param array $data Data array to render
     * @param callable $renderHeader Callback to render table header row: function(): void
     * @param callable $renderRow Callback to render data row: function($item): void
     * @param string $emptyMessage Message to show when no data
     * @param int $colspan Number of columns for empty state colspan
     * @param bool $hasFooter Whether to render footer (default: false)
     * @return void
     */
    public static function render(
        array $data,
        callable $renderHeader,
        callable $renderRow,
        string $emptyMessage,
        int $colspan,
        bool $hasFooter = false
    ): void {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <?php $renderHeader(); ?>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="<?php echo esc_attr($colspan); ?>" style="text-align: center; padding: 40px;">
                            <?php echo esc_html($emptyMessage); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($data as $item): ?>
                        <?php $renderRow($item); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($hasFooter && !empty($data)): ?>
                <tfoot>
                    <?php $renderHeader(); ?>
                </tfoot>
            <?php endif; ?>
        </table>
        <?php
    }
}
