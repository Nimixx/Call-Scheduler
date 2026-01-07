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

        // Validate data structure before transformation
        if (!$this->isValidStatsStructure($counts)) {
            $this->logDataIntegrityWarning($counts);
        }

        $stats = $this->transformStats($counts);
        $this->renderer->renderPage($stats);
    }

    /**
     * Validate that stats array has expected structure and types
     *
     * Expected structure:
     * - 'all': integer >= 0
     * - 'pending': integer >= 0
     * - 'confirmed': integer >= 0
     * - 'cancelled': integer >= 0
     *
     * @param mixed $counts Data to validate
     * @return bool True if structure is valid, false otherwise
     */
    private function isValidStatsStructure(mixed $counts): bool
    {
        // Must be array
        if (!is_array($counts)) {
            return false;
        }

        $required_keys = ['all', BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::CANCELLED];

        // Check all required keys exist
        foreach ($required_keys as $key) {
            if (!isset($counts[$key])) {
                return false;
            }

            // Values must be integers or numeric
            if (!is_int($counts[$key]) && !is_numeric($counts[$key])) {
                return false;
            }

            // Values must be >= 0
            if ((int) $counts[$key] < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Log warning when stats data structure is invalid
     *
     * @param mixed $counts Invalid data for debugging
     * @return void
     */
    private function logDataIntegrityWarning(mixed $counts): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'Call Scheduler Dashboard: Invalid stats structure received from countByStatus(). Got: %s',
                wp_json_encode($counts)
            ));
        }
    }

    /**
     * Transform BookingsRepository counts format to dashboard format
     *
     * Validates structure and provides safe defaults for missing/invalid keys.
     *
     * @param array $counts Expected keys: all, pending, confirmed, cancelled (integers)
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
