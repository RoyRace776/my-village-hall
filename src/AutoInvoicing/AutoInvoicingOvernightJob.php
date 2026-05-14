<?php
namespace MYVH\AutoInvoicing;

use MYVH\Core\Scheduling\OvernightJobInterface;
use MYVH\Core\Scheduling\OvernightJobResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AutoInvoicingOvernightJob implements OvernightJobInterface {

    private AutoInvoice $auto_invoice;

    public function __construct( AutoInvoice $auto_invoice ) {
        $this->auto_invoice = $auto_invoice;
    }

    public function is_enabled(): bool {
        return (bool) myvh_setting( 'invoicing.run_overnight', false );
    }

    public function run(): OvernightJobResult {
        $count = $this->auto_invoice->generate();

        return new OvernightJobResult(
            job_name: 'Auto-Invoicing',
            count:    (int) $count,
            success:  true,
            summary:  $count . ' invoice(s) generated'
        );
    }
}
