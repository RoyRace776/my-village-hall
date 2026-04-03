<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Organisations\OrganisationTypeRepository;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class OrganisationTypeServiceTest extends UnitTestCase {
    private $repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        \Brain\Monkey\Functions\stubs([
            'sanitize_text_field' => fn($v) => (string) $v,
            'sanitize_textarea_field' => fn($v) => (string) $v,
        ]);

        $this->repo = $this->mock(OrganisationTypeRepository::class);
        $this->service = new OrganisationTypeService($this->repo);
    }

    /** @test */
    public function save_rejects_updates_to_system_types(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(1)
            ->andReturnUsing(fn() => ['Id' => 1, 'IsSystem' => 1, 'Name' => 'Person']);

        $result = $this->service->save([
            'org_type_id' => 1,
            'name' => 'Person',
            'description' => 'Individual person',
            'is_default' => 1,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('forbidden', $result->get_error_code());
    }

    /** @test */
    public function save_sets_single_default_when_creating_default_type(): void {
        $this->repo->shouldReceive('create')
            ->once()
            ->andReturn(9);

        $this->repo->shouldReceive('clear_default_except')
            ->once()
            ->with(9)
            ->andReturn(true);

        $result = $this->service->save([
            'name' => 'Charity',
            'description' => 'Local charities',
            'is_default' => 1,
        ]);

        $this->assertSame(9, $result);
    }

    /** @test */
    public function delete_rejects_type_when_in_use(): void {
        $this->repo->shouldReceive('get_by_id')
            ->once()
            ->with(3)
            ->andReturnUsing(fn() => ['Id' => 3, 'IsSystem' => 0, 'IsDefault' => 0]);

        $this->repo->shouldReceive('count_organisations_using_type')
            ->once()
            ->with(3)
            ->andReturn(2);

        $result = $this->service->delete(3);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('in_use', $result->get_error_code());
    }
}
