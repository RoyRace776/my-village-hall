<?php
/**
 * Plugin Name: My Village Hall
 * Plugin URI: https://example.com/my-village-hall
 * Description: A comprehensive venue and room booking management system with multi-client support, recurring bookings, and customer portal
 * Version: 0.9.7
 * Author: Richard Barrett
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-village-hall
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package MyVillageHall
 */

use MYVH\Admin\AdminMenu;
use MYVH\Admin\AdminPageRouter;
use MYVH\Admin\Actions\ClientAdminsNetworkActionHandler;
use MYVH\Bootstrap\PluginBootstrap;
use MYVH\Container\Container;
use MYVH\Hooks\AdminPostRegistrar;
use MYVH\Hooks\EventListeners;
use MYVH\Lifecycle\ActivationService;
use MYVH\Lifecycle\DeactivationService;
use MYVH\Lifecycle\MultisiteHandler;
use MYVH\Lifecycle\WordPress\GeneralSettingsDeactivationPolicy;
use MYVH\Lifecycle\WordPress\WordPressCapabilityManager;
use MYVH\Lifecycle\WordPress\WordPressMultisiteContext;
use MYVH\Lifecycle\WordPress\WordPressOptionStore;
use MYVH\Lifecycle\WordPress\WordPressOvernightScheduler;
use MYVH\Lifecycle\WordPress\WordPressPluginInstaller;
use MYVH\Lifecycle\WordPress\WordPressRewriteRules;
use MYVH\Network\SiteSeeder;
use MYVH\Network\SiteProvisioningRepository;
use MYVH\Portal\ClientAdminService;
use MYVH\Admin\WordPress\WordPressAdminRequest;
use MYVH\Admin\WordPress\WordPressAdminResponse;
use MYVH\UI\MenuSeparator;
use MYVH\UI\SubmenuIconRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MYVH_VERSION',         '0.9.7' );
define( 'MYVH_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'MYVH_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'MYVH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Output buffering prevents accidental whitespace in included files from
// triggering WordPress's "unexpected output" activation error.
ob_start();
require_once MYVH_PLUGIN_DIR . 'vendor/autoload.php';
require_once MYVH_PLUGIN_DIR . 'src/Settings/settings-helper.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p6\Vcs\GitHubApi;
use YahnisElsts\PluginUpdateChecker\v5p6\Vcs\PluginUpdateChecker;

/** @var PluginUpdateChecker $updateChecker */
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/RoyRace776/my-village-hall',
    __FILE__,
    'my-village-hall'
);

$updateChecker->addQueryArgFilter( function ( $queryArgs ) {
    $queryArgs['key'] = defined( 'MYVH_UPDATE_KEY' ) ? MYVH_UPDATE_KEY : '';
    return $queryArgs;
} );

$updateChecker->setBranch( 'main' );

// Use release assets (e.g. my-village-hall.zip) rather than auto-generated
// source archives, which exclude vendor/ due to .gitignore.
/** @var GitHubApi $updateApi */
$updateApi = $updateChecker->getVcsApi();
if ( is_object( $updateApi ) && method_exists( $updateApi, 'enableReleaseAssets' ) ) {
    call_user_func( [ $updateApi, 'enableReleaseAssets' ], '/my-village-hall\.zip$/i' );
}

// Dependency-injection container
global $myvh_container;
/** @var Container $myvh_container */
$myvh_container = require MYVH_PLUGIN_DIR . 'src/Core/Support/myvh-container.php';

function myvh_get_multisite_handler(): MultisiteHandler {
    static $handler = null;

    if ( $handler instanceof MultisiteHandler ) {
        return $handler;
    }

    $installer = new WordPressPluginInstaller();
    $option_store = new WordPressOptionStore();
    $capability_manager = new WordPressCapabilityManager();
    $rewrite_rules = new WordPressRewriteRules();

    $handler = new MultisiteHandler(
        new WordPressMultisiteContext(),
        new ActivationService(
            $installer,
            $option_store,
            $capability_manager,
            $rewrite_rules
        ),
        new DeactivationService(
            $installer,
            new WordPressOvernightScheduler(),
            new GeneralSettingsDeactivationPolicy(),
            $rewrite_rules
        )
    );

    return $handler;
}

function myvh_get_plugin_bootstrap(): PluginBootstrap {
    static $bootstrap = null;

    if ( $bootstrap instanceof PluginBootstrap ) {
        return $bootstrap;
    }

    global $myvh_container;

    $installer = new WordPressPluginInstaller();
    $option_store = new WordPressOptionStore();
    $admin_request = new WordPressAdminRequest();
    $client_admin_service = new ClientAdminService();
    $client_admin_action_handler = new ClientAdminsNetworkActionHandler(
        $admin_request,
        new WordPressAdminResponse(),
        $client_admin_service
    );

    $admin_page_router = new AdminPageRouter(
        $myvh_container,
        $admin_request,
        $client_admin_service,
        $client_admin_action_handler
    );
    $admin_menu = new AdminMenu( $admin_page_router, new MenuSeparator() );
    $admin_post_registrar = new AdminPostRegistrar( $myvh_container, $admin_page_router );
    $event_listeners = new EventListeners(
        $myvh_container->get( wpdb::class ),
        new SiteSeeder(),
        $client_admin_service,
        new SiteProvisioningRepository()
    );

    $bootstrap = new PluginBootstrap(
        $installer,
        $option_store,
        $admin_menu,
        $admin_page_router,
        $admin_post_registrar,
        $event_listeners,
        new SubmenuIconRenderer()
    );

    return $bootstrap;
}

function myvh_activate( bool $network_wide ): void {
    myvh_get_multisite_handler()->activate( $network_wide );
}

function myvh_deactivate( bool $network_wide ): void {
    myvh_get_multisite_handler()->deactivate( $network_wide );
}

function myvh_on_site_initialize_install( object $site ): void {
    myvh_get_multisite_handler()->install_on_initialized_site( $site );
}

function myvh_on_site_initialize_activate( object $site ): void {
    myvh_get_multisite_handler()->activate_on_initialized_site( $site );
}

function myvh_bootstrap_plugin(): void {
    myvh_get_plugin_bootstrap()->bootstrap();
}

register_activation_hook( __FILE__, 'myvh_activate' );
register_deactivation_hook( __FILE__, 'myvh_deactivate' );

add_action( 'wp_initialize_site', 'myvh_on_site_initialize_install' );
add_action( 'wp_initialize_site', 'myvh_on_site_initialize_activate' );
add_action( 'plugins_loaded', 'myvh_bootstrap_plugin' );

ob_end_clean();
