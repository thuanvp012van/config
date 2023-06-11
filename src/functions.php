<?php

use Penguin\Component\Container\Container;

if (!function_exists('config')) {
    function config(string $key = null): mixed
    {
        $config = Container::getInstance()->get('config');
        if ($key !== null) {
            return $config->get($key);
        }
        return $config;
    }
}