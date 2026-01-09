<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Dashboard;

use CallScheduler\Admin\Bookings\BookingsRepository;
use CallScheduler\BookingStatus;
use CallScheduler\Helpers\DataValidator;
use CallScheduler\Helpers\FilterSanitizer;

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
            'cs-admin',
            CS_PLUGIN_URL . 'assets/css/admin.css',
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

        // Handle POST actions from dashboard
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleDashboardAction();
        }

        $counts = $this->repository->countByStatus();

        // Validate data structure before transformation
        if (!DataValidator::isValidStatusCounts($counts)) {
            $this->logDataIntegrityWarning($counts);
        }

        $stats = $this->transformStats($counts);

        // Get current user's bookings
        $currentUserId = get_current_user_id();
        $statusFilter = FilterSanitizer::sanitizeStatus('dashboard_status');

        $userBookings = $this->repository->getBookingsForUser($currentUserId, $statusFilter, 10);

        $this->renderer->renderPage($stats, $userBookings, $statusFilter);
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

    /**
     * Handle POST actions from dashboard (status changes, deletes)
     *
     * @return void
     */
    private function handleDashboardAction(): void
    {
        if (!isset($_POST['cs_bookings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['cs_bookings_nonce'], 'cs_bookings_action')) {
            wp_die(__('Bezpečnostní kontrola selhala.', 'call-scheduler'));
        }

        $action = $_POST['cs_action'] ?? '';
        $statusFilter = FilterSanitizer::sanitizeStatus('dashboard_status');

        switch ($action) {
            case 'change_status':
                $this->handleStatusChange($statusFilter);
                break;

            case 'delete':
                $this->handleDelete($statusFilter);
                break;
        }
    }

    /**
     * Handle status change action
     *
     * @param string|null $statusFilter Current status filter
     * @return void
     */
    private function handleStatusChange(?string $statusFilter): void
    {
        $bookingId = FilterSanitizer::sanitizePostInt('booking_id');
        $newStatus = FilterSanitizer::sanitizePostStatus('new_status');

        if ($bookingId === 0 || $newStatus === null) {
            $this->redirectToDashboard($statusFilter);
            return;
        }

        $this->repository->updateStatus($bookingId, $newStatus);
        $this->redirectToDashboard($statusFilter);
    }

    /**
     * Handle delete action
     *
     * @param string|null $statusFilter Current status filter
     * @return void
     */
    private function handleDelete(?string $statusFilter): void
    {
        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

        if ($bookingId === 0) {
            $this->redirectToDashboard($statusFilter);
            return;
        }

        $this->repository->deleteBooking($bookingId);
        $this->redirectToDashboard($statusFilter);
    }

    /**
     * Redirect back to dashboard with optional status filter
     *
     * @param string|null $statusFilter Current status filter
     * @return void
     */
    private function redirectToDashboard(?string $statusFilter): void
    {
        $args = ['page' => 'cs-dashboard'];

        if ($statusFilter !== null) {
            $args['dashboard_status'] = $statusFilter;
        }

        wp_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
