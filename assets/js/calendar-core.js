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

    function formatTimeFromISO(isoDatetime) {
        if (!isoDatetime) return "";
        try {
            const date = new Date(isoDatetime);
            if (isNaN(date.getTime())) return "";
            const hours = String(date.getHours()).padStart(2, '0');
            const mins = String(date.getMinutes()).padStart(2, '0');
            return hours + ':' + mins;
        } catch (e) {
            return "";
        }
    }

    function isPublicBooking(tags) {
        if (!tags || !Object.prototype.hasOwnProperty.call(tags, "isPublic")) {
            return true;
        }

        const raw = tags.isPublic;
        if (raw === true || raw === 1 || raw === "1") {
            return true;
        }

        if (raw === false || raw === 0 || raw === "0") {
            return false;
        }

        return String(raw).toLowerCase() !== "false";
    }

    function canViewPrivateBooking(tags) {
        if (!tags || !Object.prototype.hasOwnProperty.call(tags, "canViewPrivate")) {
            return false;
        }

        const raw = tags.canViewPrivate;
        if (raw === true || raw === 1 || raw === "1") {
            return true;
        }

        if (raw === false || raw === 0 || raw === "0") {
            return false;
        }

        return String(raw).toLowerCase() === "true";
    }

    function buildEventTooltip(event, context = "admin") {
        const tooltipParts = [];
        const tags = event && event.tags ? event.tags : {};

        const room = tags.room || tags.roomName || "";
        if (String(room).trim() !== "") {
            tooltipParts.push(String(room).trim());
        }

        // Add time range
        if (event.start && event.end) {
            const startTime = formatTimeFromISO(event.start);
            const endTime = formatTimeFromISO(event.end);
            if (startTime && endTime) {
                tooltipParts.push(startTime + '-' + endTime);
            }
        }

        const descriptionFromTag = tags.description ? String(tags.description).trim() : "";
        const descriptionFromText = event && event.text ? String(event.text).trim() : "";
        const description = descriptionFromTag || descriptionFromText;

        // Admin always sees the real description. Portal/public mask non-public events.
        if (context === "admin") {
            if (description !== "") {
                tooltipParts.push(description);
            }
        } else if (!isPublicBooking(tags) && !canViewPrivateBooking(tags)) {
            tooltipParts.push("Private event");
        } else if (description !== "") {
            tooltipParts.push(description);
        }

        return tooltipParts.join('\n');
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

            fetch(url).then(r => r.json()).then(events => {
                events.forEach(e => {
                    e.toolTip = buildEventTooltip(e, context);
                });
                calendar.events.list = events;
                calendar.update();
            }).catch(err => console.error("Failed to load calendar events", err));
        }

        if (scheduler) {
            const start = scheduler.startDate;
            const end   = scheduler.startDate.addDays(scheduler.days);

            const url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                      + `&context=${encodeURIComponent(context)}`
                      + `&start=${start.toString()}&end=${end.toString()}`;

            fetch(url).then(r => r.json()).then(events => {
                events.forEach(e => {
                    e.toolTip = buildEventTooltip(e, context);
                });
                scheduler.events.list = events;
                scheduler.update();
            }).catch(err => console.error("Failed to load scheduler events", err));
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

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ───────────────────────────────────────────────
    // VERTICAL TIMETABLE (Scheduler Day, orientation=vertical)
    // Rooms as columns, hourly time-slots as rows.
    // ───────────────────────────────────────────────
    function createVerticalTimetable(containerId, opts) {
        destroy();

        const container = document.getElementById(containerId);
        if (!container) return;

        const startHour = Number.isFinite(Number(opts.visibleStartHour)) ? Number(opts.visibleStartHour) : 8;
        const endHour   = Number.isFinite(Number(opts.visibleEndHour))   ? Number(opts.visibleEndHour)   : 22;
        const totalSlots = Math.max(1, endHour - startHour);
        let flatRooms = [];

        function renderTimetable() {
            const startDate = scheduler.startDate;
            const dayStr = startDate.toString('yyyy-MM-dd');

            // Filter events that overlap the current day
            const dayEvents = scheduler.events.list.filter(event => {
                const evS = event.start ? String(event.start).substring(0, 10) : '';
                const evE = event.end   ? String(event.end).substring(0, 10)   : '';
                return evS <= dayStr && evE >= dayStr;
            });

            // Index by room id
            const eventsByRoom = {};
            flatRooms.forEach(r => { eventsByRoom[String(r.id)] = []; });
            dayEvents.forEach(event => {
                const rid = String(event.resource || (event.tags && event.tags.roomId) || '');
                if (rid && eventsByRoom[rid] !== undefined) {
                    eventsByRoom[rid].push(event);
                }
            });

            // Build grid[slot][col] = null | { event, span } | 'occupied'
            const grid = Array.from({ length: totalSlots }, () => new Array(flatRooms.length).fill(null));

            flatRooms.forEach((room, col) => {
                (eventsByRoom[String(room.id)] || []).forEach(event => {
                    const evStart = new Date(event.start);
                    const evEnd   = new Date(event.end);
                    const sh = evStart.getHours() + evStart.getMinutes() / 60;
                    const eh = evEnd.getHours()   + evEnd.getMinutes()   / 60;
                    const startSlot = Math.max(0, Math.floor(sh) - startHour);
                    const endSlot   = Math.min(totalSlots, Math.ceil(eh) - startHour);
                    const span = Math.max(1, endSlot - startSlot);

                    if (startSlot < totalSlots && grid[startSlot][col] === null) {
                        grid[startSlot][col] = { event, span };
                        for (let s = startSlot + 1; s < startSlot + span && s < totalSlots; s++) {
                            grid[s][col] = 'occupied';
                        }
                    }
                });
            });

            // Render
            const dateLabel = startDate.toString('dddd, d MMMM yyyy');
            let html = `<table class="myvh-timetable" role="grid" aria-label="${escHtml(dateLabel)}">`;
            html += '<thead><tr>';
            html += `<th class="myvh-tt-corner">${escHtml(dateLabel)}</th>`;
            flatRooms.forEach(room => {
                html += `<th class="myvh-tt-room-header" scope="col">${escHtml(room.name || '')}</th>`;
            });
            html += '</tr></thead><tbody>';

            for (let slot = 0; slot < totalSlots; slot++) {
                const hour = startHour + slot;
                const hourStr = String(hour).padStart(2, '0') + ':00';
                html += `<tr><th class="myvh-tt-time-cell" scope="row">${escHtml(hourStr)}</th>`;

                for (let col = 0; col < flatRooms.length; col++) {
                    const cell = grid[slot][col];
                    if (cell === 'occupied') continue;

                    if (cell === null) {
                        html += '<td class="myvh-tt-empty-cell"></td>';
                    } else {
                        const { event, span } = cell;
                        const color     = event.backColor || '#2271b1';
                        const textColor = event.fontColor || '#fff';
                        const title     = event.text || 'Booking';
                        const tooltip   = event.toolTip ? String(event.toolTip).replace(/\n/g, '&#10;') : '';
                        const startT    = formatTimeFromISO(event.start);
                        const endT      = formatTimeFromISO(event.end);
                        const timeStr   = (startT && endT) ? `${startT}–${endT}` : '';

                        html += `<td class="myvh-tt-event-cell" rowspan="${span}"`
                              + ` style="background:${escHtml(color)};color:${escHtml(textColor)};"`
                              + ` data-event-id="${escHtml(String(event.id || ''))}"`
                              + ` title="${escHtml(tooltip)}">`;
                        html += `<div class="myvh-tt-event-inner"><span class="myvh-tt-event-title">${escHtml(title)}</span>`;
                        if (timeStr) html += `<span class="myvh-tt-event-time">${escHtml(timeStr)}</span>`;
                        html += '</div></td>';
                    }
                }
                html += '</tr>';
            }
            html += '</tbody></table>';

            container.innerHTML = html;

            if (typeof opts.onEventClick === 'function') {
                container.querySelectorAll('.myvh-tt-event-cell[data-event-id]').forEach(cell => {
                    const evId = cell.dataset.eventId;
                    const ev = scheduler.events.list.find(e => String(e.id) === evId);
                    if (ev) {
                        cell.style.cursor = 'pointer';
                        cell.addEventListener('click', () => opts.onEventClick({ e: { data: () => ev } }));
                    }
                });
            }
        }

        const startDate = (opts.startDate ? new DayPilot.Date(opts.startDate) : DayPilot.Date.today()).getDatePart();

        scheduler = {
            startDate,
            days: 1,
            events: { list: [] },
            update() { renderTimetable(); },
            dispose() {},
        };

        // Fetch rooms, then trigger event load
        const roomsUrl = `${opts.ajax_url}?action=myvh_calendar_rooms&nonce=${opts.nonce}&context=${encodeURIComponent(opts.context || 'admin')}`;

        fetch(roomsUrl)
            .then(r => r.json())
            .then(res => {
                const rooms = Array.isArray(res?.data) ? res.data : Object.values(res?.data || res || {});
                flatRooms = buildGroupedSchedulerResources(rooms).filter(r => !r.tags || r.tags.type !== 'venue');
                loadEvents(opts.ajax_url, opts.nonce, opts.context);
            })
            .catch(err => console.error('Failed to load rooms for timetable', err));
    }

    function createScheduler(containerId, opts, detail) {

        // Vertical timetable mode: rooms-as-columns, time-as-rows (Day detail only).
        if (opts.schedulerOrientation === 'vertical' && detail === 'Day') {
            createVerticalTimetable(containerId, opts);
            return;
        }

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

        const schedulerDayHeaderFormat = "ddd d";

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
                { groupBy: "Day", format: schedulerDayHeaderFormat }
            ];
        } else {
            startDate = DayPilot.Date.today().getDatePart();
            config.startDate = startDate;
            config.scale = "Day";
            config.days = Math.max(1, maxBookingDaysAhead);
            config.timeHeaders = [
                { groupBy: "Month" },
                { groupBy: "Day", format: schedulerDayHeaderFormat }
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
                if (currentMode === "Scheduler" && String(opts.schedulerOrientation || "").toLowerCase() === "vertical") {
                    currentDetail = "Day";
                }
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