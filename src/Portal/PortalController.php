<?php
namespace MYVH\Portal;

use MYVH\Portal\Ajax\PortalEmailTemplateAjaxController;
use MYVH\Portal\Ajax\PortalPageAjaxController;

class PortalController {
    public function __construct(
        private PortalPageAjaxController $portal_page_ajax_controller,
        private PortalEmailTemplateAjaxController $portal_email_template_ajax_controller
    ) {}

    public function register(): void {
        $this->portal_page_ajax_controller->register();
        $this->portal_email_template_ajax_controller->register();
    }

}
