window.MYVH_CalendarCore = (function () {

    let calendar = null;
    let scheduler = null;
    let currentMode = "Calendar";
    let currentDetail = "Week";
    let currentContainerId = "myvh-calendar";

    function formatUsesShortWeekday(format) {
        return typeof format === "string" && /(^|[^d])ddd([^d]|$)/.test(format);
    }

    function ensureThreeLetterEnglishWeekdays(format) {
        if (!formatUsesShortWeekday(format) || !DayPilot || !DayPilot.Locale || !DayPilot.Locale.all) {
            return;
        }

        Object.keys(DayPilot.Locale.all).forEach(function(localeId) {
            if (!/^en($|-)/i.test(localeId)) {
                return;
            }

            const locale = DayPilot.Locale.find(localeId);

            if (!locale || !Array.isArray(locale.dayNames) || locale.dayNames.length !== 7) {
                return;
            }

            locale.dayNamesShort = locale.dayNames.map(function(dayName) {
                return String(dayName).slice(0, 3);
            });
        });
    }

    function toDayPilotDate(value) {
        return value ? new DayPilot.Date(value) : null;
    }

    function toMode(value) {
        return String(value || "").toLowerCase() === "scheduler" ? "Scheduler" : "Calendar";
    }

    function toDetail(value) {
        const detail = String(value || "").toLowerCase();
        if (detail === "day") {
            return "Day";
        }
        if (detail === "month") {
            return "Month";
        }
        return "Week";
    }

    function getSchedulerStartDate(detail, value) {
        const date = toDayPilotDate(value) || DayPilot.Date.today();

        if (detail === "Month") {
            return date.firstDayOfMonth();
        }

        return date.getDatePart();
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

        const container = document.getElementById(currentContainerId);
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
    function createDayWeekCalendar(containerId, opts, detail) {

        destroy();

        const visibleStartHour = Number.isFinite(Number(opts.visibleStartHour)) ? Number(opts.visibleStartHour) : null;
        const visibleEndHour = Number.isFinite(Number(opts.visibleEndHour)) ? Number(opts.visibleEndHour) : null;

        calendar = new DayPilot.Calendar(containerId);
        calendar.viewType = detail;
        calendar.startDate = toDayPilotDate(opts.startDate) || DayPilot.Date.today();
        if (opts.headerDateFormat) {
            calendar.headerDateFormat = opts.headerDateFormat;
        }

        if (visibleStartHour !== null) {
            calendar.businessBeginsHour = visibleStartHour;
            calendar.dayBeginsHour = visibleStartHour;
        }

        if (visibleEndHour !== null) {
            calendar.businessEndsHour = visibleEndHour;
            calendar.dayEndsHour = visibleEndHour;
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

    function createMonthCalendar(containerId, opts) {

        destroy();

        calendar = new DayPilot.Month(containerId, {
            startDate: (toDayPilotDate(opts.startDate) || DayPilot.Date.today()).firstDayOfMonth(),
            locale: document.documentElement.lang || "en-us",
            eventMoveHandling: opts.editable ? "Update" : "Disabled",
            eventResizeHandling: opts.editable ? "Update" : "Disabled",
            onEventClick: args => opts.onEventClick?.(args),
            onEventMoved: args => opts.onEventMoved?.(args),
            onEventResized: args => opts.onEventResized?.(args),
            onTimeRangeSelected: args => opts.onTimeRangeSelected?.(args),
            timeRangeSelectedHandling: opts.selectable ? "Enabled" : "Disabled",
        });

        calendar.init();
        loadEvents(opts.ajax_url, opts.nonce, opts.context);
    }

    // ───────────────────────────────────────────────
    // SCHEDULER (Day / Week / Month)
    // ───────────────────────────────────────────────
    function buildGroupedSchedulerResources(rooms) {
        const groups = {};

        (rooms || []).forEach(room => {
            const venueId = room.venue_id || room.VenueId || "venue-unknown";
            const venueName = room.venue || room.VenueName || "Venue";

            if (!groups[venueId]) {
                groups[venueId] = {
                    name: venueName,
                    rooms: []
                };
            }

            groups[venueId].rooms.push({
                id: room.id,
                name: room.name || "Room"
            });
        });

        const flatResources = [];

        Object.keys(groups).sort((a, b) => {
            const aName = (groups[a]?.name || "").toLowerCase();
            const bName = (groups[b]?.name || "").toLowerCase();
            return aName.localeCompare(bName);
        }).forEach(venueId => {
            const venue = groups[venueId];

            flatResources.push({
                id: `venue-heading-${venueId}`,
                name: venue.name,
                tags: { type: "venue" }
            });

            venue.rooms.sort((a, b) => String(a.name || "").toLowerCase().localeCompare(String(b.name || "").toLowerCase()))
                .forEach(room => {
                    flatResources.push({
                        id: room.id,
                        name: room.name,
                        tags: { type: "room", venueId }
                    });
                });
        });

        return flatResources;
    }

    function createScheduler(containerId, opts, detail) {

        destroy();

        const maxBookingDaysAhead = Number.isFinite(Number(opts.maxBookingDaysAhead)) ? Number(opts.maxBookingDaysAhead) : 365;
        let startDate = getSchedulerStartDate(detail, opts.startDate);
        const config = {
            startDate: startDate,
            eventResourceField: "resource",
            rowHeaderWidth: 220,
            timeRangeSelectedHandling: opts.selectable ? "Enabled" : "Disabled",
            onEventClick: args => opts.onEventClick?.(args),
            onEventMoved: args => opts.onEventMoved?.(args),
            onEventResized: args => opts.onEventResized?.(args),
            onTimeRangeSelected: args => opts.onTimeRangeSelected?.(args),
            onBeforeRowHeaderRender: args => {
                const tags = (args.row && args.row.tags) ? args.row.tags : {};

                if (tags.type === "venue") {
                    args.row.html = `<div style="font-weight:700;color:#2c3338;letter-spacing:.01em;">${String(args.row.name || "")}</div>`;
                    return;
                }

                args.row.html = `<div style="padding-left:14px;color:#50575e;">${String(args.row.name || "")}</div>`;
            }
        };

        if (detail === "Day") {
            startDate = DayPilot.Date.today().getDatePart();
            config.startDate = startDate;
            config.scale = "Hour";
            config.days = Math.max(1, maxBookingDaysAhead);
            config.cellDuration = 60;
            config.timeHeaders = [
                { groupBy: "Day", format: opts.headerDateFormat || "d MMM" },
                { groupBy: "Hour" }
            ];
        } else if (detail === "Month") {
            startDate = DayPilot.Date.today().getDatePart();
            config.startDate = startDate;
            config.scale = "Day";
            config.days = Math.max(1, maxBookingDaysAhead);
            config.timeHeaders = [
                { groupBy: "Month" },
                { groupBy: "Day", format: opts.headerDateFormat || "d" }
            ];
        } else {
            startDate = DayPilot.Date.today().getDatePart();
            config.startDate = startDate;
            config.scale = "Day";
            config.days = Math.max(1, maxBookingDaysAhead);
            config.timeHeaders = [
                { groupBy: "Month" },
                { groupBy: "Day", format: opts.headerDateFormat || "d" }
            ];
        }

        scheduler = new DayPilot.Scheduler(containerId, config);

        // Load rooms first
        const roomsUrl = `${opts.ajax_url}?action=myvh_calendar_rooms&nonce=${opts.nonce}&context=${encodeURIComponent(opts.context || "admin")}`;

        fetch(roomsUrl)
            .then(r => r.json())
            .then(res => {

                const rooms = Array.isArray(res?.data)
                    ? res.data
                    : Object.values(res?.data || res || {});

                scheduler.resources = buildGroupedSchedulerResources(rooms);

                scheduler.init();
                loadEvents(opts.ajax_url, opts.nonce, opts.context);
            })
            .catch(err => {
                console.error("Failed to load rooms", err);
        });
    }

    function render(containerId, opts, startDate = null) {
        if (currentMode === "Scheduler") {
            createScheduler(containerId, { ...opts, startDate }, currentDetail);
            return;
        }

        if (currentDetail === "Month") {
            createMonthCalendar(containerId, { ...opts, startDate });
            return;
        }

        createDayWeekCalendar(containerId, { ...opts, startDate }, currentDetail);
    }

    function currentStartDateValue() {
        if (calendar?.startDate) {
            return calendar.startDate;
        }

        if (scheduler?.startDate) {
            return scheduler.startDate;
        }

        return DayPilot.Date.today();
    }

    // ───────────────────────────────────────────────
    // PUBLIC API
    // ───────────────────────────────────────────────
    function init(containerId, opts) {

        ensureThreeLetterEnglishWeekdays(opts.headerDateFormat);
        currentContainerId = containerId || "myvh-calendar";

        const initialState = opts.initialState || null;
        const legacyView = initialState?.view || null;
        const initialMode = initialState?.mode || opts.initialMode || (legacyView === "Rooms" ? "Scheduler" : "Calendar");
        const initialDetail = initialState?.detail || opts.initialDetail || (legacyView === "Rooms" ? "Week" : (legacyView || "Week"));
        const initialStart = initialState?.start || null;

        currentMode = toMode(initialMode);
        currentDetail = toDetail(initialDetail);

        render(currentContainerId, opts, initialStart);

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
                    mode: currentMode,
                    detail: currentDetail,
                    view: currentDetail,
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

                const mode = toMode(state.mode || currentMode);
                const detail = toDetail(state.detail || state.view || currentDetail);
                const start = state.start ? new DayPilot.Date(state.start) : null;

                if (mode !== currentMode || detail !== currentDetail) {
                    currentMode = mode;
                    currentDetail = detail;
                    render(currentContainerId, opts, start);
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
                    scheduler.startDate = currentDetail === "Month" ? start.firstDayOfMonth() : start;
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            setMode(mode, startDate = null) {
                currentMode = toMode(mode);
                render(currentContainerId, opts, startDate || currentStartDateValue());
            },

            setDetail(detail, startDate = null) {
                currentDetail = toDetail(detail);
                render(currentContainerId, opts, startDate || currentStartDateValue());
            },

            setModeAndDetail(mode, detail, startDate = null) {
                currentMode = toMode(mode);
                currentDetail = toDetail(detail);
                render(currentContainerId, opts, startDate || currentStartDateValue());
            },

            setView(view, startDate = null) {
                if (view === "Rooms") {
                    this.setModeAndDetail("Scheduler", "Week", startDate);
                    return;
                }

                this.setDetail(view, startDate);
            },

            next() {
                if (calendar) {
                    if (currentDetail === "Month") {
                        calendar.startDate = calendar.startDate.addMonths(1).firstDayOfMonth();
                    } else {
                        calendar.startDate = calendar.startDate.addDays(currentDetail === "Day" ? 1 : 7);
                    }
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    if (currentDetail === "Month") {
                        scheduler.startDate = scheduler.startDate.addMonths(1).firstDayOfMonth();
                    } else {
                        scheduler.startDate = scheduler.startDate.addDays(currentDetail === "Day" ? 1 : 7);
                    }
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            prev() {
                if (calendar) {
                    if (currentDetail === "Month") {
                        calendar.startDate = calendar.startDate.addMonths(-1).firstDayOfMonth();
                    } else {
                        calendar.startDate = calendar.startDate.addDays(currentDetail === "Day" ? -1 : -7);
                    }
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    if (currentDetail === "Month") {
                        scheduler.startDate = scheduler.startDate.addMonths(-1).firstDayOfMonth();
                    } else {
                        scheduler.startDate = scheduler.startDate.addDays(currentDetail === "Day" ? -1 : -7);
                    }
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }
            },

            today() {
                if (calendar) {
                    calendar.startDate = currentDetail === "Month"
                        ? DayPilot.Date.today().firstDayOfMonth()
                        : DayPilot.Date.today();
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context);
                }

                if (scheduler) {
                    scheduler.startDate = currentDetail === "Month"
                        ? DayPilot.Date.today().firstDayOfMonth()
                        : DayPilot.Date.today();
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