// Core calendar logic for My Village Hall (shared by admin, portal, public views)
window.CalendarCore = (function () {

    let calendar = null;
    let scheduler = null;
    let currentMode = "Calendar";
    let currentDetail = "Week";
    let currentContainerId = "myvh-calendar";
    const DEFAULT_EVENT_BACKGROUND = "#f7f3ee";
    const DEFAULT_STATUS_COLORS = {
        confirmed: "#2271b1",
        pending: "#f0a500",
        cancelled: "#9aa0a6",
        completed: "#2d8f45",
    };

    function normaliseHexColour(value) {
        const text = String(value || "").trim().toLowerCase();
        return /^#[0-9a-f]{6}$/.test(text) ? text : "";
    }

    function getReadableTextColour(backgroundColour) {
        const hex = normaliseHexColour(backgroundColour);
        if (!hex) {
            return "#1f2933";
        }

        const red = parseInt(hex.slice(1, 3), 16);
        const green = parseInt(hex.slice(3, 5), 16);
        const blue = parseInt(hex.slice(5, 7), 16);
        const brightness = (red * 299 + green * 587 + blue * 114) / 1000;

        return brightness < 145 ? "#ffffff" : "#1f2933";
    }

    function getStatusColors(opts = {}) {
        if (!opts.statusColors || typeof opts.statusColors !== "object") {
            return DEFAULT_STATUS_COLORS;
        }

        return {
            ...DEFAULT_STATUS_COLORS,
            ...opts.statusColors,
        };
    }

    function applyEventStatusColors(events, statusColors) {
        return (events || []).map(function(event) {
            const tags = event && event.tags ? event.tags : {};

            // Buffer events keep room background color but use a neutral
            // left accent bar so they remain visually distinct.
            if (tags.isBuffer) {
                const roomColour = normaliseHexColour(tags.roomColour || tags.colour || event.backColor);
                const backgroundColour = roomColour || DEFAULT_EVENT_BACKGROUND;
                const bufferAccent = "#9aa0a6";

                return {
                    ...event,
                    backColor: backgroundColour,
                    fontColor: getReadableTextColour(backgroundColour),
                    borderColor: bufferAccent,
                    barColor: bufferAccent,
                    moveDisabled: true,
                    resizeDisabled: true,
                    clickDisabled: true,
                };
            }

            const status = String(tags.status || "").toLowerCase();
            const fallbackColor = statusColors.confirmed || DEFAULT_STATUS_COLORS.confirmed;
            const accentColor = statusColors[status] || fallbackColor;
            const roomColour = normaliseHexColour(tags.roomColour || tags.colour || event.backColor);
            const backgroundColour = roomColour || DEFAULT_EVENT_BACKGROUND;

            return {
                ...event,
                backColor: backgroundColour,
                fontColor: getReadableTextColour(backgroundColour),
                borderColor: accentColor,
                barColor: accentColor,
            };
        });
    }

    /**
     * Check if a date format string uses short weekday (ddd).
     */
    function formatUsesShortWeekday(format) {
        return typeof format === "string" && /(^|[^d])ddd([^d]|$)/.test(format);
    }

    /**
     * Ensure all English locales use three-letter weekday abbreviations.
     */
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

    /**
     * Format a time string from an ISO datetime.
     */
    function formatTimeFromISO(isoDatetime) {
        if (!isoDatetime) return "";
        try {
            const date = new Date(isoDatetime);
            if (isNaN(date.getTime())) return "";
            const hours = String(date.getHours()).padStart(2, "0");
            const mins = String(date.getMinutes()).padStart(2, "0");
            return hours + ":" + mins;
        } catch (e) {
            return "";
        }
    }

    /**
     * Determine if a booking is public based on tags.
     */
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

    /**
     * Determine if the current user can view a private booking.
     */
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

    /**
     * Build a tooltip string for a calendar event.
     * @param {object} event - The event object
     * @param {string} context - Context (admin, portal, etc)
     * @returns {string} Tooltip HTML
     */
    function buildEventTooltip(event, context = "admin") {
        const tooltipParts = [];
        const tags = event && event.tags ? event.tags : {};

        // Buffer events (separate display mode): show room name and time only.
        if (tags.isBuffer) {
            const room = tags.room || tags.roomName || "";
            if (String(room).trim() !== "") {
                tooltipParts.push(String(room).trim());
            }
            if (event.start && event.end) {
                const startTime = formatTimeFromISO(event.start);
                const endTime = formatTimeFromISO(event.end);
                if (startTime && endTime) {
                    tooltipParts.push(startTime + "-" + endTime);
                }
            }
            return tooltipParts.join("\n");
        }

        const room = tags.room || tags.roomName || "";
        if (String(room).trim() !== "") {
            tooltipParts.push(String(room).trim());
        }

        // For merged-buffer events, use the stored actual booking times in the
        // tooltip so users see the real booking window, not the visual extension.
        const displayStart = tags.actualStart || event.start;
        const displayEnd   = tags.actualEnd   || event.end;
        if (displayStart && displayEnd) {
            const startTime = formatTimeFromISO(displayStart);
            const endTime   = formatTimeFromISO(displayEnd);
            if (startTime && endTime) {
                tooltipParts.push(startTime + "-" + endTime);
            }
        }

        const descriptionFromTag = tags.description ? String(tags.description).trim() : "";
        const descriptionFromText = event && event.text ? String(event.text).trim() : "";
        const description = descriptionFromTag || descriptionFromText;
        const privateLabel = tags.privateLabel ? String(tags.privateLabel).trim() : "";

        if (context === "admin") {
            if (description !== "") {
                tooltipParts.push(description);
            }
        } else if (!isPublicBooking(tags) && !canViewPrivateBooking(tags)) {
            tooltipParts.push(privateLabel || descriptionFromText || "Private booking");
        } else if (description !== "") {
            tooltipParts.push(description);
        }

        return tooltipParts.join("\n");
    }

    function toDayPilotDate(value) {
        return value ? new DayPilot.Date(value) : null;
    }

    function toMode(value) {
        return String(value || "").toLowerCase() === "scheduler" ? "Scheduler" : "Calendar";
    }

    function toDetail(value) {
        const detail = String(value || "").toLowerCase();
        if (detail === "day") return "Day";
        if (detail === "month") return "Month";
        return "Week";
    }

    function parseVisibleHour(value, fallback = null) {
        if (value === null || typeof value === "undefined" || value === "") {
            return fallback;
        }

        if (typeof value === "number") {
            return Number.isFinite(value) ? value : fallback;
        }

        const raw = String(value).trim();

        // Supports "8", "08", "08:00", "08:30", and "0830" formats.
        const hhmm = raw.match(/^(\d{1,2})(?::?(\d{2}))?$/);
        if (hhmm) {
            const hour = Number(hhmm[1]);
            const minutes = typeof hhmm[2] !== "undefined" ? Number(hhmm[2]) : 0;

            if (Number.isFinite(hour) && Number.isFinite(minutes)) {
                return hour + (minutes / 60);
            }
        }

        const numeric = Number(raw);
        return Number.isFinite(numeric) ? numeric : fallback;
    }

    function getWeekStarts(opts = {}) {
        const raw = Number(opts.startOfWeek);
        if (Number.isInteger(raw) && raw >= 0 && raw <= 6) {
            return raw;
        }
        return 1;
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

    function getVenueId(opts = {}) {
        const raw = (typeof opts.getVenueId === "function") ? opts.getVenueId() : opts.venueId;
        const venueId = Number(raw || 0);
        return Number.isFinite(venueId) && venueId > 0 ? Math.trunc(venueId) : 0;
    }

    function appendVenueParam(url, opts = {}) {
        const venueId = getVenueId(opts);
        if (venueId <= 0) {
            return url;
        }

        return `${url}&venue_id=${encodeURIComponent(venueId)}`;
    }

    function loadEvents(ajax_url, nonce, context = "admin", opts = {}) {
        const statusColors = getStatusColors(opts);

        if (calendar) {
            const start = calendar.visibleStart();
            const end = calendar.visibleEnd();

            let url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                + `&context=${encodeURIComponent(context)}`
                + `&start=${start.toString()}&end=${end.toString()}`;

            url = appendVenueParam(url, opts);

            fetch(url).then(r => r.json()).then(events => {
                events.forEach(e => {
                    e.toolTip = buildEventTooltip(e, context);
                });
                const calendarEvents = currentDetail === "Month"
                    ? events.filter(e => !e?.tags?.isBuffer)
                    : events;
                calendar.events.list = applyEventStatusColors(calendarEvents, statusColors);
                calendar.update();
            }).catch(err => console.error("Failed to load calendar events", err));
        }

        if (scheduler) {
            const start = scheduler.startDate;
            const end = scheduler.startDate.addDays(scheduler.days || 1);

            let url = `${ajax_url}?action=myvh_calendar_events&nonce=${nonce}`
                + `&context=${encodeURIComponent(context)}`
                + `&start=${start.toString()}&end=${end.toString()}`;

            url = appendVenueParam(url, opts);

            fetch(url).then(r => r.json()).then(events => {
                events.forEach(e => {
                    e.toolTip = buildEventTooltip(e, context);
                });
                scheduler.events.list = applyEventStatusColors(events, statusColors);
                scheduler.update();
            }).catch(err => console.error("Failed to load scheduler events", err));
        }
    }

    function createDayWeekCalendar(containerId, opts, detail) {
        destroy();

        const visibleStartHour = parseVisibleHour(opts.visibleStartHour, null);
        const visibleEndHour = parseVisibleHour(opts.visibleEndHour, null);
        const weekStarts = getWeekStarts(opts);
        const locale = opts.locale || document.documentElement.lang || "en-us";

        calendar = new DayPilot.Calendar(containerId);
        calendar.viewType = detail;
        calendar.startDate = toDayPilotDate(opts.startDate) || DayPilot.Date.today();
        calendar.locale = locale;
        calendar.weekStarts = weekStarts;
        calendar.cellDuration = 15;

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

        if (visibleStartHour !== null || visibleEndHour !== null) {
            // DayPilot can still render a full 24h axis unless non-business
            // hours are explicitly hidden.
            calendar.showNonBusiness = false;
            calendar.heightSpec = "BusinessHoursNoScroll";
        }

        calendar.eventMoveHandling = opts.editable ? "Update" : "Disabled";
        calendar.eventResizeHandling = opts.editable ? "Update" : "Disabled";
        calendar.timeRangeSelectedHandling = opts.selectable ? "Enabled" : "Disabled";

        calendar.onEventClick = args => {
            if (args?.e?.data?.tags?.isBuffer) return;
            opts.onEventClick?.(args);
        };
        calendar.onEventMoved = args => opts.onEventMoved?.(args);
        calendar.onEventResized = args => opts.onEventResized?.(args);
        calendar.onTimeRangeSelected = args => opts.onTimeRangeSelected?.(args);

        calendar.onViewChange = () => loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);

        calendar.init();
        loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
    }

    function createMonthCalendar(containerId, opts) {
        destroy();

        const weekStarts = getWeekStarts(opts);

        calendar = new DayPilot.Month(containerId, {
            startDate: (toDayPilotDate(opts.startDate) || DayPilot.Date.today()).firstDayOfMonth(),
            locale: opts.locale || document.documentElement.lang || "en-us",
            weekStarts: weekStarts,
            eventMoveHandling: opts.editable ? "Update" : "Disabled",
            eventResizeHandling: opts.editable ? "Update" : "Disabled",
            onEventClick: args => {
                if (args?.e?.data?.tags?.isBuffer) return;
                opts.onEventClick?.(args);
            },
            onEventMoved: args => opts.onEventMoved?.(args),
            onEventResized: args => opts.onEventResized?.(args),
            onTimeRangeSelected: args => opts.onTimeRangeSelected?.(args),
            timeRangeSelectedHandling: opts.selectable ? "Enabled" : "Disabled",
        });

        calendar.init();
        loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
    }

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
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function createVerticalTimetable(containerId, opts, detail) {
        destroy();

        const container = document.getElementById(containerId);
        if (!container) return;

        const startHour = parseVisibleHour(opts.visibleStartHour, 8);
        const endHour = parseVisibleHour(opts.visibleEndHour, 22);
        const totalSlots = Math.max(1, endHour - startHour);
        let flatRooms = [];

        function getVisibleDays(startDate) {
            if (detail === "Day") return 1;
            if (detail === "Week") return 7;
            const jsDate = new Date(startDate.toString("yyyy-MM-dd") + "T00:00:00");
            return new Date(jsDate.getFullYear(), jsDate.getMonth() + 1, 0).getDate();
        }

        function renderTimetable() {
            const startDate = scheduler.startDate;
            const rooms = flatRooms.length > 0 ? flatRooms : [{ id: "all", name: "Bookings" }];
            const roomCount = rooms.length;
            const dayCount = Math.max(1, scheduler.days);
            const rowCount = dayCount * totalSlots;
            const grid = Array.from({ length: rowCount }, () => new Array(roomCount).fill(null));

            const dayData = [];
            for (let dayOffset = 0; dayOffset < dayCount; dayOffset++) {
                const dayDate = startDate.addDays(dayOffset);
                dayData.push({
                    dayOffset,
                    dayDate,
                    dayStr: dayDate.toString("yyyy-MM-dd"),
                    dayLabel: dayDate.toString("ddd d MMM")
                });
            }

            dayData.forEach(day => {
                const dayStart = new Date(day.dayStr + "T00:00:00");
                const dayEnd = new Date(dayStart);
                dayEnd.setDate(dayEnd.getDate() + 1);

                rooms.forEach((room, roomIndex) => {
                    const col = roomIndex;

                    const matchingEvents = scheduler.events.list.filter(event => {
                        const rid = String(event.resource || (event.tags && event.tags.roomId) || "");
                        if (room.id !== "all" && rid !== String(room.id)) {
                            return false;
                        }

                        const evStart = new Date(event.start);
                        const evEnd = new Date(event.end);
                        return evStart < dayEnd && evEnd > dayStart;
                    });

                    matchingEvents.forEach(event => {
                        const evStart = new Date(event.start);
                        const evEnd = new Date(event.end);
                        const clippedStart = evStart > dayStart ? evStart : dayStart;
                        const clippedEnd = evEnd < dayEnd ? evEnd : dayEnd;

                        const sh = clippedStart.getHours() + clippedStart.getMinutes() / 60;
                        const eh = clippedEnd.getHours() + clippedEnd.getMinutes() / 60;
                        const startSlot = Math.max(0, Math.floor(sh) - startHour);
                        let endSlot = Math.min(totalSlots, Math.ceil(eh) - startHour);
                        if (endSlot <= startSlot) {
                            endSlot = Math.min(totalSlots, startSlot + 1);
                        }

                        const span = Math.max(1, endSlot - startSlot);
                        if (startSlot < totalSlots) {
                            const startRow = day.dayOffset * totalSlots + startSlot;
                            if (grid[startRow][col] === null) {
                                grid[startRow][col] = { event, span };
                                for (let s = startSlot + 1; s < startSlot + span && s < totalSlots; s++) {
                                    grid[day.dayOffset * totalSlots + s][col] = "occupied";
                                }
                            }
                        }
                    });
                });
            });

            let html = '<div class="myvh-timetable-scroll">';
            html += '<table class="myvh-timetable" role="grid">';
            html += '<thead><tr>';
            html += '<th class="myvh-tt-corner">Date</th>';
            html += '<th class="myvh-tt-corner-sub">Time</th>';
            rooms.forEach(room => {
                html += `<th class="myvh-tt-room-header" scope="col">${escHtml(room.name || "")}</th>`;
            });
            html += '</tr></thead><tbody>';

            for (let dayOffset = 0; dayOffset < dayCount; dayOffset++) {
                const day = dayData[dayOffset];

                for (let slot = 0; slot < totalSlots; slot++) {
                    const row = dayOffset * totalSlots + slot;
                    const hour = startHour + slot;
                    const hourStr = String(hour).padStart(2, "0") + ":00";
                    html += "<tr>";

                    if (slot === 0) {
                        html += `<th class="myvh-tt-date-cell" scope="rowgroup" rowspan="${totalSlots}">${escHtml(day.dayLabel)}</th>`;
                    }

                    html += `<th class="myvh-tt-time-cell" scope="row">${escHtml(hourStr)}</th>`;

                    for (let col = 0; col < roomCount; col++) {
                        const cell = grid[row][col];
                        if (cell === "occupied") {
                            continue;
                        }

                        if (cell === null) {
                            html += '<td class="myvh-tt-empty-cell"></td>';
                        } else {
                            const { event, span } = cell;
                            const color = event.backColor || "#2271b1";
                            const textColor = event.fontColor || "#fff";
                            const title = event.text || "Booking";
                            const tooltip = event.toolTip ? String(event.toolTip).replace(/\n/g, "&#10;") : "";
                            const startT = formatTimeFromISO(event.start);
                            const endT = formatTimeFromISO(event.end);
                            const timeStr = (startT && endT) ? `${startT}-${endT}` : "";

                            html += `<td class="myvh-tt-event-cell" rowspan="${span}"`
                                + ` style="background:${escHtml(color)};color:${escHtml(textColor)};"`
                                + ` data-event-id="${escHtml(String(event.id || ""))}"`
                                + ` title="${escHtml(tooltip)}">`;
                            html += `<div class="myvh-tt-event-inner"><span class="myvh-tt-event-title">${escHtml(title)}</span>`;
                            if (timeStr) {
                                html += `<span class="myvh-tt-event-time">${escHtml(timeStr)}</span>`;
                            }
                            html += "</div></td>";
                        }
                    }

                    html += "</tr>";
                }
            }

            html += "</tbody></table></div>";
            container.innerHTML = html;

            if (typeof opts.onEventClick === "function") {
                container.querySelectorAll('.myvh-tt-event-cell[data-event-id]').forEach(cell => {
                    const evId = cell.dataset.eventId;
                    const ev = scheduler.events.list.find(e => String(e.id) === evId);
                    if (ev) {
                        cell.style.cursor = "pointer";
                        cell.addEventListener("click", () => opts.onEventClick({ e: { data: () => ev } }));
                    }
                });
            }
        }

        const startDate = getSchedulerStartDate(detail, opts.startDate);

        scheduler = {
            startDate,
            days: getVisibleDays(startDate),
            events: { list: [] },
            update() { renderTimetable(); },
            dispose() {}
        };

        let roomsUrl = `${opts.ajax_url}?action=myvh_calendar_rooms&nonce=${opts.nonce}&context=${encodeURIComponent(opts.context || "admin")}`;
        roomsUrl = appendVenueParam(roomsUrl, opts);

        fetch(roomsUrl)
            .then(r => r.json())
            .then(res => {
                const rooms = Array.isArray(res?.data) ? res.data : Object.values(res?.data || res || {});
                flatRooms = buildGroupedSchedulerResources(rooms).filter(r => !r.tags || r.tags.type !== "venue");
                loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
            })
            .catch(err => console.error("Failed to load rooms for timetable", err));
    }

    function createScheduler(containerId, opts, detail) {
        const schedulerOrientation = String(opts.schedulerOrientation || "horizontal").trim().toLowerCase();
        const visibleStartHour = parseVisibleHour(opts.visibleStartHour, null);
        const visibleEndHour = parseVisibleHour(opts.visibleEndHour, null);
        const weekStarts = getWeekStarts(opts);

        if (schedulerOrientation === "vertical") {
            createVerticalTimetable(containerId, opts, detail);
            return;
        }

        destroy();

        const maxBookingDaysAhead = Number.isFinite(Number(opts.maxBookingDaysAhead)) ? Number(opts.maxBookingDaysAhead) : 365;
        let startDate = getSchedulerStartDate(detail, opts.startDate);
        const config = {
            startDate: startDate,
            locale: opts.locale || document.documentElement.lang || "en-us",
            weekStarts: weekStarts,
            eventResourceField: "resource",
            rowHeaderWidth: 220,
            timeRangeSelectedHandling: opts.selectable ? "Enabled" : "Disabled",
            onEventClick: args => {
                if (args?.e?.data?.tags?.isBuffer) return;
                opts.onEventClick?.(args);
            },
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

        if (visibleStartHour !== null) {
            config.businessBeginsHour = visibleStartHour;
            config.dayBeginsHour = visibleStartHour;
        }

        if (visibleEndHour !== null) {
            config.businessEndsHour = visibleEndHour;
            config.dayEndsHour = visibleEndHour;
        }

        if (visibleStartHour !== null || visibleEndHour !== null) {
            config.showNonBusiness = false;
        }

        const schedulerDayHeaderFormat = "ddd d";

        if (detail === "Day") {
            startDate = DayPilot.Date.today().getDatePart();
            config.startDate = startDate;
            config.scale = "Hour";
            config.days = Math.max(1, maxBookingDaysAhead);
            config.cellDuration = 15;
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

        let roomsUrl = `${opts.ajax_url}?action=myvh_calendar_rooms&nonce=${opts.nonce}&context=${encodeURIComponent(opts.context || "admin")}`;
        roomsUrl = appendVenueParam(roomsUrl, opts);

        fetch(roomsUrl)
            .then(r => r.json())
            .then(res => {
                const rooms = Array.isArray(res?.data)
                    ? res.data
                    : Object.values(res?.data || res || {});

                scheduler.resources = buildGroupedSchedulerResources(rooms);
                scheduler.init();
                loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
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
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
                }

                if (scheduler) {
                    scheduler.startDate = currentDetail === "Month" ? start.firstDayOfMonth() : start;
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
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
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
                }

                if (scheduler) {
                    if (currentDetail === "Month") {
                        scheduler.startDate = scheduler.startDate.addMonths(1).firstDayOfMonth();
                    } else {
                        scheduler.startDate = scheduler.startDate.addDays(currentDetail === "Day" ? 1 : 7);
                    }
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
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
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
                }

                if (scheduler) {
                    if (currentDetail === "Month") {
                        scheduler.startDate = scheduler.startDate.addMonths(-1).firstDayOfMonth();
                    } else {
                        scheduler.startDate = scheduler.startDate.addDays(currentDetail === "Day" ? -1 : -7);
                    }
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
                }
            },

            today() {
                if (calendar) {
                    calendar.startDate = currentDetail === "Month"
                        ? DayPilot.Date.today().firstDayOfMonth()
                        : DayPilot.Date.today();
                    calendar.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
                }

                if (scheduler) {
                    scheduler.startDate = currentDetail === "Month"
                        ? DayPilot.Date.today().firstDayOfMonth()
                        : DayPilot.Date.today();
                    scheduler.update();
                    loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
                }
            },

            reload() {
                loadEvents(opts.ajax_url, opts.nonce, opts.context, opts);
            },

            rerender(startDate = null) {
                render(currentContainerId, opts, startDate || currentStartDateValue());
            }
        };
    }

    return {
        init,
    };

})();
