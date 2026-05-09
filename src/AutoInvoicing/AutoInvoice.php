<?php
namespace MYVH\AutoInvoicing;

use MYVH\AutoInvoicing\RecurringBookingAutoInvoicing;
use MYVH\AutoInvoicing\SingleBookingAutoInvoicing;

class AutoInvoice {

protected SingleBookingAutoInvoicing $single_booking_autoinvoicing;
protected RecurringBookingAutoInvoicing $recurring_booking_autoinvoicing;

    public function __construct( SingleBookingAutoInvoicing $single_booking_autoinvoicing,
                                 RecurringBookingAutoInvoicing $recurring_booking_autoinvoicing) {
        $this->single_booking_autoinvoicing = $single_booking_autoinvoicing;
        $this->recurring_booking_autoinvoicing = $recurring_booking_autoinvoicing;
    }

    public function generate() {

        $invoice_count = 0;
        $invoice_count += $this->single_booking_autoinvoicing->process();
        $invoice_count += $this->recurring_booking_autoinvoicing->process();
        return $invoice_count;
    }

}