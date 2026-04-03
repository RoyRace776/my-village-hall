<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<footer class="site-footer">
    <div class="site-footer__inner">
        <p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
        <nav class="site-footer__nav" aria-label="<?php esc_attr_e( 'Footer navigation', 'myvh-hallflow' ); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'container'      => false,
                'fallback_cb'    => false,
            ]);
            ?>
        </nav>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
