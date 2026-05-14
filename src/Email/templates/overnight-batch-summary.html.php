<?php /* HTML email template: overnight batch summary (admin-only) */ ?>
<table style="width:100%;max-width:620px;margin:auto;font-family:sans-serif;background:#fff;border-radius:8px;box-shadow:0 2px 8px #0001;">
<tr><td style="padding:32px 32px 16px 32px;">
    <?php if (!empty($logo_url)): ?>
        <p style="text-align:center;margin:0 0 16px 0;"><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name ?? ''); ?>" style="max-width:120px;"></p>
    <?php endif; ?>
    <h2 style="margin:0 0 6px 0;color:#222;">Overnight Batch Summary</h2>
    <p style="margin:0 0 20px 0;color:#666;font-size:14px;">Run completed: <?php echo esc_html($run_date ?? ''); ?></p>
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
            <tr style="background:#f5f5f5;">
                <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #ddd;color:#444;">Job</th>
                <th style="padding:8px 12px;text-align:center;border-bottom:2px solid #ddd;color:#444;">Processed</th>
                <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #ddd;color:#444;">Status</th>
                <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #ddd;color:#444;">Detail</th>
            </tr>
        </thead>
        <tbody>
            <?php echo $summary_rows ?? ''; ?>
        </tbody>
    </table>
</td></tr>
<tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">
    &copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name ?? ''); ?> &bull; <a href="<?php echo esc_url($site_url ?? ''); ?>" style="color:#bbb;"><?php echo esc_html($site_url ?? ''); ?></a>
</td></tr>
</table>
