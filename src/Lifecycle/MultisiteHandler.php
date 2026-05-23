<?php

namespace MYVH\Lifecycle;

use MYVH\Lifecycle\Contracts\MultisiteContextInterface;

class MultisiteHandler {
    public function __construct(
        private MultisiteContextInterface $multisite_context,
        private ActivationService $activation_service,
        private DeactivationService $deactivation_service
    ) {
    }

    public function activate( bool $network_wide ): void {
        if ( $this->multisite_context->is_multisite() && $network_wide ) {
            foreach ( $this->multisite_context->get_sites() as $site ) {
                $this->multisite_context->switch_to_blog( (int) $site->blog_id );
                $this->activation_service->activate_site();
                $this->multisite_context->restore_current_blog();
            }
            return;
        }

        $this->activation_service->activate_site();
    }

    public function deactivate( bool $network_wide ): void {
        if ( $this->multisite_context->is_multisite() && $network_wide ) {
            foreach ( $this->multisite_context->get_sites() as $site ) {
                $this->multisite_context->switch_to_blog( (int) $site->blog_id );
                $this->deactivation_service->deactivate_site();
                $this->multisite_context->restore_current_blog();
            }
            return;
        }

        $this->deactivation_service->deactivate_site();
    }

    public function install_on_initialized_site( object $site ): void {
        $this->multisite_context->switch_to_blog( (int) $site->blog_id );
        $this->activation_service->install_site();
        $this->multisite_context->restore_current_blog();
    }

    public function activate_on_initialized_site( object $new_site ): void {
        $active = $this->multisite_context->get_active_sitewide_plugins();
        if ( ! isset( $active[ MYVH_PLUGIN_BASENAME ] ) ) {
            return;
        }

        $this->multisite_context->switch_to_blog( (int) $new_site->blog_id );
        $this->activation_service->activate_site();
        $this->multisite_context->restore_current_blog();
    }
}
