<?php
class MYVH_Room_Rules_Service {

    public function within_opening_hours($room, $start, $end): bool {
        $room_open  = strtotime($room['OpeningTime']);
        $room_close = strtotime($room['ClosingTime']);

        $start_time = strtotime(date('H:i', strtotime($start)));
        $end_time   = strtotime(date('H:i', strtotime($end)));

        return ($start_time >= $room_open && $end_time <= $room_close);
    }

}