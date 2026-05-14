<?php
namespace MYVH\Core\Scheduling;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OvernightJobResult {

    public function __construct(
        public readonly string $job_name,
        public readonly int    $count,
        public readonly bool   $success,
        public readonly string $summary = ''
    ) {}
}
