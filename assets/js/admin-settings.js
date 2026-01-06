/**
 * Call Scheduler - Admin Settings Page
 *
 * Handles toggle switches and conditional field visibility
 */
(function() {
    'use strict';

    /**
     * Initialize settings page functionality
     */
    function init() {
        initToggleHandlers();
    }

    /**
     * Set up toggle switch handlers for conditional fields
     */
    function initToggleHandlers() {
        const toggles = document.querySelectorAll('.cs-module-toggle');

        toggles.forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                handleToggleChange(this);
            });
        });
    }

    /**
     * Handle toggle change - show/hide conditional fields
     *
     * @param {HTMLInputElement} toggle - The toggle checkbox element
     */
    function handleToggleChange(toggle) {
        const toggleName = toggle.dataset.toggle;
        const conditionalFields = document.querySelector('[data-depends-on="' + toggleName + '"]');

        if (!conditionalFields) {
            return;
        }

        if (toggle.checked) {
            conditionalFields.style.display = '';
            // Focus first input in the revealed section
            const firstInput = conditionalFields.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                }, 100);
            }
        } else {
            conditionalFields.style.display = 'none';
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
