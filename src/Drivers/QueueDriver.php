<?php

namespace Dimita\BusinessOrchestration\Drivers;

use Illuminate\Support\Facades\Queue;

class QueueDriver implements DriverInterface
{
    protected string $connection;

    public function __construct(string $connection = 'sync')
    {
        $this->connection = $connection;
    }

    public function get(string $key)
    {
        // Queue doesn't support get, perhaps return null or throw
        return null;
    }

    public function set(string $key, $value)
    {
        // Perhaps dispatch a job with the value
        Queue::connection($this->connection)->push($value);
    }

    public function delete(string $key)
    {
        // Not applicable
    }
}