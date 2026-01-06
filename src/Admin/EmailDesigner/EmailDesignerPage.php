<?php

declare(strict_types=1);

namespace CallScheduler\Admin\EmailDesigner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Email Designer admin page
 */
final class EmailDesignerPage
{
    public function register(): void
    {
        // Assets will be registered here when needed
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.', 'call-scheduler'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Email Designer', 'call-scheduler'); ?></h1>
        </div>
        <?php
    }
}
