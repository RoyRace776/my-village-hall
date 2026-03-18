jQuery(function($) {

    // Bookings page
    if ($('.myvh-bookings-list').length) {
        if (typeof MYVH_Bookings !== 'undefined') {
            MYVH_Bookings.init();
        }
    }

    // Calendar page
    if ($('#myvh-calendar').length) {
        if (typeof MYVH_Calendar !== 'undefined') {
            MYVH_Calendar.init();
        }
    }

});