/**
 * My Village Hall – Public Calendar (DayPilot Lite)
 *
 * Reads config from the myvhCalConfig object localised by the shortcode PHP class.
 * Supports month, week and day views.
 */
(function () {
    'use strict';

    // myvhCalConfig is injected by wp_localize_script
    if (typeof myvhCalConfig === 'undefined') {
        return;
    }

    var cfg     = myvhCalConfig;
    var wrap    = null;   // .myvh-public-calendar-wrap
    var dp      = null;   // active DayPilot control
    var nav     = null;   // DayPilot navigator control
    var allRooms = Array.isArray(cfg.rooms) ? cfg.rooms.slice() : [];
    var selectedVenueId = 0;
    var pinnedVenueId = parseInt(cfg.venueId || '0', 10) || 0;
    var VENUE_STORAGE_KEY = 'myvhCalendarVenue_public';
    var lastEvents = [];
    var roomNameById = {};
    var loadRequestSeq = 0;
    var suppressNavSelect = false;
    var headerDateFormat = cfg.headerDateFormat || 'd MMM';
    var current = {
        mode : 'calendar',
        detail : cfg.view || 'month',
        date : DayPilot.Date.today(),
    };

    // ── Colour map ────────────────────────────────────────────────────────────
    var DEFAULT_STATUS_COLOURS = {
        confirmed : '#2271b1',
        pending   : '#f0a500',
        cancelled : '#9aa0a6',
        completed : '#2d8f45',
    };
    var DEFAULT_EVENT_BACKGROUND = '#f7f3ee';
    var STATUS_COLOURS = Object.assign({}, DEFAULT_STATUS_COLOURS, (cfg.statusColors && typeof cfg.statusColors === 'object') ? cfg.statusColors : {});

    function normaliseHexColour(value) {
        var text = String(value || '').trim().toLowerCase();
        return /^#[0-9a-f]{6}$/.test(text) ? text : '';
    }

    function getReadableTextColour(backgroundColour) {
        var hex = normaliseHexColour(backgroundColour);
        if (!hex) {
            return '#1f2933';
        }

        var red = parseInt(hex.slice(1, 3), 16);
        var green = parseInt(hex.slice(3, 5), 16);
        var blue = parseInt(hex.slice(5, 7), 16);
        var brightness = (red * 299 + green * 587 + blue * 114) / 1000;

        return brightness < 145 ? '#ffffff' : '#1f2933';
    }

    function buildRoomIndex() {
        roomNameById = {};

        var visibleRooms = getVisibleRooms();
        if (!Array.isArray(visibleRooms)) {
            return;
        }

        visibleRooms.forEach(function(room) {
            if (!room) {
                return;
            }

            var id = room.id;
            var name = room.name;

            if (id === null || typeof id === 'undefined') {
                return;
            }

            if (typeof name !== 'string' || name.trim() === '') {
                return;
            }

            roomNameById[String(id)] = name.trim();
        });
    }

    function getPersistedVenueId() {
        try {
            return parseInt(window.localStorage.getItem(VENUE_STORAGE_KEY) || '0', 10) || 0;
        } catch (err) {
            return 0;
        }
    }

    function setPersistedVenueId(venueId) {
        try {
            if (venueId > 0) {
                window.localStorage.setItem(VENUE_STORAGE_KEY, String(venueId));
            } else {
                window.localStorage.removeItem(VENUE_STORAGE_KEY);
            }
        } catch (err) {
            // Ignore storage failures.
        }
    }

    function buildVenueList() {
        var byId = {};

        allRooms.forEach(function(room) {
            var venueId = parseInt((room && room.venueId) || (room && room.venue_id) || '0', 10) || 0;
            var venueName = String((room && room.venue) || (room && room.VenueName) || '').trim();

            if (venueId <= 0 || !venueName) {
                return;
            }

            byId[venueId] = venueName;
        });

        return Object.keys(byId)
            .map(function(id) {
                return { id: parseInt(id, 10), name: byId[id] };
            })
            .sort(function(a, b) {
                return a.name.toLowerCase().localeCompare(b.name.toLowerCase());
            });
    }

    function getVisibleRooms() {
        if (selectedVenueId <= 0) {
            return allRooms.slice();
        }

        return allRooms.filter(function(room) {
            var venueId = parseInt((room && room.venueId) || (room && room.venue_id) || '0', 10) || 0;
            return venueId === selectedVenueId;
        });
    }

    function initialiseVenueSelector() {
        var venues = buildVenueList();
        var venueWrap = wrap ? wrap.querySelector('.myvh-cal-venue-filter') : null;
        var venueSelect = wrap ? wrap.querySelector('.myvh-cal-venue-select') : null;

        if (pinnedVenueId > 0) {
            selectedVenueId = pinnedVenueId;
            cfg.venueId = selectedVenueId;
            if (venueWrap) {
                venueWrap.style.display = 'none';
            }
            return;
        }

        if (venues.length === 0) {
            selectedVenueId = 0;
            cfg.venueId = 0;
            if (venueWrap) {
                venueWrap.style.display = 'none';
            }
            return;
        }

        if (venues.length === 1) {
            selectedVenueId = venues[0].id;
            cfg.venueId = selectedVenueId;
            setPersistedVenueId(selectedVenueId);
            if (venueWrap) {
                venueWrap.style.display = 'none';
            }
            return;
        }

        var persistedVenueId = getPersistedVenueId();
        var knownVenueIds = venues.map(function(venue) { return venue.id; });
        selectedVenueId = (persistedVenueId > 0 && knownVenueIds.indexOf(persistedVenueId) !== -1)
            ? persistedVenueId
            : venues[0].id;
        cfg.venueId = selectedVenueId;
        setPersistedVenueId(selectedVenueId);

        if (!venueWrap || !venueSelect) {
            return;
        }

        venueWrap.style.display = '';
        venueSelect.innerHTML = '';

        venues.forEach(function(venue) {
            var option = document.createElement('option');
            option.value = String(venue.id);
            option.textContent = venue.name;
            option.selected = venue.id === selectedVenueId;
            venueSelect.appendChild(option);
        });

        venueSelect.addEventListener('change', function() {
            selectedVenueId = parseInt(venueSelect.value || '0', 10) || 0;
            cfg.venueId = selectedVenueId;
            setPersistedVenueId(selectedVenueId);
            buildRoomIndex();
            renderCalendarKey();
            mountView(current.detail, current.mode);
        });
    }

    function toStatusLabel(status) {
        return String(status || '')
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, function(match) { return match.toUpperCase(); });
    }

    function createLegendItem(label, colour, itemClass, swatchClass, labelClass) {
        var item = document.createElement('span');
        item.className = itemClass;

        var swatch = document.createElement('span');
        swatch.className = swatchClass;
        swatch.style.backgroundColor = colour;

        var text = document.createElement('span');
        text.className = labelClass;
        text.textContent = label;

        item.appendChild(swatch);
        item.appendChild(text);

        return item;
    }

    function renderCalendarKey() {
        if (!wrap) {
            return;
        }

        var keyRoot = wrap.querySelector('.myvh-cal-key');
        if (!keyRoot) {
            return;
        }

        var statusWrap = keyRoot.querySelector('.myvh-cal-key-status-items');
        var roomWrap = keyRoot.querySelector('.myvh-cal-key-room-items');
        if (!statusWrap || !roomWrap) {
            return;
        }

        statusWrap.innerHTML = '';
        roomWrap.innerHTML = '';

        Object.keys(STATUS_COLOURS).forEach(function(status) {
            var colour = normaliseHexColour(STATUS_COLOURS[status]);
            if (!colour) {
                return;
            }

            statusWrap.appendChild(createLegendItem(
                toStatusLabel(status),
                colour,
                'myvh-cal-key-item',
                'myvh-cal-key-swatch',
                'myvh-cal-key-label'
            ));
        });

        var seenRoomIds = new Set();
        getVisibleRooms().forEach(function(room) {
            if (!room || room.id === null || typeof room.id === 'undefined') {
                return;
            }

            var roomId = String(room.id);
            if (seenRoomIds.has(roomId)) {
                return;
            }

            var roomName = String(room.name || '').trim();
            var roomColour = normaliseHexColour(room.roomColour || room.colour);
            if (!roomName || !roomColour) {
                return;
            }

            seenRoomIds.add(roomId);
            roomWrap.appendChild(createLegendItem(
                roomName,
                roomColour,
                'myvh-cal-key-item',
                'myvh-cal-key-swatch',
                'myvh-cal-key-label'
            ));
        });

        if (!roomWrap.children.length) {
            roomWrap.appendChild(createLegendItem(
                'No rooms available',
                '#dcdcde',
                'myvh-cal-key-item',
                'myvh-cal-key-swatch',
                'myvh-cal-key-label'
            ));
        }
    }

    function resolveRoomName(event, tags) {
        var direct = tags.room || tags.roomName || '';
        if (String(direct).trim() !== '') {
            return String(direct).trim();
        }

        var key = null;
        if (event && event.resource !== null && typeof event.resource !== 'undefined') {
            key = String(event.resource);
        } else if (tags.roomId !== null && typeof tags.roomId !== 'undefined') {
            key = String(tags.roomId);
        }

        if (key !== null && Object.prototype.hasOwnProperty.call(roomNameById, key)) {
            return roomNameById[key];
        }

        return '';
    }

    function formatTimeFromISO(isoDatetime) {
        if (!isoDatetime) return '';
        try {
            var date = new Date(isoDatetime);
            if (isNaN(date.getTime())) return '';
            var hours = String(date.getHours()).padStart(2, '0');
            var mins = String(date.getMinutes()).padStart(2, '0');
            return hours + ':' + mins;
        } catch (e) {
            return '';
        }
    }

    function isPublicBooking(tags) {
        if (!tags || !Object.prototype.hasOwnProperty.call(tags, 'isPublic')) {
            return true;
        }

        var raw = tags.isPublic;
        if (raw === true || raw === 1 || raw === '1') {
            return true;
        }

        if (raw === false || raw === 0 || raw === '0') {
            return false;
        }

        return String(raw).toLowerCase() !== 'false';
    }

    function canViewPrivateBooking(tags) {
        if (!tags || !Object.prototype.hasOwnProperty.call(tags, 'canViewPrivate')) {
            return false;
        }

        var raw = tags.canViewPrivate;
        if (raw === true || raw === 1 || raw === '1') {
            return true;
        }

        if (raw === false || raw === 0 || raw === '0') {
            return false;
        }

        return String(raw).toLowerCase() === 'true';
    }

    function buildEventTooltip(event) {
        var tooltipParts = [];
        var tags = event && event.tags ? event.tags : {};

        var room = resolveRoomName(event, tags);
        if (String(room).trim() !== '') {
            tooltipParts.push(String(room).trim());
        }

        // Add time range
        if (event.start && event.end) {
            var startTime = formatTimeFromISO(event.start);
            var endTime = formatTimeFromISO(event.end);
            if (startTime && endTime) {
                tooltipParts.push(startTime + '-' + endTime);
            }
        }

        var description = tags.description ? String(tags.description).trim() : '';

        if (!isPublicBooking(tags) && !canViewPrivateBooking(tags)) {
            tooltipParts.push('Private event');
        } else if (description !== '') {
            tooltipParts.push(description);
        }

        return tooltipParts.join('\n');
    }

    // ── Initialise on DOM ready ───────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById(cfg.containerId);
        if (!container) { return; }

        ensureThreeLetterEnglishWeekdays(headerDateFormat);

        wrap = container.closest('.myvh-public-calendar-wrap');
        initialiseVenueSelector();
        buildRoomIndex();
        renderCalendarKey();

        initNavigator();

        mountView(current.detail, current.mode);
        bindToolbar();
    });

    function getNavigatorSelectMode(detail) {
        switch (detail) {
            case 'day':
                return 'Day';
            case 'month':
                return 'Month';
            default:
                return 'Week';
        }
    }

    function syncNavigatorSelection() {
        if (!nav) { return; }
        try {
            var day = new DayPilot.Date(current.date);
            suppressNavSelect = true;
            nav.selectMode = getNavigatorSelectMode(current.detail);
            nav.select(day);
            nav.update();
        } catch (e) {
            // Keep calendar working even if navigator API differs across builds.
        } finally {
            suppressNavSelect = false;
        }
    }

    function initNavigator() {
        var navContainerId = cfg.navContainerId;
        if (!navContainerId) { return; }

        var navContainer = document.getElementById(navContainerId);
        if (!navContainer) { return; }

        try {
            nav = new DayPilot.Navigator(navContainerId, {
                showMonths: 3,
                skipMonths: 3,
                selectMode: getNavigatorSelectMode(current.detail),
                onTimeRangeSelected: function(args) {
                    if (suppressNavSelect) {
                        return;
                    }

                    current.date = args.day;

                    if (dp) {
                        dp.startDate = current.date;
                        dp.update();
                        loadEvents();
                    }

                    updateTitle();
                    syncNavigatorSelection();
                }
            });

            nav.init();
            syncNavigatorSelection();
        } catch (e) {
            nav = null;
            console.warn('MYVH calendar: failed to initialize navigator', e);
        }
    }

    // ── Mount / re-mount a DayPilot view ─────────────────────────────────────
    function mountView(detail, mode) {
        var previousDetail = current.detail;
        var previousMode = current.mode;
        var container = document.getElementById(cfg.containerId);
        var schedulerOrientation = String(cfg.schedulerOrientation || 'horizontal').trim().toLowerCase();

        current.detail = detail;
        current.mode = mode;

        // When moving from month to week/day, jump to the first visible event date
        // so week/day doesn't appear empty if today's range has no bookings.
        if (previousMode === 'calendar' && previousDetail === 'month' && (detail === 'week' || detail === 'day') && lastEvents.length > 0) {
            try {
                current.date = new DayPilot.Date(lastEvents[0].start);
            } catch (e) { /* keep current date */ }
        }

        updateModeButtons();
        updateDetailButtons();
        syncNavigatorSelection();

        // Dispose previous control
        if (dp) {
            try { dp.dispose(); } catch (e) { /* ignore */ }
            dp = null;
        }

        // Ensure previous view DOM is fully removed before mounting another DayPilot control.
        if (container) {
            container.innerHTML = '';
        }

        if (mode === 'scheduler') {
            if (schedulerOrientation === 'vertical') {
                dp = buildVerticalTimetableDp(container, detail);
            } else {
                dp = new DayPilot.Scheduler(cfg.containerId, buildSchedulerConfig(detail));
                dp.resources = buildSchedulerResources(lastEvents);
            }
        } else {
            switch (detail) {
                case 'week':
                    dp = new DayPilot.Calendar(cfg.containerId, buildWeekConfig(false));
                    break;
                case 'day':
                    dp = new DayPilot.Calendar(cfg.containerId, buildWeekConfig(true));
                    break;
                default:
                    dp = new DayPilot.Month(cfg.containerId, buildMonthConfig());
                    break;
            }
        }

        dp.init();
        loadEvents();
        updateTitle();
    }

    // ── Vertical timetable (rooms as columns, time slots as rows) ─────────────
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildVerticalTimetableDp(container, detail) {
        var startHour  = Number.isFinite(Number(cfg.visibleStartHour)) ? Number(cfg.visibleStartHour) : 8;
        var endHour    = Number.isFinite(Number(cfg.visibleEndHour))   ? Number(cfg.visibleEndHour)   : 22;
        var totalSlots = Math.max(1, endHour - startHour);
        var rooms      = getVisibleRooms();

        function getVisibleDays(startDate) {
            if (detail === 'day') {
                return 1;
            }

            if (detail === 'week') {
                return 7;
            }

            // Month: render the current month range.
            var jsDate = new Date(startDate.toString('yyyy-MM-dd') + 'T00:00:00');
            var y = jsDate.getFullYear();
            var m = jsDate.getMonth();
            return new Date(y, m + 1, 0).getDate();
        }

        function renderTimetable(vtDp) {
            var roomsForRender = rooms.length > 0 ? rooms : [ { id: 'all', name: 'Bookings' } ];
            var roomCount = roomsForRender.length;
            var dayCount = Math.max(1, vtDp.days);
            var rowCount = dayCount * totalSlots;
            var grid = [];
            var dayData = [];

            for (var r = 0; r < rowCount; r++) {
                var gridRow = [];
                for (var gc = 0; gc < roomCount; gc++) {
                    gridRow.push(null);
                }
                grid.push(gridRow);
            }

            for (var dayOffset = 0; dayOffset < dayCount; dayOffset++) {
                var dayDate = new DayPilot.Date(vtDp.startDate).addDays(dayOffset);
                dayData.push({
                    dayOffset: dayOffset,
                    dayDate: dayDate,
                    dayStr: dayDate.toString('yyyy-MM-dd'),
                    dayLabel: dayDate.toString('ddd d MMM')
                });
            }

            dayData.forEach(function(day) {
                var dayStart = new Date(day.dayStr + 'T00:00:00');
                var dayEnd = new Date(dayStart);
                dayEnd.setDate(dayEnd.getDate() + 1);

                roomsForRender.forEach(function(room, roomIndex) {
                    var col = roomIndex;

                    var matchingEvents = vtDp.events.list.filter(function(event) {
                        var rid = String(event.resource || (event.tags && event.tags.roomId) || '');
                        if (room.id !== 'all' && rid !== String(room.id)) {
                            return false;
                        }

                        var evStart = new Date(event.start);
                        var evEnd = new Date(event.end);
                        return evStart < dayEnd && evEnd > dayStart;
                    });

                    matchingEvents.forEach(function(event) {
                        var evStart = new Date(event.start);
                        var evEnd = new Date(event.end);
                        var clippedStart = evStart > dayStart ? evStart : dayStart;
                        var clippedEnd = evEnd < dayEnd ? evEnd : dayEnd;

                        var sh = clippedStart.getHours() + clippedStart.getMinutes() / 60;
                        var eh = clippedEnd.getHours() + clippedEnd.getMinutes() / 60;
                        var startSlot = Math.max(0, Math.floor(sh) - startHour);
                        var endSlot = Math.min(totalSlots, Math.ceil(eh) - startHour);
                        if (endSlot <= startSlot) {
                            endSlot = Math.min(totalSlots, startSlot + 1);
                        }

                        var span = Math.max(1, endSlot - startSlot);

                        if (startSlot < totalSlots) {
                            var startRow = day.dayOffset * totalSlots + startSlot;
                            if (grid[startRow][col] === null) {
                                grid[startRow][col] = { event: event, span: span };
                                for (var s = startSlot + 1; s < startSlot + span && s < totalSlots; s++) {
                                    grid[day.dayOffset * totalSlots + s][col] = 'occupied';
                                }
                            }
                        }
                    });
                });
            });

            var html = '<div class="myvh-timetable-scroll">';
            html += '<table class="myvh-timetable" role="grid">';
            html += '<thead><tr>';
            html += '<th class="myvh-tt-corner">Date</th>';
            html += '<th class="myvh-tt-corner-sub">Time</th>';
            roomsForRender.forEach(function(room) {
                html += '<th class="myvh-tt-room-header" scope="col">' + escHtml(room.name || '') + '</th>';
            });
            html += '</tr></thead><tbody>';

            for (var dayOffset = 0; dayOffset < dayCount; dayOffset++) {
                var day = dayData[dayOffset];

                for (var slot = 0; slot < totalSlots; slot++) {
                    var row = dayOffset * totalSlots + slot;
                    var hour = startHour + slot;
                    var hourStr = (hour < 10 ? '0' : '') + hour + ':00';
                    html += '<tr>';

                    if (slot === 0) {
                        html += '<th class="myvh-tt-date-cell" scope="rowgroup" rowspan="' + totalSlots + '">' + escHtml(day.dayLabel) + '</th>';
                    }

                    html += '<th class="myvh-tt-time-cell" scope="row">' + escHtml(hourStr) + '</th>';

                    for (var col = 0; col < roomCount; col++) {
                        var cell = grid[row][col];
                        if (cell === 'occupied') {
                            continue;
                        }

                        if (cell === null) {
                            html += '<td class="myvh-tt-empty-cell"></td>';
                        } else {
                            var event   = cell.event;
                            var span    = cell.span;
                                var accentColor = event.barColor || event.borderColor || '#2271b1';
                                var backgroundColor = event.backColor || DEFAULT_EVENT_BACKGROUND;
                                var textColor = event.fontColor || getReadableTextColour(backgroundColor);
                            var title   = event.text || 'Booking';
                            var tooltip = event.toolTip ? String(event.toolTip).replace(/\n/g, '&#10;') : '';
                            var startT  = formatTimeFromISO(event.start);
                            var endT    = formatTimeFromISO(event.end);
                            var timeStr = (startT && endT) ? (startT + '-' + endT) : '';

                            html += '<td class="myvh-tt-event-cell"'
                                  + ' rowspan="' + span + '"'
                                      + ' style="background:' + escHtml(backgroundColor) + ';color:' + escHtml(textColor) + ';border-left:4px solid ' + escHtml(accentColor) + ';"'
                                  + ' data-event-id="' + escHtml(String(event.id || '')) + '"'
                                  + ' title="' + escHtml(tooltip) + '">';
                            html += '<div class="myvh-tt-event-inner">'
                                  + '<span class="myvh-tt-event-title">' + escHtml(title) + '</span>';
                            if (timeStr) {
                                html += '<span class="myvh-tt-event-time">' + escHtml(timeStr) + '</span>';
                            }
                            html += '</div></td>';
                        }
                    }

                    html += '</tr>';
                }
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;

            // Bind click handlers
            var evList = vtDp.events.list;
            // Removed event click handler for public calendar
        }

        return {
            startDate: detail === 'month' ? new DayPilot.Date(current.date).firstDayOfMonth() : new DayPilot.Date(current.date).getDatePart(),
            days: getVisibleDays(new DayPilot.Date(current.date)),
            events: { list: [] },
            init:    function() {},
            update:  function() { renderTimetable(this); },
            dispose: function() {},
        };
    }

    function buildSchedulerConfig(detail) {
        var start = new DayPilot.Date(current.date);
        var maxBookingDaysAhead = Number.isFinite(Number(cfg.maxBookingDaysAhead)) ? Number(cfg.maxBookingDaysAhead) : 365;
        var startHour = Number.isFinite(Number(cfg.visibleStartHour)) ? Number(cfg.visibleStartHour) : 0;
        var endHour = Number.isFinite(Number(cfg.visibleEndHour)) ? Number(cfg.visibleEndHour) : 24;
        var schedulerDayHeaderFormat = 'ddd d';
        var base = {
            startDate: detail === 'month' ? start.firstDayOfMonth() : start,
            eventResourceField: 'resource',
            treeEnabled: true,
            rowHeaderWidth: 220,
            timeRangeSelectedHandling: 'Disabled',
            eventMoveHandling: 'Disabled',
            eventResizeHandling: 'Disabled',
            // onEventClick removed
            // onEventHover removed
            onBeforeRowHeaderRender: function(args) {
                var tags = (args.row && args.row.tags) ? args.row.tags : {};

                if (tags.type === 'venue') {
                    args.row.html = '<div style="font-weight:700;color:#2c3338;letter-spacing:.01em;">' + String(args.row.name || '') + '</div>';
                    return;
                }

                args.row.html = '<div style="padding-left:14px;color:#50575e;">' + String(args.row.name || '') + '</div>';
            },
        };

        if (detail === 'day') {
            base.startDate = DayPilot.Date.today().getDatePart();
            base.scale = 'Hour';
            base.days = Math.max(1, maxBookingDaysAhead);
            base.cellDuration = 60;
            base.timeHeaders = [
                { groupBy: 'Day', format: headerDateFormat },
                { groupBy: 'Hour' }
            ];
            base.businessBeginsHour = startHour;
            base.businessEndsHour = endHour;
        } else if (detail === 'month') {
            base.startDate = DayPilot.Date.today().getDatePart();
            base.scale = 'Day';
            base.days = Math.max(1, maxBookingDaysAhead);
            base.timeHeaders = [
                { groupBy: 'Month' },
                { groupBy: 'Day', format: schedulerDayHeaderFormat }
            ];
        } else {
            base.startDate = DayPilot.Date.today().getDatePart();
            base.scale = 'Day';
            base.days = Math.max(1, maxBookingDaysAhead);
            base.timeHeaders = [
                { groupBy: 'Month' },
                { groupBy: 'Day', format: schedulerDayHeaderFormat }
            ];
        }

        return base;
    }

    function buildSchedulerResources(events) {
        var visibleRooms = getVisibleRooms();

        if (Array.isArray(visibleRooms) && visibleRooms.length > 0) {
            var groups = {};

            visibleRooms.forEach(function (room) {
                var venueId = room.venueId || 'venue-unknown';
                var venueName = room.venue || 'Venue';

                if (!groups[venueId]) {
                    groups[venueId] = {
                        name: venueName,
                        rooms: []
                    };
                }

                groups[venueId].rooms.push({
                    id: room.id,
                    name: room.name || 'Room'
                });
            });

            var flatResources = [];

            Object.keys(groups).sort(function (a, b) {
                var aName = (groups[a] && groups[a].name) ? groups[a].name.toLowerCase() : '';
                var bName = (groups[b] && groups[b].name) ? groups[b].name.toLowerCase() : '';
                return aName.localeCompare(bName);
            }).forEach(function (venueId) {
                var venue = groups[venueId];

                // Non-bookable visual heading row for the venue.
                flatResources.push({
                    id: 'venue-heading-' + venueId,
                    name: venue.name,
                    tags: { type: 'venue' }
                });

                venue.rooms.sort(function (a, b) {
                    return String(a.name || '').toLowerCase().localeCompare(String(b.name || '').toLowerCase());
                }).forEach(function (room) {
                    flatResources.push({
                        id: room.id,
                        name: room.name,
                        tags: { type: 'room', venueId: venueId }
                    });
                });
            });

            return flatResources;
        }

        var byId = {};

        (events || []).forEach(function (event) {
            var id = event.resource || (event.tags && event.tags.roomId) || 'all';
            if (!id) {
                id = 'all';
            }

            if (!byId[id]) {
                var roomName = (event.tags && event.tags.room) ? event.tags.room : 'Room';
                byId[id] = {
                    id: id,
                    name: roomName
                };
            }
        });

        var list = Object.keys(byId).map(function (id) { return byId[id]; });

        if (list.length === 0) {
            list.push({ id: 'all', name: 'Bookings' });
        }

        return list;
    }

    // ── DayPilot Month config ─────────────────────────────────────────────────
    function buildMonthConfig() {
        return {
            startDate          : current.date,
            locale             : document.documentElement.lang || 'en-us',
            eventMoveHandling  : 'Disabled',
            eventResizeHandling: 'Disabled',
            // onEventClick removed
            // onEventHover removed
            cellHeaderClickHandling: 'Disabled',
        };
    }

    // ── DayPilot Calendar (week / day) config ─────────────────────────────────
    function buildWeekConfig(dayView) {
        var startHour = Number.isFinite(Number(cfg.visibleStartHour)) ? Number(cfg.visibleStartHour) : 0;
        var endHour = Number.isFinite(Number(cfg.visibleEndHour)) ? Number(cfg.visibleEndHour) : 24;

        return {
            viewType           : dayView ? 'Day' : 'Week',
            startDate          : current.date,
            headerDateFormat   : headerDateFormat,
            businessBeginsHour : startHour,
            businessEndsHour   : endHour,
            dayBeginsHour      : startHour,
            dayEndsHour        : endHour,
            eventMoveHandling  : 'Disabled',
            eventResizeHandling: 'Disabled',
            // onEventClick removed
            // onEventHover removed
            timeRangeSelectedHandling: 'Disabled',
        };
    }

    // ── Fetch events from REST API ────────────────────────────────────────────
    function loadEvents() {
        if (!dp) { return; }

        var requestId = ++loadRequestSeq;

        var range   = getVisibleRange();
        var url     = cfg.eventsUrl
                    + '?start=' + encodeURIComponent(range.start)
                    + '&end='   + encodeURIComponent(range.end);

        if (selectedVenueId > 0) { url += '&venue_id=' + selectedVenueId; }
        if (cfg.roomId  > 0) { url += '&room_id='  + cfg.roomId;  }

        fetch(url, {
            headers: { 'X-WP-Nonce': cfg.nonce },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (requestId !== loadRequestSeq) {
                return;
            }

            if (!Array.isArray(data)) {
                throw new Error('Unexpected events response payload');
            }

            lastEvents = data.slice();

            dp.events.list = data.map(function (e) {
                var normalized = normalizeEventRange(e.start, e.end);

                var tags = e.tags || {};
                var status = String(tags.status || '').toLowerCase();
                var accentColor = STATUS_COLOURS[status] || STATUS_COLOURS.confirmed;
                var roomColour = normaliseHexColour(tags.roomColour || tags.colour || e.backColor);
                var backgroundColour = roomColour || DEFAULT_EVENT_BACKGROUND;

                return {
                    id    : e.id,
                    start : normalized.start,
                    end   : normalized.end,
                    text  : e.text,
                    resource: e.resource,
                    tags  : tags,
                    toolTip: buildEventTooltip(e),
                    backColor : backgroundColour,
                    fontColor : getReadableTextColour(backgroundColour),
                    borderColor: accentColor,
                    barColor: accentColor,
                };
            });
            dp.update();
        })
        .catch(function (err) {
            if (requestId !== loadRequestSeq) {
                return;
            }
            console.error('MYVH calendar: failed to load events', err);
        });
    }

    function normalizeEventRange(start, end) {
        var s = new Date(start);
        var e = new Date(end);

        if (isNaN(s.getTime())) {
            return { start: start, end: end };
        }

        if (isNaN(e.getTime()) || e.getTime() <= s.getTime()) {
            // Ensure week/day views can render even if source event has zero/invalid duration.
            e = new Date(s.getTime() + (60 * 60 * 1000));
        }

        return {
            start: toIsoLocal(s),
            end: toIsoLocal(e),
        };
    }

    function toIsoLocal(d) {
        var pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    // ── Get visible date range for the active view ────────────────────────────
    function getVisibleRange() {
        if (!dp) {
            // Fallback
            var d   = new DayPilot.Date(current.date);
            var s   = d.firstDayOfMonth();
            var end = s.addMonths(1);
            return { start: s.toString('yyyy-MM-dd'), end: end.toString('yyyy-MM-dd') };
        }

        // Day/week scheduler/detail views can produce ambiguous visibleEnd values in some DayPilot builds,
        // so compute deterministic windows from the control startDate.
        if (current.detail === 'week' || current.detail === 'day') {
            var calStart = new DayPilot.Date(dp.startDate || current.date);
            return {
                start: calStart.toString('yyyy-MM-dd'),
                end: calStart.addDays(current.detail === 'day' ? 1 : 7).toString('yyyy-MM-dd'),
            };
        }

        try {
            // DayPilot Month exposes visibleStart / visibleEnd
            if (dp.visibleStart && dp.visibleEnd) {
                return {
                    start : dp.visibleStart().toString('yyyy-MM-dd'),
                    end   : dp.visibleEnd().toString('yyyy-MM-dd'),
                };
            }
        } catch (e) { /* fall through */ }

        // Month fallback
        var sd = new DayPilot.Date(dp.startDate || current.date);
        return {
            start : sd.firstDayOfMonth().toString('yyyy-MM-dd'),
            end   : sd.firstDayOfMonth().addMonths(1).toString('yyyy-MM-dd'),
        };
    }

    // ── Navigate ──────────────────────────────────────────────────────────────
    function navigate(direction) {
        var d = new DayPilot.Date(current.date);

        switch (current.detail) {
            case 'month':
                current.date = (direction > 0) ? d.addMonths(1).firstDayOfMonth()
                                               : d.addMonths(-1).firstDayOfMonth();
                break;
            case 'week':
                current.date = d.addDays(direction * 7);
                break;
            case 'day':
                current.date = d.addDays(direction);
                break;
        }

        dp.startDate = current.date;
        dp.update();
        loadEvents();
        syncNavigatorSelection();
        updateTitle();
    }

    function goToday() {
        current.date = DayPilot.Date.today();
        dp.startDate = current.date;
        dp.update();
        loadEvents();
        syncNavigatorSelection();
        updateTitle();
    }

    // ── Title ─────────────────────────────────────────────────────────────────
    function updateTitle() {
        if (!wrap) { return; }
        var el   = wrap.querySelector('.myvh-cal-title');
        if (!el) { return; }

        var d   = new DayPilot.Date(current.date);
        var out = '';

        switch (current.detail) {
            case 'month':
                out = d.toString(headerDateFormat);
                break;
            case 'week':
                var weekEnd = d.addDays(6);
                out = d.toString(headerDateFormat) + ' – ' + weekEnd.toString(headerDateFormat);
                break;
            case 'day':
                out = d.toString(headerDateFormat);
                break;
        }

        el.textContent = out;
    }

    // ── Event handlers ────────────────────────────────────────────────────────
    // handleEventClick and handleEventHover removed

    // ── Toolbar binding ───────────────────────────────────────────────────────
    function bindToolbar() {
        if (!wrap) { return; }

        var toolbar = wrap.querySelector('.myvh-cal-toolbar');
        if (!toolbar) { return; }

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn) { return; }

            if (btn.classList.contains('myvh-cal-prev'))  { navigate(-1); return; }
            if (btn.classList.contains('myvh-cal-next'))  { navigate(1);  return; }
            if (btn.classList.contains('myvh-cal-today')) { goToday();    return; }

            if (btn.classList.contains('myvh-mode-btn')) {
                var mode = btn.dataset.mode;
                if (mode) {
                    mountView(current.detail, mode);
                }
                return;
            }

            if (btn.classList.contains('myvh-detail-btn')) {
                var view = btn.dataset.view;
                if (view) { mountView(view, current.mode); }
            }
        });
    }

    function updateModeButtons() {
        if (!wrap) { return; }
        wrap.querySelectorAll('.myvh-mode-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.mode === current.mode);
        });
    }

    function updateDetailButtons() {
        if (!wrap) { return; }
        wrap.querySelectorAll('.myvh-detail-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.view === current.detail);
        });
    }

}());
