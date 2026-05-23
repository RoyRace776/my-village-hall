<?php

namespace MYVH\Lifecycle\WordPress;

use MYVH\Core\Scheduling\OvernightBatchRunner;
use MYVH\Core\Scheduling\OvernightJobScheduler;
use MYVH\Lifecycle\Contracts\OvernightSchedulerInterface;

class WordPressOvernightScheduler implements OvernightSchedulerInterface {
    public function clear_overdue_jobs(): void {
        OvernightJobScheduler::clear( OvernightBatchRunner::HOOK );
    }
}
