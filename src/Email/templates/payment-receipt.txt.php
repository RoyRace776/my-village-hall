<?php
$site_name = $site_name ?? get_bloginfo('name');
$customer_name = $customer_name ?? 'there';
$invoice_ref = $invoice_ref ?? '';
$payment_amount = $payment_amount ?? '';
$payment_date = $payment_date ?? '';
$payment_method = $payment_method ?? '';
$payment_reference = $payment_reference ?? '';
$payment_comment = $payment_comment ?? '';
$amount_due = $amount_due ?? '';
?>
Payment receipt

Hi <?php echo $customer_name; ?>,

We have received your payment.

Invoice: <?php echo $invoice_ref; ?>
Payment amount: <?php echo $payment_amount; ?>
Payment date: <?php echo $payment_date; ?>
Method: <?php echo $payment_method; ?>
<?php if ($payment_reference !== ''): ?>Reference: <?php echo $payment_reference; ?>
<?php endif; ?>Remaining balance: <?php echo $amount_due; ?>
<?php if ($payment_comment !== ''): ?>
Comment: <?php echo $payment_comment; ?>
<?php endif; ?>
Thank you for your payment.

<?php echo $site_name; ?>
