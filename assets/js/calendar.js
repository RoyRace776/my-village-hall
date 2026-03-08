/**
 * My Village Hall – Admin Calendar (DayPilot Lite Scheduler)
 *
 * Rooms = rows, time = columns.
 * Horizontal scrolling is native browser scroll on the container.
 * View switching tears down and rebuilds the scheduler cleanly.
 */
(function ($) {
    'use strict';

    var cfg         = window.myvhAdminCal || {};
    var dp          = null;
    var currentView = 'Week';
    var currentDate = null;

    // ── Wait for DayPilot ─────────────────────────────────────────────────────
    function waitForDayPilot(attempts) {
        attempts = attempts || 0;
        if (attempts > 40) {
            $('#vbc-calendar').html(
                '<div style="padding:20px;color:#c33;border:1px solid #c33;border-radius:4px;">' +
                '<strong>Error:</strong> DayPilot library could not be loaded. ' +
                'Check assets/js/daypilot-all.min.js exists and reload.</div>'
            );
            return;
        }
        if (typeof DayPilot === 'undefined' || typeof DayPilot.Scheduler === 'undefined') {
            setTimeout(function () { waitForDayPilot(attempts + 1); }, 150);
            return;
        }
        currentDate = DayPilot.Date.today();
        initScheduler();
        bindUI();
    }

    $(function () {
        if (!$('#vbc-calendar').length) return;
        waitForDayPilot();
    });

    // ── View config ───────────────────────────────────────────────────────────
    function viewCfg(view) {
        if (view === 'Day') {
            return {
                days:        1,
                scale:       'Hour',
                cellWidth:   60,
                timeHeaders: [
                    { groupBy: 'Day',  format: 'dddd d MMMM yyyy' },
                    { groupBy: 'Hour', format: 'HH:mm' },
                ],
            };
        }
        if (view === 'Week') {
            return {
                days:        56,          // 8 weeks — wide enough to scroll through
                scale:       'Day',
                cellWidth:   44,
                timeHeaders: [
                    { groupBy: 'Month', format: 'MMMM yyyy' },
                    { groupBy: 'Day',   format: 'ddd d' },
                ],
            };
        }
        // Month
        return {
            days:        365,             // full year — scroll left/right to browse
            scale:       'Day',
            cellWidth:   28,
            timeHeaders: [
                { groupBy: 'Month', format: 'MMM yyyy' },
                { groupBy: 'Day',   format: 'd' },
            ],
        };
    }

    // ── Init / reinit scheduler ───────────────────────────────────────────────
    function initScheduler() {
        var vc = viewCfg(currentView);

        // Fully clear the mount point before creating a new instance
        $('#vbc-calendar').empty();

        dp = new DayPilot.Scheduler('vbc-calendar', {
            startDate:                 currentDate,
            days:                      vc.days,
            scale:                     vc.scale,
            cellWidth:                 vc.cellWidth,
            timeHeaders:               vc.timeHeaders,
            theme:                     'myvh_admin_cal',
            height:                    500,
            eventMoveHandling:         'Disabled',
            eventResizeHandling:       'Disabled',
            timeRangeSelectedHandling: 'Enabled',
            onTimeRangeSelected:       onRangeSelected,
            onEventClick:              onEventClick,
            rowHeaderWidth:            160,
            rowHeaderColumns:          [{ title: 'Room', width: 160 }],
        });

        dp.init();
        updateTitle();
        updateViewButtons();
        loadResources();
    }

    // ── Switch view ───────────────────────────────────────────────────────────
    function switchView(view) {
        currentView = view;
        initScheduler();
    }

    // ── Navigation (moves startDate and rebuilds) ─────────────────────────────
    function navigate(direction) {
        var d = new DayPilot.Date(currentDate);
        if (currentView === 'Day') {
            currentDate = d.addDays(direction);
        } else if (currentView === 'Week') {
            currentDate = d.addDays(direction * 7);
        } else {
            currentDate = d.addMonths(direction).firstDayOfMonth();
        }
        dp.startDate = currentDate;
        dp.update();
        loadEvents();
        updateTitle();
    }

    function goToday() {
        currentDate  = DayPilot.Date.today();
        dp.startDate = currentDate;
        dp.update();
        loadEvents();
        updateTitle();
    }

    // ── Load rooms (resources) then events ────────────────────────────────────
    function loadResources() {
        var venueId = $('#vbc-venue-filter').val() || 0;
        var roomId  = $('#vbc-room-filter').val()  || 0;

        $.get(cfg.ajax_url, {
            action:   'myvh_cal_rooms',
            nonce:    cfg.nonce,
            venue_id: venueId,
        })
        .done(function (res) {
            if (!dp || !res.success) return;
            var rows = [];
            $.each(res.data.rooms, function (i, room) {
                if (roomId && String(room.Id) !== String(roomId)) return;
                rows.push({
                    id:   String(room.Id),
                    name: room.VenueName ? room.VenueName + ' \u203a ' + room.Name : room.Name,
                });
            });
            dp.resources = rows;
            dp.update();
            loadEvents();
        })
        .fail(function () { loadEvents(); });
    }

    // ── Load events ───────────────────────────────────────────────────────────
    function loadEvents() {
        if (!dp) return;
        var d        = new DayPilot.Date(currentDate);
        var days     = viewCfg(currentView).days;
        var start    = d.toString('yyyy-MM-dd');
        var end      = d.addDays(days).toString('yyyy-MM-dd');
        var venueId  = $('#vbc-venue-filter').val()            || 0;
        var roomId   = $('#vbc-room-filter').val()             || 0;
        var showCanc = $('#vbc-show-cancelled').is(':checked') ? 1 : 0;

        $.get(cfg.ajax_url, {
            action: 'myvh_cal_events', nonce: cfg.nonce,
            start: start, end: end,
            venue_id: venueId, room_id: roomId, show_cancelled: showCanc,
        })
        .done(function (data) {
            if (!dp) return;
            dp.events.list = (Array.isArray(data) ? data : []).map(function (e) {
                return $.extend({}, e, { resource: String(e.resource) });
            });
            dp.update();
        })
        .fail(function () {
            showNotice((cfg.strings && cfg.strings.error) || 'Error loading events', 'error');
        });
    }

    // ── Title ─────────────────────────────────────────────────────────────────
    function updateTitle() {
        if (!currentDate) return;
        var d   = new DayPilot.Date(currentDate);
        var out = '';
        if (currentView === 'Day') {
            out = d.toString('dddd, d MMMM yyyy');
        } else if (currentView === 'Week') {
            out = d.toString('d MMM yyyy') + ' \u2013 ' + d.addDays(55).toString('d MMM yyyy');
        } else {
            out = d.toString('d MMM yyyy') + ' \u2013 ' + d.addDays(364).toString('d MMM yyyy');
        }
        $('#vbc-cal-title').text(out);
    }

    function updateViewButtons() {
        $('.vbc-view-btn')
            .removeClass('active')
            .filter('[data-view="' + currentView + '"]')
            .addClass('active');
    }

    // ── DayPilot callbacks ────────────────────────────────────────────────────
    function onRangeSelected(args) {
        var start = new DayPilot.Date(args.start);
        openModal('new', {
            roomId:    args.resource,
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
            date:        new DayPilot.Date(e.data.start).toString('yyyy-MM-dd'),
            startTime:   new DayPilot.Date(e.data.start).toString('HH:mm'),
            endTime:     new DayPilot.Date(e.data.end).toString('HH:mm'),
            status:      t.status,
            description: t.description,
            isPublic:    t.public,
        });
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    function openModal(mode, data) {
        data = data || {};
        $('#vbc-booking-id, #vbc-modal-customer, #vbc-modal-room, #vbc-modal-date, #vbc-modal-start-time, #vbc-modal-end-time, #vbc-modal-description').val('');
        $('#vbc-modal-status').val('pending');
        $('#vbc-modal-public').prop('checked', true);
        $('#vbc-cancel-booking, #vbc-edit-full').hide();

        if (mode === 'new') {
            $('#vbc-modal-title').text((cfg.strings && cfg.strings.newBooking) || 'New Booking');
            if (data.date)      $('#vbc-modal-date').val(data.date);
            if (data.startTime) $('#vbc-modal-start-time').val(data.startTime);
            if (data.endTime)   $('#vbc-modal-end-time').val(data.endTime);
            if (data.roomId)    $('#vbc-modal-room').val(String(data.roomId));
        } else {
            $('#vbc-modal-title').text((cfg.strings && cfg.strings.editBooking) || 'Edit Booking');
            $('#vbc-booking-id').val(data.id);
            $('#vbc-modal-date').val(data.date);
            $('#vbc-modal-start-time').val(data.startTime);
            $('#vbc-modal-end-time').val(data.endTime);
            $('#vbc-modal-status').val(data.status || 'pending');
            $('#vbc-modal-description').val(data.description || '');
            $('#vbc-modal-public').prop('checked', !!data.isPublic);
            if (data.roomId)     $('#vbc-modal-room').val(String(data.roomId));
            if (data.customerId) $('#vbc-modal-customer').val(data.customerId);
            $('#vbc-cancel-booking').show();
            if (cfg.admin_url && data.id) {
                $('#vbc-edit-full').attr('href', cfg.admin_url + 'admin.php?page=my-village-hall&edit=' + data.id).show();
            }
        }
        $('#vbc-booking-modal').fadeIn(200);
    }

    // ── Save booking ──────────────────────────────────────────────────────────
    function saveBooking() {
        var roomId = $('#vbc-modal-room').val();
        if (!$('#vbc-modal-customer').val()) { alert((cfg.strings && cfg.strings.selectCustomer) || 'Please select a customer'); return; }
        if (!roomId)                         { alert((cfg.strings && cfg.strings.selectRoom)     || 'Please select a room');     return; }
        if (!$('#vbc-modal-date').val() || !$('#vbc-modal-start-time').val() || !$('#vbc-modal-end-time').val()) {
            alert('Please fill in the date and times'); return;
        }
        var $btn = $('#vbc-save-booking').prop('disabled', true).text('Saving\u2026');
        $.post(cfg.ajax_url, {
            action: 'myvh_cal_save_booking', nonce: cfg.nonce,
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
        })
        .done(function (res) {
            if (res.success) { showNotice(res.data.message, 'success'); $('#vbc-booking-modal').fadeOut(200); loadEvents(); }
            else { alert((res.data && res.data.message) || (cfg.strings && cfg.strings.error) || 'Error'); }
        })
        .fail(function () { alert((cfg.strings && cfg.strings.error) || 'An error occurred'); })
        .always(function () { $btn.prop('disabled', false).text((cfg.strings && cfg.strings.save) || 'Save Booking'); });
    }

    // ── Cancel booking ────────────────────────────────────────────────────────
    function cancelBooking() {
        if (!confirm((cfg.strings && cfg.strings.confirmCancel) || 'Cancel this booking?')) return;
        $.post(cfg.ajax_url, { action: 'myvh_cal_cancel_booking', nonce: cfg.nonce, booking_id: $('#vbc-booking-id').val() })
        .done(function (res) {
            if (res.success) { showNotice(res.data.message, 'success'); $('#vbc-booking-modal').fadeOut(200); loadEvents(); }
            else { alert((res.data && res.data.message) || (cfg.strings && cfg.strings.error) || 'Error'); }
        })
        .fail(function () { alert((cfg.strings && cfg.strings.error) || 'An error occurred'); });
    }

    // ── UI bindings ───────────────────────────────────────────────────────────
    function bindUI() {
        $('#vbc-prev').on('click',  function () { navigate(-1); });
        $('#vbc-next').on('click',  function () { navigate(1); });
        $('#vbc-today').on('click', goToday);

        $(document).on('click', '.vbc-view-btn', function () {
            var view = $(this).data('view');
            if (view && view !== currentView) switchView(view);
        });

        $('#vbc-venue-filter').on('change', function () {
            filterRoomDropdownByVenue($(this).val());
            loadResources();
        });
        $('#vbc-room-filter').on('change',    function () { loadResources(); });
        $('#vbc-show-cancelled').on('change', function () { loadEvents(); });
        $('#vbc-refresh-calendar').on('click',function () { loadResources(); });
        $('#vbc-new-booking').on('click',     function () { openModal('new', {}); });

        $(document).on('click', '.vbc-modal-close', function () { $(this).closest('.vbc-modal').fadeOut(200); });
        $(document).on('click', '.vbc-modal',       function (e) { if ($(e.target).hasClass('vbc-modal')) $(this).fadeOut(200); });

        $('#vbc-save-booking').on('click',   saveBooking);
        $('#vbc-cancel-booking').on('click', cancelBooking);
    }

    function filterRoomDropdownByVenue(venueId) {
        $('#vbc-room-filter option').each(function () {
            var opt = $(this);
            if (!opt.val()) { opt.show(); return; }
            opt.toggle(!venueId || String(opt.data('venue')) === String(venueId));
        });
        var $sel = $('#vbc-room-filter option:selected');
        if ($sel.val() && $sel.is(':hidden')) $('#vbc-room-filter').val('');
    }

    function showNotice(message, type) {
        var $n = $('<div class="notice notice-' + (type || 'info') + ' is-dismissible"><p>' + message + '</p></div>');
        $('.vbc-calendar-wrap h1').after($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
    }

}(jQuery));
