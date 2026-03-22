<?php
if (!defined('ABSPATH')) exit;

global $myvh_container;

$client_admin_service = isset($myvh_container) ? $myvh_container->get(MYVH_Client_Admin_Service::class) : null;
$customer_service = isset($myvh_container) ? $myvh_container->get(MYVH_Customer_Service::class) : null;
$current_user_id = get_current_user_id();
$customer = $customer_service ? $customer_service->get_by_user_id($current_user_id) : null;
$has_customer = !empty($customer['Id']);
$is_client_admin = $client_admin_service ? $client_admin_service->can_administer_blog($current_user_id, get_current_blog_id()) : false;
$accessible_sites = $client_admin_service ? $client_admin_service->get_accessible_sites_for_user($current_user_id) : [];
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
        <?php if ($has_customer): ?>
            <a href="#organisations">Organisations</a>
        <?php endif; ?>
        <a href="#account">Account</a>
        <?php if ($is_client_admin): ?>
            <a href="#client-admins">Client Admins</a>
            <a href="#customers">Customers</a>
            <a href="#settings">Settings</a>
        <?php endif; ?>

    </nav>

    <div id="portal-content"></div>

</div>
