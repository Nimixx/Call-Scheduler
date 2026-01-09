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
