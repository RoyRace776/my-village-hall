jQuery(function($) {

    // Initialize bookings list if present
    if ($('.myvh-bookings-list').length) {
        if (typeof Bookings !== 'undefined') {
            Bookings.init();
        }
    }

    // Initialize calendar if present
    if ($('#myvh-calendar').length) {
        if (typeof Calendar !== 'undefined') {
            Calendar.init();
        }
    }

    // ...add more dashboard logic here as needed...

});