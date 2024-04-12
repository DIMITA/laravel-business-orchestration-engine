<?php

namespace Dimita\BusinessOrchestration\Drivers;

use Illuminate\Support\Facades\DB;

class DatabaseDriver implements DriverInterface
{
    protected string $table = 'business_orchestration_cache';

    public function get(string $key)
    {
        return DB::table($this->table)->where('key', $key)->value('value');
    }

    public function set(string $key, $value)
    {
        DB::table($this->table)->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now()]
        );
    }

    public function delete(string $key)
    {
        DB::table($this->table)->where('key', $key)->delete();
    }
}