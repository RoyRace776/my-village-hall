<?php

require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-save-invoice-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-request-validator.php';

class MYVH_Invoice_Service_Provider
{
    public function register($container): void {
        $container->singleton(MYVH_Invoice_Repository::class);
        $container->singleton(MYVH_Invoice_Item_Repository::class);
        $container->singleton(MYVH_Invoice_Service::class);
        $container->singleton(MYVH_Invoice_Request_Validator::class);
        $container->singleton(MYVH_Invoice_Controller::class);
    }
}