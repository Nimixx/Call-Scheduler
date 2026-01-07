<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Settings\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook settings module - external integrations
 *
 * Security: Webhook secret is stored in wp-config.php constant (CS_WEBHOOK_SECRET),
 * not in the database, to prevent exposure via SQL injection or backup leaks.
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
        ];
    }

    public function sanitize(array $input): array
    {
        $output = [];

        $output['webhook_enabled'] = !empty($input['webhook_enabled']);

        $url = isset($input['webhook_url']) ? trim($input['webhook_url']) : '';

        // Validate URL
        if (!empty($url)) {
            // Enforce HTTPS
            if (!str_starts_with($url, 'https://')) {
                add_settings_error(
                    'cs_options',
                    'webhook_url_https',
                    __('Webhook URL musí používat HTTPS pro bezpečný přenos dat.', 'call-scheduler'),
                    'error'
                );
                $url = '';
            }

            // SSRF protection - block internal URLs
            if (!empty($url) && $this->isInternalUrl($url)) {
                add_settings_error(
                    'cs_options',
                    'webhook_url_internal',
                    __('Webhook URL nesmí směřovat na interní adresy.', 'call-scheduler'),
                    'error'
                );
                $url = '';
            }
        }

        $output['webhook_url'] = esc_url_raw($url);

        return $output;
    }

    /**
     * Check if URL points to internal/private network
     */
    private function isInternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return true; // Invalid URL, treat as internal
        }

        $host = strtolower($host);

        // Block localhost variations
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        // Block private IP ranges
        $ip = gethostbyname($host);
        if ($ip !== $host) { // Resolution succeeded
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        // Block common internal hostnames
        $blocked_patterns = [
            '/^10\./',
            '/^172\.(1[6-9]|2[0-9]|3[01])\./',
            '/^192\.168\./',
            '/\.local$/',
            '/\.internal$/',
            '/\.localhost$/',
        ];

        foreach ($blocked_patterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    public function render(array $options): void
    {
        $is_enabled = !empty($options['webhook_enabled']);
        $webhook_url = $options['webhook_url'] ?? '';
        $has_secret = defined('CS_WEBHOOK_SECRET') && !empty(CS_WEBHOOK_SECRET);

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
                __('HTTPS endpoint pro příjem notifikací (n8n, Zapier, Make.com).', 'call-scheduler')
            );
            $this->renderTextInput('webhook_url', $webhook_url, 'https://...');
            $this->renderFormRowEnd();

            // Secret key status (read-only info)
            $this->renderFormRowStart(
                'webhook_secret_status',
                __('Podpisový klíč', 'call-scheduler'),
                __('HMAC-SHA256 podpis pro ověření autenticity.', 'call-scheduler')
            );
            $this->renderSecretStatus($has_secret);
            $this->renderFormRowEnd();
            ?>

            <div class="cs-info-box cs-info-box-security">
                <p>
                    <span class="dashicons dashicons-shield"></span>
                    <strong><?php esc_html_e('Bezpečnostní tip:', 'call-scheduler'); ?></strong>
                    <?php esc_html_e('Pro HMAC podpis přidejte do wp-config.php:', 'call-scheduler'); ?>
                </p>
                <code>define('CS_WEBHOOK_SECRET', 'vas-tajny-klic');</code>
            </div>
        </div>
        <?php

        $this->renderCardEnd();
    }

    /**
     * Render secret key status indicator
     */
    private function renderSecretStatus(bool $has_secret): void
    {
        if ($has_secret) {
            ?>
            <span class="cs-status cs-status-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Nakonfigurováno v wp-config.php', 'call-scheduler'); ?>
            </span>
            <?php
        } else {
            ?>
            <span class="cs-status cs-status-warning">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e('Není nastaveno (webhooky bez podpisu)', 'call-scheduler'); ?>
            </span>
            <?php
        }
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
