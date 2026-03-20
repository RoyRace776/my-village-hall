var MYVH_Calendar = (function() {

    var api = null;
    var modal = null;

    function openSelectionModal(args) {
        if (!modal || !args) {
            return;
        }

        modal.open({
            start: args.start.toString(),
            end: args.end.toString(),
            customer_id: myvhCal.currentCustomerId || '',
            organisation_id: myvhCal.defaultOrganisationId || ''
        });

        api?.clearSelection?.();
    }

    function ensureSelectableRangeHandlers() {
        const cal = api?.calendar;
        if (cal) {
            cal.timeRangeSelectedHandling = 'Enabled';
            cal.onTimeRangeSelected = openSelectionModal;
            cal.update();
        }

        const sched = api?.scheduler;
        if (sched) {
            sched.timeRangeSelectedHandling = 'Enabled';
            sched.onTimeRangeSelected = openSelectionModal;
            sched.update();
        }
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
            ensureSelectableRangeHandlers();
        });

        if (schedulerModeBtn) schedulerModeBtn.addEventListener('click', () => {
            api.setMode('Scheduler');
            setActiveModeButton('Scheduler');
            ensureSelectableRangeHandlers();
        });

        if (dayBtn) dayBtn.addEventListener('click', () => {
            api.setDetail('Day');
            setActiveDetailButton('Day');
            ensureSelectableRangeHandlers();
        });

        if (weekBtn) weekBtn.addEventListener('click', () => {
            api.setDetail('Week');
            setActiveDetailButton('Week');
            ensureSelectableRangeHandlers();
        });

        if (monthBtn) monthBtn.addEventListener('click', () => {
            api.setDetail('Month');
            setActiveDetailButton('Month');
            ensureSelectableRangeHandlers();
        });

        if (nextBtn)  nextBtn.addEventListener('click', () => api.next());
        if (prevBtn)  prevBtn.addEventListener('click', () => api.prev());
        if (todayBtn) todayBtn.addEventListener('click', () => api.today());
    }

    // ─────────────────────────────
    // Init
    // ─────────────────────────────
    function init() {

        modal = MYVH_BookingModal;

        modal.init({
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,
            context: 'portal',

            loadRooms: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_calendar_rooms&nonce=${myvhCal.nonce}&context=portal`)
                    .then(r => r.json()),

            loadCustomers: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_customers&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadOrganisations: (customerId) =>
                fetch(`${myvhCal.ajax_url}?action=myvh_organisations&nonce=${myvhCal.nonce}&customer_id=${encodeURIComponent(customerId || '')}`)
                    .then(r => r.json()),

            lockCustomer: true,
            lockOrganisation: true,
            hideCustomer: true,

            onClose: () => api?.clearSelection?.(),
            onSuccess: () => api.reload()
        });

        api = MYVH_CalendarCore.init("myvh-calendar", {

            context:    "portal",
            ajax_url:   myvhCal.ajax_url,
            nonce:      myvhCal.nonce,
            headerDateFormat: myvhCal.headerDateFormat || null,
            visibleStartHour: Number(myvhCal.visibleStartHour || 0),
            visibleEndHour: Number(myvhCal.visibleEndHour || 24),

            editable:   false,
            selectable: true,
            readOnly:   true,
            initialMode: 'Calendar',
            initialDetail: 'Week',

            onEventClick: function(args) {
                const id = args.e.id ? args.e.id() : args.e.data.id;
                window.location.href = '/dashboard/?tab=view-booking&id=' + id;
            },

            onTimeRangeSelected: openSelectionModal
        });

        bindControls();
        const state = api.getState?.() || {};
        setActiveModeButton(state.mode || 'Calendar');
        setActiveDetailButton(state.detail || 'Week');
        ensureSelectableRangeHandlers();
    }

    return {
        init: init
    };

})();
