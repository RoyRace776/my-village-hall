<?php /* HTML email template for payment receipts */ ?>
<?php
$site_name = $site_name ?? get_bloginfo('name');
$logo_url = $logo_url ?? '';
$customer_name = $customer_name ?? 'there';
$invoice_ref = $invoice_ref ?? '';
$payment_amount = $payment_amount ?? '';
$payment_date = $payment_date ?? '';
$payment_method = $payment_method ?? '';
$payment_reference = $payment_reference ?? '';
$payment_comment = $payment_comment ?? '';
$amount_due = $amount_due ?? '';
?>
<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;">
    <?php if (!empty($logo_url)): ?>
        <p style="text-align:center;margin:0 0 16px 0;"><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width:120px;"></p>
    <?php endif; ?>
    <h2 style="margin:0 0 10px 0;color:#222;">Payment receipt</h2>
    <p style="margin:0 0 14px 0;color:#444;">Hi <?php echo esc_html($customer_name); ?>, we have received your payment.</p>
    <p style="margin:0 0 12px 0;color:#333;line-height:1.5;">
        <strong>Invoice:</strong> <?php echo esc_html($invoice_ref); ?><br>
        <strong>Payment amount:</strong> <?php echo esc_html($payment_amount); ?><br>
        <strong>Payment date:</strong> <?php echo esc_html($payment_date); ?><br>
        <strong>Method:</strong> <?php echo esc_html($payment_method); ?><br>
        <?php if ($payment_reference !== ''): ?>
            <strong>Reference:</strong> <?php echo esc_html($payment_reference); ?><br>
        <?php endif; ?>
        <strong>Remaining balance:</strong> <?php echo esc_html($amount_due); ?>
    </p>
    <?php if ($payment_comment !== ''): ?>
        <p style="margin:0 0 12px 0;color:#666;"><strong>Comment:</strong> <?php echo esc_html($payment_comment); ?></p>
    <?php endif; ?>
    <p style="margin:0;color:#666;">Thank you for your payment.</p>
</td></tr>
<tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>
</td></tr>
</table>
