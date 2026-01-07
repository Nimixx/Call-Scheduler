<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Bookings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Bookings admin page
 */
final class BookingsPage
{
    private BookingsRepository $repository;
    private BookingsService $service;
    private BookingsRenderer $renderer;

    public function __construct()
    {
        $this->repository = new BookingsRepository();
        $this->service = new BookingsService($this->repository);
        $this->renderer = new BookingsRenderer();
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        $screen = get_current_screen();
        if ($screen === null || !str_ends_with($screen->id, '_page_cs-bookings')) {
            return;
        }

        wp_enqueue_style(
            'cs-admin-bookings',
            CS_PLUGIN_URL . 'assets/css/admin-bookings.css',
            [],
            CS_VERSION
        );

        wp_enqueue_script(
            'cs-admin-bookings',
            CS_PLUGIN_URL . 'assets/js/admin-bookings.js',
            [],
            CS_VERSION,
            true
        );

        wp_localize_script('cs-admin-bookings', 'csBookings', [
            'confirmDelete' => __('Opravdu chcete smazat tuto rezervaci?', 'call-scheduler'),
            'confirmBulkDelete' => __('Opravdu chcete smazat vybrané rezervace?', 'call-scheduler'),
        ]);
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

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->service->handleAction();
        }

        $data = $this->service->prepareData();
        $this->renderer->renderPage($data);
    }
}
