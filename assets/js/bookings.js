











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
        var rows = document.querySelectorAll('#myvh-bookings-table tbody tr');
        rows.forEach(function(row) {
            var status = row.getAttribute('data-status');
            if (!status || checked.indexOf(status) !== -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
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
        bindStatusFilters();
    }

    return {
        init: init
    };

})();