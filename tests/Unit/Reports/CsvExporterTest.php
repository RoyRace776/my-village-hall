<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Reports;

use MYVH\Reports\CsvExporter;
use MYVH\Tests\Unit\UnitTestCase;

final class CsvExporterTest extends UnitTestCase {
    public function test_it_escapes_headers_and_values(): void {
        $exporter = new CsvExporter();

        $csv = $exporter->output([
            ['name' => 'Alice', 'note' => 'He said "Hello"'],
            ['name' => 'Bob, Jr.', 'note' => 'Needs, review'],
        ]);

        $this->assertStringContainsString("name,note", $csv);
        $this->assertStringContainsString('Alice,"He said ""Hello"""', $csv);
        $this->assertStringContainsString('"Bob, Jr.","Needs, review"', $csv);
    }
}