<?php /* HTML email template for password setup */ ?>
<table style="width:100%;max-width:480px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;text-align:center;">
    <?php if (!empty($logo_url)): ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-width:120px;margin-bottom:16px;">
    <?php endif; ?>
    <h2 style="margin:0 0 8px 0;color:#222;">Set up your password</h2>
    <p style="margin:0 0 24px 0;color:#444;">Your account has been created on <?php echo esc_html($site_name); ?>. Click the button below to set up your password and get started.</p>
    <a href="<?php echo esc_url($reset_url); ?>" style="display:inline-block;padding:12px 28px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;margin-bottom:24px;">Set up password</a>
    <p style="color:#888;font-size:13px;margin:24px 0 0 0;">This link will expire in 1 hour. If you need another, contact your administrator.</p>
</td></tr>
<tr><td style="padding:0 32px 32px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>
</td></tr>
</table>
