











// Bookings list and actions for My Village Hall

var Bookings = (function() {

    var initialized = false;

    /**
     * Filter bookings table rows by selected statuses.
     */
    function filterByStatus() {
        var checked = Array.from(document.querySelectorAll('.myvh-status-filter:checked')).map(function(cb) {
            return cb.value;
        });
        var rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
        rows.forEach(function(row) {
            var status = row.getAttribute('data-status');
            if (!status || checked.indexOf(status) !== -1) {
                row.classList.remove('myvh-hidden-by-filter');
            } else {
                row.classList.add('myvh-hidden-by-filter');
            }
        });
    }

    /**
     * Bind status filter checkboxes for client-side filtering.
     */
    function bindStatusFilters() {
        var boxes = document.querySelectorAll('.myvh-status-filter');
        boxes.forEach(function(cb) {
            cb.addEventListener('change', filterByStatus);
        });
        // Initial filter
        filterByStatus();
    }

    /**
     * Bind group header toggles for expanding/collapsing booking groups.
     */
    function bindGroupToggles() {
        document.addEventListener('click', function(e) {
            var header = e.target.closest('.myvh-booking-group-header');
            if (!header) {
                return;
            }

            // Ignore clicks on links inside the group header.
            if (e.target.closest('a')) {
                return;
            }

            var group = header.getAttribute('data-group');
            var groupToggle = header.querySelector('.myvh-group-toggle');
            var isOpen = !header.classList.contains('is-open');

            header.classList.toggle('is-open', isOpen);

            if (groupToggle) {
                groupToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            if (!group) {
                return;
            }

            var children = document.querySelectorAll('tr.myvh-recurring-child[data-group="' + group + '"]');
            children.forEach(function(row) {
                row.classList.toggle('is-open', isOpen);
            });

            if (e.target.closest('.myvh-group-toggle')) {
                e.preventDefault();
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
        bindGroupToggles();
        bindActions();
        bindStatusFilters();
    }

    return {
        init: init
    };

})();