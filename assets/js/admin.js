jQuery(document).ready(function($) {
    
    // Helper functions
    function showMessage(element, message, type) {
        element.removeClass('success error').addClass(type).text(message).fadeIn();
        setTimeout(function() { element.fadeOut(); }, 3000);
    }
    
    function showLoading(button) {
        button.prop('disabled', true).after('<span class="myvh-loading"></span>');
    }
    
    function hideLoading(button) {
        button.prop('disabled', false);
        $('.myvh-loading').remove();
    }
    
    // ==================== ROOMS ====================
    
    function syncOrganisationBillingFields() {
        var toggle = $('.myvh-org-invoice-toggle');
        var fields = $('.myvh-org-billing-fields');

        if (!toggle.length || !fields.length) {
            return;
        }

        if (toggle.is(':checked')) {
            fields.show();
        } else {
            fields.hide();
        }
    }

    $('.myvh-org-invoice-toggle').on('change', syncOrganisationBillingFields);
    syncOrganisationBillingFields();

    // Toggle custom room hours
    $('#use_venue_hours').on('change', function() {
        if ($(this).is(':checked')) {
            $('#room_hours_section').hide();
            $('.room-hours-field').hide();
            $('#opening_time').val('');
            $('#closing_time').val('');
        } else {
            $('#room_hours_section').show();
            $('.room-hours-field').show();
        }
    });
    
    // ==================== RECURRING BOOKINGS ====================
    
    // Toggle recurring options
    $('#enable_recurring').on('change', function() {
        if ($(this).is(':checked')) {
            $('#recurring_options').slideDown();
        } else {
            $('#recurring_options').slideUp();
        }
    });
    
    // Recurrence type change
    $('#recurrence_type').on('change', function() {
        var type = $(this).val();
        
        if (type === 'custom' || type === 'daily' || type === 'monthly') {
            $('#recurrence_interval_row').show();
            $('#interval_unit').text(type === 'daily' ? 'days' : (type === 'monthly' ? 'months' : 'days'));
        } else {
            $('#recurrence_interval_row').hide();
        }
        
        if (type === 'monthly_day') {
            $('#monthly_day_options').show();
        } else {
            $('#monthly_day_options').hide();
        }
    });
    
    // End repeat type change
    $('input[name="recurrence_end_type"]').on('change', function() {
        var type = $(this).val();
        $('#recurrence_end_date').prop('disabled', type !== 'on_date');
        $('#max_occurrences').prop('disabled', type !== 'after_occurrences');
    });
    
    // ==================== BOOKING FORM ====================
    
    // Load rooms when venue selected (for multi-venue setup)
    $('#venue_id').on('change', function() {
        var venueId = $(this).val();
        var roomContainer = $('#room-checkboxes');
        
        if (!venueId) {
            roomContainer.html('<p class="description">Select a venue first to see available rooms</p>');
            return;
        }
        
        roomContainer.html('<p class="description">Loading rooms...</p>');
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'myvh_get_rooms_by_venue',
                nonce: myvhAjax.nonce,
                venue_id: venueId
            },
            success: function(response) {
                if (response.success && response.data.rooms.length > 0) {
                    var html = '';
                    response.data.rooms.forEach(function(room) {
                        html += '<label style="display: block; margin: 8px 0;">';
                        html += '<input type="radio" name="room_id" value="' + room.Id + '" required class="room-radio">';
                        html += '<strong>' + room.Name + '</strong>';
                        if (room.Description) {
                            html += '<br><span style="margin-left: 24px; color: #666; font-size: 13px;">';
                            html += room.Description;
                            html += '</span>';
                        }
                        html += '</label>';
                    });
                    roomContainer.html(html);
                    
                    // Attach change handler to new radio buttons
                    attachRoomChangeHandler();
                } else {
                    roomContainer.html('<p class="description">No rooms available for this venue</p>');
                }
            },
            error: function() {
                roomContainer.html('<p class="description">Error loading rooms</p>');
            }
        });
    });
    
    // Function to attach room change handler
    function attachRoomChangeHandler() {
        $('.room-radio').off('change').on('change', function() {
            if ($(this).is(':checked')) {
                loadRoomTimes($(this).val());
            }
        });
    }
    
    // Attach to existing radio buttons on page load
    attachRoomChangeHandler();
    
    // Auto-load times if venue is pre-selected (single venue or editing)
    if ($('#venue_id').val() && $('.room-radio:checked').length > 0) {
        loadRoomTimes($('.room-radio:checked').val());
    }
    
    // Function to load time options for a room
    function loadRoomTimes(roomId) {
        var startTimeSelect = $('#start_time');
        var endTimeSelect = $('#end_time');
        
        // Save currently selected values
        var selectedStartTime = startTimeSelect.val();
        var selectedEndTime = endTimeSelect.val();
        
        if (!roomId) {
            startTimeSelect.html('<option value="">Select room first</option>');
            endTimeSelect.html('<option value="">Select room first</option>');
            return;
        }
        
        // Show loading state
        startTimeSelect.html('<option value="">Loading times...</option>');
        endTimeSelect.html('<option value="">Loading times...</option>');
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'myvh_get_room_time_options',
                nonce: myvhAjax.nonce,
                room_id: roomId
            },
            success: function(response) {
                if (response.success) {
                    startTimeSelect.html('<option value="">Select start time</option>' + response.data.start_time_options);
                    endTimeSelect.html('<option value="">Select end time</option>' + response.data.end_time_options);
                    
                    // Restore previously selected values if they exist in the new options
                    if (selectedStartTime) {
                        startTimeSelect.val(selectedStartTime);
                    }
                    if (selectedEndTime) {
                        endTimeSelect.val(selectedEndTime);
                    }
                } else {
                    startTimeSelect.html('<option value="">Error loading times</option>');
                    endTimeSelect.html('<option value="">Error loading times</option>');
                }
            },
            error: function() {
                startTimeSelect.html('<option value="">Error loading times</option>');
                endTimeSelect.html('<option value="">Error loading times</option>');
            }
        });
    }
    
    // Save booking
    $('#myvh-booking-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var messageEl = form.find('.myvh-form-message');
        
        showLoading(submitBtn);
        
        var bookingId = form.find('input[name="booking_id"]').val();
        var isRecurring = $('#enable_recurring').is(':checked');
        var editScope = form.find('input[name="edit_scope"]:checked').val();
        
        // Determine which action to use
        var action = 'myvh_save_booking';
        
        if (bookingId && editScope) {
            // Editing an existing recurring booking
            action = 'myvh_update_recurring_booking';
        } else if (isRecurring) {
            // Creating a new recurring booking
            action = 'myvh_save_recurring_booking';
        }
        
        console.log('Saving booking with action:', action, 'Edit scope:', editScope);
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=' + action + '&nonce=' + myvhAjax.nonce,
            success: function(response) {
                hideLoading(submitBtn);
                
                if (response.success) {
                    var msg = response.data.message;
                    if (response.data.generated_count) {
                        msg += ' (' + response.data.generated_count + ' future bookings created)';
                    }
                    showMessage(messageEl, msg, 'success');
                    setTimeout(function() {
                        window.location.href = '?page=my-village-hall';
                    }, 2000);
                } else {
                    showMessage(messageEl, response.data.message, 'error');
                }
            },
            error: function() {
                hideLoading(submitBtn);
                showMessage(messageEl, 'An error occurred. Please try again.', 'error');
            }
        });
    });
    
    // Delete booking
    $('.myvh-delete-booking').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this booking?')) {
            return;
        }
        
        var bookingId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'myvh_delete_booking',
                nonce: myvhAjax.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(400, function() { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // ==================== OTHER PAGES ====================
    
    // Customer form
    $('#myvh-customer-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var messageEl = form.find('.myvh-form-message');
        
        showLoading(submitBtn);
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=myvh_save_customer&nonce=' + myvhAjax.nonce,
            success: function(response) {
                hideLoading(submitBtn);
                
                if (response.success) {
                    showMessage(messageEl, response.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = '?page=myvh-customers';
                    }, 1500);
                } else {
                    showMessage(messageEl, response.data.message, 'error');
                }
            },
            error: function() {
                hideLoading(submitBtn);
                showMessage(messageEl, 'An error occurred. Please try again.', 'error');
            }
        });
    });
    
    // Delete customer
    $('.myvh-delete-customer').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this customer?')) {
            return;
        }
        
        var customerId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'myvh_delete_customer',
                nonce: myvhAjax.nonce,
                customer_id: customerId
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(400, function() { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // Venue form
    $('#myvh-venue-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var messageEl = form.find('.myvh-form-message');
        
        showLoading(submitBtn);
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=myvh_save_venue&nonce=' + myvhAjax.nonce,
            success: function(response) {
                hideLoading(submitBtn);
                
                if (response.success) {
                    showMessage(messageEl, response.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = '?page=myvh-venues';
                    }, 1500);
                } else {
                    showMessage(messageEl, response.data.message, 'error');
                }
            },
            error: function() {
                hideLoading(submitBtn);
                showMessage(messageEl, 'An error occurred. Please try again.', 'error');
            }
        });
    });
    
    // Delete venue
    $('.myvh-delete-venue').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this venue?')) {
            return;
        }
        
        var venueId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'myvh_delete_venue',
                nonce: myvhAjax.nonce,
                venue_id: venueId
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(400, function() { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // Room form
    $('#myvh-room-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var submitBtn = form.find('button[type="submit"]');
        var messageEl = form.find('.myvh-form-message');
        
        showLoading(submitBtn);
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: form.serialize() + '&action=myvh_save_room&nonce=' + myvhAjax.nonce,
            success: function(response) {
                hideLoading(submitBtn);
                
                if (response.success) {
                    showMessage(messageEl, response.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = '?page=myvh-rooms';
                    }, 1500);
                } else {
                    showMessage(messageEl, response.data.message, 'error');
                }
            },
            error: function() {
                hideLoading(submitBtn);
                showMessage(messageEl, 'An error occurred. Please try again.', 'error');
            }
        });
    });
    
    // Delete room
    $('.myvh-delete-room').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this room?')) {
            return;
        }
        
        var roomId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: myvhAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'myvh_delete_room',
                nonce: myvhAjax.nonce,
                room_id: roomId
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(400, function() { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
});
