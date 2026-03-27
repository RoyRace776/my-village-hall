<?php

require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-save-invoice-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-request-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-generator-service.php';

class Invoice_Service_Provider
{
    public function register($container): void {
        $container->singleton(Invoice_Repository::class);
        $container->singleton(Invoice_Item_Repository::class);
        $container->singleton(Invoice_Service::class);
        $container->singleton(Invoice_Request_Validator::class);
        $container->singleton(Invoice_Controller::class);
        $container->singleton(Invoice_Generator_Service::class);
    }
}