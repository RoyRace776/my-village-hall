<?php

class MYVH_Event_Dispatcher {

    public static function dispatch($event_name, $payload = []): void {

        /**
         * Domain events go through here
         */
        do_action('myvh_event_' . $event_name, $payload);

    }

}