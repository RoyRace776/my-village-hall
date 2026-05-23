<?php

namespace MYVH\Lifecycle\Contracts;

interface OvernightSchedulerInterface {
    public function clear_overdue_jobs(): void;
}
