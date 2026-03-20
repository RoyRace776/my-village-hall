var MYVH_CalendarAdmin = (function() {

    var api = null;

    function setActiveViewButton(view) {
        document.querySelectorAll('.myvh-view-btn').forEach(function(button) {
            button.classList.toggle('active', button.dataset.view === view);
        });
    }

    function getStateFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const view = params.get('cal_view');
        const start = params.get('cal_start');

        if (!view && !start) {
            return null;
        }

        return { view, start };
    }

    function buildCalendarReturnUrl() {
        const url = new URL(window.location.href);
        const state = api?.getState?.();

        url.searchParams.set('page', 'myvh-calendar');

        if (state?.view) {
            url.searchParams.set('cal_view', state.view);
        }

        if (state?.start) {
            url.searchParams.set('cal_start', state.start);
        }

        return url.toString();
    }

    // ─────────────────────────────
    // UI Controls (view + nav)
    // ─────────────────────────────
    function bindControls() {

        const roomsBtn = document.getElementById('myvh_rooms');
        const dayBtn   = document.getElementById('myvh-day');
        const weekBtn  = document.getElementById('myvh-week');
        const monthBtn = document.getElementById('myvh-month');

        const nextBtn  = document.getElementById('myvh-next');
        const prevBtn  = document.getElementById('myvh-prev');
        const todayBtn = document.getElementById('myvh-today');

        if (roomsBtn) roomsBtn.addEventListener('click', () => {
            api.setView('Rooms');
            setActiveViewButton('Resources');
        });

        if (dayBtn) dayBtn.addEventListener('click', () => {
            api.setView('Day');
            setActiveViewButton('Day');
        });

        if (weekBtn) weekBtn.addEventListener('click', () => {
            api.setView('Week');
            setActiveViewButton('Week');
        });

        if (monthBtn) monthBtn.addEventListener('click', () => {
            api.setView('Month');
            setActiveViewButton('Month');
        });

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

            onClose: () => api?.clearSelection?.(),
            onSuccess: () => api.reload()
        });

        // IMPORTANT: use initCalendar (alias to init)
        const restoredState = getStateFromUrl();

        api = MYVH_CalendarCore.init("myvh-calendar", {
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,
            editable: true,
            selectable: true,
            initialState: restoredState,
            headerDateFormat: myvhCal.headerDateFormat || null,

            onEventClick: function(args) {
                const id = args.e.id ? args.e.id() : args.e.data.id;
                const target = new URL('/wp-admin/admin.php', window.location.origin);
                target.searchParams.set('page', 'my-village-hall');
                target.searchParams.set('view', id);
                target.searchParams.set('return_to', buildCalendarReturnUrl());
                window.location.href = target.toString();
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

                api.clearSelection?.();
            }
        });

        bindControls();
        setActiveViewButton(restoredState?.view === 'Rooms' ? 'Resources' : (restoredState?.view || 'Week'));

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