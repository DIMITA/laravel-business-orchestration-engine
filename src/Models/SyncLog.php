<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $table = 'sync_log';

    protected $fillable = ['model_type', 'model_id', 'operation', 'changed_fields', 'version', 'metadata'];

    protected $casts = [
        'changed_fields' => 'array',
        'metadata' => 'array',
    ];
}