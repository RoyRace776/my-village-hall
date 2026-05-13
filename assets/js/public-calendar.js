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

    let cfg     = myvhCalConfig;
    let wrap    = null;   // .myvh-public-calendar-wrap
    let dp      = null;   // active DayPilot control
    let nav     = null;   // DayPilot navigator control
    let allRooms = Array.isArray(cfg.rooms) ? cfg.rooms.slice() : [];
    let selectedVenueId = 0;
    let pinnedVenueId = parseInt(cfg.venueId || '0', 10) || 0;
    let VENUE_STORAGE_KEY = 'myvhCalendarVenue_public';
    let lastEvents = [];
        let selectedRoomIds = new Set();
    let roomNameById = {};
    let loadRequestSeq = 0;
    let suppressNavSelect = false;
    let headerDateFormat = cfg.headerDateFormat || 'd MMM';
    let current = {
        mode : 'calendar',
        detail : cfg.view || 'month',
        date : DayPilot.Date.today(),
    };

    function getSchedulerWeekCellWidth() {
        let container = document.getElementById(cfg.containerId);
        let containerWidth = Math.round(
            (container && container.getBoundingClientRect ? container.getBoundingClientRect().width : 0)
            || (container && container.clientWidth)
            || (container && container.parentElement && container.parentElement.clientWidth)
            || 0
        );
        let rowHeaderWidth = 220;
        let availableWidth = containerWidth - rowHeaderWidth - 1;

        if (!Number.isFinite(availableWidth) || availableWidth <= 0) {
            return null;
        }

        return Math.max(40, Math.floor(availableWidth / 7));
    }

    // ── Colour map ────────────────────────────────────────────────────────────
    let DEFAULT_STATUS_COLOURS = {
        confirmed : '#2271b1',
        pending   : '#f0a500',
        cancelled : '#9aa0a6',
        completed : '#2d8f45',
    };
    let DEFAULT_EVENT_BACKGROUND = '#f7f3ee';
    let STATUS_COLOURS = Object.assign({}, DEFAULT_STATUS_COLOURS, (cfg.statusColors && typeof cfg.statusColors === 'object') ? cfg.statusColors : {});

    function normaliseHexColour(value) {
        let text = String(value || '').trim().toLowerCase();
        return /^#[0-9a-f]{6}$/.test(text) ? text : '';
    }

    function getReadableTextColour(backgroundColour) {
        let hex = normaliseHexColour(backgroundColour);
        if (!hex) {
            return '#1f2933';
        }

        let red = parseInt(hex.slice(1, 3), 16);
        let green = parseInt(hex.slice(3, 5), 16);
        let blue = parseInt(hex.slice(5, 7), 16);
        let brightness = (red * 299 + green * 587 + blue * 114) / 1000;

        return brightness < 145 ? '#ffffff' : '#1f2933';
    }

    function buildRoomIndex() {
        roomNameById = {};

        let visibleRooms = getVisibleRooms();
        if (!Array.isArray(visibleRooms)) {
            return;
        }

        visibleRooms.forEach(function(room) {
            if (!room) {
                return;
            }

            let id = room.id;
            let name = room.name;

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
        let byId = {};

        allRooms.forEach(function(room) {
            let venueId = parseInt((room && room.venueId) || (room && room.venue_id) || '0', 10) || 0;
            let venueName = String((room && room.venue) || (room && room.VenueName) || '').trim();

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
            let venueFiltered = getVenueFilteredRooms();
            if (selectedRoomIds.size === 0) {
                return venueFiltered;
            }
            return venueFiltered.filter(function(room) {
            return !selectedRoomIds.has(parseInt((room && room.id) || 0, 10));
            });
        }

        function getVenueFilteredRooms() {
            if (selectedVenueId <= 0) {
                return allRooms.slice();
            }
            return allRooms.filter(function(room) {
            let venueId = parseInt((room && room.venueId) || (room && room.venue_id) || '0', 10) || 0;
            return venueId === selectedVenueId;
        });
    }

    function initialiseVenueSelector() {
        let venues = buildVenueList();
        let venueWrap = wrap ? wrap.querySelector('.myvh-cal-venue-filter') : null;
        let venueSelect = wrap ? wrap.querySelector('.myvh-cal-venue-select') : null;

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

        let persistedVenueId = getPersistedVenueId();
        let knownVenueIds = venues.map(function(venue) { return venue.id; });
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
            let option = document.createElement('option');
            option.value = String(venue.id);
            option.textContent = venue.name;
            option.selected = venue.id === selectedVenueId;
            venueSelect.appendChild(option);
        });

        venueSelect.addEventListener('change', function() {
            selectedVenueId = parseInt(venueSelect.value || '0', 10) || 0;
            cfg.venueId = selectedVenueId;
            setPersistedVenueId(selectedVenueId);
                selectedRoomIds = new Set();
            buildRoomIndex();
            renderCalendarKey();
                renderRoomFilter();
            mountView(current.detail, current.mode);
        });
    }

    function toStatusLabel(status) {
        return String(status || '')
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, function(match) { return match.toUpperCase(); });
    }

    function createLegendItem(label, colour, itemClass, swatchClass, labelClass) {
        let item = document.createElement('span');
        item.className = itemClass;

        let swatch = document.createElement('span');
        swatch.className = swatchClass;
        swatch.style.backgroundColor = colour;

        let text = document.createElement('span');
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

        let keyRoot = wrap.querySelector('.myvh-cal-key');
        if (!keyRoot) {
            return;
        }

        let statusWrap = keyRoot.querySelector('.myvh-cal-key-status-items');
        let roomWrap = keyRoot.querySelector('.myvh-cal-key-room-items');
        if (!statusWrap || !roomWrap) {
            return;
        }

        statusWrap.innerHTML = '';
        roomWrap.innerHTML = '';

        Object.keys(STATUS_COLOURS).forEach(function(status) {
            let colour = normaliseHexColour(STATUS_COLOURS[status]);
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

        let seenRoomIds = new Set();
        getVisibleRooms().forEach(function(room) {
            if (!room || room.id === null || typeof room.id === 'undefined') {
                return;
            }

            let roomId = String(room.id);
            if (seenRoomIds.has(roomId)) {
                return;
            }

            let roomName = String(room.name || '').trim();
            let roomColour = normaliseHexColour(room.roomColour || room.colour);
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

        function renderRoomFilter() {
            let filterEl = wrap ? wrap.querySelector('.myvh-cal-room-filter') : null;
            if (!filterEl) {
                return;
            }

            let venueRooms = getVenueFilteredRooms();

            if (venueRooms.length <= 1) {
                filterEl.style.display = 'none';
                filterEl.innerHTML = '';
                return;
            }

            if (current.mode === 'scheduler') {
                filterEl.style.display = 'none';
                return;
            }

            filterEl.style.display = '';
            filterEl.innerHTML = '';

            venueRooms.forEach(function(room) {
                let roomId = parseInt((room && room.id) || 0, 10);
                let roomName = String((room && room.name) || '').trim();
                let roomColour = normaliseHexColour((room && (room.roomColour || room.colour)) || '');
                if (!roomName) {
                    return;
                }

                let item = document.createElement('label');
                item.className = 'myvh-room-filter-item';

                let cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = !selectedRoomIds.has(roomId);
                cb.addEventListener('change', function() {
                    if (cb.checked) {
                        selectedRoomIds.delete(roomId);
                    } else {
                        selectedRoomIds.add(roomId);
                    }
                    let allChecked = venueRooms.every(function(r) {
                        return !selectedRoomIds.has(parseInt((r && r.id) || 0, 10));
                    });
                    if (allChecked) {
                        selectedRoomIds = new Set();
                    }
                    buildRoomIndex();
                    renderCalendarKey();
                    loadEvents();
                });

                item.appendChild(cb);

                if (roomColour) {
                    let swatch = document.createElement('span');
                    swatch.className = 'myvh-room-filter-swatch';
                    swatch.style.backgroundColor = roomColour;
                    swatch.setAttribute('aria-hidden', 'true');
                    item.appendChild(swatch);
                }

                let label = document.createElement('span');
                label.className = 'myvh-room-filter-name';
                label.textContent = roomName;
                item.appendChild(label);

                filterEl.appendChild(item);
            });
        }

    function resolveRoomName(event, tags) {
        let direct = tags.room || tags.roomName || '';
        if (String(direct).trim() !== '') {
            return String(direct).trim();
        }

        let key = null;
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
            let date = new Date(isoDatetime);
            if (isNaN(date.getTime())) return '';
            let hours = String(date.getHours()).padStart(2, '0');
            let mins = String(date.getMinutes()).padStart(2, '0');
            return hours + ':' + mins;
        } catch (e) {
            return '';
        }
    }

    function isPublicBooking(tags) {
        if (!tags || !Object.prototype.hasOwnProperty.call(tags, 'isPublic')) {
            return true;
        }

        let raw = tags.isPublic;
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

        let raw = tags.canViewPrivate;
        if (raw === true || raw === 1 || raw === '1') {
            return true;
        }

        if (raw === false || raw === 0 || raw === '0') {
            return false;
        }

        return String(raw).toLowerCase() === 'true';
    }

    function buildEventTooltip(event) {
        let tooltipParts = [];
        let tags = event && event.tags ? event.tags : {};

        let room = resolveRoomName(event, tags);
        if (String(room).trim() !== '') {
            tooltipParts.push(String(room).trim());
        }

        // Add time range
        if (event.start && event.end) {
            let startTime = formatTimeFromISO(event.start);
            let endTime = formatTimeFromISO(event.end);
            if (startTime && endTime) {
                tooltipParts.push(startTime + '-' + endTime);
            }
        }

        let description = tags.description ? String(tags.description).trim() : '';

        if (!isPublicBooking(tags) && !canViewPrivateBooking(tags)) {
            tooltipParts.push('Private event');
        } else if (description !== '') {
            tooltipParts.push(description);
        }

        return tooltipParts.join('\n');
    }

    // ── Initialise on DOM ready ───────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        let container = document.getElementById(cfg.containerId);
        if (!container) { return; }

        if (typeof ensureThreeLetterEnglishWeekdays === 'function') {
            ensureThreeLetterEnglishWeekdays(headerDateFormat);
        }

        wrap = container.closest('.myvh-public-calendar-wrap');
        initialiseVenueSelector();
        buildRoomIndex();
        renderCalendarKey();
            renderRoomFilter();

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
            let day = new DayPilot.Date(current.date);
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
        let navContainerId = cfg.navContainerId;
        if (!navContainerId) { return; }

        let navContainer = document.getElementById(navContainerId);
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
        let previousDetail = current.detail;
        let previousMode = current.mode;
        let container = document.getElementById(cfg.containerId);
        let schedulerOrientation = String(cfg.schedulerOrientation || 'horizontal').trim().toLowerCase();

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
        let startHour  = Number.isFinite(Number(cfg.visibleStartHour)) ? Number(cfg.visibleStartHour) : 8;
        let endHour    = Number.isFinite(Number(cfg.visibleEndHour))   ? Number(cfg.visibleEndHour)   : 22;
        let cellDuration = 15;  // 15-minute intervals
        let slotsPerHour = 60 / cellDuration;  // 4 slots per hour
        let totalSlots = Math.max(1, (endHour - startHour) * slotsPerHour);
        let rooms      = getVisibleRooms();

        function getVisibleDays(startDate) {
            if (detail === 'day') {
                return 1;
            }

            if (detail === 'week') {
                return 7;
            }

            // Month: render the current month range.
            let jsDate = new Date(startDate.toString('yyyy-MM-dd') + 'T00:00:00');
            let y = jsDate.getFullYear();
            let m = jsDate.getMonth();
            return new Date(y, m + 1, 0).getDate();
        }

        function renderTimetable(vtDp) {
            let roomsForRender = rooms.length > 0 ? rooms : [ { id: 'all', name: 'Bookings' } ];
            let roomCount = roomsForRender.length;
            let dayCount = Math.max(1, vtDp.days);
            let rowCount = dayCount * totalSlots;
            let grid = [];
            let dayData = [];

            for (let r = 0; r < rowCount; r++) {
                let gridRow = [];
                for (let gc = 0; gc < roomCount; gc++) {
                    gridRow.push(null);
                }
                grid.push(gridRow);
            }

            for (let dayOffset = 0; dayOffset < dayCount; dayOffset++) {
                let dayDate = new DayPilot.Date(vtDp.startDate).addDays(dayOffset);
                dayData.push({
                    dayOffset: dayOffset,
                    dayDate: dayDate,
                    dayStr: dayDate.toString('yyyy-MM-dd'),
                    dayLabel: dayDate.toString('ddd d MMM')
                });
            }

            dayData.forEach(function(day) {
                let dayStart = new Date(day.dayStr + 'T00:00:00');
                let dayEnd = new Date(dayStart);
                dayEnd.setDate(dayEnd.getDate() + 1);

                roomsForRender.forEach(function(room, roomIndex) {
                    let col = roomIndex;

                    let matchingEvents = vtDp.events.list.filter(function(event) {
                        let rid = String(event.resource || (event.tags && event.tags.roomId) || '');
                        if (room.id !== 'all' && rid !== String(room.id)) {
                            return false;
                        }

                        let evStart = new Date(event.start);
                        let evEnd = new Date(event.end);
                        return evStart < dayEnd && evEnd > dayStart;
                    });

                    matchingEvents.forEach(function(event) {
                        let evStart = new Date(event.start);
                        let evEnd = new Date(event.end);
                        let clippedStart = evStart > dayStart ? evStart : dayStart;
                        let clippedEnd = evEnd < dayEnd ? evEnd : dayEnd;

                        let sh = clippedStart.getHours() + clippedStart.getMinutes() / 60;
                        let eh = clippedEnd.getHours() + clippedEnd.getMinutes() / 60;
                        let startSlot = Math.max(0, Math.floor((sh - startHour) * slotsPerHour));
                        let endSlot = Math.min(totalSlots, Math.ceil((eh - startHour) * slotsPerHour));
                        if (endSlot <= startSlot) {
                            endSlot = Math.min(totalSlots, startSlot + 1);
                        }

                        let span = Math.max(1, endSlot - startSlot);

                        if (startSlot < totalSlots) {
                            let startRow = day.dayOffset * totalSlots + startSlot;
                            if (grid[startRow][col] === null) {
                                grid[startRow][col] = { event: event, span: span };
                                for (let s = startSlot + 1; s < startSlot + span && s < totalSlots; s++) {
                                    grid[day.dayOffset * totalSlots + s][col] = 'occupied';
                                }
                            }
                        }
                    });
                });
            });

            let timetableClass = detail === 'day' ? 'myvh-timetable myvh-timetable--day' : 'myvh-timetable';
            let html = '<div class="myvh-timetable-scroll">';
            html += '<table class="' + timetableClass + '" role="grid">';
            html += '<thead><tr>';
            html += '<th class="myvh-tt-corner">Date</th>';
            html += '<th class="myvh-tt-corner-sub">Time</th>';
            roomsForRender.forEach(function(room) {
                html += '<th class="myvh-tt-room-header" scope="col">' + escHtml(room.name || '') + '</th>';
            });
            html += '</tr></thead><tbody>';

            for (let dayOffset = 0; dayOffset < dayCount; dayOffset++) {
                let day = dayData[dayOffset];

                for (let slot = 0; slot < totalSlots; slot++) {
                    let row = dayOffset * totalSlots + slot;
                    let totalMinutes = Math.floor((slot / slotsPerHour) * 60);
                    let hour = startHour + Math.floor(totalMinutes / 60);
                    let minutes = totalMinutes % 60;
                    let hourStr = (hour < 10 ? '0' : '') + hour + ':' + (minutes < 10 ? '0' : '') + minutes;
                    html += '<tr>';

                    if (slot === 0) {
                        html += '<th class="myvh-tt-date-cell" scope="rowgroup" rowspan="' + totalSlots + '">' + escHtml(day.dayLabel) + '</th>';
                    }

                    html += '<th class="myvh-tt-time-cell" scope="row">' + escHtml(hourStr) + '</th>';

                    for (let col = 0; col < roomCount; col++) {
                        let cell = grid[row][col];
                        if (cell === 'occupied') {
                            continue;
                        }

                        if (cell === null) {
                            html += '<td class="myvh-tt-empty-cell"></td>';
                        } else {
                            let event   = cell.event;
                            let span    = cell.span;
                                let accentColor = event.barColor || event.borderColor || '#2271b1';
                                let backgroundColor = event.backColor || DEFAULT_EVENT_BACKGROUND;
                                let textColor = event.fontColor || getReadableTextColour(backgroundColor);
                            let title   = event.text || 'Booking';
                            let tooltip = event.toolTip ? String(event.toolTip).replace(/\n/g, '&#10;') : '';
                            let startT  = formatTimeFromISO(event.start);
                            let endT    = formatTimeFromISO(event.end);
                            let timeStr = (startT && endT) ? (startT + '-' + endT) : '';

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
            let evList = vtDp.events.list;
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
        let start = new DayPilot.Date(current.date);
        let maxBookingDaysAhead = Number.isFinite(Number(cfg.maxBookingDaysAhead)) ? Number(cfg.maxBookingDaysAhead) : 365;
        let startHour = Number.isFinite(Number(cfg.visibleStartHour)) ? Number(cfg.visibleStartHour) : 0;
        let endHour = Number.isFinite(Number(cfg.visibleEndHour)) ? Number(cfg.visibleEndHour) : 24;
        let schedulerDayHeaderFormat = 'ddd d';
        let base = {
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
                let tags = (args.row && args.row.tags) ? args.row.tags : {};

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
            base.cellDuration = 15;
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
            base.days = 7;
            base.timeHeaders = [
                { groupBy: 'Month' },
                { groupBy: 'Day', format: schedulerDayHeaderFormat }
            ];

            let weekCellWidth = getSchedulerWeekCellWidth();
            if (weekCellWidth !== null) {
                base.cellWidth = weekCellWidth;
            }
        }

        return base;
    }

    function buildSchedulerResources(events) {
        let visibleRooms = getVisibleRooms();

        if (Array.isArray(visibleRooms) && visibleRooms.length > 0) {
            let groups = {};

            visibleRooms.forEach(function (room) {
                let venueId = room.venueId || 'venue-unknown';
                let venueName = room.venue || 'Venue';

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

            let flatResources = [];

            Object.keys(groups).sort(function (a, b) {
                let aName = (groups[a] && groups[a].name) ? groups[a].name.toLowerCase() : '';
                let bName = (groups[b] && groups[b].name) ? groups[b].name.toLowerCase() : '';
                return aName.localeCompare(bName);
            }).forEach(function (venueId) {
                let venue = groups[venueId];

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

        let byId = {};

        (events || []).forEach(function (event) {
            let id = event.resource || (event.tags && event.tags.roomId) || 'all';
            if (!id) {
                id = 'all';
            }

            if (!byId[id]) {
                let roomName = (event.tags && event.tags.room) ? event.tags.room : 'Room';
                byId[id] = {
                    id: id,
                    name: roomName
                };
            }
        });

        let list = Object.keys(byId).map(function (id) { return byId[id]; });

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
        let startHour = Number.isFinite(Number(cfg.visibleStartHour)) ? Number(cfg.visibleStartHour) : 0;
        let endHour = Number.isFinite(Number(cfg.visibleEndHour)) ? Number(cfg.visibleEndHour) : 24;

        return {
            viewType           : dayView ? 'Day' : 'Week',
            startDate          : current.date,
            headerDateFormat   : headerDateFormat,
            cellDuration       : 15,
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

        let requestId = ++loadRequestSeq;

        let range   = getVisibleRange();
        let url     = cfg.eventsUrl
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

                let eventsToRender = data;
                if (current.mode !== 'scheduler' && selectedRoomIds.size > 0) {
                    eventsToRender = data.filter(function(e) {
                        let rid = parseInt((e.tags && e.tags.roomId) || e.resource || 0, 10);
                        return !selectedRoomIds.has(rid);
                    });
                }

                dp.events.list = eventsToRender.map(function (e) {
                let normalized = normalizeEventRange(e.start, e.end);

                let tags = e.tags || {};
                let status = String(tags.status || '').toLowerCase();
                let accentColor = STATUS_COLOURS[status] || STATUS_COLOURS.confirmed;
                let roomColour = normaliseHexColour(tags.roomColour || tags.colour || e.backColor);
                let backgroundColour = roomColour || DEFAULT_EVENT_BACKGROUND;

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
        let s = new Date(start);
        let e = new Date(end);

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
        let pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }

    // ── Get visible date range for the active view ────────────────────────────
    function getVisibleRange() {
        if (!dp) {
            // Fallback
            let d   = new DayPilot.Date(current.date);
            let s   = d.firstDayOfMonth();
            let end = s.addMonths(1);
            return { start: s.toString('yyyy-MM-dd'), end: end.toString('yyyy-MM-dd') };
        }

        // Day/week scheduler/detail views can produce ambiguous visibleEnd values in some DayPilot builds,
        // so compute deterministic windows from the control startDate.
        if (current.detail === 'week' || current.detail === 'day') {
            let calStart = new DayPilot.Date(dp.startDate || current.date);
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
        let sd = new DayPilot.Date(dp.startDate || current.date);
        return {
            start : sd.firstDayOfMonth().toString('yyyy-MM-dd'),
            end   : sd.firstDayOfMonth().addMonths(1).toString('yyyy-MM-dd'),
        };
    }

    // ── Navigate ──────────────────────────────────────────────────────────────
    function navigate(direction) {
        let d = new DayPilot.Date(current.date);

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
        let el   = wrap.querySelector('.myvh-cal-title');
        if (!el) { return; }

        let d   = new DayPilot.Date(current.date);
        let out = '';

        switch (current.detail) {
            case 'month':
                out = d.toString(headerDateFormat);
                break;
            case 'week':
                let weekEnd = d.addDays(6);
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

        let toolbar = wrap.querySelector('.myvh-cal-toolbar');
        if (!toolbar) { return; }

        toolbar.addEventListener('click', function (e) {
            let btn = e.target.closest('button');
            if (!btn) { return; }

            if (btn.classList.contains('myvh-cal-prev'))  { navigate(-1); return; }
            if (btn.classList.contains('myvh-cal-next'))  { navigate(1);  return; }
            if (btn.classList.contains('myvh-cal-today')) { goToday();    return; }

            if (btn.classList.contains('myvh-mode-btn')) {
                let mode = btn.dataset.mode;
                if (mode) {
                    mountView(current.detail, mode);
                        renderRoomFilter();
                }
                return;
            }

            if (btn.classList.contains('myvh-detail-btn')) {
                let view = btn.dataset.view;
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
