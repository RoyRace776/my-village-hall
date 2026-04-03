<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-bg" aria-hidden="true"></div>
<header class="site-header">
    <div class="site-header__inner">
        <a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <span class="site-brand__kicker"><?php bloginfo( 'name' ); ?></span>
            <strong class="site-brand__title"><?php esc_html_e( 'Village Hall Hub', 'myvh-hallflow' ); ?></strong>
        </a>

        <button class="site-menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu">
            <span><?php esc_html_e( 'Menu', 'myvh-hallflow' ); ?></span>
        </button>

        <nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'myvh-hallflow' ); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_id'        => 'primary-menu',
                'container'      => false,
                'fallback_cb'    => false,
            ]);
            ?>
        </nav>
    </div>
</header>
