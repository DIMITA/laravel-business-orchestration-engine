<?php

namespace Dimita\BusinessOrchestration\Drivers;

use InvalidArgumentException;

class DriverFactory
{
    public static function make(string $driver): DriverInterface
    {
        $config = config('business-orchestration.drivers.' . $driver);

        return match ($driver) {
            'database' => new DatabaseDriver(),
            'redis' => new RedisDriver($config['connection'] ?? 'default'),
            'queue' => new QueueDriver($config['connection'] ?? 'sync'),
            default => throw new InvalidArgumentException("Unsupported driver: $driver"),
        };
    }
}