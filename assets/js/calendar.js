jQuery(document).ready(function($) {
    
    let calendar;
    let currentBookingId = null;
    
    // Initialize calendar
    function initCalendar() {
        const calendarEl = document.getElementById('vbc-calendar');
        if (!calendarEl) {
            console.log('Calendar element not found');
            return;
        }
        
        console.log('Initializing calendar...');
        
        const venueFilter = $('#vbc-venue-filter').val();
        const roomFilter = $('#vbc-room-filter').val();
        
        // Destroy existing calendar if it exists
        if (calendar) {
            calendar.destroy();
        }
        
        calendar = new window.FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
            },
            buttonText: {
                today: 'Today',
                month: 'Month',
                week: 'Week',
                day: 'Day',
                list: 'List'
            },
            editable: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            weekends: true,
            navLinks: true,
            nowIndicator: true,
            
            // Event sources
            events: function(info, successCallback, failureCallback) {
                console.log('Fetching events for date range:', info.startStr, 'to', info.endStr);

                var showCancelled = $('#vbc-show-cancelled').is(':checked');
                
                $.ajax({
                    url: vbcAjax.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'vbc_get_calendar_events',
                        nonce: vbcAjax.nonce,
                        start: info.startStr,
                        end: info.endStr,
                        venue_id: venueFilter,
                        room_id: roomFilter,
                        show_cancelled: showCancelled
                    },
                    success: function(response) {
                        console.log('Events response received:', response);
                        console.log('Response type:', typeof response);
                        console.log('Is array:', Array.isArray(response));
                        
                        // Ensure we have an array
                        if (Array.isArray(response)) {
                            console.log('Returning', response.length, 'events to calendar');
                            successCallback(response);
                        } else if (response && typeof response === 'object' && response.data) {
                            // If WordPress wrapped it in success/data format
                            console.log('Response was wrapped, extracting data');
                            if (Array.isArray(response.data)) {
                                successCallback(response.data);
                            } else {
                                console.error('response.data is not an array:', response.data);
                                successCallback([]);
                            }
                        } else {
                            console.error('Invalid response format:', response);
                            successCallback([]);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error fetching events:', status, error);
                        console.error('Response text:', xhr.responseText);
                        failureCallback();
                    }
                });
            },
            
            // Click on date to create booking
            select: function(info) {
                openBookingModal('new', {
                    start: info.startStr,
                    end: info.endStr
                });
                calendar.unselect();
            },
            
            // Click on event to view/edit
            eventClick: function(info) {
                const event = info.event;
                openViewModal(event);
            },
            
            // Drag and drop to reschedule
            eventDrop: function(info) {
                updateBookingDates(info.event);
            },
            
            // Resize event
            eventResize: function(info) {
                updateBookingDates(info.event);
            },
            
            // Event rendering
            eventDidMount: function(info) {
                // Add tooltip
                $(info.el).attr('title', 
                    info.event.extendedProps.customerName + ' - ' + 
                    info.event.extendedProps.roomName + '\n' +
                    info.event.extendedProps.venueName + '\n' +
                    'Status: ' + info.event.extendedProps.status
                );
            }
        });
        
        console.log('Calendar object created, calling render...');
        calendar.render();
        console.log('Calendar rendered successfully');
        
        // Force button handlers after render
        setTimeout(function() {
            const buttons = {
                prev: document.querySelector('.fc-prev-button'),
                next: document.querySelector('.fc-next-button'),
                today: document.querySelector('.fc-today-button'),
                month: document.querySelector('.fc-dayGridMonth-button'),
                week: document.querySelector('.fc-timeGridWeek-button'),
                day: document.querySelector('.fc-timeGridDay-button'),
                list: document.querySelector('.fc-listWeek-button')
            };
            
            console.log('FullCalendar buttons:', buttons);
            
            // Force click handlers on all buttons
            Object.keys(buttons).forEach(function(key) {
                const btn = buttons[key];
                if (btn) {
                    console.log('Setting up ' + key + ' button');
                    
                    // Remove any existing handlers and add new one
                    const newBtn = btn.cloneNode(true);
                    btn.parentNode.replaceChild(newBtn, btn);
                    
                    newBtn.addEventListener('click', function(e) {
                        console.log(key + ' button clicked!');
                        e.preventDefault();
                        e.stopPropagation();
                        
                        try {
                            switch(key) {
                                case 'prev':
                                    calendar.prev();
                                    break;
                                case 'next':
                                    calendar.next();
                                    break;
                                case 'today':
                                    calendar.today();
                                    break;
                                case 'month':
                                    calendar.changeView('dayGridMonth');
                                    break;
                                case 'week':
                                    calendar.changeView('timeGridWeek');
                                    break;
                                case 'day':
                                    calendar.changeView('timeGridDay');
                                    break;
                                case 'list':
                                    calendar.changeView('listWeek');
                                    break;
                            }
                            console.log(key + ' action completed');
                        } catch(error) {
                            console.error('Error in ' + key + ' handler:', error);
                        }
                        
                        return false;
                    }, true);
                }
            });
            
            console.log('All button handlers forcibly attached');
        }, 1000);
    }
    
    // Update booking dates after drag/resize
    function updateBookingDates(event) {
        $.ajax({
            url: vbcAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vbc_update_booking_dates',
                nonce: vbcAjax.nonce,
                booking_id: event.id,
                start: event.start.toISOString(),
                end: event.end ? event.end.toISOString() : event.start.toISOString()
            },
            success: function(response) {
                if (response.success) {
                    showNotification(vbcAjax.strings.bookingUpdated, 'success');
                } else {
                    alert(response.data.message);
                    calendar.refetchEvents();
                }
            },
            error: function() {
                alert(vbcAjax.strings.error);
                calendar.refetchEvents();
            }
        });
    }
    
    // Open booking modal (new or edit)
    function openBookingModal(mode, data) {
        currentBookingId = data.id || null;
        
        $('#vbc-booking-id').val(currentBookingId || '');
        
        if (mode === 'new') {
            $('#vbc-modal-title').text('New Booking');
            $('#vbc-booking-form')[0].reset();
            $('#vbc-delete-booking').hide();
            
            // Set date and times from calendar selection
            if (data.start) {
                const start = new Date(data.start);
                $('#vbc-modal-date').val(formatDate(start));
                $('#vbc-modal-start-time').val(formatTime(start));
                
                if (data.end) {
                    const end = new Date(data.end);
                    $('#vbc-modal-end-time').val(formatTime(end));
                } else {
                    // Default to 1 hour later
                    const end = new Date(start.getTime() + 60 * 60 * 1000);
                    $('#vbc-modal-end-time').val(formatTime(end));
                }
            }
        } else {
            $('#vbc-modal-title').text('Edit Booking');
            $('#vbc-delete-booking').show();
            
            // Populate form with booking data
            $('#vbc-booking-id').val(data.id);
            $('#vbc-modal-customer').val(data.extendedProps.customerId);
            $('#vbc-modal-status').val(data.extendedProps.statusId);
            $('#vbc-modal-public').prop('checked', data.extendedProps.public == 1);
            $('#vbc-modal-description').val(data.extendedProps.description);
            
            const start = new Date(data.start);
            const end = new Date(data.end || data.start);
            
            $('#vbc-modal-date').val(formatDate(start));
            $('#vbc-modal-start-time').val(formatTime(start));
            $('#vbc-modal-end-time').val(formatTime(end));
            
            // Load room info - we need to get venue first
            loadBookingDetailsForEdit(data.id);
        }
        
        $('#vbc-booking-modal').fadeIn(200);
    }
    
    // Load booking details for editing
    function loadBookingDetailsForEdit(bookingId) {
        $.ajax({
            url: vbcAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vbc_get_booking_details',
                nonce: vbcAjax.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    const booking = response.data.booking;
                    
                    // Get room details to determine venue
                    $.ajax({
                        url: vbcAjax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'myvh_get_room_details',
                            nonce: vbcAjax.nonce,
                            room_id: booking.RoomId
                        },
                        success: function(roomResponse) {
                            if (roomResponse.success) {
                                const room = roomResponse.data.room;
                                
                                // Check if venue dropdown exists (multi-venue setup)
                                if ($('#vbc-modal-venue').is('select')) {
                                    $('#vbc-modal-venue').val(room.VenueId).trigger('change');
                                    
                                    // Wait for rooms to load, then select the room radio button
                                    setTimeout(function() {
                                        $('input[name="room_id"][value="' + booking.RoomId + '"]').prop('checked', true);
                                    }, 300);
                                } else {
                                    // Single venue - just select the room radio button
                                    $('input[name="room_id"][value="' + booking.RoomId + '"]').prop('checked', true);
                                }
                            }
                        }
                    });
                }
            }
        });
    }
    
    // Open view modal
    function openViewModal(event) {
        $('#vbc-view-customer').text(event.extendedProps.customerName);
        $('#vbc-view-venue').text(event.extendedProps.venueName);
        $('#vbc-view-room').text(event.extendedProps.roomName);
        $('#vbc-view-start').text(formatDateTime(event.start));
        $('#vbc-view-end').text(formatDateTime(event.end || event.start));
        $('#vbc-view-status').html('<span class="vbc-status-badge" style="background-color: ' + event.backgroundColor + '">' + event.extendedProps.status + '</span>');
        $('#vbc-view-description').text(event.extendedProps.description || '-');
        
        $('#vbc-view-modal').data('event', event).fadeIn(200);
    }
    
    // Format date for date input (YYYY-MM-DD)
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        return `${year}-${month}-${day}`;
    }
    
    // Format time for select dropdown (HH:MM)
    function formatTime(date) {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        // Round to nearest quarter hour for select options
        let mins = parseInt(minutes);
        let roundedMinutes = Math.round(mins / 15) * 15;
        
        if (roundedMinutes === 60) {
            roundedMinutes = 0;
        }
        
        return `${hours}:${String(roundedMinutes).padStart(2, '0')}`;
    }
    
    // Format date for datetime-local input
    function formatDateTimeLocal(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
    
    // Format date for display
    function formatDateTime(date) {
        if (!date) return '';
        
        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return new Date(date).toLocaleDateString('en-US', options);
    }
    
    // Show notification
    function showNotification(message, type) {
        const notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.vbc-calendar-wrap h1').after(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Close modal
    $('.vbc-modal-close').on('click', function() {
        $(this).closest('.vbc-modal').fadeOut(200);
    });
    
    // Close modal on background click
    $('.vbc-modal').on('click', function(e) {
        if ($(e.target).hasClass('vbc-modal')) {
            $(this).fadeOut(200);
        }
    });
    
    // New booking button
    $('#vbc-new-booking').on('click', function() {
        console.log('New booking button clicked');
        openBookingModal('new', {});
    });
    
    // Venue filter change
    $('#vbc-venue-filter').on('change', function() {
        console.log('Venue filter changed');
        const venueId = $(this).val();
        
        // Load rooms for this venue in the filter dropdown
        loadRoomsForFilter(venueId);
        
        // Refresh calendar
        if (calendar) {
            calendar.refetchEvents();
        }
    });
    
    // Load rooms for venue filter dropdown
    function loadRoomsForFilter(venueId) {
        const filterSelect = $('#vbc-room-filter');
        filterSelect.html('<option value="">Loading...</option>');
        
        if (!venueId) {
            filterSelect.html('<option value="">All Rooms</option>');
            return;
        }
        
        $.ajax({
            url: vbcAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vbc_get_rooms_for_calendar',
                nonce: vbcAjax.nonce,
                venue_id: venueId
            },
            success: function(response) {
                if (response.success) {
                    let options = '<option value="">All Rooms</option>';
                    response.data.rooms.forEach(function(room) {
                        options += '<option value="' + room.Id + '">' + room.Name + '</option>';
                    });
                    filterSelect.html(options);
                } else {
                    filterSelect.html('<option value="">All Rooms</option>');
                }
            },
            error: function() {
                filterSelect.html('<option value="">All Rooms</option>');
            }
        });
    }
    
    // Room filter change
    $('#vbc-room-filter').on('change', function() {
        console.log('Room filter changed');
        if (calendar) {
            calendar.refetchEvents();
        }
    });
    
    // Refresh calendar button
    $('#vbc-refresh-calendar').on('click', function() {
        console.log('Refresh button clicked');
        if (calendar) {
            calendar.refetchEvents();
        }
    });

    $('#vbc-show-cancelled').on('change', function() {
    console.log('Show cancelled toggled:', $(this).is(':checked'));
    if (calendar) {
        calendar.refetchEvents();
    }
    });
    
    console.log('Calendar button handlers attached');
    
    // Modal venue change
    $('#vbc-modal-venue').on('change', function() {
        const venueId = $(this).val();
        loadRoomsForVenue(venueId);
    });
    
    // Load rooms for venue (as radio buttons)
    function loadRoomsForVenue(venueId) {
        const container = $('#vbc-modal-room-container');
        container.html('<p class="description">Loading...</p>');
        
        if (!venueId) {
            container.html('<p class="description">Select a venue first to see available rooms</p>');
            return;
        }
        
        $.ajax({
            url: vbcAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vbc_get_rooms_for_calendar',
                nonce: vbcAjax.nonce,
                venue_id: venueId
            },
            success: function(response) {
                if (response.success && response.data.rooms.length > 0) {
                    let html = '';
                    response.data.rooms.forEach(function(room) {
                        html += '<label style="display: block; margin: 8px 0;">';
                        html += '<input type="radio" name="room_id" value="' + room.Id + '" required class="vbc-room-radio">';
                        html += '<strong>' + room.Name + '</strong>';
                        if (room.Description) {
                            html += '<br><span style="margin-left: 24px; color: #666; font-size: 13px;">';
                            html += room.Description;
                            html += '</span>';
                        }
                        html += '</label>';
                    });
                    container.html(html);
                } else {
                    container.html('<p class="description">No rooms available for this venue</p>');
                }
            },
            error: function() {
                container.html('<p class="description">Error loading rooms</p>');
            }
        });
    }
    
    // Save booking
    $('#vbc-save-booking').on('click', function() {
        const form = $('#vbc-booking-form');
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        const bookingId = $('#vbc-booking-id').val();
        const isRecurring = $('#vbc-modal-recurring').is(':checked');
        const formData = form.serialize();
        
        const action = isRecurring ? 'myvh_save_recurring_booking' : (bookingId ? 'myvh_save_booking' : 'vbc_create_booking');
        
        $.ajax({
            url: vbcAjax.ajax_url,
            type: 'POST',
            data: formData + '&action=' + action + '&nonce=' + vbcAjax.nonce,
            success: function(response) {
                if (response.success) {
                    let message = response.data.message;
                    if (isRecurring && response.data.generated_count) {
                        message += ' (' + response.data.generated_count + ' future bookings created)';
                    }
                    showNotification(message, 'success');
                    $('#vbc-booking-modal').fadeOut(200);
                    if (calendar) {
                        calendar.refetchEvents();
                    }
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(vbcAjax.strings.error);
            }
        });
    });
    
    // Edit booking from view modal
    $('#vbc-edit-booking').on('click', function() {
        const event = $('#vbc-view-modal').data('event');
        $('#vbc-view-modal').fadeOut(200);
        setTimeout(function() {
            openBookingModal('edit', event);
        }, 300);
    });
    
    // Delete booking
    $('#vbc-delete-booking').on('click', function() {
        if (!confirm(vbcAjax.strings.confirmDelete)) {
            return;
        }
        
        const bookingId = $('#vbc-booking-id').val();
        
        $.ajax({
            url: vbcAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'vbc_delete_booking_calendar',
                nonce: vbcAjax.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    showNotification(vbcAjax.strings.bookingDeleted, 'success');
                    $('#vbc-booking-modal').fadeOut(200);
                    if (calendar) {
                        calendar.refetchEvents();
                    }
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert(vbcAjax.strings.error);
            }
        });
    });
    
    // Initialize calendar on page load
    if ($('#vbc-calendar').length) {
        console.log('Calendar element found, initializing...');
        
        // Make sure FullCalendar is loaded
        if (typeof window.FullCalendar === 'undefined') {
            console.error('FullCalendar library not loaded!');
            $('#vbc-calendar').html('<div style="padding: 20px; background: #fee; border: 1px solid #c33; color: #c33; border-radius: 4px;"><strong>Error:</strong> Calendar library not loaded. Please refresh the page.</div>');
        } else {
            console.log('FullCalendar loaded');
            // Small delay to ensure DOM is fully ready
            setTimeout(function() {
                try {
                    initCalendar();
                } catch(error) {
                    console.error('Error initializing calendar:', error);
                    $('#vbc-calendar').html('<div style="padding: 20px; background: #fee; border: 1px solid #c33; color: #c33; border-radius: 4px;"><strong>Error:</strong> ' + error.message + '</div>');
                }
            }, 100);
        }
    } else {
        console.log('Calendar element not found on this page');
    }
    
    // ==================== RECURRING BOOKINGS ====================
    
    // Toggle recurring options
    $('#vbc-modal-recurring').on('change', function() {
        if ($(this).is(':checked')) {
            $('#vbc-recurring-options').slideDown();
        } else {
            $('#vbc-recurring-options').slideUp();
        }
    });
    
    // Recurrence type change
    $('#vbc-recurrence-type').on('change', function() {
        const type = $(this).val();
        let label = '';
        
        // Update interval label based on recurrence type
        switch(type) {
            case 'daily':
                label = 'day(s)';
                break;
            case 'weekly':
            case 'biweekly':
                label = 'week(s)';
                break;
            case 'monthly':
            case 'monthly_day':
                label = 'month(s)';
                break;
            default:
                label = 'day(s)';
        }
        
        $('#vbc-interval-label').text(label);
        
        // Show/hide monthly day options
        if (type === 'monthly_day') {
            $('#vbc-monthly-day-options').show();
        } else {
            $('#vbc-monthly-day-options').hide();
        }
    });
    
});
