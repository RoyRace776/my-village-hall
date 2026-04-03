<?php
/*
Template Name: MYVH Login
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>
<main class="site-main site-main--auth">
    <section class="shell-block shell-block--auth">
        <header class="shell-block__header">
            <p><?php esc_html_e( 'Account access', 'myvh-hallflow' ); ?></p>
            <h1><?php the_title(); ?></h1>
        </header>
        <div class="shell-block__content">
            <?php echo myvh_hallflow_render_shortcode_or_notice( '[myvh_login]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>
</main>
<?php
get_footer();
