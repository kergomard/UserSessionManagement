<?php

namespace kergomard\UserSessionManagement\Config;

interface Repository
{
    public function get(): Config;
    public function store(Config $config): void;
}
