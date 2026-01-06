<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Settings admin page
 */
final class SettingsPage
{
    public function register(): void
    {
        // Reserved for future asset enqueuing
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.', 'call-scheduler'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Nastavení', 'call-scheduler'); ?></h1>
        </div>
        <?php
    }
}
