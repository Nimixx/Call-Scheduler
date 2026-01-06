/**
 * Call Scheduler - Admin Bookings Page Scripts
 */

(function() {
    'use strict';

    // Select all checkboxes
    document.addEventListener('DOMContentLoaded', function() {
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

        // Sync bulk action selects
        var bulkAction1 = document.getElementById('bulk_action');
        var bulkAction2 = document.getElementById('bulk_action2');

        if (bulkAction1 && bulkAction2) {
            bulkAction1.addEventListener('change', function() {
                bulkAction2.value = this.value;
            });
            bulkAction2.addEventListener('change', function() {
                bulkAction1.value = this.value;
            });
        }

        // Confirm bulk delete
        var form = document.getElementById('cs-bookings-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                var action = document.getElementById('bulk_action').value;
                if (action === 'delete') {
                    var checked = document.querySelectorAll('input[name="booking_ids[]"]:checked');
                    if (checked.length > 0 && !confirm(csBookings.confirmBulkDelete)) {
                        e.preventDefault();
                    }
                }
            });
        }
    });

    // Change status function
    window.csChangeStatus = function(bookingId, newStatus) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        var fields = {
            'cs_bookings_nonce': document.querySelector('input[name="cs_bookings_nonce"]').value,
            'cs_action': 'change_status',
            'booking_id': bookingId,
            'new_status': newStatus
        };

        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    };

    // Delete booking function
    window.csDeleteBooking = function(bookingId) {
        if (!confirm(csBookings.confirmDelete)) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        var fields = {
            'cs_bookings_nonce': document.querySelector('input[name="cs_bookings_nonce"]').value,
            'cs_action': 'delete',
            'booking_id': bookingId
        };

        for (var key in fields) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = fields[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    };
})();
