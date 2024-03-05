<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class EventStore extends Model
{
    protected $fillable = ['aggregate_id', 'event_type', 'payload', 'version'];

    protected $casts = [
        'payload' => 'array',
    ];
}