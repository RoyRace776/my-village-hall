<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {}
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Core {

use Mockery;
use MYVH\Core\Support\RepositoryBase;
use MYVH\Tests\Unit\UnitTestCase;

/**
 * Concrete subclass exposing protected helpers for testing.
 */
class ConcreteRepository extends RepositoryBase {
    public function __construct(\wpdb $wpdb) {
        $this->wpdb = $wpdb;
        $this->table_name = 'wp_test_items';
    }

    public function expose_sanitize_identifier(string $id): string {
        return $this->sanitize_identifier($id);
    }

    public function expose_normalize_order(string $order): string {
        return $this->normalize_order($order);
    }
}

/**
 * Security-focused regression tests for RepositoryBase.
 *
 * Verifies that dynamic SQL identifier assembly and sort-direction handling
 * are resistant to SQL injection payloads.
 */
class RepositorySecurityTest extends UnitTestCase
{
    /** @var \Mockery\MockInterface&\wpdb */
    private $wpdb;
    private ConcreteRepository $repo;
    private string $last_query = '';

    protected function setUp(): void {
        parent::setUp();

        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';

        // Capture the query passed to get_results for assertion.
        $self = $this;
        $this->wpdb->shouldReceive('get_results')
            ->andReturnUsing(function ($query) use ($self): array {
                $self->last_query = $query;
                return [];
            })
            ->byDefault();

        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($query, ...$args) => $query)
            ->byDefault();

        $this->repo = new ConcreteRepository($this->wpdb);
    }

    // -------------------------------------------------------------------------
    // sanitize_identifier()
    // -------------------------------------------------------------------------

    /** @dataProvider valid_identifiers */
    public function test_sanitize_identifier_accepts_valid_names(string $input, string $expected): void {
        $this->assertSame($expected, $this->repo->expose_sanitize_identifier($input));
    }

    public static function valid_identifiers(): array {
        return [
            'simple column'           => ['Id',          'Id'],
            'underscored column'      => ['start_date',  'start_date'],
            'table qualified'         => ['b.StartDate', 'b.StartDate'],
            'fully qualified'         => ['tbl.Col_Name','tbl.Col_Name'],
            'uppercase'               => ['CREATED_AT',  'CREATED_AT'],
            'leading space (trimmed)' => [' Id',         'Id'],
        ];
    }

    /** @dataProvider injection_payloads */
    public function test_sanitize_identifier_rejects_injection_payload(string $payload): void {
        $this->assertSame('', $this->repo->expose_sanitize_identifier($payload));
    }

    public static function injection_payloads(): array {
        return [
            'DROP TABLE payload'      => ['Id; DROP TABLE wp_users;--'],
            'sleep injection'         => ['Id, SLEEP(5)--'],
            "OR 1=1 payload"          => ["Name' OR '1'='1"],
            'subquery injection'      => ['(SELECT password FROM wp_users LIMIT 1)'],
            'backtick injection'      => ['`Id` OR 1=1'],
            'comment stripping'       => ['Id/*'],
            'trailing semicolon'      => ['Id;'],
            'space in name'           => ['table name'],
            'hyphen in name'          => ['col-name'],
            'empty string'            => [''],
        ];
    }

    // -------------------------------------------------------------------------
    // normalize_order()
    // -------------------------------------------------------------------------

    /** @dataProvider valid_order_values */
    public function test_normalize_order_accepts_valid_directions(string $input, string $expected): void {
        $this->assertSame($expected, $this->repo->expose_normalize_order($input));
    }

    public static function valid_order_values(): array {
        return [
            'ASC uppercase'  => ['ASC',  'ASC'],
            'DESC uppercase' => ['DESC', 'DESC'],
            'asc lowercase'  => ['asc',  'ASC'],
            'desc lowercase' => ['desc', 'DESC'],
            'mixed case'     => ['Desc', 'DESC'],
        ];
    }

    /** @dataProvider invalid_order_values */
    public function test_normalize_order_defaults_to_asc_for_invalid_input(string $input): void {
        $this->assertSame('ASC', $this->repo->expose_normalize_order($input));
    }

    public static function invalid_order_values(): array {
        return [
            'empty string'         => [''],
            'injection attempt'    => ["ASC; DROP TABLE wp_users;--"],
            'UNION injection'      => ['ASC UNION SELECT * FROM wp_users'],
            'random word'          => ['RANDOM'],
            'numeric'              => ['1'],
        ];
    }

    // -------------------------------------------------------------------------
    // get_all() – ORDER BY fragment in the built query
    // -------------------------------------------------------------------------

    public function test_get_all_omits_order_by_clause_when_orderby_is_invalid(): void {
        $this->repo->get_all(['orderby' => "Id; DROP TABLE users;--", 'order' => 'ASC']);

        $this->assertStringNotContainsStringIgnoringCase(
            'DROP',
            $this->last_query,
            'Malicious ORDER BY payload must not reach the SQL query.'
        );
    }

    public function test_get_all_includes_safe_order_by_when_identifier_is_valid(): void {
        $this->repo->get_all(['orderby' => 'Id', 'order' => 'DESC']);

        $this->assertStringContainsStringIgnoringCase(
            'ORDER BY Id DESC',
            $this->last_query,
            'Valid ORDER BY clause should be included in the query.'
        );
    }

    public function test_get_all_normalises_bad_order_direction_to_asc(): void {
        $this->repo->get_all(['orderby' => 'Id', 'order' => "ASC; DROP TABLE users"]);

        $this->assertStringContainsString('ORDER BY Id ASC', $this->last_query);
    }

    // -------------------------------------------------------------------------
    // find() – WHERE column name injection
    // -------------------------------------------------------------------------

    public function test_find_skips_where_clause_for_invalid_column_name(): void {
        $this->repo->find(["Id` OR 1=1 OR `Id" => 1]);

        $this->assertStringNotContainsStringIgnoringCase(
            'OR 1=1',
            $this->last_query
        );
    }

    public function test_find_includes_where_clause_for_valid_column_name(): void {
        $this->repo->find(['Id' => 42]);

        $this->assertStringContainsStringIgnoringCase(
            'WHERE',
            $this->last_query
        );
    }
}

} // end namespace MYVH\Tests\Unit\Core
