<?php
/*
Template Name: MYVH Public Calendar
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main class="site-main site-main--calendar">
    <section class="shell-block shell-block--calendar">
        <header class="shell-block__header">
            <p><?php esc_html_e( 'Availability', 'myvh-hallflow' ); ?></p>
            <h1><?php the_title(); ?></h1>
        </header>
        <div class="shell-block__content">
            <?php echo myvh_hallflow_render_shortcode_or_notice( '[myvh_public_calendar view="month" height="760"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>
</main>
<?php
get_footer();
