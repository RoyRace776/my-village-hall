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

        api = MYVH_CalendarCore.initCalendar("myvh-calendar", {

            context:    "admin",
            ajaxUrl:    myvhCal.ajax_url,
            nonce:      myvhCal.nonce,

            editable:   true,
            selectable: true,
            readOnly:   false,

            // ───── Click = open/edit booking
            onEventClick: function(args) {
                const id = args.e.id();
                window.location.href = '/wp-admin/admin.php?page=myvh-bookings&action=edit&id=' + id;
            },

            // ───── Drag move
            onEventMoved: function(args) {
                updateEvent({
                    id:    args.e.id(),
                    start: args.newStart.toString(),
                    end:   args.newEnd.toString()
                }).fail(function() {
                    alert('Failed to move event');
                    api.reload();
                });
            },

            // ───── Resize
            onEventResized: function(args) {
                updateEvent({
                    id:    args.e.id(),
                    start: args.newStart.toString(),
                    end:   args.newEnd.toString()
                }).fail(function() {
                    alert('Failed to resize event');
                    api.reload();
                });
            },

            // ───── Create (drag select)
            onTimeRangeSelected: function(args) {

                const title = prompt("Booking title:");

                if (!title) {
                    api.calendar.clearSelection();
                    return;
                }

                createEvent({
                    start: args.start.toString(),
                    end:   args.end.toString(),
                    text:  title
                }).done(function() {
                    api.reload();
                }).fail(function() {
                    alert('Failed to create booking');
                });

                api.calendar.clearSelection();
            }
        });

        bindControls();
    }

    return {
        init: init
    };

})();

jQuery(function() {
    MYVH_CalendarAdmin.init();
});
