window.MYVH_CalendarCore = (function () {

    let calendar = null;
    let scheduler = null;
    let currentView = "Week";

    function destroy() {
        if (calendar) {
            calendar.dispose();
            calendar = null;
        }
        if (scheduler) {
            scheduler.dispose();
            scheduler = null;
        }

        const container = document.getElementById("myvh-calendar");
        if (container) {
            while (container.firstChild) {
                container.removeChild(container.firstChild);
            }
        }
    }

    // ───────────────────────────────────────────────
    // EVENT LOADING (shared)
    // ───────────────────────────────────────────────
    function loadEvents(ajax_url, nonce) {

        if (calendar) {
            const start = calendar.visibleStart();
            const end   = calendar.visibleEnd();

            const url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                      + `&start=${start.toString()}&end=${end.toString()}`;

            calendar.events.load(url);
        }

        if (scheduler) {
            const start = scheduler.startDate;
            const end   = scheduler.startDate.addDays(scheduler.days);

            const url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                      + `&start=${start.toString()}&end=${end.toString()}`;

            scheduler.events.load(url);
        }
    }

    // ───────────────────────────────────────────────
    // CALENDAR (Day/Week)
    // ───────────────────────────────────────────────
    function createCalendar(containerId, opts) {

        destroy();

        calendar = new DayPilot.Calendar(containerId);
        calendar.viewType = opts.view;
        calendar.startDate = DayPilot.Date.today();

        calendar.eventMoveHandling = opts.editable ? "Update" : "Disabled";
        calendar.eventResizeHandling = opts.editable ? "Update" : "Disabled";
        calendar.timeRangeSelectedHandling = opts.selectable ? "Enabled" : "Disabled";

        calendar.onEventClick = args => opts.onEventClick?.(args);
        calendar.onEventMoved = args => opts.onEventMoved?.(args);
        calendar.onEventResized = args => opts.onEventResized?.(args);
        calendar.onTimeRangeSelected = args => opts.onTimeRangeSelected?.(args);

        calendar.onViewChange = () => loadEvents(opts.ajax_url, opts.nonce);

        calendar.init();
        loadEvents(opts.ajax_url, opts.nonce);
    }

    // ───────────────────────────────────────────────
    // SCHEDULER (Rooms / Month)
    // ───────────────────────────────────────────────
    function createScheduler(containerId, opts, mode) {

        destroy();

        const today = DayPilot.Date.today();
        let startDate, days;

        if (mode === "Month") {
            startDate = today.firstDayOfMonth();
            days = startDate.daysInMonth();
        } else {
            // Rooms view = week grid
            startDate = today.firstDayOfWeek();
            days = 7;
        }

        scheduler = new DayPilot.Scheduler(containerId, {
            scale: "Day",
            startDate,
            days,
            eventResourceField: "resource",
            timeHeaders: [
                { groupBy: "Month" },
                { groupBy: "Day", format: "d" }
            ],
            timeRangeSelectedHandling: opts.selectable ? "Enabled" : "Disabled",
            onEventClick: args => opts.onEventClick?.(args),
            onEventMoved: args => opts.onEventMoved?.(args),
            onEventResized: args => opts.onEventResized?.(args),
            onTimeRangeSelected: args => opts.onTimeRangeSelected?.(args)
        });

        // Load rooms first
        fetch(`${opts.ajax_url}?action=myvh_calendar_rooms&nonce=${opts.nonce}`)
            .then(r => r.json())
            .then(res => {

                const rooms = Array.isArray(res?.data)
                    ? res.data
                    : Object.values(res?.data || res || {});

                scheduler.resources = rooms;

                scheduler.init();
                loadEvents(opts.ajax_url, opts.nonce);
            })
            .catch(err => {
                console.error("Failed to load rooms", err);
        });
    }

    // ───────────────────────────────────────────────
    // PUBLIC API
    // ───────────────────────────────────────────────
    function init(containerId, opts) {

        // default view
        createCalendar(containerId, { ...opts, view: "Week" });

        return {

            setView(view) {
                currentView = view;

                if (view === "Day" || view === "Week") {
                    createCalendar(containerId, { ...opts, view });
                }

                if (view === "Rooms") {
                    createScheduler(containerId, opts, "Rooms");
                }

                if (view === "Month") {
                    createScheduler(containerId, opts, "Month");
                }
            },

            next() {
                if (calendar) {
                    calendar.startDate = calendar.startDate.addDays(
                        currentView === "Day" ? 1 : 7
                    );
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce);
                }

                if (scheduler) {
                    scheduler.startDate = scheduler.startDate.addDays(
                        currentView === "Month" ? scheduler.startDate.daysInMonth() : 7
                    );
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce);
                }
            },

            prev() {
                if (calendar) {
                    calendar.startDate = calendar.startDate.addDays(
                        currentView === "Day" ? -1 : -7
                    );
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce);
                }

                if (scheduler) {
                    scheduler.startDate = scheduler.startDate.addDays(
                        currentView === "Month" ? -scheduler.startDate.daysInMonth() : -7
                    );
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce);
                }
            },

            today() {
                if (calendar) {
                    calendar.startDate = DayPilot.Date.today();
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce);
                }

                if (scheduler) {
                    scheduler.startDate = DayPilot.Date.today().firstDayOfMonth();
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce);
                }
            },

            reload() {
                loadEvents(opts.ajax_url, opts.nonce);
            }
        };
    }

    return {
            init,
           };

})();