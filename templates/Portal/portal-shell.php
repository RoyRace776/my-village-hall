<?php
if (!defined('ABSPATH')) exit;

// $accessible_sites, $is_client_admin, and $has_customer are resolved by
// PortalBootstrapDataService and unpacked by PortalShortcode::render().
$accessible_sites = (isset($accessible_sites) && is_array($accessible_sites)) ? $accessible_sites : [];
$is_client_admin = !empty($is_client_admin);
$has_customer = !empty($has_customer);
$portal_logout_url = isset($portal_logout_url) ? (string) $portal_logout_url : wp_logout_url();
$portal_branding = (isset($portal_branding) && is_array($portal_branding)) ? $portal_branding : [];
?>

<div id="myvh-portal">

    <?php $current_user = wp_get_current_user(); ?>
    <?php $account_label = trim((string) ($current_user->display_name ?? '')); ?>
    <?php if ($account_label === ''): ?>
        <?php $account_label = __('Account', 'my-village-hall'); ?>
    <?php endif; ?>
    <?php $accessible_site_count = count($accessible_sites); ?>
    <?php $account_label_display = $account_label; ?>
    <?php if ($accessible_site_count > 1): ?>
        <?php $account_label_display .= sprintf(' [%d]', $accessible_site_count); ?>
    <?php endif; ?>

    <?php $brand_title = trim((string) ($portal_branding['site_title'] ?? '')); ?>
    <?php if ($brand_title === ''): ?>
        <?php $brand_title = (string) get_bloginfo('name'); ?>
    <?php endif; ?>
    <?php $brand_logo = trim((string) ($portal_branding['logo_url'] ?? '')); ?>

    <!-- Portal header: sticky branding + client switcher at top -->
    <div class="myvh-portal-header" data-portal-header>
        <div class="myvh-portal-brand" aria-label="<?php esc_attr_e('Portal branding', 'my-village-hall'); ?>">
            <div class="myvh-portal-brand__inner">
                <?php if ($brand_logo !== ''): ?>
                    <img class="myvh-portal-brand__logo" src="<?php echo esc_url($brand_logo); ?>" alt="<?php echo esc_attr($brand_title); ?>">
                <?php endif; ?>
                <span class="myvh-portal-brand__title"><?php echo esc_html($brand_title); ?></span>
            </div>
        </div>

        <!-- Portal navigation: part of sticky header -->
        <nav class="<?php echo esc_attr( apply_filters( 'myvh_portal_nav_class', 'portal-nav' ) ); ?>" data-portal-nav>

            <a href="#dashboard"><span class="myvh-portal-menu-icon dashicons dashicons-dashboard" aria-hidden="true"></span><span>Dashboard</span></a>
            <a href="#bookings"><span class="myvh-portal-menu-icon dashicons dashicons-list-view" aria-hidden="true"></span><span><?php echo $is_client_admin ? 'Bookings' : 'My Bookings'; ?></span></a>
            <a href="#calendar"><span class="myvh-portal-menu-icon dashicons dashicons-calendar-alt" aria-hidden="true"></span><span>Calendar</span></a>
            <a href="#invoices"><span class="myvh-portal-menu-icon dashicons dashicons-media-spreadsheet" aria-hidden="true"></span><span>View Invoices</span></a>
            <?php if ($has_customer||$is_client_admin): ?>
                <a href="#organisations"><span class="myvh-portal-menu-icon dashicons dashicons-admin-multisite" aria-hidden="true"></span><span>Organisations</span></a>
            <?php endif; ?>
            <?php if ($is_client_admin): ?>
                <div class="myvh-portal-nav-group" data-portal-nav-group>
                    <button
                        type="button"
                        class="myvh-portal-nav-toggle"
                        aria-expanded="false"
                        aria-controls="myvh-portal-admin-menu"
                    >
                        <span class="myvh-portal-menu-icon dashicons dashicons-admin-tools" aria-hidden="true"></span>
                        <span>Admin</span>
                        <span class="myvh-portal-nav-toggle-icon" aria-hidden="true"></span>
                    </button>
                    <div id="myvh-portal-admin-menu" class="myvh-portal-nav-submenu">
                        <a href="#client-admins"><span class="myvh-portal-menu-icon dashicons dashicons-admin-users" aria-hidden="true"></span><span>Client Admins</span></a>
                        <a href="#customers"><span class="myvh-portal-menu-icon dashicons dashicons-groups" aria-hidden="true"></span><span>Customers</span></a>
                        <a href="#organisation-types"><span class="myvh-portal-menu-icon dashicons dashicons-category" aria-hidden="true"></span><span>Organisation Types</span></a>
                        <a href="#venues"><span class="myvh-portal-menu-icon dashicons dashicons-location-alt" aria-hidden="true"></span><span>Venues</span></a>
                        <a href="#rooms"><span class="myvh-portal-menu-icon dashicons dashicons-admin-home" aria-hidden="true"></span><span>Rooms</span></a>
                        <a href="#room-rates"><span class="myvh-portal-menu-icon dashicons dashicons-money-alt" aria-hidden="true"></span><span>Room Rates</span></a>
                        <a href="#addons"><span class="myvh-portal-menu-icon dashicons dashicons-admin-plugins" aria-hidden="true"></span><span>Add-ons</span></a>
                        <a href="#payments"><span class="myvh-portal-menu-icon dashicons dashicons-money" aria-hidden="true"></span><span>Payments</span></a>
                        <a href="#invoice-generate"><span class="myvh-portal-menu-icon dashicons dashicons-media-document" aria-hidden="true"></span><span>Generate Invoices</span></a>
                        <a href="#single-booking-invoice-rules"><span class="myvh-portal-menu-icon dashicons dashicons-filter" aria-hidden="true"></span><span>Single Invoice Rules</span></a>
                        <a href="#recurring-booking-invoice-rules"><span class="myvh-portal-menu-icon dashicons dashicons-filter" aria-hidden="true"></span><span>Recurring Invoice Rules</span></a>
                        <a href="#email-templates"><span class="myvh-portal-menu-icon dashicons dashicons-email-alt" aria-hidden="true"></span><span>Email Templates</span></a>
                        <a href="#settings"><span class="myvh-portal-menu-icon dashicons dashicons-admin-generic" aria-hidden="true"></span><span>Settings</span></a>
                        <?php if (\MYVH\Audit\AuditTrail::is_enabled()): ?>
                            <a href="#audit-log"><span class="myvh-portal-menu-icon dashicons dashicons-visibility" aria-hidden="true"></span><span>Audit Log</span></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="myvh-portal-nav-group myvh-portal-nav-group--account" data-portal-nav-group>
                <button
                    type="button"
                    class="myvh-portal-nav-toggle"
                    aria-expanded="false"
                    aria-controls="myvh-portal-account-menu"
                >
                    <span class="myvh-portal-menu-icon dashicons dashicons-admin-users" aria-hidden="true"></span>
                    <span class="myvh-portal-account-label"><?php echo esc_html($account_label_display); ?></span>
                    <span class="myvh-portal-nav-toggle-icon" aria-hidden="true"></span>
                </button>
                <div id="myvh-portal-account-menu" class="myvh-portal-nav-submenu myvh-portal-nav-submenu--account">
                    <a href="#account"><span class="myvh-portal-menu-icon dashicons dashicons-id" aria-hidden="true"></span><span>Account</span></a>
                    <?php if ($accessible_site_count > 1): ?>
                        <div class="myvh-portal-account-sites">
                            <span class="myvh-portal-sites-label">Your clients</span>
                            <div class="myvh-portal-account-sites-list">
                                <?php foreach ($accessible_sites as $site): ?>
                                    <a href="<?php echo esc_url($site['url']); ?>"<?php echo !empty($site['is_current']) ? ' aria-current="page"' : ''; ?>>
                                        <?php echo esc_html($site['name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($portal_logout_url); ?>"><span class="myvh-portal-menu-icon dashicons dashicons-external" aria-hidden="true"></span><span>Logout</span></a>
                </div>
            </div>

        </nav>
    </div>

    <div id="portal-content"></div>

</div>

<?php
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-create.php';
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-view.php';
?>
