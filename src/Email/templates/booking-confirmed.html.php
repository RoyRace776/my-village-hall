<?php /* HTML email template for booking confirmation */ ?>
<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;">
    <?php if (!empty($logo_url)): ?>
        <p style="text-align:center;margin:0 0 16px 0;"><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width:120px;"></p>
    <?php endif; ?>
    <h2 style="margin:0 0 10px 0;color:#222;">Booking Confirmed</h2>
    <p style="margin:0 0 16px 0;color:#444;">Hi <?php echo esc_html($customer_name ?? 'there'); ?>, your booking has been confirmed.</p>
    <p style="margin:0 0 12px 0;color:#333;line-height:1.5;">
        <strong>Reference:</strong> <?php echo esc_html($booking_ref ?? ''); ?><br>
        <strong>Date:</strong> <?php echo esc_html($booking_date ?? ''); ?><br>
        <strong>Time:</strong> <?php echo esc_html($booking_time ?? ''); ?><br>
        <strong>Venue:</strong> <?php echo esc_html($venue_name ?? ''); ?><br>
        <strong>Room:</strong> <?php echo esc_html($room_name ?? ''); ?><br>
        <strong>Amount:</strong> <?php echo esc_html($booking_amount ?? ''); ?>
    </p>
    <?php if (!empty($booking_description)): ?>
        <p style="margin:0 0 12px 0;color:#666;"><?php echo esc_html($booking_description); ?></p>
    <?php endif; ?>
    <?php if (!empty($customer_address)): ?>
        <p style="margin:0;color:#666;"><strong>Address:</strong> <?php echo esc_html($customer_address); ?></p>
    <?php endif; ?>
</td></tr>
<tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name ?? ''); ?>
</td></tr>
</table>
