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
    var current = {
        view : cfg.view || 'month',
        date : DayPilot.Date.today(),
    };

    // ── Colour map ────────────────────────────────────────────────────────────
    var STATUS_COLOURS = {
        confirmed : '#2271b1',
        pending   : '#f0a500',
    };

    // ── Initialise on DOM ready ───────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById(cfg.containerId);
        if (!container) { return; }

        wrap = container.closest('.myvh-public-calendar-wrap');

        mountView(current.view);
        bindToolbar();
    });

    // ── Mount / re-mount a DayPilot view ─────────────────────────────────────
    function mountView(view) {
        current.view = view;
        updateViewButtons();

        // Dispose previous control
        if (dp) {
            try { dp.dispose(); } catch (e) { /* ignore */ }
            dp = null;
        }

        switch (view) {
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

        dp.init();
        loadEvents();
        updateTitle();
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
            theme              : 'myvh_cal',
        };
    }

    // ── DayPilot Calendar (week / day) config ─────────────────────────────────
    function buildWeekConfig(dayView) {
        return {
            viewType           : dayView ? 'Day' : 'Week',
            startDate          : current.date,
            locale             : document.documentElement.lang || 'en-us',
            eventMoveHandling  : 'Disabled',
            eventResizeHandling: 'Disabled',
            heightSpec         : 'Fixed',
            height             : cfg.height,
            onEventClick       : handleEventClick,
            onEventHover       : handleEventHover,
            theme              : 'myvh_cal',
            timeRangeSelectedHandling: 'Disabled',
        };
    }

    // ── Fetch events from REST API ────────────────────────────────────────────
    function loadEvents() {
        if (!dp) { return; }

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
            dp.events.list = data.map(function (e) {
                return {
                    id    : e.id,
                    start : e.start,
                    end   : e.end,
                    text  : e.text,
                    tags  : e.tags,
                    backColor : STATUS_COLOURS[e.tags && e.tags.status] || STATUS_COLOURS.confirmed,
                    fontColor : '#ffffff',
                    borderColor: 'darker',
                };
            });
            dp.update();
        })
        .catch(function (err) {
            console.error('MYVH calendar: failed to load events', err);
        });
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

        try {
            // DayPilot Month exposes visibleStart / visibleEnd
            if (dp.visibleStart && dp.visibleEnd) {
                return {
                    start : dp.visibleStart().toString('yyyy-MM-dd'),
                    end   : dp.visibleEnd().toString('yyyy-MM-dd'),
                };
            }
        } catch (e) { /* fall through */ }

        // Calendar week / day
        var sd = new DayPilot.Date(dp.startDate || current.date);
        return {
            start : sd.toString('yyyy-MM-dd'),
            end   : sd.addDays(current.view === 'day' ? 1 : 7).toString('yyyy-MM-dd'),
        };
    }

    // ── Navigate ──────────────────────────────────────────────────────────────
    function navigate(direction) {
        var d = new DayPilot.Date(current.date);

        switch (current.view) {
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
        updateTitle();
    }

    function goToday() {
        current.date = DayPilot.Date.today();
        dp.startDate = current.date;
        dp.update();
        loadEvents();
        updateTitle();
    }

    // ── Title ─────────────────────────────────────────────────────────────────
    function updateTitle() {
        if (!wrap) { return; }
        var el   = wrap.querySelector('.myvh-cal-title');
        if (!el) { return; }

        var d   = new DayPilot.Date(current.date);
        var out = '';

        switch (current.view) {
            case 'month':
                out = d.toString('MMMM yyyy');
                break;
            case 'week':
                var weekEnd = d.addDays(6);
                out = d.toString('d MMM') + ' – ' + weekEnd.toString('d MMM yyyy');
                break;
            case 'day':
                out = d.toString('dddd, d MMMM yyyy');
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
        if (args.e && args.e.data && args.e.data.tags) {
            var t = args.e.data.tags;
            args.e.data.toolTip = [
                t.venue || '',
                t.room  || '',
            ].filter(Boolean).join(' › ');
        }
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

            if (btn.classList.contains('myvh-view-btn')) {
                var view = btn.dataset.view;
                if (view) { mountView(view); }
            }
        });
    }

    function updateViewButtons() {
        if (!wrap) { return; }
        wrap.querySelectorAll('.myvh-view-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.view === current.view);
        });
    }

}());
