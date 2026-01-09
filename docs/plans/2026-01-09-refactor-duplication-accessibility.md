# Code Refactoring: Eliminate Duplication & Improve Accessibility

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Eliminate duplicate PHP status rendering code, consolidate JS form submission logic, and add proper accessibility attributes to improve code maintainability and user experience.

**Architecture:**
1. Create `RowActionsRenderer` component to consolidate status action rendering (PHP)
2. Create `FormSubmissionHelper` utility for reusable form submission logic (JS)
3. Replace inline `onclick` handlers with semantic buttons and data attributes
4. Add ARIA labels, roles, and semantic HTML throughout

**Tech Stack:** PHP 8.0+, JavaScript ES5+, WordPress nonces, ARIA accessibility standards

---

## Task 1: Create RowActionsRenderer Component

**Files:**
- Create: `src/Admin/Components/RowActionsRenderer.php`
- Test: Verify component works correctly

**Step 1: Write the RowActionsRenderer component**

Create a new file at `src/Admin/Components/RowActionsRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace CallScheduler\Admin\Components;

use CallScheduler\BookingStatus;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders row action links for status changes and deletion
 *
 * Consolidates duplicate status action rendering logic from BookingsRenderer
 * and DashboardRenderer into a single, reusable component.
 */
final class RowActionsRenderer
{
    /**
     * Render row action links (status changes and delete)
     *
     * @param int $bookingId Booking ID
     * @param string $currentStatus Current booking status
     * @return string HTML for row actions
     */
    public static function render(int $bookingId, string $currentStatus): string
    {
        $availableStatuses = array_filter(
            BookingStatus::all(),
            fn($status) => $status !== $currentStatus
        );

        if (empty($availableStatuses)) {
            return '';
        }

        $links = [];
        foreach ($availableStatuses as $status) {
            $links[] = sprintf(
                '<a href="#" '
                . 'class="cs-row-action-status" '
                . 'data-booking-id="%d" '
                . 'data-new-status="%s" '
                . 'role="button" '
                . 'aria-label="%s" '
                . 'tabindex="0">%s</a>',
                $bookingId,
                esc_attr($status),
                esc_attr(sprintf(__('Změnit stav na: %s', 'call-scheduler'), BookingStatus::label($status))),
                esc_html(BookingStatus::label($status))
            );
        }

        ob_start();
        ?>
        <div class="row-actions">
            <span class="status"><?php echo implode(' | ', $links); ?></span>
            |
            <span class="delete">
                <a href="#"
                   class="cs-row-action-delete submitdelete"
                   data-booking-id="<?php echo esc_attr($bookingId); ?>"
                   role="button"
                   aria-label="<?php esc_attr_e('Smazat tuto rezervaci', 'call-scheduler'); ?>"
                   tabindex="0">
                    <?php esc_html_e('Smazat', 'call-scheduler'); ?>
                </a>
            </span>
        </div>
        <?php
        return ob_get_clean();
    }
}
```

**Step 2: Update BookingsRenderer to use RowActionsRenderer**

Modify `src/Admin/Bookings/BookingsRenderer.php`:

**At the top, add the import:**
```php
use CallScheduler\Admin\Components\RowActionsRenderer;
```

**Replace the `renderRowActions()` method (lines 241-271) with:**
```php
private function renderRowActions(object $booking): void
{
    echo RowActionsRenderer::render((int) $booking->id, $booking->status);
}
```

**Step 3: Update DashboardRenderer to use RowActionsRenderer**

Modify `src/Admin/Dashboard/DashboardRenderer.php`:

**At the top, add the import:**
```php
use CallScheduler\Admin\Components\RowActionsRenderer;
```

**Replace the inline status rendering (lines 233-263) with:**

Change the renderBookingsTable callback from:
```php
function ($booking): void {
    $availableStatuses = array_filter(
        BookingStatus::all(),
        fn($status) => $status !== $booking->status
    );

    $links = [];
    foreach ($availableStatuses as $status) {
        $links[] = sprintf(
            '<a href="#" onclick="csChangeStatus(%d, \'%s\'); return false;">%s</a>',
            $booking->id,
            esc_attr($status),
            esc_html(BookingStatus::label($status))
        );
    }
    ?>
    <tr>
        <td>
            <strong><?php echo esc_html($booking->customer_name); ?></strong>
            <?php if (!empty($links)): ?>
                <div class="row-actions">
                    <span class="status"><?php echo implode(' | ', $links); ?></span>
                    |
                    <span class="delete">
                        <a href="#"
                           onclick="csDeleteBooking(<?php echo esc_attr($booking->id); ?>); return false;"
                           class="submitdelete">
                            <?php echo esc_html__('Smazat', 'call-scheduler'); ?>
                        </a>
                    </span>
                </div>
            <?php endif; ?>
        </td>
        ...
```

To:
```php
function ($booking): void {
    ?>
    <tr>
        <td>
            <strong><?php echo esc_html($booking->customer_name); ?></strong>
            <?php echo RowActionsRenderer::render((int) $booking->id, $booking->status); ?>
        </td>
        ...
```

**Step 4: Verify both pages still work**

Check that:
- `http://yoursite/wp-admin/admin.php?page=cs-bookings` - Bookings page shows row actions
- `http://yoursite/wp-admin/admin.php?page=cs-dashboard` - Dashboard shows row actions

Both should have functioning status change and delete links.

**Step 5: Commit**

```bash
git add src/Admin/Components/RowActionsRenderer.php \
        src/Admin/Bookings/BookingsRenderer.php \
        src/Admin/Dashboard/DashboardRenderer.php
git commit -m "refactor: create RowActionsRenderer to eliminate duplicate status action rendering

- Extract status action link generation to reusable RowActionsRenderer component
- Both BookingsRenderer and DashboardRenderer now use same component
- Add ARIA labels and role attributes for better accessibility
- Replace inline onclick handlers with data attributes (prepared for JS refactoring)"
```

---

## Task 2: Create Form Submission Helper (JavaScript)

**Files:**
- Modify: `assets/js/admin-bookings.js`
- Test: Verify form submissions work

**Step 1: Add FormSubmissionHelper utility**

Replace the entire `assets/js/admin-bookings.js` with:

```javascript
/**
 * Call Scheduler - Admin Bookings Page Scripts
 */

(function() {
    'use strict';

    /**
     * FormSubmissionHelper - Consolidates form submission logic
     * Handles creating and submitting forms with proper error handling
     */
    window.FormSubmissionHelper = {
        /**
         * Create and submit a form with given fields
         *
         * @param {Object} fields - Key-value pairs to include as hidden inputs
         * @returns {void}
         */
        submitForm: function(fields) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            // Add CSRF nonce from page
            var nonceField = document.querySelector('input[name="cs_bookings_nonce"]');
            if (!nonceField) {
                console.error('FormSubmissionHelper: Nonce field not found');
                return;
            }

            fields.cs_bookings_nonce = nonceField.value;

            // Build form inputs
            for (var key in fields) {
                if (fields.hasOwnProperty(key)) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    form.appendChild(input);
                }
            }

            document.body.appendChild(form);
            form.submit();
        }
    };

    /**
     * Change booking status
     *
     * @param {number} bookingId - Booking ID
     * @param {string} newStatus - New status value
     * @returns {boolean} - Always returns false for use in event handlers
     */
    window.csChangeStatus = function(bookingId, newStatus) {
        FormSubmissionHelper.submitForm({
            'cs_action': 'change_status',
            'booking_id': bookingId,
            'new_status': newStatus
        });
        return false;
    };

    /**
     * Delete a booking with confirmation
     *
     * @param {number} bookingId - Booking ID
     * @returns {boolean} - Always returns false for use in event handlers
     */
    window.csDeleteBooking = function(bookingId) {
        if (!confirm(window.csBookings.confirmDelete)) {
            return false;
        }

        FormSubmissionHelper.submitForm({
            'cs_action': 'delete',
            'booking_id': bookingId
        });
        return false;
    };

    // Initialize checkbox and bulk action handlers
    document.addEventListener('DOMContentLoaded', function() {
        initializeCheckboxes();
        initializeBulkActions();
        initializeRowActions();
    });

    /**
     * Initialize select-all checkbox functionality
     */
    function initializeCheckboxes() {
        var selectAllCheckboxes = document.querySelectorAll('#cb-select-all');
        var bookingCheckboxes = document.querySelectorAll('input[name="booking_ids[]"]');

        selectAllCheckboxes.forEach(function(selectAll) {
            selectAll.addEventListener('change', function() {
                var isChecked = this.checked;
                bookingCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = isChecked;
                });
                // Sync other select-all checkboxes
                selectAllCheckboxes.forEach(function(cb) {
                    cb.checked = isChecked;
                });
            });
        });
    }

    /**
     * Initialize bulk action confirmation
     */
    function initializeBulkActions() {
        var form = document.getElementById('cs-bookings-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function(e) {
            var action = document.getElementById('bulk_action');
            if (!action) {
                return;
            }

            if (action.value === 'delete') {
                var checked = document.querySelectorAll('input[name="booking_ids[]"]:checked');
                if (checked.length > 0 && !confirm(window.csBookings.confirmBulkDelete)) {
                    e.preventDefault();
                }
            }
        });
    }

    /**
     * Initialize row action links (status changes, delete)
     * Converts data-driven links to proper keyboard-accessible buttons
     */
    function initializeRowActions() {
        var statusLinks = document.querySelectorAll('.cs-row-action-status');
        var deleteLinks = document.querySelectorAll('.cs-row-action-delete');

        // Status change links
        statusLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var bookingId = this.getAttribute('data-booking-id');
                var newStatus = this.getAttribute('data-new-status');
                csChangeStatus(bookingId, newStatus);
            });

            // Keyboard support: Enter and Space activate the button
            link.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // Delete links
        deleteLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var bookingId = this.getAttribute('data-booking-id');
                csDeleteBooking(bookingId);
            });

            // Keyboard support: Enter and Space activate the button
            link.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    }
})();
```

**Step 2: Verify scripts still work**

Test on both pages:
- Click a status change link - form should submit
- Click delete link - confirmation should appear, then form submits
- Bulk actions should still work on bookings page
- Keyboard support: Tab to a link, press Enter - should activate

**Step 3: Commit**

```bash
git add assets/js/admin-bookings.js
git commit -m "refactor: consolidate form submission logic in JavaScript

- Extract common form creation logic into FormSubmissionHelper utility
- Both csChangeStatus() and csDeleteBooking() now use shared helper
- Add keyboard event handlers for accessibility (Enter/Space support)
- Organize code into logical sections: checkboxes, bulk actions, row actions
- Add JSDoc comments for better code documentation"
```

---

## Task 3: Add CSS for Row Actions Accessibility

**Files:**
- Modify: `assets/css/pages/bookings.css`

**Step 1: Add accessibility styles**

Add to `assets/css/pages/bookings.css`:

```css
/* Row Actions - Accessibility Improvements */
.row-actions a {
    text-decoration: none;
    transition: color 0.15s ease;
}

.row-actions a:hover {
    text-decoration: underline;
}

.row-actions a:focus {
    outline: 2px solid var(--wp-admin-blue);
    outline-offset: 2px;
    border-radius: 2px;
}

.row-actions a[role="button"] {
    cursor: pointer;
}

.row-actions a[role="button"]:focus {
    outline: 2px solid var(--wp-admin-blue);
    outline-offset: 2px;
}

/* Delete action styling */
.row-actions .delete a {
    color: #a02830;
}

.row-actions .delete a:hover {
    color: #8b1f24;
}

.row-actions .status a {
    color: #0073aa;
}

.row-actions .status a:hover {
    color: #005a87;
}
```

**Step 2: Verify styles apply**

Check both pages visually:
- Row action links should have hover underline
- Delete link should be red
- Status links should be blue
- Focus outline should appear when tabbing

**Step 3: Commit**

```bash
git add assets/css/pages/bookings.css
git commit -m "style: add accessibility styles for row action links

- Add focus outlines for keyboard navigation
- Add color transitions on hover
- Distinguish status links (blue) from delete links (red)
- Add proper visual feedback for button-like links"
```

---

## Task 4: Add Accessibility Labels to Checkboxes

**Files:**
- Modify: `src/Admin/Bookings/BookingsRenderer.php`

**Step 1: Update checkbox markup**

In `src/Admin/Bookings/BookingsRenderer.php`, update the `renderTableHeader()` method (lines 192-207):

**Replace:**
```php
<td class="manage-column column-cb check-column">
    <input type="checkbox" id="cb-select-all" />
</td>
```

**With:**
```php
<td class="manage-column column-cb check-column">
    <label for="cb-select-all" class="screen-reader-text">
        <?php esc_html_e('Vybrat všechny rezervace', 'call-scheduler'); ?>
    </label>
    <input type="checkbox" id="cb-select-all" />
</td>
```

Also update the table body checkboxes in `renderTableRow()` method (lines 212-215):

**Replace:**
```php
<th scope="row" class="check-column">
    <input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr($booking->id); ?>" />
</th>
```

**With:**
```php
<th scope="row" class="check-column">
    <label for="booking_<?php echo esc_attr($booking->id); ?>" class="screen-reader-text">
        <?php esc_attr_e('Vybrat tuto rezervaci', 'call-scheduler'); ?>
    </label>
    <input type="checkbox"
           id="booking_<?php echo esc_attr($booking->id); ?>"
           name="booking_ids[]"
           value="<?php echo esc_attr($booking->id); ?>" />
</th>
```

**Step 2: Verify accessibility**

Use screen reader (or accessibility inspector):
- Checkboxes should now have proper labels
- "Select all bookings" for select-all checkbox
- "Select this booking" for individual checkboxes

**Step 3: Commit**

```bash
git add src/Admin/Bookings/BookingsRenderer.php
git commit -m "a11y: add screen reader labels to booking checkboxes

- Add descriptive labels for select-all checkbox
- Add individual labels for each booking checkbox with ID
- Improve accessibility for screen reader users"
```

---

## Task 5: Update Dashboard to Use Same Row Actions

**Files:**
- Modify: `src/Admin/Dashboard/DashboardRenderer.php` (already done in Task 1, verify here)

**Step 1: Verify RowActionsRenderer is used**

Check `src/Admin/Dashboard/DashboardRenderer.php` line ~250:

Should show:
```php
<td>
    <strong><?php echo esc_html($booking->customer_name); ?></strong>
    <?php echo RowActionsRenderer::render((int) $booking->id, $booking->status); ?>
</td>
```

If not, update it to use RowActionsRenderer as shown in Task 1, Step 3.

**Step 2: Verify it works**

- Dashboard page should show status change and delete links
- Links should have proper ARIA labels
- Click a link - should submit form

**Step 3: No new commit needed**

This was already done in Task 1.

---

## Task 6: Test Accessibility and Functionality

**Step 1: Manual Testing Checklist**

Test on both Bookings and Dashboard pages:

**Keyboard Navigation:**
- [ ] Tab to each row action link
- [ ] Links highlight with focus outline
- [ ] Press Enter on a link - action executes
- [ ] Press Space on a link - action executes

**Screen Reader (use NVDA, JAWS, or browser tools):**
- [ ] "Status change to Confirmed, button" or similar
- [ ] "Delete this booking, button" or similar
- [ ] "Select all bookings, checkbox" for select-all
- [ ] "Select this booking, checkbox" for individual

**Mouse:**
- [ ] Click status link - form submits
- [ ] Click delete link - confirmation appears
- [ ] Confirmation works as expected
- [ ] Page reloads with updated status

**Visual:**
- [ ] Focus outline appears when tabbing
- [ ] Hover effects visible
- [ ] Status links are blue, delete link is red
- [ ] Row actions properly formatted

**Step 2: Test the FormSubmissionHelper**

Open browser DevTools Console and run:
```javascript
// Test status change
csChangeStatus(1, 'confirmed');

// Test delete
csDeleteBooking(1);
```

Both should create forms and submit (after confirmation for delete).

**Step 3: Commit test results**

```bash
git commit --allow-empty -m "test: verify accessibility and functionality improvements

Tested on:
- Bookings page ✓
- Dashboard page ✓

Keyboard navigation: ✓
Screen reader support: ✓
Form submission: ✓
Confirmation dialogs: ✓

All improvements working as expected."
```

---

## Summary of Changes

| File | Change | Impact |
|------|--------|--------|
| `RowActionsRenderer.php` | NEW | Eliminates duplicate PHP status rendering code |
| `BookingsRenderer.php` | Refactored | Uses RowActionsRenderer, adds ARIA labels |
| `DashboardRenderer.php` | Refactored | Uses RowActionsRenderer, cleaner code |
| `admin-bookings.js` | Refactored | FormSubmissionHelper consolidates logic, adds keyboard support |
| `bookings.css` | Enhanced | Accessibility styles for focus, colors, transitions |

**Total LOC Reduction:**
- BookingsRenderer: ~20 lines removed (now uses component)
- DashboardRenderer: ~30 lines removed (now uses component)
- admin-bookings.js: ~20 lines removed (shared logic)

**Accessibility Improvements:**
- ✓ ARIA labels on status/delete links
- ✓ Keyboard navigation (Enter/Space support)
- ✓ Screen reader labels on checkboxes
- ✓ Focus outlines
- ✓ Semantic role attributes
- ✓ Tab order improvements

---

Plan complete and saved to `docs/plans/2026-01-09-refactor-duplication-accessibility.md`.

**Two execution options:**

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review code, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution

**Which approach do you prefer?**