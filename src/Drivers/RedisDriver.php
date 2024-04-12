<?php

namespace Dimita\BusinessOrchestration\Drivers;

use Illuminate\Support\Facades\Redis;

class RedisDriver implements DriverInterface
{
    protected string $connection;

    public function __construct(string $connection = 'default')
    {
        $this->connection = $connection;
    }

    public function get(string $key)
    {
        return json_decode(Redis::connection($this->connection)->get($key), true);
    }

    public function set(string $key, $value)
    {
        Redis::connection($this->connection)->set($key, json_encode($value));
    }

    public function delete(string $key)
    {
        Redis::connection($this->connection)->del($key);
    }
}