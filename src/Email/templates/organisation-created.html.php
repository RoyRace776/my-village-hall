<?php /* HTML email template for organisation creation admin notifications */ ?>
<table style="width:100%;max-width:620px;margin:auto;font-family:sans-serif;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px #0001;">
    <tr>
        <td style="padding:28px 32px 12px 32px;">
            <?php if (!empty($logo_url)): ?>
                <p style="margin:0 0 16px 0;">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name ?? ''); ?>" style="max-width:120px;">
                </p>
            <?php endif; ?>
            <h2 style="margin:0 0 8px 0;color:#222;">New organisation created</h2>
            <p style="margin:0 0 16px 0;color:#444;">A new organisation has been created on <?php echo esc_html($site_name ?? ''); ?>.</p>
            <p style="margin:0 0 10px 0;">
                <strong>Name:</strong> <?php echo esc_html($organisation_name ?? ''); ?><br>
                <strong>ID:</strong> <?php echo esc_html((string) ($organisation_id ?? '')); ?><br>
                <strong>Contact email:</strong> <?php echo esc_html($contact_email ?? ''); ?><br>
                <strong>Contact phone:</strong> <?php echo esc_html($contact_phone ?? ''); ?>
            </p>
            <p style="margin:16px 0 0 0;color:#666;">
                <strong>Created by:</strong> <?php echo esc_html($created_by_name ?? ''); ?>
                <?php if (!empty($created_by_email)): ?>
                    (<?php echo esc_html($created_by_email); ?>)
                <?php endif; ?>
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:0 32px 24px 32px;color:#999;font-size:12px;">
            <?php echo esc_html($site_name ?? ''); ?> • <?php echo esc_html($site_url ?? ''); ?>
        </td>
    </tr>
</table>
