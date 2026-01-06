<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings;

use CallScheduler\Admin\Settings\Modules\AbstractSettingsModule;
use CallScheduler\Admin\Settings\Modules\SettingsModuleInterface;
use CallScheduler\Admin\Settings\Modules\TimingModule;
use CallScheduler\Admin\Settings\Modules\WebhookModule;
use CallScheduler\Admin\Settings\Modules\WhitelabelModule;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Settings admin page
 *
 * Renders settings using modular architecture - each module handles one settings card.
 */
final class SettingsPage
{
    private const OPTION_GROUP = 'cs_settings';

    /**
     * @var SettingsModuleInterface[]
     */
    private array $modules = [];

    public function __construct()
    {
        $this->modules = [
            new TimingModule(),
            new WhitelabelModule(),
            new WebhookModule(),
        ];
    }

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            AbstractSettingsModule::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeOptions'],
                'default' => $this->getDefaults(),
            ]
        );
    }

    public function enqueueAssets(string $hook): void
    {
        $screen = get_current_screen();
        if ($screen === null || !str_ends_with($screen->id, '_page_cs-settings')) {
            return;
        }

        wp_enqueue_style(
            'cs-admin-settings',
            CS_PLUGIN_URL . 'assets/css/admin-settings.css',
            [],
            CS_VERSION
        );

        wp_enqueue_script(
            'cs-admin-settings',
            CS_PLUGIN_URL . 'assets/js/admin-settings.js',
            [],
            CS_VERSION,
            true
        );
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitizeOptions(mixed $input): array
    {
        if (!is_array($input)) {
            return $this->getDefaults();
        }

        $output = [];

        // Let each module sanitize its own options
        foreach ($this->modules as $module) {
            $moduleOptions = $module->sanitize($input);
            $output = array_merge($output, $moduleOptions);
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        $defaults = [];

        foreach ($this->modules as $module) {
            $defaults = array_merge($defaults, $module->getDefaults());
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getOptions(): array
    {
        $options = get_option(AbstractSettingsModule::OPTION_NAME, []);

        // Build defaults from all modules
        $defaults = [
            // Timing
            'slot_duration' => 60,
            'buffer_time' => 0,
            // Whitelabel
            'whitelabel_enabled' => false,
            'whitelabel_plugin_name' => '',
            // Webhooks
            'webhook_enabled' => false,
            'webhook_url' => '',
            'webhook_secret' => '',
        ];

        return wp_parse_args($options, $defaults);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.', 'call-scheduler'));
        }

        $options = self::getOptions();
        ?>
        <div class="wrap cs-settings-page">
            <h1><?php esc_html_e('Nastavení', 'call-scheduler'); ?></h1>

            <?php settings_errors(); ?>

            <div class="cs-info-box">
                <p>
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('Nastavení pluginu pro rezervace.', 'call-scheduler'); ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <?php foreach ($this->modules as $module): ?>
                    <?php $module->render($options); ?>
                <?php endforeach; ?>

                <div class="cs-settings-footer cs-settings-footer-sticky">
                    <?php submit_button(__('Uložit nastavení', 'call-scheduler'), 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }
}
