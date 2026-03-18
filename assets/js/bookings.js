var MYVH_Bookings = (function() {

    function bindAccordion() {

        $(document).on('click', '.myvh-group-header', function(e) {

            // Ignore clicks on links
            if ($(e.target).is('a')) return;

            var group = $(this).data('group');

            $(this).toggleClass('is-open');

            $('.myvh-group-children[data-group="' + group + '"]')
                .toggleClass('is-open');
        });
    }

    function bindActions() {

        // Cancel booking (AJAX-ready later)
        $(document).on('click', '.myvh-cancel-booking', function(e) {

            if (!confirm('Cancel this booking?')) {
                e.preventDefault();
                return;
            }

        });
    }

    function init() {
        bindAccordion();
        bindActions();
    }

    return {
        init: init
    };

})();