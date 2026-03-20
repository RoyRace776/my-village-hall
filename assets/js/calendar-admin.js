var MYVH_CalendarAdmin = (function() {

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
    // AJAX Helpers
    // ─────────────────────────────
    function updateEvent(data) {
        return jQuery.post(myvhCal.ajax_url, {
            action: 'myvh_update_event',
            nonce:  myvhCal.nonce,
            ...data
        });
    }

    function createEvent(data) {
        return jQuery.post(myvhCal.ajax_url, {
            action: 'myvh_create_event',
            nonce:  myvhCal.nonce,
            ...data
        });
    }

    // ─────────────────────────────
    // Init
    // ─────────────────────────────
    function init() {

        const modal = MYVH_BookingModal;

        modal.init({
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,

            loadRooms: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_calendar_rooms&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadCustomers: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_customers&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadOrganisations: (customerId) =>
                fetch(`${myvhCal.ajax_url}?action=myvh_organisations&nonce=${myvhCal.nonce}&customer_id=${encodeURIComponent(customerId || '')}`)
                    .then(r => r.json()),

            onSuccess: () => api.reload()
        });

        // IMPORTANT: use initCalendar (alias to init)
        api = MYVH_CalendarCore.init("myvh-calendar", {
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,
            editable: true,
            selectable: true,

            onEventClick: function(args) {
                const id = args.e.id ? args.e.id() : args.e.data.id;
                window.location.href = '/wp-admin/admin.php?page=myvh-bookings&action=edit&id=' + id;
            },

            onEventMoved: function(args) {
                updateEvent({
                    id:    args.e.id ? args.e.id() : args.e.data.id,
                    start: args.newStart.toString(),
                    end:   args.newEnd.toString()
                }).fail(function() {
                    alert('Failed to move event');
                    api.reload();
                });
            },

            onEventResized: function(args) {
                updateEvent({
                    id:    args.e.id ? args.e.id() : args.e.data.id,
                    start: args.newStart.toString(),
                    end:   args.newEnd.toString()
                }).fail(function() {
                    alert('Failed to resize event');
                    api.reload();
                });
            },

            onTimeRangeSelected: function(args) {
                modal.open({
                    start: args.start.toString(),
                    end: args.end.toString()
                });

                api.calendar && api.calendar.clearSelection?.();
            }
        });

        bindControls();

        // Optional: expose for debugging
        window.myvhApi = api;
    }

    return {
        init: init
    };

})();

// SINGLE initialisation
document.addEventListener("DOMContentLoaded", function() {
    MYVH_CalendarAdmin.init();
});