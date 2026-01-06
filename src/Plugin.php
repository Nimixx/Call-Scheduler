<?php

declare(strict_types=1);

namespace CallScheduler;

/**
 * Main plugin class - singleton pattern
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private bool $booted = false;

    private Container $container;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get the service container
     */
    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Static shortcut to get container
     */
    public static function getContainer(): Container
    {
        return self::instance()->container();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->registerServices();
        $this->registerHooks();
    }

    /**
     * Register shared services in the container
     */
    private function registerServices(): void
    {
        // Cache service (singleton)
        $this->container->set('cache', fn() => new Cache());

        // Email service (singleton, uses cache)
        $this->container->set('email', fn(Container $c) => new Email());
    }

    private function registerHooks(): void
    {
        add_action('init', [$this, 'onInit']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('rest_api_init', [$this, 'configureCors']);
        add_action('admin_notices', [$this, 'showActivationNotice']);
        add_action('admin_init', [$this, 'handleSeederAction']);

        // Cache invalidation hooks
        add_action('cs_booking_created', [$this, 'invalidateBookingsCache']);

        // Register admin pages
        $adminPages = new Admin\AdminPages();
        $adminPages->register();

        // Register user profile fields
        $userProfile = new Admin\UserProfile();
        $userProfile->register();

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('cs seed', function () {
                Seeder::run();
                \WP_CLI::success('Database seeded successfully!');
            });
        }
    }

    public function registerRestRoutes(): void
    {
        $controllers = [
            new Rest\TeamMembersController(),
            new Rest\AvailabilityController(),
            new Rest\BookingsController(),
        ];

        foreach ($controllers as $controller) {
            $controller->register();
        }
    }

    public function onInit(): void
    {
        // Load translations
        load_plugin_textdomain('call-scheduler', false, dirname(plugin_basename(CS_PLUGIN_FILE)) . '/languages');

        // Run database upgrades if needed
        Installer::maybeUpgrade();
    }

    /**
     * Invalidate bookings cache when booking is created via REST API
     */
    public function invalidateBookingsCache(): void
    {
        $this->container->cache()->delete('bookings_status_counts');
    }

    public function configureCors(): void
    {
        // Configure allowed origins for the booking API
        // Define CS_ALLOWED_ORIGINS in wp-config.php:
        // define('CS_ALLOWED_ORIGINS', 'https://yourdomain.com,https://www.yourdomain.com');

        // Handle OPTIONS preflight for cs/v1 routes
        add_action('rest_api_init', function () {
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && $this->isCsRoute()) {
                $this->sendCorsHeaders();
                status_header(200);
                exit;
            }
        }, 1);

        add_filter('rest_pre_serve_request', function (bool $served): bool {
            // Only add CORS headers for cs/v1 routes
            if (!$this->isCsRoute()) {
                return $served;
            }

            $this->sendCorsHeaders();
            return $served;
        });
    }

    private function isCsRoute(): bool
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($request_uri, '/wp-json/cs/v1/') !== false
            || strpos($request_uri, '?rest_route=/cs/v1/') !== false;
    }

    private function sendCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (Config::isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce, X-CS-Token');
            header('Access-Control-Allow-Credentials: true');
        }
    }

    public function handleSeederAction(): void
    {
        if (!isset($_GET['cs_seed']) || $_GET['cs_seed'] !== '1') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('cs_seed_action');

        Seeder::run();

        wp_redirect(admin_url('?cs_seeded=1'));
        exit;
    }

    public function showActivationNotice(): void
    {
        if (! get_transient('cs_activation_notice')) {
            return;
        }

        delete_transient('cs_activation_notice');

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Call Scheduler activated successfully!', 'call-scheduler')
        );
    }
}
