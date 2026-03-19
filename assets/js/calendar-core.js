window.MYVH_CalendarCore = {
    initCalendar: function(containerId, options = {}) {

        const calendar = new DayPilot.Calendar(containerId);

        // ─────────────────────────────
        // Initial Setup
        // ─────────────────────────────
        calendar.viewType = options.view || "Week";
        calendar.startDate = DayPilot.Date.today();

        // Feature flags
        calendar.eventMoveHandling     = options.editable   ? "Update"  : "Disabled";
        calendar.eventResizeHandling   = options.editable   ? "Update"  : "Disabled";
        calendar.timeRangeSelectedHandling = options.selectable ? "Enabled" : "Disabled";

        if (options.readOnly) {
            calendar.eventClickHandling = "Disabled";
        }

        // ─────────────────────────────
        // Event Loading (centralised)
        // ─────────────────────────────
        function loadEvents() {
            const start = calendar.visibleStart();
            const end   = calendar.visibleEnd();

            const url = `${options.ajax_url}?action=myvh_calendar_events`
                            + `&nonce=${options.nonce}`
                            + `&start=${start.toString()}`
                            + `&end=${end.toString()}`;

            calendar.events.load(url);
        }

        // ─────────────────────────────
        // Hooks (delegated to callers)
        // ─────────────────────────────
        calendar.onEventClick = (args) => {
            options.onEventClick?.(args);
        };

        calendar.onEventMoved = (args) => {
            options.onEventMoved?.(args);
        };

        calendar.onEventResized = (args) => {
            options.onEventResized?.(args);
        };

        calendar.onTimeRangeSelected = (args) => {
            options.onTimeRangeSelected?.(args);
        };

        // Reload events when view/date changes
        calendar.onViewChange = loadEvents;

        // ─────────────────────────────
        // Init
        // ─────────────────────────────
        calendar.init();
        loadEvents();

        // ─────────────────────────────
        // Navigation API
        // ─────────────────────────────
        function setView(view) {

            if (view === "Month") {

                const firstDay = calendar.startDate.firstDayOfMonth();
                const start    = firstDay.firstDayOfWeek();
                const lastDay  = firstDay.addMonths(1).addDays(-1);
                const end      = firstDay.addDays(6);
                const totalDays = Math.ceil((end.getTime() - start.getTime()) / (24 * 60 * 60 * 1000)) + 1;

                calendar.viewType  = "Days";
                calendar.startDate = start;
                calendar.days      = totalDays;

            } else {

                calendar.viewType = view;
                calendar.days     = view === "Day" ? 1 : 7;
            }

            calendar.update();
            loadEvents();
        }

        function next() {
            calendar.startDate = calendar.startDate.addDays(
                calendar.viewType === "Day"  ?  1 :
                calendar.viewType === "Week" ?  7 : 31
            );
            calendar.update();
            loadEvents();
        }

        function prev() {
            calendar.startDate = calendar.startDate.addDays(
                calendar.viewType === "Day"  ?  -1 :
                calendar.viewType === "Week" ?  -7 : -31
            );
            calendar.update();
            loadEvents();
        }

        function today() {
            calendar.startDate = DayPilot.Date.today();
            calendar.update();
            loadEvents();
        }

        // ─────────────────────────────
        // Public API
        // ─────────────────────────────
        return {
            calendar,
            setView,
            next,
            prev,
            today,
            reload: loadEvents
        };
    }
};
