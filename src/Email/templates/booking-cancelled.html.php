<?php /* HTML email template for booking cancellation */ ?>
<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;">
    <?php if (!empty($logo_url)): ?>
        <p style="text-align:center;margin:0 0 16px 0;"><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name ?? ''); ?>" style="max-width:120px;"></p>
    <?php endif; ?>
    <h2 style="margin:0 0 10px 0;color:#222;">Booking Cancelled</h2>
    <p style="margin:0 0 16px 0;color:#444;">Hi <?php echo esc_html($customer_name ?? 'there'); ?>, your booking has been cancelled.</p>
    <p style="margin:0 0 12px 0;color:#333;line-height:1.5;">
        <strong>Reference:</strong> <?php echo esc_html($booking_ref ?? ''); ?><br>
        <strong>Date:</strong> <?php echo esc_html($booking_date ?? ''); ?><br>
        <strong>Time:</strong> <?php echo esc_html($booking_time ?? ''); ?><br>
        <strong>Venue:</strong> <?php echo esc_html($venue_name ?? ''); ?><br>
        <strong>Room:</strong> <?php echo esc_html($room_name ?? ''); ?><br>
    </p>
    <p style="margin:0;color:#666;">If you need more information, please contact us.</p>
</td></tr>
<tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name ?? ''); ?>
</td></tr>
</table>
