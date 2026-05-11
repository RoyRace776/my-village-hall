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
        $recurring_result = $this->recurring_booking_autoinvoicing->process_with_result();
        $single_result = $this->single_booking_autoinvoicing->process_with_result(
            $recurring_result['treat_as_single_bookings'] ?? []
        );

        $recurring_invoice_ids = $recurring_result['created_invoice_ids'] ?? [];
        $single_invoice_ids = $single_result['created_invoice_ids'] ?? [];

        return count($recurring_invoice_ids) + count($single_invoice_ids);
    }

}