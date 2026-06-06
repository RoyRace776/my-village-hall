<?php
namespace MYVH\Calendar;

use MYVH\Application\Services\CalendarRenderer;
use MYVH\Application\Services\EventDetailRenderer;
use MYVH\Application\Services\EventListRenderer;
use MYVH\Application\Services\PortalCalendarRenderer;
use MYVH\Container\Container;

class CalendarServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(CalendarService::class);
        $container->singleton(CalendarAjaxController::class);
        $container->singleton(CalendarRenderer::class);
        $container->singleton(EventListRenderer::class);
        $container->singleton(EventDetailRenderer::class);
        $container->singleton(PortalCalendarRenderer::class);
    }
}