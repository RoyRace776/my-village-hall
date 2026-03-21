var MYVH_CalendarAdmin = (function() {

    var api = null;
    var nav = null;
    var suppressNavSelect = false;

    function getNavSelectMode(detail) {
        if (detail === 'Day') {
            return 'Day';
        }
        if (detail === 'Month') {
            return 'Month';
        }
        return 'Week';
    }

    function syncNavigator() {
        if (!nav || !api?.getState) {
            return;
        }

        const state = api.getState();
        if (!state?.start) {
            return;
        }

        suppressNavSelect = true;
        try {
            nav.selectMode = getNavSelectMode(state.detail || 'Week');
            nav.select(new DayPilot.Date(state.start));
            nav.update();
        } finally {
            suppressNavSelect = false;
        }
    }

    function initNavigator() {
        const navEl = document.getElementById('myvh-calendar-nav-picker');
        if (!navEl || typeof DayPilot === 'undefined') {
            return;
        }

        nav = new DayPilot.Navigator('myvh-calendar-nav-picker', {
            showMonths: 3,
            skipMonths: 3,
            selectMode: 'Week',
            onTimeRangeSelected: function(args) {
                if (suppressNavSelect) {
                    return;
                }

                const state = api?.getState?.() || {};
                api?.setModeAndDetail?.(state.mode || 'Calendar', state.detail || 'Week', args.day);
                setActiveModeButton(state.mode || 'Calendar');
                setActiveDetailButton(state.detail || 'Week');
                syncNavigator();
            }
        });

        nav.init();
        syncNavigator();
    }

    function setActiveModeButton(mode) {
        document.querySelectorAll('.myvh-mode-btn').forEach(function(button) {
            button.classList.toggle('active', button.dataset.mode === mode);
        });
    }

    function setActiveDetailButton(detail) {
        document.querySelectorAll('.myvh-detail-btn').forEach(function(button) {
            button.classList.toggle('active', button.dataset.view === detail);
        });
    }

    function getStateFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const mode = params.get('cal_mode');
        const detail = params.get('cal_detail');
        const view = params.get('cal_view');
        const start = params.get('cal_start');

        if (!mode && !detail && !view && !start) {
            return null;
        }

        if (!mode && !detail && view) {
            return {
                mode: view === 'Rooms' ? 'Scheduler' : 'Calendar',
                detail: view === 'Rooms' ? 'Week' : view,
                start,
            };
        }

        return { mode, detail, start };
    }

    function buildCalendarReturnUrl() {
        const url = new URL(window.location.href);
        const state = api?.getState?.();

        url.searchParams.set('page', 'myvh-calendar');

        if (state?.view) {
            url.searchParams.set('cal_view', state.view);
        } else {
            url.searchParams.delete('cal_view');
        }

        if (state?.mode) {
            url.searchParams.set('cal_mode', state.mode);
        } else {
            url.searchParams.delete('cal_mode');
        }

        if (state?.detail) {
            url.searchParams.set('cal_detail', state.detail);
        } else {
            url.searchParams.delete('cal_detail');
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

        const calendarModeBtn = document.getElementById('myvh-mode-calendar');
        const schedulerModeBtn = document.getElementById('myvh-mode-scheduler');
        const dayBtn   = document.getElementById('myvh-day');
        const weekBtn  = document.getElementById('myvh-week');
        const monthBtn = document.getElementById('myvh-month');

        const nextBtn  = document.getElementById('myvh-next');
        const prevBtn  = document.getElementById('myvh-prev');
        const todayBtn = document.getElementById('myvh-today');

        if (calendarModeBtn) calendarModeBtn.addEventListener('click', () => {
            api.setMode('Calendar');
            setActiveModeButton('Calendar');
            syncNavigator();
        });

        if (schedulerModeBtn) schedulerModeBtn.addEventListener('click', () => {
            api.setMode('Scheduler');
            setActiveModeButton('Scheduler');
            syncNavigator();
        });

        if (dayBtn) dayBtn.addEventListener('click', () => {
            api.setDetail('Day');
            setActiveDetailButton('Day');
            syncNavigator();
        });

        if (weekBtn) weekBtn.addEventListener('click', () => {
            api.setDetail('Week');
            setActiveDetailButton('Week');
            syncNavigator();
        });

        if (monthBtn) monthBtn.addEventListener('click', () => {
            api.setDetail('Month');
            setActiveDetailButton('Month');
            syncNavigator();
        });

        if (nextBtn)  nextBtn.addEventListener('click', () => { api.next(); syncNavigator(); });
        if (prevBtn)  prevBtn.addEventListener('click', () => { api.prev(); syncNavigator(); });
        if (todayBtn) todayBtn.addEventListener('click', () => { api.today(); syncNavigator(); });
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
            initialMode: restoredState?.mode || 'Calendar',
            initialDetail: restoredState?.detail || 'Week',
            headerDateFormat: myvhCal.headerDateFormat || null,
            visibleStartHour: Number(myvhCal.visibleStartHour || 0),
            visibleEndHour: Number(myvhCal.visibleEndHour || 24),

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
        const state = api.getState?.() || {};
        setActiveModeButton(state.mode || 'Calendar');
        setActiveDetailButton(state.detail || 'Week');
        initNavigator();

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