











// Bookings list and actions for My Village Hall
var MYVH_Bookings = (function() {

    var initialized = false;

    /**
     * Bind accordion toggle logic for grouped bookings.
     * Clicking a group header toggles its children.
     */
    function bindAccordion() {
        document.addEventListener('click', function(e) {
            var header = e.target.closest('.myvh-group-header');
            if (!header) {
                return;
            }

            // Ignore clicks on links inside the group header.
            if (e.target.closest('a')) {
                return;
            }

            var group = header.getAttribute('data-group');
            header.classList.toggle('is-open');

            if (!group) {
                return;
            }

            var children = document.querySelector('.myvh-group-children[data-group="' + group + '"]');
            if (children) {
                children.classList.toggle('is-open');
            }
        });
    }

    /**
     * Bind actions for bookings, e.g. cancel button with confirmation.
     */
    function bindActions() {
        document.addEventListener('click', function(e) {
            var cancelBtn = e.target.closest('.myvh-cancel-booking');
            if (!cancelBtn) {
                return;
            }

            if (!window.confirm('Cancel this booking?')) {
                e.preventDefault();
                return;
            }
        });
    }

    /**
     * Initialize bookings logic (accordion, actions). Only runs once.
     */
    function init() {
        if (initialized) {
            return;
        }

        initialized = true;
        bindAccordion();
        bindActions();
    }

    return {
        init: init
    };

})();