<?php
if (!defined('ABSPATH')) exit;

// $accessible_sites, $is_client_admin, and $has_customer are resolved by
// PortalBootstrapDataService and unpacked by PortalShortcode::render().
?>

<div id="myvh-portal">

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
        <a href="#account">Account</a>
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
                    <a href="#settings">Settings</a>
                </div>
            </div>
        <?php endif; ?>

    </nav>

    <div id="portal-content"></div>

</div>

<?php
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-create.php';
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-view.php';
?>
