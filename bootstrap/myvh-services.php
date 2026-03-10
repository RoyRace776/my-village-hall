<?php

$myvh_container->singleton(MYVH_Availability_Service::class);

$myvh_container->singleton(MYVH_Pricing_Service::class);

$myvh_container->singleton(MYVH_Room_Service::class);

$myvh_container->singleton(MYVH_Booking_Validator::class);

$myvh_container->singleton(MYVH_Room_Rules_Service::class);

$myvh_container->singleton(MYVH_Customer_Service::class);

$myvh_container->singleton(MYVH_Booking_Service::class);

$myvh_container->singleton(MYVH_Recurring_Pattern_Service::class);

$myvh_container->singleton(MYVH_Customer_Group_Service::class);

$myvh_container->singleton(MYVH_Venue_Service::class);

$myvh_container->singleton(MYVH_Room_Rate_Service::class);

$myvh_container->singleton(MYVH_Addon_Service::class);

//$myvh_container->singleton('calendar_service', function($c) {
//    return new MYVH_Calendar_Service(
//        $c->get('booking_repo')
//    );
//});