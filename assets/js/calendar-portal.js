// Portal calendar logic for My Village Hall
var Calendar = (function() {

        var api = null;
        var createModal = null;
        var viewModal = null;
        var nav = null;
        var suppressNavSelect = false;
        var selectedVenueId = 0;
        var allVenues = [];
        var DEFAULT_STATUS_COLORS = {
            confirmed: '#2271b1',
            pending: '#f0a500',
            cancelled: '#9aa0a6',
            completed: '#2d8f45'
        };
        var VENUE_STORAGE_KEY = 'myvhCalendarVenue_portal';

        function normaliseHexColour(value) {
            var text = String(value || '').trim().toLowerCase();
            return /^#[0-9a-f]{6}$/.test(text) ? text : '';
        }

        function toStatusLabel(status) {
            return String(status || '')
                .replace(/[_-]+/g, ' ')
                .replace(/\b\w/g, function(match) { return match.toUpperCase(); });
        }

        function createLegendItem(label, colour) {
            var item = document.createElement('span');
            item.className = 'myvh-calendar-key-item';

            var swatch = document.createElement('span');
            swatch.className = 'myvh-calendar-key-swatch';
            swatch.style.backgroundColor = colour;

            var text = document.createElement('span');
            text.className = 'myvh-calendar-key-label';
            text.textContent = label;

            item.appendChild(swatch);
            item.appendChild(text);
            return item;
        }

        function renderCalendarKey(rooms) {
            var root = document.getElementById('myvh-calendar-key');
            if (!root) {
                return;
            }

            var statusWrap = root.querySelector('.myvh-calendar-key-status-items');
            var roomWrap = root.querySelector('.myvh-calendar-key-room-items');
            if (!statusWrap || !roomWrap) {
                return;
            }

            statusWrap.innerHTML = '';
            roomWrap.innerHTML = '';

            var statusColors = Object.assign({}, DEFAULT_STATUS_COLORS, (myvhCal && typeof myvhCal.statusColors === 'object') ? myvhCal.statusColors : {});

            Object.keys(statusColors).forEach(function(status) {
                var colour = normaliseHexColour(statusColors[status]);
                if (!colour) {
                    return;
                }

                statusWrap.appendChild(createLegendItem(toStatusLabel(status), colour));
            });

            var seenRoomIds = new Set();
            (Array.isArray(rooms) ? rooms : []).forEach(function(room) {
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
                roomWrap.appendChild(createLegendItem(roomName, roomColour));
            });

            if (!roomWrap.children.length) {
                roomWrap.appendChild(createLegendItem('No rooms available', '#dcdcde'));
            }
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
            var byId = {};

            (Array.isArray(rooms) ? rooms : []).forEach(function(room) {
                var venueId = parseInt(room && room.venue_id, 10) || 0;
                var venueName = String((room && room.venue) || '').trim();

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

            var venueIds = venues.map(function(venue) { return venue.id; });

            if (venues.length === 1) {
                return venueIds[0];
            }

            var persistedVenueId = getPersistedVenueId();
            if (persistedVenueId > 0 && venueIds.indexOf(persistedVenueId) !== -1) {
                return persistedVenueId;
            }

            return venueIds[0];
        }

        function loadRoomsForVenue(venueId) {
            var url = myvhCal.ajax_url + '?action=myvh_calendar_rooms&nonce=' + encodeURIComponent(myvhCal.nonce) + '&context=portal';
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

        function loadPortalCustomers() {
            return fetch(`${myvhCal.ajax_url}?action=myvh_customers&nonce=${myvhCal.nonce}`)
                .then(function(response) { return response.json(); });
        }

        function loadPortalOrganisations(customerId) {
            return fetch(`${myvhCal.ajax_url}?action=myvh_organisations&nonce=${myvhCal.nonce}&customer_id=${encodeURIComponent(customerId || '')}`)
                .then(function(response) { return response.json(); });
        }

        function notifyPortalBookingFlowChanged() {
            document.dispatchEvent(new CustomEvent('myvh:portal-booking-changed'));
        }

        function renderVenueSelector() {
            var wrap = document.getElementById('myvh-calendar-venue-wrap');
            var select = document.getElementById('myvh-calendar-venue-select');

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
                var option = document.createElement('option');
                option.value = String(venue.id);
                option.textContent = venue.name;
                option.selected = venue.id === selectedVenueId;
                select.appendChild(option);
            });

            select.onchange = function() {
                var nextVenueId = parseInt(select.value || '0', 10) || 0;
                selectedVenueId = nextVenueId;
                setPersistedVenueId(nextVenueId);

                if (api && typeof api.rerender === 'function') {
                    api.rerender();
                    ensureSelectableRangeHandlers();
                    syncNavigator();
                }

                loadCalendarRooms()
                    .then(function(rooms) {
                        renderCalendarKey(rooms);
                    })
                    .catch(function() {
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

            const isSmallViewport = window.matchMedia('(max-width: 782px)').matches;
            const visibleMonths = isSmallViewport ? 1 : 3;

            nav = new DayPilot.Navigator('myvh-calendar-nav-picker', {
                showMonths: visibleMonths,
                skipMonths: visibleMonths,
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
                    ensureSelectableRangeHandlers();
                    syncNavigator();
                }
            });

            nav.init();
            syncNavigator();
        }

        /**
         * Open the booking modal for a selected range.
         */
        function openSelectionModal(args) {
            console.log("openSelectionModal", args);
            if (!createModal || !args) {
                console.log("createModal is null");
                return;
            }

            const isClientAdmin = !!Number(myvhCal.isClientAdmin || 0);

            createModal.open({
                start: args.start.toString(),
                end: args.end.toString(),
                customer_id: isClientAdmin ? '' : (myvhCal.currentCustomerId || ''),
                organisation_id: isClientAdmin ? '' : (myvhCal.defaultOrganisationId || '')
            });

            api?.clearSelection?.();
        }

        /**
         * Open the booking modal in read-only mode for an event.
         */
        // TODO: Change this to use the view modal form rather than the create form in view only mode
        function openReadOnlyModal(args) {
            console.log("openReadOnlyModal", args);
            if (!viewModal || !args || !args.e) {
                return;
            }

            const bookingId = args.e.id
                ? args.e.id()
                : (args.e.data?.id || args.e.data?.Id);

            if (!bookingId) {
                return;
            }

            viewModal.open({
                bookingId: bookingId,
                args: args.e.data,
                viewOnly: true
            });
        }

        function openCreateModal(data) {
            if (!createModal) {
                return;
            }

            createModal.open(data || {});
        }

        function openEditModal(bookingId) {
            if (!createModal || !bookingId) {
                return;
            }

            createModal.open({ editMode: true, bookingId: bookingId });
        }

        function openViewModal(bookingId) {
            if (!viewModal || !bookingId) {
                return;
            }

            viewModal.open({ bookingId: bookingId, viewOnly: true });
        }

        function deleteBooking(bookingId) {
            if (!bookingId) {
                return Promise.resolve(false);
            }

            if (!window.confirm('Delete this booking? This action cannot be undone.')) {
                return Promise.resolve(false);
            }

            const formData = new FormData();
            formData.append('action', 'myvh_portal_delete_booking');
            formData.append('nonce', myvhCal.nonce);
            formData.append('booking_id', String(bookingId));

            return fetch(myvhCal.ajax_url, {
                method: 'POST',
                body: formData
            })
                .then(function(response) { return response.json(); })
                .then(function(result) {
                    if (!result || !result.success) {
                        throw new Error(result && result.data && result.data.message ? result.data.message : (result && result.data) || 'Failed to delete booking');
                    }

                    viewModal.close();
                    if (api && typeof api.reload === 'function') {
                        api.reload();
                    }

                    notifyPortalBookingFlowChanged();

                    return true;
                })
                .catch(function(error) {
                    window.alert(error && error.message ? error.message : 'Failed to delete booking');
                    return false;
                });
        }

        function initialisePortalBookingFlow() {
            if (window.MyvhPortalCalendarFlow) {
                return window.MyvhPortalCalendarFlow;
            }

            createModal = BookingModalCreate;
            viewModal = BookingModalView;

            const isClientAdmin = !!Number(myvhCal.isClientAdmin || 0);
            const handleBookingSaved = function() {
                if (api && typeof api.reload === 'function') {
                    api.reload();
                }

                notifyPortalBookingFlowChanged();
            };

            createModal.init({
                ajax_url: myvhCal.ajax_url,
                nonce: myvhCal.nonce,
                context: 'portal',

                loadRooms: function() {
                    return loadCalendarRooms();
                },

                loadCustomers: function() {
                    return loadPortalCustomers();
                },

                loadOrganisations: function(customerId) {
                    return loadPortalOrganisations(customerId);
                },

                lockCustomer: !isClientAdmin,
                lockOrganisation: false,
                hideCustomer: !isClientAdmin,
                lockAddonPrices: true,
                requireOrganisation: true,
                canManageNoInvoiceRequired: isClientAdmin,

                onClose: () => api?.clearSelection?.(),
                onSuccess: handleBookingSaved
            });

            viewModal.init({
                ajax_url: myvhCal.ajax_url,
                nonce: myvhCal.nonce,
                context: 'portal',

                loadRooms: function() {
                    return loadCalendarRooms();
                },

                loadCustomers: function() {
                    return loadPortalCustomers();
                },

                loadOrganisations: function(customerId) {
                    return loadPortalOrganisations(customerId);
                },

                lockCustomer: !isClientAdmin,
                lockOrganisation: false,
                hideCustomer: !isClientAdmin,
                lockAddonPrices: true,
                requireOrganisation: true,
                canManageNoInvoiceRequired: isClientAdmin,

                onClose: () => api?.clearSelection?.(),
                onEdit: ({ bookingId }) => {
                    createModal.open({ editMode: true, bookingId: bookingId });
                },
                onDelete: ({ bookingId }) => {
                    deleteBooking(bookingId);
                },
                onSuccess: handleBookingSaved
            });

            window.MyvhPortalCalendarFlow = {
                openCreate: openCreateModal,
                openEdit: openEditModal,
                openView: openViewModal,
                deleteBooking: deleteBooking
            };

            document.dispatchEvent(new CustomEvent('myvh:portal-booking-flow-ready'));

            return window.MyvhPortalCalendarFlow;
        }

        /**
         * Ensure selectable range handlers are set up for calendar and scheduler.
         */
        function ensureSelectableRangeHandlers() {
            const cal = api?.calendar;
            if (cal) {
                cal.timeRangeSelectedHandling = 'Enabled';
                cal.onTimeRangeSelected = openSelectionModal;
                cal.update();
            }

            const sched = api?.scheduler;
            if (sched) {
                sched.timeRangeSelectedHandling = 'Enabled';
                sched.onTimeRangeSelected = openSelectionModal;
                sched.update();
            }
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
         * Bind UI controls for calendar view, navigation, and mode switching.
         */
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
                ensureSelectableRangeHandlers();
                syncNavigator();
            });

            if (schedulerModeBtn) schedulerModeBtn.addEventListener('click', () => {
                api.setMode('Scheduler');
                setActiveModeButton('Scheduler');
                ensureSelectableRangeHandlers();
                syncNavigator();
            });

            if (dayBtn) dayBtn.addEventListener('click', () => {
                api.setDetail('Day');
                setActiveDetailButton('Day');
                ensureSelectableRangeHandlers();
                syncNavigator();
            });
            if (weekBtn) weekBtn.addEventListener('click', () => {
                api.setDetail('Week');
                setActiveDetailButton('Week');
                ensureSelectableRangeHandlers();
                syncNavigator();
            });

            if (monthBtn) monthBtn.addEventListener('click', () => {
                api.setDetail('Month');
                setActiveDetailButton('Month');
                ensureSelectableRangeHandlers();
                syncNavigator();
            });

            if (nextBtn)  nextBtn.addEventListener('click', () => { api.next(); syncNavigator(); });
            if (prevBtn)  prevBtn.addEventListener('click', () => { api.prev(); syncNavigator(); });
            if (todayBtn) todayBtn.addEventListener('click', () => { api.today(); syncNavigator(); });
        }

        /**
         * Initialize the portal calendar, modal, and controls.
         */
        function init() {
            initialisePortalBookingFlow();

            function initialiseCalendar() {
                api = CalendarCore.init("myvh-calendar", {

                    context:    "portal",
                    ajax_url:   myvhCal.ajax_url,
                    nonce:      myvhCal.nonce,
                    headerDateFormat: myvhCal.headerDateFormat || null,
                    startOfWeek: Number.isInteger(Number(myvhCal.startOfWeek)) ? Number(myvhCal.startOfWeek) : 1,
                    visibleStartHour: myvhCal.visibleStartHour,
                    visibleEndHour: myvhCal.visibleEndHour,
                    schedulerOrientation: String(myvhCal.schedulerOrientation || 'horizontal').toLowerCase(),
                    statusColors: (myvhCal && myvhCal.statusColors) ? myvhCal.statusColors : null,
                    getVenueId: function() { return selectedVenueId; },

                    editable:   false,
                    selectable: true,
                    readOnly:   true,
                    initialMode: 'Calendar',
                    initialDetail: 'Week',

                    // onEventClick removed: clicking events no longer shows booking

                    onTimeRangeSelected: openSelectionModal,
                    onEventClick : openReadOnlyModal
                });

                bindControls();
                const state = api.getState?.() || {};
                setActiveModeButton(state.mode || 'Calendar');
                setActiveDetailButton(state.detail || 'Week');
                ensureSelectableRangeHandlers();
                initNavigator();

                loadCalendarRooms()
                    .then(function(rooms) {
                        renderCalendarKey(rooms);
                    })
                    .catch(function() {
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
        }

        return {
            init: init,
            ensureBookingFlow: initialisePortalBookingFlow
        };

    })();
