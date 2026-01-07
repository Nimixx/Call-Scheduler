<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Dashboard;

use CallScheduler\Admin\Bookings\BookingsRepository;
use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Controller for the Dashboard admin page
 */
final class DashboardPage
{
    private BookingsRepository $repository;
    private DashboardRenderer $renderer;

    public function __construct(?BookingsRepository $repository = null)
    {
        $this->repository = $repository ?? new BookingsRepository();
        $this->renderer = new DashboardRenderer();
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        $screen = get_current_screen();
        if ($screen === null || !str_ends_with($screen->id, '_page_cs-dashboard')) {
            return;
        }

        wp_enqueue_style(
            'cs-admin-dashboard',
            CS_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            [],
            CS_VERSION
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

        $counts = $this->repository->countByStatus();
        $stats = $this->transformStats($counts);
        $this->renderer->renderPage($stats);
    }

    /**
     * Transform BookingsRepository counts format to dashboard format
     *
     * Validates structure and provides safe defaults for missing keys.
     *
     * @param array $counts Expected keys: all, pending, confirmed, cancelled
     * @return array{total: int, pending: int, confirmed: int, cancelled: int}
     */
    private function transformStats(array $counts): array
    {
        return [
            'total' => (int) ($counts['all'] ?? 0),
            'pending' => (int) ($counts[BookingStatus::PENDING] ?? 0),
            'confirmed' => (int) ($counts[BookingStatus::CONFIRMED] ?? 0),
            'cancelled' => (int) ($counts[BookingStatus::CANCELLED] ?? 0),
        ];
    }
}
