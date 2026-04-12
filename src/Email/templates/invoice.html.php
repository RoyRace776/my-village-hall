<?php /* HTML email template for invoice notifications */ ?>
<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;">
    <?php if (!empty($logo_url)): ?>
        <p style="text-align:center;margin:0 0 16px 0;"><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width:120px;"></p>
    <?php endif; ?>
    <h2 style="margin:0 0 10px 0;color:#222;">Invoice <?php echo esc_html($invoice_ref ?? ''); ?></h2>
    <p style="margin:0 0 14px 0;color:#444;">Hi <?php echo esc_html($customer_name ?? 'there'); ?>, your invoice is now available.</p>
    <p style="margin:0 0 12px 0;color:#333;line-height:1.5;">
        <strong>Total:</strong> <?php echo esc_html($invoice_total ?? ''); ?><br>
        <strong>Due date:</strong> <?php echo esc_html($invoice_due_date ?? ''); ?><br>
        <strong>Status:</strong> <?php echo esc_html($invoice_status ?? ''); ?><br>
        <strong>Organisation:</strong> <?php echo esc_html($organisation_name ?? ''); ?>
    </p>
    <?php if (!empty($booking_details)): ?>
        <p style="margin:0 0 14px 0;color:#666;"><?php echo esc_html($booking_details); ?></p>
    <?php endif; ?>
    <?php if (!empty($invoice_url)): ?>
        <p style="margin:0;"><a href="<?php echo esc_url($invoice_url); ?>" style="display:inline-block;padding:10px 20px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;">View invoice</a></p>
    <?php endif; ?>
</td></tr>
<tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name ?? ''); ?>
</td></tr>
</table>
