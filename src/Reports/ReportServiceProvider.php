<?php

declare(strict_types=1);

namespace MYVH\Reports;

use MYVH\Container\Container;
use MYVH\Reports\Http\RunReportEndpoint;
use MYVH\Reports\Http\DeleteReportEndpoint;
use MYVH\Reports\Http\SaveReportEndpoint;
use MYVH\Reports\Http\SchemaReportEndpoint;

if (!defined('ABSPATH')) {
    exit;
}

final class ReportServiceProvider {
    public function register(Container $container): void {
        $container->singleton(ReportRepository::class);
        $container->singleton(ReportQueryBuilder::class);
        $container->singleton(ReportPermissionService::class);
        $container->singleton(ReportViewModelFactory::class);
        $container->singleton(CsvExporter::class);
        $container->singleton(ReportEngine::class);
        $container->singleton(RunReportEndpoint::class);
        $container->singleton(DeleteReportEndpoint::class);
        $container->singleton(SaveReportEndpoint::class);
        $container->singleton(SchemaReportEndpoint::class);
    }
}
