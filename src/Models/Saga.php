<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class Saga extends Model
{
    protected $fillable = ['name', 'status', 'current_step', 'payload'];

    protected $casts = [
        'payload' => 'array',
    ];

    public function steps()
    {
        return $this->hasMany(SagaStep::class);
    }
}