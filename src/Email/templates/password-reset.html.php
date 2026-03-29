<?php /* HTML email template for password reset */ ?>
<table style="width:100%;max-width:480px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;text-align:center;">
    <?php if (!empty($logo_url)): ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width:120px;margin-bottom:16px;">
    <?php endif; ?>
    <h2 style="margin:0 0 8px 0;color:#222;">Reset your password</h2>
    <p style="margin:0 0 24px 0;color:#444;">We received a request to reset your password for your <?php echo esc_html($site_name); ?> account.</p>
    <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block;padding:12px 28px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;margin-bottom:24px;">Set a new password</a>
    <p style="color:#888;font-size:13px;margin:24px 0 0 0;">If you did not request this, you can safely ignore this email.</p>
</td></tr>
<tr><td style="padding:0 32px 32px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>
</td></tr>
</table>
