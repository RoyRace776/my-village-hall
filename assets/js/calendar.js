/**
 * My Village Hall – Admin Calendar (DayPilot Lite)
 *
 * Defensive implementation:
 * - Waits for DayPilot to be available before initialising
 * - View switching re-uses the same container correctly
 * - Navigation updates startDate then calls update()
 */
(function ($) {
    'use strict';

    var cfg         = window.myvhAdminCal || {};
    var dp          = null;
    var currentView = 'Month';
    var currentDate = null;   // set once DayPilot is confirmed available

    // ── Wait for DayPilot then boot ───────────────────────────────────────────
    function waitForDayPilot(attempts) {
        attempts = attempts || 0;
        if (attempts > 40) {
            $('#vbc-calendar').html(
                '<div style="padding:20px;color:#c33;border:1px solid #c33;border-radius:4px;">' +
                '<strong>Error:</strong> DayPilot calendar library could not be loaded. ' +
                'Please check your internet connection and reload the page.</div>'
            );
            return;
        }
        if (typeof DayPilot === 'undefined' || typeof DayPilot.Month === 'undefined') {
            setTimeout(function () { waitForDayPilot(attempts + 1); }, 150);
            return;
        }
        // DayPilot is ready
        currentDate = DayPilot.Date.today();
        mountView(currentView);
        bindUI();
    }

    $(function () {
        if (!$('#vbc-calendar').length) return;
        waitForDayPilot();
    });

    // ── Mount a view ──────────────────────────────────────────────────────────
    function mountView(view) {
        currentView = view;
        updateViewButtons();
        updateTitle();

        // Destroy previous instance cleanly
        if (dp) {
            try { dp.dispose(); } catch (e) {}
            // Also clear the DOM node so DayPilot doesn't find stale markup
            $('#vbc-calendar').empty();
            dp = null;
        }

        if (view === 'Week' || view === 'Day') {
            dp = new DayPilot.Calendar('vbc-calendar', {
                viewType:                  view,
                startDate:                 currentDate,
                theme:                     'myvh_admin_cal',
                heightSpec:                'Fixed',
                height:                    600,
                businessBeginsHour:        8,
                businessEndsHour:          22,
                eventMoveHandling:         'Disabled',
                eventResizeHandling:       'Disabled',
                timeRangeSelectedHandling: 'Enabled',
                onTimeRangeSelected:       onRangeSelected,
                onEventClick:              onEventClick,
            });
        } else {
            dp = new DayPilot.Month('vbc-calendar', {
                startDate:           currentDate,
                theme:               'myvh_admin_cal',
                cellHeight:          100,
                eventMoveHandling:   'Disabled',
                eventResizeHandling: 'Disabled',
                onTimeRangeSelected: onRangeSelected,
                onEventClick:        onEventClick,
            });
        }

        dp.init();
        loadEvents();
    }

    // ── Load events from server ───────────────────────────────────────────────
    function loadEvents() {
        if (!dp) return;

        var range    = getVisibleRange();
        var venueId  = $('#vbc-venue-filter').val()         || 0;
        var roomId   = $('#vbc-room-filter').val()          || 0;
        var showCanc = $('#vbc-show-cancelled').is(':checked') ? 1 : 0;

        $.get(cfg.ajax_url, {
            action:         'myvh_cal_events',
            nonce:          cfg.nonce,
            start:          range.start,
            end:            range.end,
            venue_id:       venueId,
            room_id:        roomId,
            show_cancelled: showCanc,
        })
        .done(function (data) {
            if (!dp) return;
            dp.events.list = Array.isArray(data) ? data : [];
            dp.update();
        })
        .fail(function () {
            showNotice(cfg.strings && cfg.strings.error || 'Error loading events', 'error');
        });
    }

    // ── Visible date range ────────────────────────────────────────────────────
    function getVisibleRange() {
        var d = new DayPilot.Date(currentDate);
        if (currentView === 'Week') {
            return { start: d.toString('yyyy-MM-dd'), end: d.addDays(7).toString('yyyy-MM-dd') };
        }
        if (currentView === 'Day') {
            return { start: d.toString('yyyy-MM-dd'), end: d.addDays(1).toString('yyyy-MM-dd') };
        }
        // Month – pad by a week either side to capture partial weeks
        return {
            start: d.firstDayOfMonth().addDays(-7).toString('yyyy-MM-dd'),
            end:   d.firstDayOfMonth().addMonths(1).addDays(7).toString('yyyy-MM-dd'),
        };
    }

    // ── Navigation ────────────────────────────────────────────────────────────
    function navigate(direction) {
        if (!dp) return;
        var d = new DayPilot.Date(currentDate);
        if (currentView === 'Month') {
            currentDate = d.addMonths(direction).firstDayOfMonth();
        } else if (currentView === 'Week') {
            currentDate = d.addDays(direction * 7);
        } else {
            currentDate = d.addDays(direction);
        }
        dp.startDate = currentDate;
        dp.update();
        loadEvents();
        updateTitle();
    }

    function goToday() {
        if (!dp) return;
        currentDate = DayPilot.Date.today();
        dp.startDate = currentDate;
        dp.update();
        loadEvents();
        updateTitle();
    }

    // ── Title ─────────────────────────────────────────────────────────────────
    function updateTitle() {
        if (!currentDate) return;
        var d   = new DayPilot.Date(currentDate);
        var out = '';
        if (currentView === 'Month') {
            out = d.toString('MMMM yyyy');
        } else if (currentView === 'Week') {
            out = d.toString('d MMM') + ' \u2013 ' + d.addDays(6).toString('d MMM yyyy');
        } else {
            out = d.toString('dddd, d MMMM yyyy');
        }
        $('#vbc-cal-title').text(out);
    }

    function updateViewButtons() {
        $('.vbc-view-btn')
            .removeClass('active')
            .filter('[data-view="' + currentView + '"]')
            .addClass('active');
    }

    // ── DayPilot event callbacks ──────────────────────────────────────────────
    function onRangeSelected(args) {
        var start = new DayPilot.Date(args.start);
        openModal('new', {
            date:      start.toString('yyyy-MM-dd'),
            startTime: start.toString('HH:mm'),
            endTime:   new DayPilot.Date(args.end).toString('HH:mm'),
        });
        if (dp && dp.clearSelection) dp.clearSelection();
    }

    function onEventClick(args) {
        var e = args.e;
        var t = (e.data && e.data.tags) ? e.data.tags : {};
        openModal('edit', {
            id:          e.data.id,
            customerId:  t.customerId,
            roomId:      t.roomId,
            venueId:     t.venueId,
            date:        new DayPilot.Date(e.data.start).toString('yyyy-MM-dd'),
            startTime:   new DayPilot.Date(e.data.start).toString('HH:mm'),
            endTime:     new DayPilot.Date(e.data.end).toString('HH:mm'),
            status:      t.status,
            description: t.description,
            isPublic:    t.public,
        });
    }

    // ── Booking modal ─────────────────────────────────────────────────────────
    function openModal(mode, data) {
        data = data || {};

        // Reset
        $('#vbc-booking-id').val('');
        $('#vbc-modal-customer').val('');
        $('#vbc-modal-venue').val('');
        $('#vbc-modal-date').val('');
        $('#vbc-modal-start-time').val('');
        $('#vbc-modal-end-time').val('');
        $('#vbc-modal-status').val('pending');
        $('#vbc-modal-description').val('');
        $('#vbc-modal-public').prop('checked', true);
        $('input[name="vbc_room_id"]').prop('checked', false);
        $('#vbc-cancel-booking').hide();
        $('#vbc-edit-full').hide();
        showAllRooms();

        if (mode === 'new') {
            $('#vbc-modal-title').text((cfg.strings && cfg.strings.newBooking) || 'New Booking');
            if (data.date)      $('#vbc-modal-date').val(data.date);
            if (data.startTime) $('#vbc-modal-start-time').val(data.startTime);
            if (data.endTime)   $('#vbc-modal-end-time').val(data.endTime);
        } else {
            $('#vbc-modal-title').text((cfg.strings && cfg.strings.editBooking) || 'Edit Booking');
            $('#vbc-booking-id').val(data.id);
            $('#vbc-modal-date').val(data.date);
            $('#vbc-modal-start-time').val(data.startTime);
            $('#vbc-modal-end-time').val(data.endTime);
            $('#vbc-modal-status').val(data.status || 'pending');
            $('#vbc-modal-description').val(data.description || '');
            $('#vbc-modal-public').prop('checked', !!data.isPublic);
            $('#vbc-cancel-booking').show();
            if (cfg.admin_url && data.id) {
                $('#vbc-edit-full').attr('href', cfg.admin_url + 'admin.php?page=my-village-hall&edit=' + data.id).show();
            }
            if (data.venueId) {
                $('#vbc-modal-venue').val(data.venueId);
                filterRoomsByVenue(data.venueId);
            }
            if (data.roomId) {
                $('input[name="vbc_room_id"][value="' + data.roomId + '"]').prop('checked', true);
            }
            if (data.customerId) {
                $('#vbc-modal-customer').val(data.customerId);
            }
        }

        $('#vbc-booking-modal').fadeIn(200);
    }

    function filterRoomsByVenue(venueId) {
        if (!venueId) { showAllRooms(); return; }
        $('.vbc-room-option').each(function () {
            $(this).toggle(String($(this).data('venue')) === String(venueId));
        });
    }

    function showAllRooms() {
        $('.vbc-room-option').show();
    }

    // ── Save booking ──────────────────────────────────────────────────────────
    function saveBooking() {
        var roomId = $('input[name="vbc_room_id"]:checked').val();

        if (!$('#vbc-modal-customer').val()) {
            alert((cfg.strings && cfg.strings.selectCustomer) || 'Please select a customer');
            return;
        }
        if (!roomId) {
            alert((cfg.strings && cfg.strings.selectRoom) || 'Please select a room');
            return;
        }
        if (!$('#vbc-modal-date').val() || !$('#vbc-modal-start-time').val() || !$('#vbc-modal-end-time').val()) {
            alert('Please fill in the date and times');
            return;
        }

        var payload = {
            action:      'myvh_cal_save_booking',
            nonce:       cfg.nonce,
            booking_id:  $('#vbc-booking-id').val() || '',
            customer_id: $('#vbc-modal-customer').val(),
            room_id:     roomId,
            start_date:  $('#vbc-modal-date').val(),
            end_date:    $('#vbc-modal-date').val(),
            start_time:  $('#vbc-modal-start-time').val() + ':00',
            end_time:    $('#vbc-modal-end-time').val()   + ':00',
            status:      $('#vbc-modal-status').val(),
            description: $('#vbc-modal-description').val(),
            public:      $('#vbc-modal-public').is(':checked') ? 1 : 0,
        };

        var $btn = $('#vbc-save-booking').prop('disabled', true).text('Saving\u2026');

        $.post(cfg.ajax_url, payload)
         .done(function (res) {
            if (res.success) {
                showNotice(res.data.message, 'success');
                $('#vbc-booking-modal').fadeOut(200);
                loadEvents();
            } else {
                alert((res.data && res.data.message) || (cfg.strings && cfg.strings.error) || 'Error');
            }
         })
         .fail(function () {
            alert((cfg.strings && cfg.strings.error) || 'An error occurred');
         })
         .always(function () {
            $btn.prop('disabled', false).text((cfg.strings && cfg.strings.save) || 'Save Booking');
         });
    }

    // ── Cancel booking ────────────────────────────────────────────────────────
    function cancelBooking() {
        if (!confirm((cfg.strings && cfg.strings.confirmCancel) || 'Cancel this booking?')) return;

        $.post(cfg.ajax_url, {
            action:     'myvh_cal_cancel_booking',
            nonce:      cfg.nonce,
            booking_id: $('#vbc-booking-id').val(),
        })
        .done(function (res) {
            if (res.success) {
                showNotice(res.data.message, 'success');
                $('#vbc-booking-modal').fadeOut(200);
                loadEvents();
            } else {
                alert((res.data && res.data.message) || (cfg.strings && cfg.strings.error) || 'Error');
            }
        })
        .fail(function () {
            alert((cfg.strings && cfg.strings.error) || 'An error occurred');
        });
    }

    // ── UI bindings ───────────────────────────────────────────────────────────
    function bindUI() {
        $('#vbc-prev').on('click',  function () { navigate(-1); });
        $('#vbc-next').on('click',  function () { navigate(1); });
        $('#vbc-today').on('click', goToday);

        // View buttons
        $(document).on('click', '.vbc-view-btn', function () {
            var view = $(this).data('view');
            if (view && view !== currentView) {
                mountView(view);
            }
        });

        // Filters
        $('#vbc-venue-filter').on('change', function () {
            filterRoomDropdownByVenue($(this).val());
            loadEvents();
        });
        $('#vbc-room-filter').on('change',    function () { loadEvents(); });
        $('#vbc-show-cancelled').on('change', function () { loadEvents(); });
        $('#vbc-refresh-calendar').on('click',function () { loadEvents(); });

        // New booking button
        $('#vbc-new-booking').on('click', function () { openModal('new', {}); });

        // Modal close
        $(document).on('click', '.vbc-modal-close', function () {
            $(this).closest('.vbc-modal').fadeOut(200);
        });
        $(document).on('click', '.vbc-modal', function (e) {
            if ($(e.target).hasClass('vbc-modal')) {
                $(this).fadeOut(200);
            }
        });

        // Modal venue filter
        $('#vbc-modal-venue').on('change', function () {
            filterRoomsByVenue($(this).val());
        });

        // Save / cancel
        $('#vbc-save-booking').on('click',   saveBooking);
        $('#vbc-cancel-booking').on('click', cancelBooking);
    }

    function filterRoomDropdownByVenue(venueId) {
        $('#vbc-room-filter option').each(function () {
            var opt = $(this);
            if (!opt.val()) { opt.show(); return; }
            opt.toggle(!venueId || String(opt.data('venue')) === String(venueId));
        });
        // Reset if the selected room is now hidden
        var $sel = $('#vbc-room-filter option:selected');
        if ($sel.val() && $sel.is(':hidden')) {
            $('#vbc-room-filter').val('');
        }
    }

    function showNotice(message, type) {
        var $n = $('<div class="notice notice-' + (type || 'info') + ' is-dismissible"><p>' + message + '</p></div>');
        $('.vbc-calendar-wrap h1').after($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
    }

}(jQuery));
