<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook settings module - external integrations
 */
final class WebhookModule extends AbstractSettingsModule
{
    public function getId(): string
    {
        return 'webhook';
    }

    public function getTitle(): string
    {
        return __('Webhooks', 'call-scheduler');
    }

    public function getIcon(): string
    {
        return 'rest-api';
    }

    public function getDefaults(): array
    {
        return [
            'webhook_enabled' => false,
            'webhook_url' => '',
            'webhook_secret' => '',
        ];
    }

    public function sanitize(array $input): array
    {
        $output = [];

        $output['webhook_enabled'] = !empty($input['webhook_enabled']);

        $output['webhook_url'] = isset($input['webhook_url'])
            ? esc_url_raw(trim($input['webhook_url']))
            : '';

        $output['webhook_secret'] = isset($input['webhook_secret'])
            ? sanitize_text_field($input['webhook_secret'])
            : '';

        return $output;
    }

    public function render(array $options): void
    {
        $is_enabled = !empty($options['webhook_enabled']);
        $webhook_url = $options['webhook_url'] ?? '';
        $webhook_secret = $options['webhook_secret'] ?? '';

        $this->renderCardStart();

        // Enable toggle row
        $this->renderFormRowStart(
            'webhook_enabled',
            __('Povolit webhooks', 'call-scheduler'),
            __('Odesílat HTTP notifikace při vytvoření rezervace.', 'call-scheduler')
        );
        $this->renderToggle('webhook_enabled', $is_enabled);
        $this->renderFormRowEnd();

        // Conditional fields container
        ?>
        <div class="cs-conditional-fields" data-depends-on="webhook_enabled" <?php echo $is_enabled ? '' : 'style="display: none;"'; ?>>
            <?php
            // Webhook URL
            $this->renderFormRowStart(
                'webhook_url',
                __('Webhook URL', 'call-scheduler'),
                __('Cílová URL pro příjem webhook notifikací (n8n, Zapier, Make.com).', 'call-scheduler')
            );
            $this->renderTextInput('webhook_url', $webhook_url, 'https://...');
            $this->renderFormRowEnd();

            // Secret key
            $this->renderFormRowStart(
                'webhook_secret',
                __('Tajný klíč (volitelné)', 'call-scheduler'),
                __('Pro HMAC-SHA256 podpis payloadu. Příjemce může ověřit autenticitu.', 'call-scheduler')
            );
            $this->renderTextInput('webhook_secret', $webhook_secret, '');
            $this->renderFormRowEnd();
            ?>
        </div>
        <?php

        $this->renderCardEnd();
    }

    /**
     * Check if webhooks are enabled and configured
     */
    public static function isEnabled(): bool
    {
        $options = get_option(self::OPTION_NAME, []);
        return !empty($options['webhook_enabled']) && !empty($options['webhook_url']);
    }
}
