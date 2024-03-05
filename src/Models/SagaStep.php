<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class SagaStep extends Model
{
    protected $fillable = ['saga_id', 'step_name', 'status', 'executed_at', 'compensated_at', 'error'];

    protected $casts = [
        'executed_at' => 'datetime',
        'compensated_at' => 'datetime',
    ];

    public function saga()
    {
        return $this->belongsTo(Saga::class);
    }
}