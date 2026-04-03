<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function myvh_hallflow_setup(): void {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );

    register_nav_menus([
        'primary' => __( 'Primary Menu', 'myvh-hallflow' ),
        'footer'  => __( 'Footer Menu', 'myvh-hallflow' ),
    ]);
}
add_action( 'after_setup_theme', 'myvh_hallflow_setup' );

function myvh_hallflow_enqueue_assets(): void {
    wp_enqueue_style(
        'myvh-hallflow-fonts',
        'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;700&family=Newsreader:opsz,wght@6..72,500;6..72,700&display=swap',
        [],
        null
    );

    wp_enqueue_style(
        'myvh-hallflow-style',
        get_theme_file_uri( 'assets/css/site.css' ),
        [ 'myvh-hallflow-fonts' ],
        wp_get_theme()->get( 'Version' )
    );

    wp_enqueue_script(
        'myvh-hallflow-site',
        get_theme_file_uri( 'assets/js/site.js' ),
        [],
        wp_get_theme()->get( 'Version' ),
        true
    );
}
add_action( 'wp_enqueue_scripts', 'myvh_hallflow_enqueue_assets' );

function myvh_hallflow_body_classes( array $classes ): array {
    if ( is_page_template( 'templates/template-portal.php' ) ) {
        $classes[] = 'myvh-page-portal';
    }

    if ( is_page_template( 'templates/template-login.php' ) ) {
        $classes[] = 'myvh-page-login';
    }

    if ( is_page_template( 'templates/template-public-calendar.php' ) ) {
        $classes[] = 'myvh-page-public-calendar';
    }

    return $classes;
}
add_filter( 'body_class', 'myvh_hallflow_body_classes' );

function myvh_hallflow_disable_portal_font_duplication( $url ) {
    if ( is_page_template( 'templates/template-portal.php' ) || has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'myvh_portal' ) ) {
        return false;
    }

    return $url;
}
add_filter( 'myvh_portal_fonts_url', 'myvh_hallflow_disable_portal_font_duplication' );

function myvh_hallflow_render_shortcode_or_notice( string $shortcode ): string {
    if ( shortcode_exists( trim( $shortcode, '[]' ) ) ) {
        return do_shortcode( $shortcode );
    }

    return sprintf(
        '<div class="myvh-plugin-notice"><h2>%s</h2><p>%s</p></div>',
        esc_html__( 'My Village Hall plugin not active', 'myvh-hallflow' ),
        esc_html__( 'Activate the My Village Hall plugin to use this page.', 'myvh-hallflow' )
    );
}
