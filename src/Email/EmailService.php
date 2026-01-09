<?php

declare(strict_types=1);

namespace CallScheduler\Email;

use CallScheduler\BookingStatus;
use CallScheduler\TemplateLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email service for sending booking-related emails
 *
 * Handles customer confirmations, admin notifications, and status change emails.
 * All templates are generic and unbranded for reuse across projects.
 */
final class EmailService
{
    /**
     * Hook callback: Booking created
     */
    public function onBookingCreated(int $bookingId, int $userId, string $bookingDate): void
    {
        $booking = $this->getBookingData($bookingId);
        if (!$booking) {
            return;
        }

        $this->sendCustomerConfirmation($booking);
        $this->sendAdminNotification($booking);
    }

    /**
     * Hook callback: Status changed
     */
    public function onStatusChanged(int $bookingId, string $newStatus, ?string $oldStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $booking = $this->getBookingData($bookingId);
        if (!$booking) {
            return;
        }

        $booking['old_status'] = $oldStatus;
        $booking['old_status_label'] = $oldStatus ? BookingStatus::label($oldStatus) : null;

        $this->sendStatusChangeEmail($booking);
    }

    /**
     * Send confirmation email to customer
     */
    public function sendCustomerConfirmation(array $booking): bool
    {
        if (!apply_filters('cs_should_send_email', true, 'customer_confirmation', $booking)) {
            return false;
        }

        $teamMember = $this->getTeamMemberInfo((int) $booking['user_id']);

        $data = $this->buildTemplateData($booking, $teamMember);
        $data = apply_filters('cs_email_data', $data, 'customer_confirmation');

        $to = apply_filters('cs_email_recipient', $booking['customer_email'], 'customer_confirmation', $booking);

        $subject = sprintf(
            /* translators: %s: site name */
            __('Booking Confirmation - %s', 'call-scheduler'),
            $data['siteName']
        );
        $subject = apply_filters('cs_email_subject', $subject, 'customer_confirmation', $booking);

        $html = TemplateLoader::load('customer-confirmation', $data);

        return $this->send($to, $subject, $html);
    }

    /**
     * Send notification email to admin/team member
     */
    public function sendAdminNotification(array $booking): bool
    {
        if (!apply_filters('cs_should_send_email', true, 'admin_notification', $booking)) {
            return false;
        }

        $teamMember = $this->getTeamMemberInfo((int) $booking['user_id']);
        if (!$teamMember || !$teamMember->user_email) {
            return false;
        }

        $data = $this->buildTemplateData($booking, $teamMember);
        $data['dashboardUrl'] = admin_url('admin.php?page=cs-bookings');
        $data = apply_filters('cs_email_data', $data, 'admin_notification');

        $to = apply_filters('cs_email_recipient', $teamMember->user_email, 'admin_notification', $booking);

        $subject = sprintf(
            /* translators: %s: customer name */
            __('New Booking: %s', 'call-scheduler'),
            $booking['customer_name']
        );
        $subject = apply_filters('cs_email_subject', $subject, 'admin_notification', $booking);

        $html = TemplateLoader::load('admin-notification', $data);

        return $this->send($to, $subject, $html);
    }

    /**
     * Send status change email to customer
     */
    public function sendStatusChangeEmail(array $booking): bool
    {
        if (!apply_filters('cs_should_send_email', true, 'status_change', $booking)) {
            return false;
        }

        $teamMember = $this->getTeamMemberInfo((int) $booking['user_id']);

        $data = $this->buildTemplateData($booking, $teamMember);
        $data['newStatus'] = BookingStatus::label($booking['status']);
        $data['newStatusRaw'] = $booking['status'];
        $data['oldStatus'] = $booking['old_status_label'] ?? null;
        $data['statusColor'] = BookingStatus::color($booking['status']);
        $data = apply_filters('cs_email_data', $data, 'status_change');

        $to = apply_filters('cs_email_recipient', $booking['customer_email'], 'status_change', $booking);

        $subject = sprintf(
            /* translators: %s: status label */
            __('Booking %s', 'call-scheduler'),
            $data['newStatus']
        );
        $subject = apply_filters('cs_email_subject', $subject, 'status_change', $booking);

        $html = TemplateLoader::load('status-change', $data);

        return $this->send($to, $subject, $html);
    }

    /**
     * Build common template data array
     */
    private function buildTemplateData(array $booking, ?object $teamMember): array
    {
        return [
            'siteName'        => get_bloginfo('name'),
            'siteUrl'         => home_url(),
            'adminEmail'      => get_option('admin_email'),
            'logoUrl'         => $this->getLogoUrl(),
            'customerName'    => $booking['customer_name'],
            'customerEmail'   => $booking['customer_email'],
            'bookingId'       => $booking['id'] ?? null,
            'bookingDate'     => $this->formatDate($booking['booking_date']),
            'bookingTime'     => $this->formatTime($booking['booking_time']),
            'bookingDateRaw'  => $booking['booking_date'],
            'bookingTimeRaw'  => $booking['booking_time'],
            'teamMemberName'  => $teamMember->display_name ?? '',
            'teamMemberEmail' => $teamMember->user_email ?? '',
        ];
    }

    /**
     * Send email with HTML content
     */
    private function send(string $to, string $subject, string $html): bool
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . esc_attr(get_bloginfo('name')) . ' <' . get_option('admin_email') . '>',
        ];

        $headers = apply_filters('cs_email_headers', $headers, 'default');

        return wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Format date for display using WordPress date format
     */
    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return wp_date(get_option('date_format', 'F j, Y'), $timestamp);
    }

    /**
     * Format time for display using WordPress time format
     */
    private function formatTime(string $time): string
    {
        $timestamp = strtotime($time);
        if ($timestamp === false) {
            return $time;
        }

        return wp_date(get_option('time_format', 'g:i A'), $timestamp);
    }

    /**
     * Get logo URL from theme or settings
     */
    private function getLogoUrl(): string
    {
        $customLogoId = get_theme_mod('custom_logo');
        if ($customLogoId) {
            $logoUrl = wp_get_attachment_image_url($customLogoId, 'medium');
            if ($logoUrl) {
                return $logoUrl;
            }
        }

        return '';
    }

    /**
     * Get team member user object
     */
    private function getTeamMemberInfo(int $userId): ?object
    {
        return get_user_by('ID', $userId) ?: null;
    }

    /**
     * Get booking data from database
     */
    private function getBookingData(int $bookingId): ?array
    {
        global $wpdb;

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, u.display_name as team_member_name
             FROM {$wpdb->prefix}cs_bookings b
             LEFT JOIN {$wpdb->users} u ON b.user_id = u.ID
             WHERE b.id = %d",
            $bookingId
        ), ARRAY_A);

        return $booking ?: null;
    }
}
