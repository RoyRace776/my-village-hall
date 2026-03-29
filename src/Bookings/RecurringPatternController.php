<?php

namespace MYVH\Bookings;

class RecurringPatternController
{
    private $service;

    public function __construct(RecurringPatternService $service)
    {
        $this->service = $service;
    }

    // ... (full code from original, with namespace and use statements updated)
}
