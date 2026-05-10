<?php
namespace MYVH\Calendar;

use MYVH\Container\Container;

class CalendarServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(CalendarService::class);
        $container->singleton(CalendarAjaxController::class);
    }
}