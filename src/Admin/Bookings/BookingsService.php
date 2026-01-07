<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Bookings;

use CallScheduler\BookingStatus;
use CallScheduler\Helpers\FilterSanitizer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles business logic for bookings management
 */
final class BookingsService
{
    private BookingsRepository $repository;

    public function __construct(BookingsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function prepareData(): array
    {
        $status = $this->getFilterStatus();
        $dateFrom = $this->getFilterDateFrom();
        $dateTo = $this->getFilterDateTo();
        $page = $this->getCurrentPage();

        $bookings = $this->repository->getBookings($status, $dateFrom, $dateTo, $page);
        $totalItems = $this->repository->countBookings($status, $dateFrom, $dateTo);
        $statusCounts = $this->repository->countByStatus();

        return [
            'bookings' => $bookings,
            'status_counts' => $statusCounts,
            'current_status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'current_page' => $page,
            'total_items' => $totalItems,
            'total_pages' => (int) ceil($totalItems / $this->repository->getPerPage()),
            'per_page' => $this->repository->getPerPage(),
            'show_success' => isset($_GET['updated']) && $_GET['updated'] === '1',
            'show_error' => isset($_GET['error']) && $_GET['error'] === '1',
            'success_message' => $this->getSuccessMessage(),
        ];
    }

    public function handleAction(): void
    {
        if (!isset($_POST['cs_bookings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['cs_bookings_nonce'], 'cs_bookings_action')) {
            wp_die(__('Bezpečnostní kontrola selhala.', 'call-scheduler'));
        }

        $action = $_POST['cs_action'] ?? '';
        $redirectArgs = $this->getFilterRedirectArgs();

        switch ($action) {
            case 'change_status':
                $this->handleStatusChange($redirectArgs);
                break;

            case 'delete':
                $this->handleDelete($redirectArgs);
                break;

            case 'bulk':
                $this->handleBulkAction($redirectArgs);
                break;
        }
    }

    private function handleStatusChange(array &$redirectArgs): void
    {
        $bookingId = FilterSanitizer::sanitizePostInt('booking_id');
        $newStatus = FilterSanitizer::sanitizePostStatus('new_status');

        if ($bookingId === 0 || $newStatus === null) {
            $redirectArgs['error'] = '1';
            $this->redirect($redirectArgs);
            return;
        }

        $result = $this->repository->updateStatus($bookingId, $newStatus);

        if ($result) {
            $redirectArgs['updated'] = '1';
            $redirectArgs['action_type'] = 'status';
        } else {
            $redirectArgs['error'] = '1';
        }

        $this->redirect($redirectArgs);
    }

    private function handleDelete(array &$redirectArgs): void
    {
        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;

        if ($bookingId === 0) {
            $redirectArgs['error'] = '1';
            $this->redirect($redirectArgs);
            return;
        }

        $result = $this->repository->deleteBooking($bookingId);

        if ($result) {
            $redirectArgs['updated'] = '1';
            $redirectArgs['action_type'] = 'delete';
        } else {
            $redirectArgs['error'] = '1';
        }

        $this->redirect($redirectArgs);
    }

    private function handleBulkAction(array &$redirectArgs): void
    {
        $bulkAction = FilterSanitizer::sanitizePostText('bulk_action');
        $bookingIds = isset($_POST['booking_ids']) ? array_map('absint', (array) $_POST['booking_ids']) : [];

        if (empty($bookingIds) || $bulkAction === '' || $bulkAction === '-1') {
            $this->redirect($redirectArgs);
            return;
        }

        $affectedCount = 0;

        if ($bulkAction === 'delete') {
            $affectedCount = $this->repository->bulkDelete($bookingIds);
            $redirectArgs['action_type'] = 'bulk_delete';
        } elseif (BookingStatus::isValid($bulkAction)) {
            $affectedCount = $this->repository->bulkUpdateStatus($bookingIds, $bulkAction);
            $redirectArgs['action_type'] = 'bulk_status';
        }

        if ($affectedCount > 0) {
            $redirectArgs['updated'] = '1';
            $redirectArgs['count'] = $affectedCount;
        }

        $this->redirect($redirectArgs);
    }

    private function getFilterStatus(): ?string
    {
        return FilterSanitizer::sanitizeStatus('status');
    }

    private function getFilterDateFrom(): ?string
    {
        return FilterSanitizer::sanitizeDate('date_from');
    }

    private function getFilterDateTo(): ?string
    {
        return FilterSanitizer::sanitizeDate('date_to');
    }

    private function getCurrentPage(): int
    {
        $page = FilterSanitizer::sanitizeGetInt('paged');
        return max(1, $page === 0 ? 1 : $page);
    }

    private function getFilterRedirectArgs(): array
    {
        $args = [];

        if ($this->getFilterStatus() !== null) {
            $args['status'] = $this->getFilterStatus();
        }

        if ($this->getFilterDateFrom() !== null) {
            $args['date_from'] = $this->getFilterDateFrom();
        }

        if ($this->getFilterDateTo() !== null) {
            $args['date_to'] = $this->getFilterDateTo();
        }

        return $args;
    }

    private function getSuccessMessage(): ?string
    {
        if (!isset($_GET['updated']) || $_GET['updated'] !== '1') {
            return null;
        }

        $actionType = isset($_GET['action_type']) ? sanitize_text_field($_GET['action_type']) : '';
        $count = FilterSanitizer::sanitizeGetInt('count');
        $count = $count === 0 ? 1 : $count;

        switch ($actionType) {
            case 'status':
                return __('Stav rezervace byl úspěšně změněn.', 'call-scheduler');

            case 'delete':
                return __('Rezervace byla úspěšně smazána.', 'call-scheduler');

            case 'bulk_delete':
                return sprintf(
                    _n('Byla smazána %d rezervace.', 'Bylo smazáno %d rezervací.', $count, 'call-scheduler'),
                    $count
                );

            case 'bulk_status':
                return sprintf(
                    _n('Byl změněn stav %d rezervace.', 'Byl změněn stav %d rezervací.', $count, 'call-scheduler'),
                    $count
                );

            default:
                return __('Změny byly úspěšně uloženy.', 'call-scheduler');
        }
    }

    private function redirect(array $args): void
    {
        wp_redirect(add_query_arg($args, admin_url('admin.php?page=cs-bookings')));
        exit;
    }
}
