<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px #0001;">
    <tr>
        <td style="padding:28px 32px 12px 32px;text-align:center;">
            <?php if (!empty($logo_url)) : ?>
                <p style="margin:0 0 16px 0;">
                    <img src="<?php echo esc_url((string) $logo_url); ?>" alt="<?php echo esc_attr((string) ($site_name ?? '')); ?>" style="max-width:120px;">
                </p>
            <?php endif; ?>

            <h2 style="margin:0 0 8px 0;color:#222;">Verify your email</h2>
            <p style="margin:0 0 20px 0;color:#444;">
                Hello <?php echo esc_html((string) ($customer_name ?? 'there')); ?>, please verify your email address to finish setting up your account on <?php echo esc_html((string) ($site_name ?? 'our site')); ?>.
            </p>
            <p style="margin:0 0 24px 0;">
                <a href="<?php echo esc_url((string) ($verification_url ?? '')); ?>" style="display:inline-block;padding:12px 24px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">Verify email address</a>
            </p>
            <p style="color:#888;font-size:13px;margin:0;">This link expires in <?php echo esc_html((string) ($verification_ttl_hours ?? '24')); ?> hour(s).</p>
        </td>
    </tr>
    <tr>
        <td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">&copy; <?php echo esc_html((string) date('Y')); ?> <?php echo esc_html((string) ($site_name ?? '')); ?></td>
    </tr>
</table>
