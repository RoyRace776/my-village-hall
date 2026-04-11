<?php
namespace MYVH\Portal;

use MYVH\Portal\Ajax\PortalPageAjaxController;

class PortalController {
    public function __construct(
        private PortalPageAjaxController $portal_page_ajax_controller
    ) {}

    public function register(): void {
        $this->portal_page_ajax_controller->register();
    }

}
