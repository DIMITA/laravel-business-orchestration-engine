<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class ModelVersion extends Model
{
    protected $fillable = ['model_type', 'model_id', 'version', 'snapshot', 'hash'];

    protected $casts = [
        'snapshot' => 'array',
    ];
}