<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Email\EmailService;
use MYVH\Events\OrganisationListener;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class OrganisationListenerTest extends UnitTestCase
{
    /** @var EmailService&\Mockery\MockInterface */
    private $email_service;
    /** @var ClientAdminService&\Mockery\MockInterface */
    private $client_admin_service;
    private OrganisationListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email_service        = Mockery::mock(EmailService::class);
        $this->client_admin_service = Mockery::mock(ClientAdminService::class);
        $this->listener             = new OrganisationListener($this->email_service, $this->client_admin_service);

        Functions\stubs([
            'get_current_blog_id' => 1,
            'get_option'         => '',
            'is_email'           => fn($v) => str_contains((string) $v, '@'),
            'get_userdata'       => null,
            'get_users'          => [],
        ]);
    }

    // ── register ─────────────────────────────────────────────────────────

    /** @test */
    public function register_hooks_handle_organisation_created(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('myvh_event_organisation.created', Mockery::type('array'));

        $this->listener->register();
        $this->addToAssertionCount(1);
    }

    // ── handle_organisation_created ───────────────────────────────────────

    /** @test */
    public function it_does_nothing_when_organisation_id_missing(): void
    {
        $this->email_service->shouldNotReceive('send');

        $this->listener->handle_organisation_created(['organisation_name' => 'Test']);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_does_nothing_when_organisation_name_missing(): void
    {
        $this->email_service->shouldNotReceive('send');

        $this->listener->handle_organisation_created(['organisation_id' => 5]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_sends_email_to_client_admins(): void
    {
        // Stub two users, only one is a client admin
        $user1 = Mockery::mock('WP_User');
        $user1->ID         = 1;
        $user1->user_email = 'admin@example.com';

        $user2 = Mockery::mock('WP_User');
        $user2->ID         = 2;
        $user2->user_email = 'user@example.com';

        Functions\when('get_users')->justReturn([$user1, $user2]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->with(1, 1)->andReturn(true);
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->with(2, 1)->andReturn(false);

        $this->email_service->shouldReceive('get_branding')
            ->andReturn(['site_name' => 'My Hall', 'site_url' => 'https://example.com', 'logo_url' => '']);

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['to'] === 'admin@example.com' &&
                $args['template'] === 'organisation-created'
            ));

        $this->listener->handle_organisation_created([
            'organisation_id'   => 5,
            'organisation_name' => 'Village FC',
            'contact_email'     => 'fc@example.com',
            'contact_phone'     => '01234 567890',
        ]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_falls_back_to_admin_email_when_no_client_admins(): void
    {
        Functions\when('get_users')->justReturn([]);
        Functions\when('get_option')->justReturn('siteadmin@example.com');
        Functions\when('is_email')->justReturn(true);

        $this->email_service->shouldReceive('get_branding')
            ->andReturn(['site_name' => 'My Hall', 'site_url' => 'https://example.com', 'logo_url' => '']);

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn($args) => $args['to'] === 'siteadmin@example.com'));

        $this->listener->handle_organisation_created([
            'organisation_id'   => 7,
            'organisation_name' => 'New Group',
        ]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_includes_creator_info_when_user_id_provided(): void
    {
        $creator = Mockery::mock('WP_User');
        $creator->display_name = 'Bob Builder';
        $creator->user_login   = 'bob';
        $creator->user_email   = 'bob@example.com';

        Functions\when('get_userdata')->justReturn($creator);
        Functions\when('get_users')->justReturn([]);
        Functions\when('get_option')->justReturn('admin@example.com');
        Functions\when('is_email')->justReturn(true);

        $this->email_service->shouldReceive('get_branding')
            ->andReturn(['site_name' => 'My Hall', 'site_url' => 'https://example.com', 'logo_url' => '']);

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['template_vars']['created_by_name'] === 'Bob Builder' &&
                $args['template_vars']['created_by_email'] === 'bob@example.com'
            ));

        $this->listener->handle_organisation_created([
            'organisation_id'     => 8,
            'organisation_name'   => 'Test Group',
            'created_by_user_id'  => 10,
        ]);
        $this->addToAssertionCount(1);
    }
}
