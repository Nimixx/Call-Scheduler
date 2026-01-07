<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Components;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reusable WordPress admin notice renderer
 *
 * Provides consistent rendering of success, error, warning, and info notices.
 */
final class NoticeRenderer
{
    /**
     * Render a success notice
     *
     * @param string $message Message to display
     * @param bool $dismissible Whether notice is dismissible (default: true)
     * @return void
     */
    public static function success(string $message, bool $dismissible = true): void
    {
        self::render('success', $message, 'dashicons-yes-alt', $dismissible);
    }

    /**
     * Render an error notice
     *
     * @param string $message Message to display
     * @param bool $dismissible Whether notice is dismissible (default: true)
     * @return void
     */
    public static function error(string $message, bool $dismissible = true): void
    {
        self::render('error', $message, 'dashicons-warning', $dismissible);
    }

    /**
     * Render a warning notice
     *
     * @param string $message Message to display
     * @param bool $dismissible Whether notice is dismissible (default: false)
     * @return void
     */
    public static function warning(string $message, bool $dismissible = false): void
    {
        self::render('warning', $message, null, $dismissible);
    }

    /**
     * Render an info notice
     *
     * @param string $message Message to display
     * @param bool $dismissible Whether notice is dismissible (default: true)
     * @return void
     */
    public static function info(string $message, bool $dismissible = true): void
    {
        self::render('info', $message, null, $dismissible);
    }

    /**
     * Render a generic notice
     *
     * @param string $type Notice type (success, error, warning, info)
     * @param string $message Message to display (already escaped/translated)
     * @param string|null $dashicon Optional dashicon class name (e.g., 'dashicons-yes-alt')
     * @param bool $dismissible Whether notice is dismissible
     * @return void
     */
    private static function render(
        string $type,
        string $message,
        ?string $dashicon = null,
        bool $dismissible = true
    ): void {
        $classes = ['notice', "notice-{$type}"];
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>">
            <p>
                <?php if ($dashicon): ?>
                    <span class="dashicons <?php echo esc_attr($dashicon); ?>"></span>
                <?php endif; ?>
                <?php echo $message; // Message should already be escaped by caller ?>
            </p>
        </div>
        <?php
    }
}
