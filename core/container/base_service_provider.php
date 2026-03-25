<?php

interface ServiceProvider
{
    public function register($container): void;
}