<?php

namespace MYVH\Bootstrap;

use MYVH\Admin\AdminMenu;
use MYVH\Admin\AdminPageRouter;
use MYVH\Calendar\CalendarShortcode;
use MYVH\Core\Support\AssetLoader;
use MYVH\Hooks\AdminPostRegistrar;
use MYVH\Hooks\EventListeners;
use MYVH\Lifecycle\Contracts\OptionStoreInterface;
use MYVH\Lifecycle\Contracts\PluginInstallerInterface;
use MYVH\Login\PasswordResetLoader;
use MYVH\Network\NetworkDashboard;
use MYVH\Settings\SettingsPage;
use MYVH\Settings\SettingsRegistry;
use MYVH\UI\SubmenuIconRenderer;

class PluginBootstrap {
    private bool $booted = false;
    private const VERSION_OPTION = 'myvh_version';

    public function __construct(
        private PluginInstallerInterface $installer,
        private OptionStoreInterface $option_store,
        private AdminMenu $admin_menu,
        private AdminPageRouter $admin_page_router,
        private AdminPostRegistrar $admin_post_registrar,
        private EventListeners $event_listeners,
        private SubmenuIconRenderer $submenu_icon_renderer
    ) {
    }

    public function bootstrap(): void {
        if ( $this->booted ) {
            return;
        }

        $this->booted = true;

        load_plugin_textdomain(
            'my-village-hall',
            false,
            dirname( MYVH_PLUGIN_BASENAME ) . '/languages'
        );

        add_action( 'init', [ $this, 'on_init' ] );
        add_action( 'admin_menu', [ $this->admin_menu, 'register' ] );
        add_action( 'admin_head', [ $this->submenu_icon_renderer, 'render' ] );
        add_action( 'wp_head', [ $this->admin_page_router, 'hide_portal_page_title' ], 20 );
        add_filter( 'show_admin_bar', [ $this, 'filter_show_admin_bar' ] );

        if ( is_admin() ) {
            add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
        }

        AssetLoader::init();
        SettingsRegistry::auto_register( MYVH_PLUGIN_DIR . 'src/Settings' );
        ( new SettingsPage() )->init();

        $this->admin_post_registrar->register();
        $this->event_listeners->register();

        require_once MYVH_PLUGIN_DIR . 'src/Bootstrap/myvh-bootstrap.php';

        ( new CalendarShortcode() )->init();
        ( new PasswordResetLoader() )->init();

        if ( is_multisite() && is_network_admin() ) {
            ( new NetworkDashboard() )->init();
        }
    }

    public function on_init(): void {
        if ( $this->option_store->get( self::VERSION_OPTION ) !== MYVH_VERSION ) {
            $this->installer->run();
            $this->option_store->set( self::VERSION_OPTION, MYVH_VERSION );
        }
    }

    public function maybe_upgrade(): void {
        $this->installer->maybe_upgrade();
    }

    public function filter_show_admin_bar( bool $show ): bool {
        if ( is_admin() || ! is_user_logged_in() ) {
            return $show;
        }

        return current_user_can( 'manage_myvh' ) ? $show : false;
    }
}
