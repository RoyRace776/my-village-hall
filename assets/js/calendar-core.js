window.MYVH_CalendarCore = (function () {

    let calendar = null;
    let scheduler = null;
    let currentView = "Week";

    function toDayPilotDate(value) {
        return value ? new DayPilot.Date(value) : null;
    }

    function getSchedulerStartDate(mode, value) {
        const date = toDayPilotDate(value) || DayPilot.Date.today();

        if (mode === "Month") {
            return date.firstDayOfMonth();
        }

        return date;
    }

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
    function loadEvents(ajax_url, nonce, context = "admin") {

        if (calendar) {
            const start = calendar.visibleStart();
            const end   = calendar.visibleEnd();

            const url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                      + `&context=${encodeURIComponent(context)}`
                      + `&start=${start.toString()}&end=${end.toString()}`;

            calendar.events.load(url);
        }

        if (scheduler) {
            const start = scheduler.startDate;
            const end   = scheduler.startDate.addDays(scheduler.days);

            const url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                      + `&context=${encodeURIComponent(context)}`
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
        calendar.startDate = toDayPilotDate(opts.startDate) || DayPilot.Date.today();
        if (opts.headerDateFormat) {
            calendar.headerDateFormat = opts.headerDateFormat;
        }

        calendar.eventMoveHandling = opts.editable ? "Update" : "Disabled";
        calendar.eventResizeHandling = opts.editable ? "Update" : "Disabled";
        calendar.timeRangeSelectedHandling = opts.selectable ? "Enabled" : "Disabled";

        calendar.onEventClick = args => opts.onEventClick?.(args);
        calendar.onEventMoved = args => opts.onEventMoved?.(args);
        calendar.onEventResized = args => opts.onEventResized?.(args);
        calendar.onTimeRangeSelected = args => opts.onTimeRangeSelected?.(args);

        calendar.onViewChange = () => loadEvents(opts.ajax_url, opts.nonce, opts.context);

        calendar.init();
        loadEvents(opts.ajax_url, opts.nonce, opts.context);
    }

    // ───────────────────────────────────────────────
    // SCHEDULER (Rooms / Month)
    // ───────────────────────────────────────────────
    function createScheduler(containerId, opts, mode) {

        destroy();

        let startDate, days;

        if (mode === "Month") {
            startDate = getSchedulerStartDate(mode, opts.startDate);
            days = startDate.daysInMonth();
        } else {
            // Rooms view = week grid
            startDate = getSchedulerStartDate(mode, opts.startDate);
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
                loadEvents(opts.ajax_url, opts.nonce, opts.context);
            })
            .catch(err => {
                console.error("Failed to load rooms", err);
        });
    }

    // ───────────────────────────────────────────────
    // PUBLIC API
    // ───────────────────────────────────────────────
    function init(containerId, opts) {

        const initialState = opts.initialState || null;
        const initialView = initialState?.view || "Week";
        const initialStart = initialState?.start || null;

        currentView = initialView;

        if (initialView === "Day" || initialView === "Week") {
            createCalendar(containerId, { ...opts, view: initialView, startDate: initialStart });
        }

        if (initialView === "Rooms") {
            createScheduler(containerId, { ...opts, startDate: initialStart }, "Rooms");
        }

        if (initialView === "Month") {
            createScheduler(containerId, { ...opts, startDate: initialStart }, "Month");
        }

        return {

            clearSelection() {
                calendar?.clearSelection?.();
                scheduler?.clearSelection?.();
            },

            get calendar() {
                return calendar;
            },

            get scheduler() {
                return scheduler;
            },

            getState() {
                return {
                    view: currentView,
                    start: calendar
                        ? calendar.startDate.toString()
                        : scheduler
                            ? scheduler.startDate.toString()
                            : ""
                };
            },

            restoreState(state) {
                if (!state) {
                    return;
                }

                const view = state.view || currentView;
                const start = state.start ? new DayPilot.Date(state.start) : null;

                if (view !== currentView) {
                    this.setView(view, start);
                    return;
                }

                if (!start) {
                    return;
                }

                if (calendar) {
                    calendar.startDate = start;
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    scheduler.startDate = view === "Month" ? start.firstDayOfMonth() : start;
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            setView(view, startDate = null) {
                currentView = view;

                if (view === "Day" || view === "Week") {
                    createCalendar(containerId, { ...opts, view, startDate });
                }

                if (view === "Rooms") {
                    createScheduler(containerId, { ...opts, startDate }, "Rooms");
                }

                if (view === "Month") {
                    createScheduler(containerId, { ...opts, startDate }, "Month");
                }
            },

            next() {
                if (calendar) {
                    calendar.startDate = calendar.startDate.addDays(
                        currentView === "Day" ? 1 : 7
                    );
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    scheduler.startDate = scheduler.startDate.addDays(
                        currentView === "Month" ? scheduler.startDate.daysInMonth() : 7
                    );
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            prev() {
                if (calendar) {
                    calendar.startDate = calendar.startDate.addDays(
                        currentView === "Day" ? -1 : -7
                    );
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    scheduler.startDate = scheduler.startDate.addDays(
                        currentView === "Month" ? -scheduler.startDate.daysInMonth() : -7
                    );
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            today() {
                if (calendar) {
                    calendar.startDate = DayPilot.Date.today();
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    scheduler.startDate = DayPilot.Date.today().firstDayOfMonth();
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            reload() {
                loadEvents(opts.ajax_url, opts.nonce, opts.context);
            }
        };
    }

    return {
            init,
           };

})();