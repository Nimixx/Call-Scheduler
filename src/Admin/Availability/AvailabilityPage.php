<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Availability;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Availability admin page
 */
final class AvailabilityPage
{
    private AvailabilityRepository $repository;
    private AvailabilityService $service;
    private AvailabilityRenderer $renderer;

    public function __construct()
    {
        $this->repository = new AvailabilityRepository();
        $this->service = new AvailabilityService($this->repository);
        $this->renderer = new AvailabilityRenderer($this->service);
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        // Check if we're on the availability page using the page query parameter
        // This is more reliable than checking the hook name which can change with translations
        $screen = get_current_screen();
        if ($screen === null || !str_ends_with($screen->id, '_page_cs-availability')) {
            return;
        }

        wp_enqueue_style(
            'cs-admin',
            CS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CS_VERSION
        );

        wp_enqueue_script(
            'cs-admin-availability',
            CS_PLUGIN_URL . 'assets/js/admin-availability.js',
            [],
            CS_VERSION,
            true
        );
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nemáte dostatečná oprávnění pro přístup na tuto stránku.', 'call-scheduler'));
        }

        if (!$this->repository->isPluginInstalled()) {
            $this->renderer->renderInstallationError();
            return;
        }

        if (isset($_POST['cs_save_availability'])) {
            $this->service->saveAvailability();
        }

        $data = $this->service->prepareData();
        $this->renderer->renderPage($data);
    }
}
