<?php
namespace MYVH\Calendar;

class CalendarServiceProvider
{
    public function register($container): void {
        $container->singleton(CalendarService::class);
        $container->singleton(CalendarAjaxController::class);
    }
}