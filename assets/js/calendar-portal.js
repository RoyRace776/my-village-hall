var MYVH_Calendar = (function() {

    var api = null;
    var modal = null;
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

        const isSmallViewport = window.matchMedia('(max-width: 782px)').matches;
        const visibleMonths = isSmallViewport ? 1 : 3;

        nav = new DayPilot.Navigator('myvh-calendar-nav-picker', {
            showMonths: visibleMonths,
            skipMonths: visibleMonths,
            selectMode: 'Week',
            onTimeRangeSelected: function(args) {
                if (suppressNavSelect) {
                    return;
                }

                const state = api?.getState?.() || {};
                api?.setModeAndDetail?.(state.mode || 'Calendar', state.detail || 'Week', args.day);
                setActiveModeButton(state.mode || 'Calendar');
                setActiveDetailButton(state.detail || 'Week');
                ensureSelectableRangeHandlers();
                syncNavigator();
            }
        });

        nav.init();
        syncNavigator();
    }

    function openSelectionModal(args) {
        if (!modal || !args) {
            return;
        }

        const isClientAdmin = !!Number(myvhCal.isClientAdmin || 0);

        modal.open({
            start: args.start.toString(),
            end: args.end.toString(),
            customer_id: isClientAdmin ? '' : (myvhCal.currentCustomerId || ''),
            organisation_id: isClientAdmin ? '' : (myvhCal.defaultOrganisationId || '')
        });

        api?.clearSelection?.();
    }

    function openReadOnlyModal(args) {
        if (!modal || !args || !args.e) {
            return;
        }

        modal.open({
            args: args.e.data,
            viewOnly: true
        });
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
            syncNavigator();
        });

        if (schedulerModeBtn) schedulerModeBtn.addEventListener('click', () => {
            api.setMode('Scheduler');
            setActiveModeButton('Scheduler');
            ensureSelectableRangeHandlers();
            syncNavigator();
        });

        if (dayBtn) dayBtn.addEventListener('click', () => {
            api.setDetail('Day');
            setActiveDetailButton('Day');
            ensureSelectableRangeHandlers();
            syncNavigator();
        });

        if (weekBtn) weekBtn.addEventListener('click', () => {
            api.setDetail('Week');
            setActiveDetailButton('Week');
            ensureSelectableRangeHandlers();
            syncNavigator();
        });

        if (monthBtn) monthBtn.addEventListener('click', () => {
            api.setDetail('Month');
            setActiveDetailButton('Month');
            ensureSelectableRangeHandlers();
            syncNavigator();
        });

        if (nextBtn)  nextBtn.addEventListener('click', () => { api.next(); syncNavigator(); });
        if (prevBtn)  prevBtn.addEventListener('click', () => { api.prev(); syncNavigator(); });
        if (todayBtn) todayBtn.addEventListener('click', () => { api.today(); syncNavigator(); });
    }

    // ─────────────────────────────
    // Init
    // ─────────────────────────────
    function init() {

        modal = MYVH_BookingModal;
        const isClientAdmin = !!Number(myvhCal.isClientAdmin || 0);

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

            lockCustomer: !isClientAdmin,
            lockOrganisation: !isClientAdmin,
            hideCustomer: !isClientAdmin,
            lockAddonPrices: true,
            requireOrganisation: true,

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
            schedulerOrientation: String(myvhCal.schedulerOrientation || 'horizontal').toLowerCase(),

            editable:   false,
            selectable: true,
            readOnly:   true,
            initialMode: 'Calendar',
            initialDetail: 'Week',

            // onEventClick removed: clicking events no longer shows booking

            onTimeRangeSelected: openSelectionModal,
            onEventClick : openReadOnlyModal
        });

        bindControls();
        const state = api.getState?.() || {};
        setActiveModeButton(state.mode || 'Calendar');
        setActiveDetailButton(state.detail || 'Week');
        ensureSelectableRangeHandlers();
        initNavigator();
    }

    return {
        init: init
    };

})();
