<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whitelabel settings module - plugin name customization
 */
final class WhitelabelModule extends AbstractSettingsModule
{
    public function getId(): string
    {
        return 'whitelabel';
    }

    public function getTitle(): string
    {
        return __('Whitelabel', 'call-scheduler');
    }

    public function getIcon(): string
    {
        return 'admin-customizer';
    }

    public function getDefaults(): array
    {
        return [
            'whitelabel_enabled' => false,
            'whitelabel_plugin_name' => '',
        ];
    }

    public function sanitize(array $input): array
    {
        $output = [];

        $output['whitelabel_enabled'] = !empty($input['whitelabel_enabled']);

        $output['whitelabel_plugin_name'] = isset($input['whitelabel_plugin_name'])
            ? sanitize_text_field($input['whitelabel_plugin_name'])
            : '';

        return $output;
    }

    public function render(array $options): void
    {
        $is_enabled = !empty($options['whitelabel_enabled']);
        $plugin_name = $options['whitelabel_plugin_name'] ?? '';

        $this->renderCardStart();

        // Enable toggle row
        $this->renderFormRowStart(
            __('Povolit whitelabel', 'call-scheduler'),
            __('Prejmenujte plugin pro vaseho zakaznika.', 'call-scheduler')
        );
        $this->renderToggle('whitelabel_enabled', $is_enabled);
        $this->renderFormRowEnd();

        // Conditional fields container
        ?>
        <div class="cs-conditional-fields" data-depends-on="whitelabel_enabled" <?php echo $is_enabled ? '' : 'style="display: none;"'; ?>>
            <?php
            $this->renderFormRowStart(
                __('Nazev pluginu', 'call-scheduler'),
                __('Zobrazuje se v menu administrace.', 'call-scheduler')
            );
            $this->renderTextInput('whitelabel_plugin_name', $plugin_name, __('Rezervace', 'call-scheduler'));
            $this->renderFormRowEnd();
            ?>
        </div>
        <?php

        $this->renderCardEnd();
    }

    /**
     * Get the plugin name to display (whitelabel or default)
     */
    public static function getPluginName(): string
    {
        $options = get_option('cs_options', []);

        if (!empty($options['whitelabel_enabled']) && !empty($options['whitelabel_plugin_name'])) {
            return $options['whitelabel_plugin_name'];
        }

        return __('Rezervace', 'call-scheduler');
    }
}
