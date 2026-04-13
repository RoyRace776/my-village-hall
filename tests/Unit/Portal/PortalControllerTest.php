<?php

namespace MYVH\Tests\Unit\Portal;

use MYVH\Portal\Ajax\PortalEmailTemplateAjaxController;
use MYVH\Portal\Ajax\PortalPageAjaxController;
use MYVH\Portal\PortalController;
use MYVH\Tests\Unit\UnitTestCase;

class PortalControllerTest extends UnitTestCase {
    /** @var PortalPageAjaxController&\Mockery\MockInterface */
    private $portal_page_ajax_controller;

    /** @var PortalEmailTemplateAjaxController&\Mockery\MockInterface */
    private $portal_email_template_ajax_controller;

    private PortalController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->portal_page_ajax_controller = $this->mock(PortalPageAjaxController::class);
        $this->portal_email_template_ajax_controller = $this->mock(PortalEmailTemplateAjaxController::class);

        $this->controller = new PortalController(
            $this->portal_page_ajax_controller,
            $this->portal_email_template_ajax_controller
        );
    }

    /** @test */
    public function register_delegates_to_both_ajax_controllers(): void {
        $this->portal_page_ajax_controller->shouldReceive('register')
            ->once();

        $this->portal_email_template_ajax_controller->shouldReceive('register')
            ->once();

        $this->controller->register();

        $this->addToAssertionCount(1);
    }
}
