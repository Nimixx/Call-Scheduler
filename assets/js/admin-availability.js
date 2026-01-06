/**
 * Call Scheduler - Availability Page JavaScript
 */

(function() {
    'use strict';

    /**
     * Toggle weekdays (Mon-Fri)
     */
    window.csToggleWeekdays = function(enabled) {
        [1, 2, 3, 4, 5].forEach(day => {
            const checkbox = document.querySelector('.cs-day-checkbox[data-day="' + day + '"]');
            if (checkbox) {
                checkbox.checked = enabled;
                updateRowState(day);
            }
        });
    };

    /**
     * Toggle all days
     */
    window.csToggleAll = function(enabled) {
        document.querySelectorAll('.cs-day-checkbox').forEach(cb => {
            cb.checked = enabled;
            updateRowState(cb.dataset.day);
        });
    };

    /**
     * Set all time inputs to specific values
     */
    window.csSetAllTimes = function(start, end) {
        document.querySelectorAll('input[name*="[start_time]"]').forEach(input => {
            input.value = start;
        });
        document.querySelectorAll('input[name*="[end_time]"]').forEach(input => {
            input.value = end;
        });
        updateAllHours();
    };

    /**
     * Update row visual state based on checkbox
     */
    function updateRowState(day) {
        const row = document.querySelector('tr[data-day="' + day + '"]');
        const checkbox = row.querySelector('.cs-day-checkbox');

        if (checkbox.checked) {
            row.classList.remove('cs-row-disabled');
            row.classList.add('cs-row-enabled');
        } else {
            row.classList.remove('cs-row-enabled');
            row.classList.add('cs-row-disabled');
        }

        updateHours(day);
    }

    /**
     * Calculate and display hours for a day
     */
    function updateHours(day) {
        const row = document.querySelector('tr[data-day="' + day + '"]');
        const checkbox = row.querySelector('.cs-day-checkbox');
        const startInput = row.querySelector('input[name*="[start_time]"]');
        const endInput = row.querySelector('input[name*="[end_time]"]');
        const hoursDisplay = row.querySelector('.cs-hours-display');

        if (!checkbox.checked) {
            hoursDisplay.textContent = '-';
            return;
        }

        const start = startInput.value.split(':');
        const end = endInput.value.split(':');
        const startMinutes = parseInt(start[0]) * 60 + parseInt(start[1]);
        const endMinutes = parseInt(end[0]) * 60 + parseInt(end[1]);
        let diffMinutes = endMinutes - startMinutes;

        // Handle overnight shifts (end <= start means it wraps to next day)
        const isOvernight = diffMinutes <= 0;
        if (isOvernight) {
            diffMinutes += 1440; // Add 24 hours (24 * 60 minutes)
        }

        const hours = Math.floor(diffMinutes / 60);
        const minutes = diffMinutes % 60;

        const timeText = hours + 'h' + (minutes > 0 ? ' ' + minutes + 'm' : '');
        hoursDisplay.textContent = isOvernight ? timeText + ' (overnight)' : timeText;
    }

    /**
     * Update all hours displays
     */
    function updateAllHours() {
        [0, 1, 2, 3, 4, 5, 6].forEach(day => updateHours(day));
    }

    /**
     * Initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        // Checkbox changes
        document.querySelectorAll('.cs-day-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateRowState(this.dataset.day);
            });
        });

        // Time input changes
        document.querySelectorAll('.cs-time-input').forEach(input => {
            input.addEventListener('change', function() {
                updateHours(this.dataset.day);
            });
        });

        // Initialize hours display
        updateAllHours();
    });
})();
