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
    var STATUS_COLOURS = {
        confirmed : '#2271b1',
        pending   : '#f0a500',
    };

    function buildRoomIndex() {
        roomNameById = {};

        if (!Array.isArray(cfg.rooms)) {
            return;
        }

        cfg.rooms.forEach(function(room) {
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

        if (!key) {
            return '';
        }

        return roomNameById[key] || '';
    }

    function formatUsesShortWeekday(format) {
        return typeof format === 'string' && /(^|[^d])ddd([^d]|$)/.test(format);
    }

    function ensureThreeLetterEnglishWeekdays(format) {
        if (!formatUsesShortWeekday(format) || !DayPilot || !DayPilot.Locale || !DayPilot.Locale.all) {
            return;
        }

        Object.keys(DayPilot.Locale.all).forEach(function(localeId) {
            if (!/^en($|-)/i.test(localeId)) {
                return;
            }

            var locale = DayPilot.Locale.find(localeId);

            if (!locale || !Array.isArray(locale.dayNames) || locale.dayNames.length !== 7) {
                return;
            }

            locale.dayNamesShort = locale.dayNames.map(function(dayName) {
                return String(dayName).slice(0, 3);
            });
        });
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
        buildRoomIndex();

        wrap = container.closest('.myvh-public-calendar-wrap');

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
            dp = new DayPilot.Scheduler(cfg.containerId, buildSchedulerConfig(detail));
            dp.resources = buildSchedulerResources(lastEvents);
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
            onEventClick: handleEventClick,
            onEventHover: handleEventHover,
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
        if (Array.isArray(cfg.rooms) && cfg.rooms.length > 0) {
            var groups = {};

            cfg.rooms.forEach(function (room) {
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
            onEventClick       : handleEventClick,
            onEventHover       : handleEventHover,
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
            onEventClick       : handleEventClick,
            onEventHover       : handleEventHover,
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

        if (cfg.venueId > 0) { url += '&venue_id=' + cfg.venueId; }
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

                return {
                    id    : e.id,
                    start : normalized.start,
                    end   : normalized.end,
                    text  : e.text,
                    resource: e.resource,
                    tags  : e.tags,
                    toolTip: buildEventTooltip(e),
                    backColor : STATUS_COLOURS[e.tags && e.tags.status] || STATUS_COLOURS.confirmed,
                    fontColor : '#ffffff',
                    borderColor: 'darker',
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
    function handleEventClick(args) {
        // Read-only calendar: show a simple tooltip / no action needed.
        // You can extend this to open a details modal if desired.
    }

    function handleEventHover(args) {
        // Tooltips are already set in loadEvents with time and description
        // This serves as a fallback if needed
    }

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
