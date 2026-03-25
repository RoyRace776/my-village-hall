jQuery(function($) {

    // Initialize bookings list if present
    if ($('.myvh-bookings-list').length) {
        if (typeof MYVH_Bookings !== 'undefined') {
            MYVH_Bookings.init();
        }
    }

    // Initialize calendar if present
    if ($('#myvh-calendar').length) {
        if (typeof MYVH_Calendar !== 'undefined') {
            MYVH_Calendar.init();
        }
    }

    // ...add more dashboard logic here as needed...

});