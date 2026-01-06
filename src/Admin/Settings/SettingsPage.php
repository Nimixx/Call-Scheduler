<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings;

use CallScheduler\Config;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Settings admin page
 */
final class SettingsPage
{
    private const OPTION_GROUP = 'cs_settings';
    private const OPTION_NAME = 'cs_options';

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerSettings(): void
    {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
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
    }

    /**
     * @param mixed $input
     * @return array<string, int>
     */
    public function sanitizeOptions(mixed $input): array
    {
        $defaults = $this->getDefaults();

        if (!is_array($input)) {
            return $defaults;
        }

        $output = [];

        // Slot duration - must be positive and divide into 60 (or be 90/120)
        $slot_duration = isset($input['slot_duration']) ? absint($input['slot_duration']) : $defaults['slot_duration'];
        $valid_durations = [15, 30, 60, 90, 120];
        $output['slot_duration'] = in_array($slot_duration, $valid_durations, true) ? $slot_duration : $defaults['slot_duration'];

        // Buffer time - must be non-negative and less than slot duration
        $buffer_time = isset($input['buffer_time']) ? absint($input['buffer_time']) : $defaults['buffer_time'];
        $output['buffer_time'] = $buffer_time < $output['slot_duration'] ? $buffer_time : 0;

        return $output;
    }

    /**
     * @return array<string, int>
     */
    private function getDefaults(): array
    {
        return [
            'slot_duration' => 60,
            'buffer_time' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function getOptions(): array
    {
        $options = get_option(self::OPTION_NAME, []);

        $defaults = [
            'slot_duration' => 60,
            'buffer_time' => 0,
        ];

        return wp_parse_args($options, $defaults);
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.', 'call-scheduler'));
        }

        $options = self::getOptions();
        ?>
        <div class="wrap cs-settings-page">
            <h1><?php echo esc_html__('Nastavení', 'call-scheduler'); ?></h1>

            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php echo esc_html__('Nastavení bylo úspěšně uloženo!', 'call-scheduler'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="cs-info-box">
                <p>
                    <span class="dashicons dashicons-info"></span>
                    <?php echo esc_html__('Nastavení délky rezervací a mezičasu mezi schůzkami.', 'call-scheduler'); ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <div class="cs-settings-card">
                    <div class="cs-settings-header">
                        <h2 class="cs-settings-title">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html__('Časování rezervací', 'call-scheduler'); ?>
                        </h2>
                    </div>

                    <div class="cs-settings-body">
                        <div class="cs-form-row">
                            <div class="cs-form-label">
                                <label for="cs_slot_duration">
                                    <?php echo esc_html__('Délka rezervace', 'call-scheduler'); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Jak dlouho trvá jedna schůzka.', 'call-scheduler'); ?>
                                </p>
                            </div>
                            <div class="cs-form-field">
                                <select
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[slot_duration]"
                                    id="cs_slot_duration"
                                    class="cs-input"
                                    style="width: 150px;"
                                >
                                    <?php
                                    $durations = [
                                        15 => '15 minut',
                                        30 => '30 minut',
                                        60 => '1 hodina',
                                        90 => '1,5 hodiny',
                                        120 => '2 hodiny',
                                    ];
                                    foreach ($durations as $value => $label) :
                                        ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($options['slot_duration'], $value); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="cs-form-row">
                            <div class="cs-form-label">
                                <label for="cs_buffer_time">
                                    <?php echo esc_html__('Mezičas', 'call-scheduler'); ?>
                                </label>
                                <p class="description">
                                    <?php echo esc_html__('Pauza mezi schůzkami pro přípravu.', 'call-scheduler'); ?>
                                </p>
                            </div>
                            <div class="cs-form-field">
                                <input
                                    type="number"
                                    name="<?php echo esc_attr(self::OPTION_NAME); ?>[buffer_time]"
                                    id="cs_buffer_time"
                                    class="cs-input"
                                    value="<?php echo esc_attr((string) $options['buffer_time']); ?>"
                                    min="0"
                                    max="60"
                                    step="5"
                                >
                                <span class="cs-unit"><?php echo esc_html__('minut', 'call-scheduler'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="cs-settings-footer">
                        <?php submit_button(__('Uložit nastavení', 'call-scheduler'), 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
