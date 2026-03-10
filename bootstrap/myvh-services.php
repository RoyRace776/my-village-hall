<?php

$myvh_container->singleton('availability_service', function($c) {
    return new MYVH_Availability_Service(
        $c->get('booking_repo')
    );
});

$myvh_container->singleton('pricing_service', function($c) {
    return new MYVH_Pricing_Service(
        $c->get('room_rate_repo'),
        $c->get('addon_repo')
    );
});

$myvh_container->singleton('room_service', function($c) {
    return new MYVH_Room_Service(
        $c->get('room_repo')
    );
});

$myvh_container->singleton('booking_validator', function($c) {
    return new MYVH_Booking_Validator(
    );
});


$myvh_container->singleton('room_rules_service', function($c) {
    return new MYVH_Room_Rules_Service(
    );
});

$myvh_container->singleton('customer_service', function($c) {
    return new MYVH_Customer_Service(
        $c->get('customer_repo'),
        $c->get('booking_repo')
    );
});

$myvh_container->singleton('booking_service', function($c) {
    return new MYVH_Booking_Service(
        $c->get('room_service'),
        $c->get('booking_repo'),
        $c->get('booking_addon_repo'),
        $c->get('booking_validator'),
        $c->get('availability_service'),
        $c->get('room_rules_service'),
        $c->get('pricing_service'),
        $c->get('recurring_pattern_service')
    );
});

$myvh_container->singleton('recurring_pattern_service', function($c) {
    return new MYVH_Recurring_Pattern_Service(
        $c->get('recurring_pattern_repo'),
        $c->get('booking_repo')
    );
});

$myvh_container->singleton('customer_group_service', function($c) {
    return new MYVH_Customer_Group_Service(
        $c->get('customer_group_repo')
    );
});

$myvh_container->singleton('venue_service', function($c) {
    return new MYVH_Venue_Service(
        $c->get('venue_repo')
    );
});

$myvh_container->singleton('room_rate_service', function($c) {
    return new MYVH_Room_Rate_Service(
        $c->get('room_rate_repo')
    );
});

$myvh_container->singleton('addon_service', function($c) {
    return new MYVH_Addon_Service(
        $c->get('addon_repo')
    );
});

//$myvh_container->singleton('calendar_service', function($c) {
//    return new MYVH_Calendar_Service(
//        $c->get('booking_repo')
//    );
//});