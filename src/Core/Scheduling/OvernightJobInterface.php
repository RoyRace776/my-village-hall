<?php
namespace MYVH\Core\Scheduling;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface OvernightJobInterface {

    /** Whether this job should run in tonight's batch. */
    public function is_enabled(): bool;

    /** Execute the job and return a result. */
    public function run(): OvernightJobResult;
}
