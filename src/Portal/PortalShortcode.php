<?php
namespace MYVH\Portal;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MYVH\Core\Shortcode\ShortcodeInterface;

class PortalShortcode implements ShortcodeInterface
{
    private PortalBootstrapDataService $bootstrap;

    public function __construct( PortalBootstrapDataService $bootstrap ) {
        $this->bootstrap = $bootstrap;
    }

    public function tag(): string
    {
        return 'myvh_portal';
    }

    public function render( mixed $atts = [], mixed $content = null): string
    {
        return ( new \MYVH\Application\Services\PortalRenderer(
            $this->bootstrap,
            new \MYVH\Application\Services\LoginRenderer()
        ) )->render( (array) $atts );
    }
}
