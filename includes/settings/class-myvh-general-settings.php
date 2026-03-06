<?php

class MYVH_General_Settings extends MYVH_Settings_Base {

    protected $option_name = 'myvh_general_settings';

    protected $schema = [

        'currency_symbol' => [
            'type' => 'string',
            'default' => '£',
            'label' => 'Currecny symbol'
        ]

    ];

}