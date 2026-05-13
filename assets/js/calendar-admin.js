// Admin calendar logic for My Village Hall
window.CalendarAdmin = (function() {

    let api = null;
    let nav = null;
    let suppressNavSelect = false;
    let selectedVenueId = 0;
    let allVenues = [];
    let selectedRoomIds = new Set();
    let allAdminRooms = [];
    let DEFAULT_STATUS_COLORS = {
        confirmed: '#2271b1',
        pending: '#f0a500',
        cancelled: '#9aa0a6',
        completed: '#2d8f45'
    };
    let VENUE_STORAGE_KEY = 'myvhCalendarVenue_admin';

    function normaliseHexColour(value) {
        let text = String(value || '').trim().toLowerCase();
        return /^#[0-9a-f]{6}$/.test(text) ? text : '';
    }

    function toStatusLabel(status) {
        return String(status || '')
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, function(match) { return match.toUpperCase(); });
    }

    function portalAlert(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
            return window.MyvhPortalDialog.alert(message);
        }

        return Promise.resolve(true);
    }

    function createLegendItem(label, colour) {
        let item = document.createElement('span');
        item.className = 'myvh-calendar-key-item';

        let swatch = document.createElement('span');
        swatch.className = 'myvh-calendar-key-swatch';
        swatch.style.backgroundColor = colour;

        let text = document.createElement('span');
        text.className = 'myvh-calendar-key-label';
        text.textContent = label;

        item.appendChild(swatch);
        item.appendChild(text);
        return item;
    }

    function renderCalendarKey(rooms) {
        let root = document.getElementById('myvh-calendar-key');
        if (!root) {
            return;
        }

        let statusWrap = root.querySelector('.myvh-calendar-key-status-items');
        let roomWrap = root.querySelector('.myvh-calendar-key-room-items');
        if (!statusWrap || !roomWrap) {
            return;
        }

        statusWrap.innerHTML = '';
        roomWrap.innerHTML = '';

        let statusColors = Object.assign({}, DEFAULT_STATUS_COLORS, (myvhCal && typeof myvhCal.statusColors === 'object') ? myvhCal.statusColors : {});

        Object.keys(statusColors).forEach(function(status) {
            let colour = normaliseHexColour(statusColors[status]);
            if (!colour) {
                return;
            }

            statusWrap.appendChild(createLegendItem(toStatusLabel(status), colour));
        });

        let seenRoomIds = new Set();
        (Array.isArray(rooms) ? rooms : []).forEach(function(room) {
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
            roomWrap.appendChild(createLegendItem(roomName, roomColour));
        });

        if (!roomWrap.children.length) {
            roomWrap.appendChild(createLegendItem('No rooms available', '#dcdcde'));
        }
    }

    function renderRoomFilter(rooms) {
        let container = document.getElementById('myvh-calendar-room-filter');
        if (!container) {
            return;
        }

        let venueRooms = (Array.isArray(rooms) ? rooms : []).filter(function(room) {
            if (selectedVenueId <= 0) return true;
            let vid = parseInt((room && (room.venue_id || room.venueId)) || 0, 10);
            return vid === selectedVenueId;
        });

        if (venueRooms.length <= 1) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        let currentState = api && typeof api.getState === 'function' ? api.getState() : { mode: 'Calendar' };
        if (currentState.mode === 'Scheduler') {
            container.style.display = 'none';
            return;
        }

        container.style.display = '';
        container.innerHTML = '';

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
                if (selectedRoomIds.size === venueRooms.length) {
                    selectedRoomIds = new Set();
                }
                onRoomFilterChange();
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

            container.appendChild(item);
        });
    }

    function onRoomFilterChange() {
        let state = api && typeof api.getState === 'function' ? api.getState() : {};
        if (state.mode === 'Scheduler') {
            return;
        }
        if (api && typeof api.reload === 'function') {
            api.reload();
        }
        renderCalendarKey(allAdminRooms);
    }

    function syncRoomFilterVisibility() {
        renderRoomFilter(allAdminRooms);
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

    function buildVenuesFromRooms(rooms) {
        let byId = {};

        (Array.isArray(rooms) ? rooms : []).forEach(function(room) {
            let venueId = parseInt(room && room.venue_id, 10) || 0;
            let venueName = String((room && room.venue) || '').trim();

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

    function resolveInitialVenueId(venues) {
        if (!Array.isArray(venues) || venues.length === 0) {
            return 0;
        }

        let venueIds = venues.map(function(venue) { return venue.id; });

        if (venues.length === 1) {
            return venueIds[0];
        }

        let persistedVenueId = getPersistedVenueId();
        if (persistedVenueId > 0 && venueIds.indexOf(persistedVenueId) !== -1) {
            return persistedVenueId;
        }

        return venueIds[0];
    }

    function loadRoomsForVenue(venueId) {
        let url = myvhCal.ajax_url + '?action=myvh_calendar_rooms&nonce=' + encodeURIComponent(myvhCal.nonce) + '&context=admin';
        if (venueId > 0) {
            url += '&venue_id=' + encodeURIComponent(venueId);
        }

        return fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(payload) {
                return Array.isArray(payload) ? payload : (Array.isArray(payload && payload.data) ? payload.data : []);
            });
    }

    function loadCalendarRooms() {
        return loadRoomsForVenue(selectedVenueId);
    }

    function renderVenueSelector() {
        let wrap = document.getElementById('myvh-calendar-venue-wrap');
        let select = document.getElementById('myvh-calendar-venue-select');

        if (!wrap || !select) {
            return;
        }

        if (!Array.isArray(allVenues) || allVenues.length <= 1) {
            wrap.style.display = 'none';
            select.innerHTML = '';
            return;
        }

        wrap.style.display = '';
        select.innerHTML = '';

        allVenues.forEach(function(venue) {
            let option = document.createElement('option');
            option.value = String(venue.id);
            option.textContent = venue.name;
            option.selected = venue.id === selectedVenueId;
            select.appendChild(option);
        });

        select.onchange = function() {
            let nextVenueId = parseInt(select.value || '0', 10) || 0;
            selectedVenueId = nextVenueId;
            setPersistedVenueId(nextVenueId);
            selectedRoomIds = new Set();

            if (api && typeof api.rerender === 'function') {
                api.rerender();
                syncNavigator();
            }

            loadCalendarRooms()
                .then(function(rooms) {
                    allAdminRooms = rooms;
                    renderRoomFilter(rooms);
                    renderCalendarKey(rooms);
                })
                .catch(function() {
                    allAdminRooms = [];
                    renderRoomFilter([]);
                    renderCalendarKey([]);
                });
        };
    }

    /**
     * Get the navigation select mode (Day/Week/Month) based on detail.
     */
    function getNavSelectMode(detail) {
        if (detail === 'Day') {
            return 'Day';
        }
        if (detail === 'Month') {
            return 'Month';
        }
        return 'Week';
    }

    /**
     * Sync the DayPilot navigator with the current calendar state.
     */
    function syncNavigator() {
        if (!nav || !api?.getState) {
            return;
        }

        const state = api.getState();
        if (!state?.start) {
            return;
        }

        suppressNavSelect = true;
        try {
            nav.selectMode = getNavSelectMode(state.detail || 'Week');
            nav.select(new DayPilot.Date(state.start));
            nav.update();
        } finally {
            suppressNavSelect = false;
        }
    }

    /**
     * Initialize the DayPilot navigator control for calendar navigation.
     */
    function initNavigator() {
        const navEl = document.getElementById('myvh-calendar-nav-picker');
        if (!navEl || typeof DayPilot === 'undefined') {
            return;
        }

        nav = new DayPilot.Navigator('myvh-calendar-nav-picker', {
            showMonths: 3,
            skipMonths: 3,
            weekStarts: Number.isInteger(Number(myvhCal.startOfWeek)) ? Number(myvhCal.startOfWeek) : 1,
            selectMode: 'Week',
            onTimeRangeSelected: function(args) {
                if (suppressNavSelect) {
                    return;
                }

                const state = api?.getState?.() || {};
                api?.setModeAndDetail?.(state.mode || 'Calendar', state.detail || 'Week', args.day);
                setActiveModeButton(state.mode || 'Calendar');
                setActiveDetailButton(state.detail || 'Week');
                syncNavigator();
            }
        });

        nav.init();
        syncNavigator();
    }

    /**
     * Highlight the active calendar mode button.
     */
    function setActiveModeButton(mode) {
        document.querySelectorAll('.myvh-mode-btn').forEach(function(button) {
            button.classList.toggle('active', button.dataset.mode === mode);
        });
    }

    /**
     * Highlight the active calendar detail button.
     */
    function setActiveDetailButton(detail) {
        document.querySelectorAll('.myvh-detail-btn').forEach(function(button) {
            button.classList.toggle('active', button.dataset.view === detail);
        });
    }

    /**
     * Parse calendar state from the URL query string.
     */
    function getStateFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const mode = params.get('cal_mode');
        const detail = params.get('cal_detail');
        const view = params.get('cal_view');
        const start = params.get('cal_start');

        if (!mode && !detail && !view && !start) {
            return null;
        }

        if (!mode && !detail && view) {
            return {
                mode: view === 'Rooms' ? 'Scheduler' : 'Calendar',
                detail: view === 'Rooms' ? 'Week' : view,
                start,
            };
        }

        return { mode, detail, start };
    }

    function buildCalendarReturnUrl() {
        const url = new URL(window.location.href);
        const state = api?.getState?.();

        url.searchParams.set('page', 'myvh-calendar');

        if (state?.view) {
            url.searchParams.set('cal_view', state.view);
        } else {
            url.searchParams.delete('cal_view');
        }

        if (state?.mode) {
            url.searchParams.set('cal_mode', state.mode);
        } else {
            url.searchParams.delete('cal_mode');
        }

        if (state?.detail) {
            url.searchParams.set('cal_detail', state.detail);
        } else {
            url.searchParams.delete('cal_detail');
        }

        if (state?.start) {
            url.searchParams.set('cal_start', state.start);
        }

        return url.toString();
    }

    // ─────────────────────────────
    // UI Controls (view + nav)
    // ─────────────────────────────
    function bindControls() {

        const calendarModeBtn = document.getElementById('myvh-mode-calendar');
        const schedulerModeBtn = document.getElementById('myvh-mode-scheduler');
        const dayBtn   = document.getElementById('myvh-day');
        const weekBtn  = document.getElementById('myvh-week');
        const monthBtn = document.getElementById('myvh-month');

        const nextBtn  = document.getElementById('myvh-next');
        const prevBtn  = document.getElementById('myvh-prev');
        const todayBtn = document.getElementById('myvh-today');

        if (calendarModeBtn) calendarModeBtn.addEventListener('click', () => {
            api.setMode('Calendar');
            setActiveModeButton('Calendar');
            syncNavigator();
            syncRoomFilterVisibility();
        });

        if (schedulerModeBtn) schedulerModeBtn.addEventListener('click', () => {
            api.setMode('Scheduler');
            setActiveModeButton('Scheduler');
            syncNavigator();
            syncRoomFilterVisibility();
        });

        if (dayBtn) dayBtn.addEventListener('click', () => {
            api.setDetail('Day');
            setActiveDetailButton('Day');
            syncNavigator();
        });

        if (weekBtn) weekBtn.addEventListener('click', () => {
            api.setDetail('Week');
            setActiveDetailButton('Week');
            syncNavigator();
        });

        if (monthBtn) monthBtn.addEventListener('click', () => {
            api.setDetail('Month');
            setActiveDetailButton('Month');
            syncNavigator();
        });

        if (nextBtn)  nextBtn.addEventListener('click', () => { api.next(); syncNavigator(); });
        if (prevBtn)  prevBtn.addEventListener('click', () => { api.prev(); syncNavigator(); });
        if (todayBtn) todayBtn.addEventListener('click', () => { api.today(); syncNavigator(); });
    }

    // ─────────────────────────────
    // AJAX Helpers
    // ─────────────────────────────
    function updateEvent(data) {
        return jQuery.post(myvhCal.ajax_url, {
            action: 'myvh_update_event',
            nonce:  myvhCal.nonce,
            ...data
        });
    }

    function createEvent(data) {
        return jQuery.post(myvhCal.ajax_url, {
            action: 'myvh_create_event',
            nonce:  myvhCal.nonce,
            ...data
        });
    }

    // ─────────────────────────────
    // Init
    // ─────────────────────────────
    function init() {

        const modal = BookingModalCreate;

        modal.init({
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,

            loadRooms: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_calendar_rooms&nonce=${myvhCal.nonce}&context=admin${selectedVenueId > 0 ? `&venue_id=${encodeURIComponent(selectedVenueId)}` : ''}`)
                    .then(r => r.json())
                    .then(function(payload) {
                        return Array.isArray(payload) ? payload : (Array.isArray(payload && payload.data) ? payload.data : []);
                    }),

            loadCustomers: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_customers&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadOrganisations: (customerId) =>
                fetch(`${myvhCal.ajax_url}?action=myvh_organisations&nonce=${myvhCal.nonce}&customer_id=${encodeURIComponent(customerId || '')}`)
                    .then(r => r.json()),

            onClose: () => api?.clearSelection?.(),
            onSuccess: () => api.reload()
        });

        // IMPORTANT: use initCalendar (alias to init)
        const restoredState = getStateFromUrl();

        function initialiseCalendar() {
            api = CalendarCore.init("myvh-calendar", {
                ajax_url: myvhCal.ajax_url,
                nonce: myvhCal.nonce,
                editable: true,
                selectable: true,
                initialState: restoredState,
                initialMode: restoredState?.mode || 'Calendar',
                initialDetail: restoredState?.detail || 'Week',
                headerDateFormat: myvhCal.headerDateFormat || null,
                startOfWeek: Number.isInteger(Number(myvhCal.startOfWeek)) ? Number(myvhCal.startOfWeek) : 1,
                visibleStartHour: myvhCal.visibleStartHour,
                visibleEndHour: myvhCal.visibleEndHour,
                schedulerOrientation: String(myvhCal.schedulerOrientation || 'horizontal').toLowerCase(),
                statusColors: (myvhCal && myvhCal.statusColors) ? myvhCal.statusColors : null,
                context: 'admin',
                getVenueId: function() { return selectedVenueId; },

                filterEvents: function(events) {
                    if (selectedRoomIds.size === 0) return events;
                    return events.filter(function(e) {
                        let rid = parseInt((e.tags && e.tags.roomId) || e.resource || 0, 10);
                        return !selectedRoomIds.has(rid);
                    });
                },

                onEventClick: function(args) {
                    const id = args.e.id ? args.e.id() : args.e.data.id;
                    const target = new URL('/wp-admin/admin.php', window.location.origin);
                    target.searchParams.set('page', 'my-village-hall');
                    target.searchParams.set('view', id);
                    target.searchParams.set('return_to', buildCalendarReturnUrl());
                    window.location.href = target.toString();
                },

                onEventMoved: function(args) {
                    updateEvent({
                        id:    args.e.id ? args.e.id() : args.e.data.id,
                        start: args.newStart.toString(),
                        end:   args.newEnd.toString()
                    }).fail(function() {
                        portalAlert('Failed to move event');
                        api.reload();
                    });
                },

                onEventResized: function(args) {
                    updateEvent({
                        id:    args.e.id ? args.e.id() : args.e.data.id,
                        start: args.newStart.toString(),
                        end:   args.newEnd.toString()
                    }).fail(function() {
                        portalAlert('Failed to resize event');
                        api.reload();
                    });
                },

                onTimeRangeSelected: function(args) {
                    modal.open({
                        start: args.start.toString(),
                        end: args.end.toString()
                    });

                    api.clearSelection?.();
                }
            });

            bindControls();
            const state = api.getState?.() || {};
            setActiveModeButton(state.mode || 'Calendar');
            setActiveDetailButton(state.detail || 'Week');
            initNavigator();

            loadCalendarRooms()
                .then(function(rooms) {
                    allAdminRooms = rooms;
                    renderRoomFilter(rooms);
                    renderCalendarKey(rooms);
                })
                .catch(function() {
                    allAdminRooms = [];
                    renderRoomFilter([]);
                    renderCalendarKey([]);
                });
        }

        loadRoomsForVenue(0)
            .then(function(rooms) {
                allVenues = buildVenuesFromRooms(rooms);
                selectedVenueId = resolveInitialVenueId(allVenues);
                setPersistedVenueId(selectedVenueId);
                renderVenueSelector();
            })
            .catch(function() {
                allVenues = [];
                selectedVenueId = 0;
                renderVenueSelector();
            })
            .finally(function() {
                initialiseCalendar();
            });

        // Optional: expose for debugging
        window.myvhApi = api;
    }

    return {
        init: init
    };

})();

// SINGLE initialisation
document.addEventListener("DOMContentLoaded", function() {
    CalendarAdmin.init();
});