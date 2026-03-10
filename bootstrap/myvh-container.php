<?php

global $wpdb;
global $myvh_container;

$myvh_container = new MYVH_Container();

require MYVH_PLUGIN_DIR . 'bootstrap/myvh-repositories.php';
require MYVH_PLUGIN_DIR . 'bootstrap/myvh-services.php';
require MYVH_PLUGIN_DIR . 'bootstrap/myvh-controllers.php';

return $myvh_container;