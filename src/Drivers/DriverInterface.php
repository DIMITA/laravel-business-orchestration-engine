<?php

namespace Dimita\BusinessOrchestration\Drivers;

interface DriverInterface
{
    public function get(string $key);
    public function set(string $key, $value);
    public function delete(string $key);
}