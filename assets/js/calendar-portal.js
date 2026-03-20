var MYVH_Calendar = (function() {

    var api = null;

    // ─────────────────────────────
    // UI Controls (view + nav)
    // ─────────────────────────────
    function bindControls() {

        const dayBtn   = document.getElementById('myvh-day');
        const weekBtn  = document.getElementById('myvh-week');
        const monthBtn = document.getElementById('myvh-month');

        const nextBtn  = document.getElementById('myvh-next');
        const prevBtn  = document.getElementById('myvh-prev');
        const todayBtn = document.getElementById('myvh-today');

        if (dayBtn)   dayBtn.addEventListener('click', () => api.setView('Day'));
        if (weekBtn)  weekBtn.addEventListener('click', () => api.setView('Week'));
        if (monthBtn) monthBtn.addEventListener('click', () => api.setView('Month'));

        if (nextBtn)  nextBtn.addEventListener('click', () => api.next());
        if (prevBtn)  prevBtn.addEventListener('click', () => api.prev());
        if (todayBtn) todayBtn.addEventListener('click', () => api.today());
    }

    // ─────────────────────────────
    // Init
    // ─────────────────────────────
    function init() {

        api = MYVH_CalendarCore.init("myvh-calendar", {

            context:    "portal",
            ajax_url:    myvhCal.ajax_url,
            nonce:      myvhCal.nonce,

            editable:   false,
            selectable: false,
            readOnly:   false,

            onEventClick: function(args) {
                const id = args.e.id ? args.e.id() : args.e.data.id;
                window.location.href = '/dashboard/?tab=view-booking&id=' + id;
            }
        });

        bindControls();
    }

    document.addEventListener("DOMContentLoaded", function() {

        const modal = MYVH_BookingModal;

        modal.init({
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,

            // Restricted loaders
            loadRooms: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_calendar_rooms&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadCustomers: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_my_customer&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadOrganisations: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_my_organisations&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            // 🔐 Lock fields
            lockCustomer: true,
            lockOrganisation: true,

            onSuccess: () => api.reload()
        });

        api = MYVH_CalendarCore.init("myvh-calendar", {
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,
            selectable: true,
            editable: false,

            onTimeRangeSelected: function(args) {

                modal.open({
                    start: args.start.toString(),
                    end: args.end.toString(),

                    // 👇 Passed from backend via wp_localize_script
                    customer_id: MYVH.currentCustomerId,
                    organisation_id: MYVH.defaultOrganisationId
                });

                api.calendar.clearSelection();
            }
        });

    });

    return {
        init: init
    };

})();
