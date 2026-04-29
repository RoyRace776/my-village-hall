<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="myvh-site-request-wrap">
    <form method="post" enctype="multipart/form-data" class="myvh-site-request-form">
        <h2><?php echo esc_html__('Create your new site', 'my-village-hall'); ?></h2>
        <p><?php echo esc_html__('Complete the details below to request your new subdomain site.', 'my-village-hall'); ?></p>

        <?php if (!empty($state['message'])): ?>
            <div class="myvh-site-request-message myvh-site-request-message--<?php echo esc_attr($state['status']); ?>">
                <?php echo esc_html($state['message']); ?>
                <?php if (!empty($state['site_url'])): ?>
                    <p><a href="<?php echo esc_url($state['site_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($state['site_url']); ?></a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="myvh-site-request-grid">
            <label>
                <span><?php echo esc_html__('Site name', 'my-village-hall'); ?></span>
                <input type="text" name="site_name" required value="<?php echo esc_attr($form_values['site_name']); ?>">
            </label>

            <label>
                <span><?php echo esc_html__('Subdomain', 'my-village-hall'); ?></span>
                <input type="text" name="subdomain" required value="<?php echo esc_attr($form_values['subdomain']); ?>" pattern="[a-z0-9-]{3,30}">
            </label>

            <label>
                <span><?php echo esc_html__('Logo', 'my-village-hall'); ?></span>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.gif,.webp,.svg">
            </label>

            <label>
                <span><?php echo esc_html__('Admin email', 'my-village-hall'); ?></span>
                <input type="email" name="admin_email" required value="<?php echo esc_attr($form_values['admin_email']); ?>">
            </label>

            <label>
                <span><?php echo esc_html__('Admin first name', 'my-village-hall'); ?></span>
                <input type="text" name="admin_first_name" required value="<?php echo esc_attr($form_values['admin_first_name']); ?>">
            </label>

            <label>
                <span><?php echo esc_html__('Admin last name', 'my-village-hall'); ?></span>
                <input type="text" name="admin_last_name" required value="<?php echo esc_attr($form_values['admin_last_name']); ?>">
            </label>

            <label>
                <span><?php echo esc_html__('Admin password', 'my-village-hall'); ?></span>
                <input type="password" name="admin_password" required minlength="9">
            </label>

            <input type="hidden" name="captcha_token" id="myvh-captcha-token" value="">
        </div>

        <?php if (!empty($captcha_site_key)): ?>
            <p class="description" data-myvh-captcha-site-key="<?php echo esc_attr($captcha_site_key); ?>">
                <?php echo esc_html__('CAPTCHA is enabled. Your front-end CAPTCHA widget should write its response token into the hidden captcha_token field.', 'my-village-hall'); ?>
            </p>
        <?php endif; ?>

        <?php wp_nonce_field('myvh_create_site_request', 'myvh_create_site_nonce'); ?>
        <input type="hidden" name="myvh_create_site_action" value="1">

        <button type="submit"><?php echo esc_html__('Request new site', 'my-village-hall'); ?></button>
    </form>
</div>
