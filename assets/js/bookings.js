var MYVH_Bookings = (function() {

    var initialized = false;

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