# Email Templates Design Document

## Design Document: Generic Email System for Call Scheduler Plugin

**Date:** 2026-01-09
**Branch:** feature/email

---

## 1. Executive Summary

This document outlines the design for a generic, unbranded, reusable email system for the Call Scheduler WordPress plugin. The system will support three email types:

1. **Customer Confirmation Email** - Sent when a booking is created
2. **Admin Notification Email** - Sent to team members when new bookings arrive
3. **Status Change Email** - Sent to customers when booking status changes

The design emphasizes reusability across projects, mobile responsiveness, and compatibility with email clients through inline CSS.

---

## 2. Email Types

### 2.1 Customer Confirmation (→ Client)
- **Trigger:** Booking created
- **Recipient:** Customer email
- **Purpose:** Confirm booking details
- **Tone:** Friendly, confirmatory

### 2.2 Admin Notification (→ Admin/Team Member)
- **Trigger:** Booking created
- **Recipient:** Team member email
- **Purpose:** Alert about new booking
- **Tone:** Informational, action-oriented

### 2.3 Status Change (→ Client)
- **Trigger:** Booking status changes (pending → confirmed, confirmed → cancelled, etc.)
- **Recipient:** Customer email
- **Purpose:** Inform about status update
- **Tone:** Status-appropriate (celebratory for confirmed, apologetic for cancelled)

---

## 3. Architecture

### 3.1 File Structure

```
src/
  Email/
    EmailService.php           # Main service class

templates/emails/
  layouts/
    base.php                   # Generic base layout
  partials/
    button.php                 # CTA button component (existing)
    info-card.php              # Booking details card (existing)
    status-badge.php           # Status indicator (NEW)
  customer-confirmation.php    # Refactored (generic text)
  admin-notification.php       # Refactored
  status-change.php            # NEW template
```

### 3.2 Existing Infrastructure

The plugin already has:
- `src/Email.php` - Current email class (to be refactored)
- `src/TemplateLoader.php` - Template rendering
- `templates/emails/layouts/base.php` - Base layout
- `templates/emails/partials/` - Reusable components

---

## 4. Placeholder Variables

All templates will support these standard placeholders:

| Placeholder | Description | Example Value |
|-------------|-------------|---------------|
| `$siteName` | WordPress site name | "Acme Scheduling" |
| `$siteUrl` | Site home URL | "https://example.com" |
| `$adminEmail` | Admin contact email | "admin@example.com" |
| `$logoUrl` | Logo image URL | "https://example.com/logo.png" |
| `$customerName` | Customer's name | "John Smith" |
| `$customerEmail` | Customer's email | "john@example.com" |
| `$bookingId` | Booking reference ID | "1234" |
| `$bookingDate` | Formatted date | "January 15, 2026" |
| `$bookingTime` | Formatted time | "2:00 PM" |
| `$teamMemberName` | Team member's name | "Jane Doe" |
| `$newStatus` | New status label | "Confirmed" |
| `$oldStatus` | Previous status | "Pending" |
| `$statusColor` | Status color hex | "#10b981" |

---

## 5. HTML/CSS Approach

### 5.1 Compatibility Requirements

| Technique | Purpose |
|-----------|---------|
| **Table-based layout** | Consistent rendering in Outlook, Gmail, Yahoo |
| **Inline CSS** | Gmail, mobile clients strip `<style>` tags |
| **MSO conditionals** | Outlook-specific fixes |
| **role="presentation"** | Accessibility for screen readers |
| **Max-width 600px** | Optimal readability |
| **System fonts** | Reliable rendering, fast loading |

### 5.2 Color Palette

| Token | Hex | Usage |
|-------|-----|-------|
| `accent` | #6366f1 | Primary brand color, links |
| `text` | #1f2937 | Body text |
| `textLight` | #6b7280 | Secondary text |
| `background` | #f9fafb | Email body background |
| `white` | #ffffff | Container background |
| `success` | #10b981 | Confirmed status |
| `warning` | #ea580c | Pending status |
| `error` | #ef4444 | Cancelled status |

### 5.3 Responsive Breakpoint

```css
@media only screen and (max-width: 600px) {
    .email-container { width: 100% !important; }
    .email-padding { padding: 24px 16px !important; }
}
```

---

## 6. Integration Points

### 6.1 WordPress Hooks

**Existing:**
```php
do_action('cs_booking_created', $booking_id, $user_id, $booking_date);
```

**New (add to BookingsRepository::updateStatus()):**
```php
do_action('cs_booking_status_changed', $id, $newStatus, $oldStatus);
```

### 6.2 Filter Hooks for Customization

```php
// Modify email data before sending
$data = apply_filters('cs_email_data', $data, $emailType);

// Modify recipient
$to = apply_filters('cs_email_recipient', $to, $emailType, $booking);

// Modify subject
$subject = apply_filters('cs_email_subject', $subject, $emailType, $booking);

// Disable specific email types
$shouldSend = apply_filters('cs_should_send_email', true, $emailType, $booking);
```

---

## 7. Template Examples

### 7.1 Customer Confirmation Structure

```
┌─────────────────────────────────────┐
│           [LOGO/SITE NAME]          │
├─────────────────────────────────────┤
│                                     │
│  BOOKING CONFIRMED                  │
│                                     │
│  Hello {{customer_name}},           │
│                                     │
│  Thank you for your booking.        │
│                                     │
│  ┌─────────────────────────────┐    │
│  │ Date:  January 15, 2026     │    │
│  │ Time:  2:00 PM              │    │
│  │ With:  Jane Doe             │    │
│  │ Ref:   #1234                │    │
│  └─────────────────────────────┘    │
│                                     │
│  We look forward to speaking        │
│  with you!                          │
│                                     │
├─────────────────────────────────────┤
│  Questions? Contact us at           │
│  admin@example.com                  │
│  © 2026 Site Name                   │
└─────────────────────────────────────┘
```

### 7.2 Status Change Structure

```
┌─────────────────────────────────────┐
│           [LOGO/SITE NAME]          │
├─────────────────────────────────────┤
│                                     │
│  BOOKING STATUS UPDATE              │
│                                     │
│  Hello {{customer_name}},           │
│                                     │
│  Status: [CONFIRMED]  (green badge) │
│                                     │
│  Great news! Your booking has       │
│  been confirmed.                    │
│                                     │
│  ┌─────────────────────────────┐    │
│  │ Date:  January 15, 2026     │    │
│  │ Time:  2:00 PM              │    │
│  │ With:  Jane Doe             │    │
│  └─────────────────────────────┘    │
│                                     │
│  Previous status: Pending           │
│                                     │
├─────────────────────────────────────┤
│  © 2026 Site Name                   │
└─────────────────────────────────────┘
```

---

## 8. Implementation Tasks

### Phase 1: Email Service
1. Create `src/Email/EmailService.php` (refactor from `Email.php`)
2. Add `cs_booking_status_changed` hook to `BookingsRepository::updateStatus()`
3. Register hook listeners in `Plugin.php`

### Phase 2: Templates
4. Update `templates/emails/layouts/base.php` - make generic/unbranded
5. Create `templates/emails/partials/status-badge.php`
6. Update `templates/emails/customer-confirmation.php` - generic English text
7. Update `templates/emails/admin-notification.php`
8. Create `templates/emails/status-change.php`

### Phase 3: Plain Text
9. Add plain text fallback generation

---

## 9. Critical Files

| File | Action |
|------|--------|
| `src/Email.php` | Refactor into `src/Email/EmailService.php` |
| `src/Admin/Bookings/BookingsRepository.php:243` | Add status change hook |
| `src/Plugin.php` | Register email hook listeners |
| `templates/emails/layouts/base.php` | Update to generic text |
| `templates/emails/status-change.php` | Create new template |
