<?php

function app(string $abstract )
{
    static $container = null;

    if ($container === null) {

        global $myvh_container;
        $container = $myvh_container;
    }

    return $abstract ? $container->get($abstract) : $container;
}