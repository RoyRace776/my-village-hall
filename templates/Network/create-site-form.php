<?php
if (!defined('ABSPATH')) {
    exit;
}


$state_data = (isset($state) && is_array($state)) ? $state : [];
$state = array_merge([
    'status' => '',
    'message' => '',
    'site_url' => '',
], $state_data);

$form_values_data = (isset($form_values) && is_array($form_values)) ? $form_values : [];
$form_values = array_merge([
    'site_name' => '',
    'subdomain' => '',
    'admin_first_name' => '',
    'admin_last_name' => '',
    'admin_email' => '',
], $form_values_data);

$verification_result = !empty($verification_result);
$verification_action = isset($verification_action) ? (string) $verification_action : 'verify';
$submitted = !empty($submitted);
$network_domain = isset($network_domain) ? (string) $network_domain : '';
$captcha_site_key = isset($captcha_site_key) ? (string) $captcha_site_key : '';
$request_page_url = isset($request_page_url) ? (string) $request_page_url : home_url('/');
?>
<?php if (!empty($verification_result)): ?>
<div class="myvh-site-request-wrap myvh-site-request-wrap--confirm">
    <div class="myvh-site-request-confirm myvh-site-request-confirm--<?php echo esc_attr($state['status'] === 'success' ? 'success' : 'error'); ?>">
        <div class="myvh-site-request-confirm__icon" aria-hidden="true">
            <?php if ($verification_action === 'cancel'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 8l8 8"/>
                <path d="M16 8l-8 8"/>
            </svg>
            <?php elseif ($state['status'] === 'success'): ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="m8 12 2.5 2.5L16 9"/>
            </svg>
            <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 8v5"/>
                <circle cx="12" cy="16.5" r="0.5" fill="currentColor"/>
            </svg>
            <?php endif; ?>
        </div>

        <h2>
            <?php if ($verification_action === 'cancel' && $state['status'] === 'success'): ?>
                <?php echo esc_html__('Site request cancelled', 'my-village-hall'); ?>
            <?php elseif ($verification_action === 'cancel'): ?>
                <?php echo esc_html__('We could not cancel this request', 'my-village-hall'); ?>
            <?php elseif ($state['status'] === 'success'): ?>
                <?php echo esc_html__('Site setup complete', 'my-village-hall'); ?>
            <?php else: ?>
                <?php echo esc_html__('Site setup could not be completed', 'my-village-hall'); ?>
            <?php endif; ?>
        </h2>

        <p><?php echo esc_html($state['message']); ?></p>

        <div class="myvh-site-request-confirm__summary" aria-label="<?php echo esc_attr__('Provisioning details', 'my-village-hall'); ?>">
            <h3>
                <?php if ($verification_action === 'cancel'): ?>
                    <?php echo esc_html__('Request details', 'my-village-hall'); ?>
                <?php else: ?>
                    <?php echo esc_html__('Provisioning details', 'my-village-hall'); ?>
                <?php endif; ?>
            </h3>
            <dl>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Site name', 'my-village-hall'); ?></dt>
                    <dd><?php echo esc_html($form_values['site_name']); ?></dd>
                </div>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Site address', 'my-village-hall'); ?></dt>
                    <dd><samp><?php echo esc_html($form_values['subdomain']); ?><?php if (!empty($network_domain)): ?>.<?php echo esc_html($network_domain); ?><?php endif; ?></samp></dd>
                </div>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Administrator', 'my-village-hall'); ?></dt>
                    <dd><?php echo esc_html(trim($form_values['admin_first_name'] . ' ' . $form_values['admin_last_name'])); ?></dd>
                </div>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Administrator email', 'my-village-hall'); ?></dt>
                    <dd><?php echo esc_html($form_values['admin_email']); ?></dd>
                </div>
            </dl>
        </div>

        <?php if (!empty($state['site_url'])): ?>
            <a class="myvh-site-request-confirm__link" href="<?php echo esc_url($state['site_url']); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html__('Visit new site', 'my-village-hall'); ?>
            </a>
        <?php elseif ($verification_action === 'cancel'): ?>
            <a class="myvh-site-request-confirm__link myvh-site-request-confirm__link--secondary" href="<?php echo esc_url(remove_query_arg(['myvh_site_verify', 'myvh_site_cancel', 'token'])); ?>">
                <?php echo esc_html__('Start a new request', 'my-village-hall'); ?>
            </a>
        <?php elseif ($state['status'] !== 'success'): ?>
            <a class="myvh-site-request-confirm__link myvh-site-request-confirm__link--secondary" href="<?php echo esc_url(remove_query_arg(['myvh_site_verify', 'myvh_site_cancel', 'token'])); ?>">
                <?php echo esc_html__('Start a new request', 'my-village-hall'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php elseif (!empty($submitted)): ?>
<div class="myvh-site-request-wrap myvh-site-request-wrap--confirm">
    <div class="myvh-site-request-confirm">
        <div class="myvh-site-request-confirm__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="m2 7 10 7 10-7"/>
            </svg>
        </div>
        <h2><?php echo esc_html__('Check your email to verify', 'my-village-hall'); ?></h2>
        <p><?php echo esc_html__('We have sent a verification link to the email address below. Click the link in that email to complete site setup.', 'my-village-hall'); ?></p>

        <div class="myvh-site-request-confirm__summary" aria-label="<?php echo esc_attr__('Submitted details', 'my-village-hall'); ?>">
            <h3><?php echo esc_html__('Site request summary', 'my-village-hall'); ?></h3>
            <dl>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Site name', 'my-village-hall'); ?></dt>
                    <dd><?php echo esc_html($form_values['site_name']); ?></dd>
                </div>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Site address', 'my-village-hall'); ?></dt>
                    <dd><samp><?php echo esc_html($form_values['subdomain']); ?><?php if (!empty($network_domain)): ?>.<?php echo esc_html($network_domain); ?><?php endif; ?></samp></dd>
                </div>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Administrator name', 'my-village-hall'); ?></dt>
                    <dd><?php echo esc_html(trim($form_values['admin_first_name'] . ' ' . $form_values['admin_last_name'])); ?></dd>
                </div>
                <div class="myvh-site-request-confirm__row">
                    <dt><?php echo esc_html__('Verification email sent to', 'my-village-hall'); ?></dt>
                    <dd><?php echo esc_html($form_values['admin_email']); ?></dd>
                </div>
            </dl>
        </div>

        <p class="myvh-site-request-confirm__note"><?php echo esc_html__('The verification link expires in 1 hour. If you do not receive the email, check your spam folder.', 'my-village-hall'); ?></p>
    </div>
</div>
<?php else: ?>
<div class="myvh-site-request-wrap">
    <form method="post" enctype="multipart/form-data" class="myvh-site-request-form">
        <div class="myvh-site-request-hero">
            <div class="myvh-site-request-hero__content">
                <span class="myvh-site-request-kicker"><?php echo esc_html__('Site setup request', 'my-village-hall'); ?></span>
                <h2><?php echo esc_html__('Create your new site', 'my-village-hall'); ?></h2>
                <p><?php echo esc_html__('Complete the details below to request your new site. We will use this information to provision the site and create the first administrator account.', 'my-village-hall'); ?></p>
            </div>

            <div class="myvh-site-request-summary" aria-label="<?php echo esc_attr__('What happens next', 'my-village-hall'); ?>">
                <h3><?php echo esc_html__('What happens next', 'my-village-hall'); ?></h3>
                <ul>
                    <li><?php echo esc_html__('We review the request and prepare your site.', 'my-village-hall'); ?></li>
                    <li><?php echo esc_html__('Your chosen details are used to configure the site and admin login.', 'my-village-hall'); ?></li>
                    <li><?php echo esc_html__('You receive confirmation once the site is ready.', 'my-village-hall'); ?></li>
                </ul>
            </div>
        </div>

        <?php if (!empty($state['message'])): ?>
            <div class="myvh-site-request-message myvh-site-request-message--<?php echo esc_attr($state['status']); ?>">
                <?php echo esc_html($state['message']); ?>
                <?php if (!empty($state['site_url'])): ?>
                    <p><a href="<?php echo esc_url($state['site_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($state['site_url']); ?></a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="myvh-site-request-panel">
            <div class="myvh-site-request-section">
                <div class="myvh-site-request-section__header">
                    <h3><?php echo esc_html__('Site details', 'my-village-hall'); ?></h3>
                    <p><?php echo esc_html__('These details define how the new site will appear and where it will live on the network.', 'my-village-hall'); ?></p>
                </div>

                <div class="myvh-site-request-grid">
                    <label class="myvh-site-request-field">
                        <span class="myvh-site-request-field__label"><?php echo esc_html__('Site name', 'my-village-hall'); ?> <span class="myvh-site-request-badge"><?php echo esc_html__('Required', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-site-name-hint"><?php echo esc_html__('The public name shown across the site, such as in headers, emails, and browser titles.', 'my-village-hall'); ?></span>
                        <input type="text" name="site_name" required value="<?php echo esc_attr($form_values['site_name']); ?>" aria-describedby="myvh-site-name-hint">
                    </label>

                    <label class="myvh-site-request-field">
                        <span class="myvh-site-request-field__label"><?php echo isset($is_subdomain) && $is_subdomain ? esc_html__('Subdomain', 'my-village-hall') : esc_html__('Site path', 'my-village-hall'); ?> <span class="myvh-site-request-badge"><?php echo esc_html__('Required', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-subdomain-hint"><?php echo isset($is_subdomain) && $is_subdomain ? esc_html__('Choose a subdomain name. Use 3-30 lowercase letters, numbers, or hyphens, without a hyphen at the start or end.', 'my-village-hall') : esc_html__('Choose a path segment. Use 3-30 lowercase letters, numbers, or hyphens, without a hyphen at the start or end.', 'my-village-hall'); ?></span>
                        <input type="text" name="subdomain" id="myvh-subdomain-input" required value="<?php echo esc_attr($form_values['subdomain']); ?>" pattern="[a-z0-9-]{3,30}" aria-describedby="myvh-subdomain-hint myvh-subdomain-preview-desc myvh-subdomain-warning">
                        <?php if (!empty($network_domain)): ?>
                        <span class="myvh-site-request-url-preview" id="myvh-subdomain-preview" aria-live="polite">
                            <span class="myvh-site-request-url-preview__label"><?php echo esc_html__('Your site will be at:', 'my-village-hall'); ?></span>
                            <samp id="myvh-subdomain-preview-value">
                                <?php if (isset($is_subdomain) && $is_subdomain): ?>
                                    <em><?php echo esc_html__('yoursite', 'my-village-hall'); ?></em>.<?php echo esc_html($network_domain); ?>
                                <?php else: ?>
                                    <?php echo esc_html($network_domain); ?><?php echo esc_html(isset($network_path) ? $network_path : '/'); ?><em><?php echo esc_html__('yoursite', 'my-village-hall'); ?></em>
                                <?php endif; ?>
                            </samp>
                        </span>
                        <span id="myvh-subdomain-preview-desc" class="sr-only"><?php echo esc_html__('URL preview updates as you type.', 'my-village-hall'); ?></span>
                        <?php endif; ?>
                        <span class="myvh-site-request-field__warning" id="myvh-subdomain-warning" role="note">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            <?php echo esc_html__('This cannot be changed after the site has been created. Choose carefully.', 'my-village-hall'); ?>
                        </span>
                    </label>
                    <?php if (!empty($network_domain)): ?>
                    <script>
                    (function () {
                        var input   = document.getElementById('myvh-subdomain-input');
                        var preview = document.getElementById('myvh-subdomain-preview-value');
                        var isSubdomain = <?php echo wp_json_encode(isset($is_subdomain) && $is_subdomain); ?>;
                        var domain  = <?php echo wp_json_encode($network_domain); ?>;
                        var path    = <?php echo wp_json_encode(isset($network_path) ? $network_path : '/'); ?>;
                        var placeholder = <?php echo wp_json_encode(__('yoursite', 'my-village-hall')); ?>;
                        function update() {
                            var val = input.value.trim() || placeholder;
                            if (isSubdomain) {
                                preview.textContent = val + '.' + domain;
                            } else {
                                preview.textContent = domain + path + val;
                            }
                        }
                        input.addEventListener('input', update);
                        update();
                    })();
                    </script>
                    <?php endif; ?>

                    <label class="myvh-site-request-field myvh-site-request-field--full">
                        <span class="myvh-site-request-field__label"><?php echo esc_html__('Logo', 'my-village-hall'); ?> <span class="myvh-site-request-badge myvh-site-request-badge--muted"><?php echo esc_html__('Optional', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-logo-hint"><?php echo esc_html__('Upload a logo if you want the new site branded from the start. PNG, JPG, GIF, WebP, and SVG files are accepted.', 'my-village-hall'); ?></span>
                        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.gif,.webp,.svg" aria-describedby="myvh-logo-hint">
                    </label>
                </div>
            </div>

            <div class="myvh-site-request-section">
                <div class="myvh-site-request-section__header">
                    <h3><?php echo esc_html__('Administrator account', 'my-village-hall'); ?></h3>
                    <p><?php echo esc_html__('These details are used to create the first administrator who will manage the site after launch.', 'my-village-hall'); ?></p>
                </div>

                <div class="myvh-site-request-grid">
                    <label class="myvh-site-request-field">
                        <span class="myvh-site-request-field__label"><?php echo esc_html__('Admin email', 'my-village-hall'); ?> <span class="myvh-site-request-badge"><?php echo esc_html__('Required', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-admin-email-hint"><?php echo esc_html__('We send site setup and account information to this address, and it becomes the administrator login email.', 'my-village-hall'); ?></span>
                        <input type="email" name="admin_email" required value="<?php echo esc_attr($form_values['admin_email']); ?>" aria-describedby="myvh-admin-email-hint">
                    </label>

                    <label class="myvh-site-request-field">
                        <span class="myvh-site-request-field__label"><?php echo esc_html__('Admin first name', 'my-village-hall'); ?> <span class="myvh-site-request-badge"><?php echo esc_html__('Required', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-admin-first-name-hint"><?php echo esc_html__('The first name for the initial administrator account.', 'my-village-hall'); ?></span>
                        <input type="text" name="admin_first_name" required value="<?php echo esc_attr($form_values['admin_first_name']); ?>" aria-describedby="myvh-admin-first-name-hint">
                    </label>

                    <label class="myvh-site-request-field">
                        <span class="myvh-site-request-field__label"><?php echo esc_html__('Admin last name', 'my-village-hall'); ?> <span class="myvh-site-request-badge"><?php echo esc_html__('Required', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-admin-last-name-hint"><?php echo esc_html__('The surname stored on the administrator profile.', 'my-village-hall'); ?></span>
                        <input type="text" name="admin_last_name" required value="<?php echo esc_attr($form_values['admin_last_name']); ?>" aria-describedby="myvh-admin-last-name-hint">
                    </label>

                    <label class="myvh-site-request-field myvh-site-request-field--full">
                        <span class="myvh-site-request-field__label"><?php echo esc_html__('Admin password', 'my-village-hall'); ?> <span class="myvh-site-request-badge"><?php echo esc_html__('Required', 'my-village-hall'); ?></span></span>
                        <span class="myvh-site-request-field__hint" id="myvh-admin-password-hint"><?php echo esc_html__('Use at least 9 characters including an uppercase letter, a lowercase letter, a number, and a symbol.', 'my-village-hall'); ?></span>
                        <input type="password" name="admin_password" required minlength="9" aria-describedby="myvh-admin-password-hint">
                    </label>

                    <input type="hidden" name="captcha_token" id="myvh-captcha-token" value="">
                </div>
            </div>
        </div>

        <?php if (!empty($captcha_site_key)): ?>
            <p class="description myvh-site-request-captcha-note" data-myvh-captcha-site-key="<?php echo esc_attr($captcha_site_key); ?>">
                <?php echo esc_html__('CAPTCHA is enabled. Your front-end CAPTCHA widget should write its response token into the hidden captcha_token field.', 'my-village-hall'); ?>
            </p>
        <?php endif; ?>

        <?php wp_nonce_field('myvh_create_site_request', 'myvh_create_site_nonce'); ?>
        <input type="hidden" name="myvh_request_page_url" value="<?php echo esc_url($request_page_url ?? home_url('/')); ?>">
        <input type="hidden" name="myvh_create_site_action" value="1">

        <div class="myvh-site-request-actions">
            <button type="submit"><?php echo esc_html__('Request new site', 'my-village-hall'); ?></button>
            <p class="myvh-site-request-footnote"><?php echo esc_html__('Submitting this form will email the administrator a link to click on. Clicking on the link will setup the new site.', 'my-village-hall'); ?></p>
        </div>
    </form>
</div>
<?php endif; ?>
