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

    <nav class="<?php echo esc_attr( apply_filters( 'myvh_portal_nav_class', 'portal-nav' ) ); ?>">

        <a href="#dashboard">Dashboard</a>
        <a href="#bookings"><?php echo $is_client_admin ? 'Bookings' : 'My Bookings'; ?></a>
        <a href="#calendar">Calendar</a>
        <a href="#invoices">Invoices</a>
        <?php if ($has_customer||$is_client_admin): ?>
            <a href="#organisations">Organisations</a>
        <?php endif; ?>
        <a href="#account">Account</a>
        <?php if ($is_client_admin): ?>
            <a href="#client-admins">Client Admins</a>
            <a href="#customers">Customers</a>
            <a href="#organisation-types">Organisation Types</a>
            <a href="#rooms">Rooms</a>
            <a href="#room-rates">Room Rates</a>
            <a href="#settings">Settings</a>
        <?php endif; ?>

    </nav>

    <div id="portal-content"></div>

</div>
