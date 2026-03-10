<?php
/**
 * Helper function to generate time select options (quarter-hour increments)
 */
function myvh_get_time_options($selected = '', $start_hour = 0, $end_hour = 23, $on_hour_only = false) {
    $options = '';
    
    for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
        $hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);
        
        if ($on_hour_only) {
            // Only show times on the hour (for opening/closing times)
            $time = $hour_str . ':00';
            $selected_attr = ($time == substr($selected, 0, 5)) ? ' selected' : '';
            $options .= '<option value="' . $time . '"' . $selected_attr . '>' . $time . '</option>';
        } else {
            // Show all quarter-hour increments
            $minutes = array('00', '15', '30', '45');
            
            foreach ($minutes as $minute) {
                $time = $hour_str . ':' . $minute;
                $selected_attr = ($time == substr($selected, 0, 5)) ? ' selected' : '';
                $options .= '<option value="' . $time . '"' . $selected_attr . '>' . $time . '</option>';
            }
        }
    }
    
    return $options;
}

/**
 * Get time options limited to room/venue operating hours
 */
//function myvh_get_booking_time_options($room_id, $selected = '') {
//    global $myvh_db;
//    
//    if (!$room_id) {
//        // No room selected yet, return empty
//        return '';
//    }
//    
//    $room = $myvh_db->get_room($room_id);
//    if (!$room) {
//        return '';
//   }
//    
//    $venue = $myvh_db->get_venue($room->VenueId);
//    if (!$venue) {
//        return '';
//    }
//    
//    // Determine effective opening/closing times
//    $opening_time = $room->OpeningTime ? $room->OpeningTime : $venue->OpeningTime;
//    $closing_time = $room->ClosingTime ? $room->ClosingTime : $venue->ClosingTime;
//    
//    // Parse opening and closing times
//    list($open_hour, $open_min) = explode(':', substr($opening_time, 0, 5));
//    list($close_hour, $close_min) = explode(':', substr($closing_time, 0, 5));
//    
//    $options = '';
//    
//    // Normalize selected time to HH:MM format
//    $selected_normalized = substr($selected, 0, 5);
//    
//    // Generate options from opening to closing time
//    for ($hour = intval($open_hour); $hour <= intval($close_hour); $hour++) {
//        $hour_str = str_pad($hour, 2, '0', STR_PAD_LEFT);
//        $minutes = array('00', '15', '30', '45');
//        
//        foreach ($minutes as $minute) {
//            $time = $hour_str . ':' . $minute;
//           
//            // Skip if before opening time
//            if ($hour == intval($open_hour) && intval($minute) < intval($open_min)) {
//                continue;
//            }
//            
//            // Skip if after closing time
//            if ($hour == intval($close_hour) && intval($minute) > intval($close_min)) {
//                continue;
//            }
//            
//            // Don't go past closing time
//            if ($hour > intval($close_hour)) {
//                break;
//            }
//            
//            $selected_attr = ($time == $selected_normalized) ? ' selected' : '';
//            $options .= '<option value="' . $time . '"' . $selected_attr . '>' . $time . '</option>';
//        }
//    }
//    
//    return $options;
//}
