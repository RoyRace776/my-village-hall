<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main class="site-main">
    <section class="hero-slab">
        <div class="hero-slab__content">
            <p class="hero-slab__eyebrow"><?php esc_html_e( 'Community bookings, simplified', 'myvh-hallflow' ); ?></p>
            <h1><?php bloginfo( 'name' ); ?></h1>
            <p><?php esc_html_e( 'Run your hall calendar, customer login, and booking workflows from one elegant portal experience.', 'myvh-hallflow' ); ?></p>
            <div class="hero-slab__actions">
                <a class="pill-btn" href="<?php echo esc_url( home_url( '/portal/' ) ); ?>"><?php esc_html_e( 'Open Portal', 'myvh-hallflow' ); ?></a>
                <a class="pill-btn pill-btn--ghost" href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>"><?php esc_html_e( 'View Public Calendar', 'myvh-hallflow' ); ?></a>
            </div>
        </div>
    </section>

    <section class="showcase-grid">
        <article class="showcase-card">
            <h2><?php esc_html_e( 'Customer Portal', 'myvh-hallflow' ); ?></h2>
            <p><?php esc_html_e( 'Members can sign in, manage bookings, and track invoices in a single account area.', 'myvh-hallflow' ); ?></p>
        </article>
        <article class="showcase-card">
            <h2><?php esc_html_e( 'Live Availability', 'myvh-hallflow' ); ?></h2>
            <p><?php esc_html_e( 'Your public calendar remains clear, responsive, and easy to navigate on any device.', 'myvh-hallflow' ); ?></p>
        </article>
        <article class="showcase-card">
            <h2><?php esc_html_e( 'Admin Friendly', 'myvh-hallflow' ); ?></h2>
            <p><?php esc_html_e( 'Designed to sit comfortably around your My Village Hall plugin workflows.', 'myvh-hallflow' ); ?></p>
        </article>
    </section>
</main>
<?php
get_footer();
