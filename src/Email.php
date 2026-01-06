<?php

declare(strict_types=1);

namespace CallScheduler;

if (!defined('ABSPATH')) {
    exit;
}

final class Email
{
    /**
     * Send booking confirmation email to customer
     *
     * @param array $booking Booking data with keys: customer_email, customer_name, booking_date, booking_time, user_id
     * @return bool True if email was sent successfully
     */
    public function sendCustomerConfirmation(array $booking): bool
    {
        $to = $booking['customer_email'];
        $team_member = $this->getTeamMemberInfo($booking['user_id']);

        $subject = 'Vaše rezervace je potvrzena';
        $message = $this->buildCustomerConfirmationEmail($booking, $team_member);

        return wp_mail($to, $subject, $message, $this->getEmailHeaders());
    }

    /**
     * Send booking notification email to team member
     *
     * @param array $booking Booking data
     * @return bool True if email was sent successfully
     */
    public function sendTeamMemberNotification(array $booking): bool
    {
        $team_member = $this->getTeamMemberInfo($booking['user_id']);

        if (!$team_member || !$team_member->user_email) {
            return false;
        }

        $to = $team_member->user_email;
        $subject = sprintf(
            'Nová rezervace: %s',
            $booking['customer_name']
        );
        $message = $this->buildTeamMemberNotificationEmail($booking, $team_member);

        return wp_mail($to, $subject, $message, $this->getEmailHeaders());
    }

    /**
     * Build customer confirmation email HTML
     *
     * @param array $booking Booking data
     * @param object $team_member Team member user object
     * @return string HTML email content
     */
    private function buildCustomerConfirmationEmail(array $booking, object $team_member): string
    {
        return TemplateLoader::load('customer-confirmation', [
            'customerName' => $booking['customer_name'],
            'bookingDate' => $this->formatCzechDate($booking['booking_date']),
            'bookingTime' => $booking['booking_time'],
            'teamMemberName' => $team_member->display_name,
            'siteName' => get_bloginfo('name'),
            'adminEmail' => get_option('admin_email'),
            'logoUrl' => home_url('/wp-content/uploads/2026/01/logo.png'),
        ]);
    }

    /**
     * Build team member notification email HTML
     *
     * @param array $booking Booking data
     * @param object $team_member Team member user object
     * @return string HTML email content
     */
    private function buildTeamMemberNotificationEmail(array $booking, object $team_member): string
    {
        return TemplateLoader::load('team-member-notification', [
            'customerName' => $booking['customer_name'],
            'customerEmail' => $booking['customer_email'],
            'bookingDate' => $this->formatCzechDate($booking['booking_date']),
            'bookingTime' => $booking['booking_time'],
            'siteName' => get_bloginfo('name'),
            'adminEmail' => get_option('admin_email'),
            'logoUrl' => home_url('/wp-content/uploads/2026/01/logo.png'),
        ]);
    }

    /**
     * Get team member user object by ID
     *
     * @param int $user_id Team member user ID
     * @return object|null User object or null if not found
     */
    private function getTeamMemberInfo(int $user_id): ?object
    {
        return get_user_by('ID', $user_id);
    }

    /**
     * Format date to Czech human-readable format
     *
     * @param string $date Date in Y-m-d format (e.g., 2026-07-13)
     * @return string Formatted date (e.g., 13 července 2026)
     */
    private function formatCzechDate(string $date): string
    {
        $months = [
            1 => 'ledna', 2 => 'února', 3 => 'března', 4 => 'dubna',
            5 => 'května', 6 => 'června', 7 => 'července', 8 => 'srpna',
            9 => 'září', 10 => 'října', 11 => 'listopadu', 12 => 'prosince',
        ];

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $day = (int) date('j', $timestamp);
        $month = (int) date('n', $timestamp);
        $year = date('Y', $timestamp);

        return sprintf('%d %s %s', $day, $months[$month], $year);
    }

    /**
     * Get email headers for HTML emails
     *
     * @return array Email headers
     */
    private function getEmailHeaders(): array
    {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . esc_attr(get_option('admin_email')),
        ];
    }
}
