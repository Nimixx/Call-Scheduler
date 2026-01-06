<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Bookings;

use CallScheduler\BookingStatus;

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
        $bookingId = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $newStatus = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';

        if ($bookingId === 0 || !BookingStatus::isValid($newStatus)) {
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
        $bulkAction = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
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
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;

        if ($status !== null && !BookingStatus::isValid($status)) {
            return null;
        }

        return $status;
    }

    private function getFilterDateFrom(): ?string
    {
        $date = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : null;

        if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date;
    }

    private function getFilterDateTo(): ?string
    {
        $date = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : null;

        if ($date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date;
    }

    private function getCurrentPage(): int
    {
        return isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
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
        $count = isset($_GET['count']) ? absint($_GET['count']) : 1;

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
