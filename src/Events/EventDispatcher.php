<?php
namespace MYVH\Events;

class EventDispatcher {

    public static function dispatch( mixed $event_name, mixed $payload = []): void {

        /**
         * Domain events go through here
         */
        do_action('myvh_event_' . $event_name, $payload);

    }

}
