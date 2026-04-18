<?php
if (!defined('ABSPATH')) exit;

// $accessible_sites, $is_client_admin, and $has_customer are resolved by
// PortalBootstrapDataService and unpacked by PortalShortcode::render().
?>

<div id="myvh-portal">

    <?php $current_user = wp_get_current_user(); ?>
    <?php $account_label = trim((string) ($current_user->display_name ?? '')); ?>
    <?php if ($account_label === ''): ?>
        <?php $account_label = __('Account', 'my-village-hall'); ?>
    <?php endif; ?>

    <?php $brand_title = trim((string) ($portal_branding['site_title'] ?? '')); ?>
    <?php if ($brand_title === ''): ?>
        <?php $brand_title = (string) get_bloginfo('name'); ?>
    <?php endif; ?>
    <?php $brand_logo = trim((string) ($portal_branding['logo_url'] ?? '')); ?>

    <div class="myvh-portal-brand" aria-label="<?php esc_attr_e('Portal branding', 'my-village-hall'); ?>">
        <div class="myvh-portal-brand__inner">
            <?php if ($brand_logo !== ''): ?>
                <img class="myvh-portal-brand__logo" src="<?php echo esc_url($brand_logo); ?>" alt="<?php echo esc_attr($brand_title); ?>">
            <?php endif; ?>
            <span class="myvh-portal-brand__title"><?php echo esc_html($brand_title); ?></span>
        </div>
    </div>

    <?php if (count($accessible_sites) > 1): ?>
        <div class="myvh-portal-sites">
            <span class="myvh-portal-sites-label">Your clients</span>
            <div class="myvh-portal-sites-list">
                <?php foreach ($accessible_sites as $site): ?>
                    <a class="myvh-portal-site-link<?php echo !empty($site['is_current']) ? ' is-current' : ''; ?>" href="<?php echo esc_url($site['url']); ?>">
                        <?php echo esc_html($site['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <nav class="<?php echo esc_attr( apply_filters( 'myvh_portal_nav_class', 'portal-nav' ) ); ?>" data-portal-nav>

        <a href="#dashboard">Dashboard</a>
        <a href="#bookings"><?php echo $is_client_admin ? 'Bookings' : 'My Bookings'; ?></a>
        <a href="#calendar">Calendar</a>
        <a href="#invoices">View Invoices</a>
        <?php if ($has_customer||$is_client_admin): ?>
            <a href="#organisations">Organisations</a>
        <?php endif; ?>
        <?php if ($is_client_admin): ?>
            <div class="myvh-portal-nav-group" data-portal-nav-group>
                <button
                    type="button"
                    class="myvh-portal-nav-toggle"
                    aria-expanded="false"
                    aria-controls="myvh-portal-admin-menu"
                >
                    <span>Admin</span>
                    <span class="myvh-portal-nav-toggle-icon" aria-hidden="true"></span>
                </button>
                <div id="myvh-portal-admin-menu" class="myvh-portal-nav-submenu">
                    <a href="#client-admins">Client Admins</a>
                    <a href="#customers">Customers</a>
                    <a href="#organisation-types">Organisation Types</a>
                    <a href="#venues">Venues</a>
                    <a href="#rooms">Rooms</a>
                    <a href="#room-rates">Room Rates</a>
                    <a href="#addons">Add-ons</a>
                    <a href="#payments">Payments</a>
                    <a href="#invoice-generate">Generate Invoices</a>
                    <a href="#email-templates">Email Templates</a>
                    <a href="#settings">Settings</a>
                    <?php if (\MYVH\Audit\AuditTrail::is_enabled()): ?>
                        <a href="#audit-log">Audit Log</a>
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
                <span><?php echo esc_html($account_label); ?></span>
                <span class="myvh-portal-nav-toggle-icon" aria-hidden="true"></span>
            </button>
            <div id="myvh-portal-account-menu" class="myvh-portal-nav-submenu myvh-portal-nav-submenu--account">
                <a href="#account">Account</a>
                <a href="<?php echo esc_url($portal_logout_url); ?>">Logout</a>
            </div>
        </div>

    </nav>

    <div id="portal-content"></div>

</div>

<?php
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-create.php';
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-view.php';
?>
