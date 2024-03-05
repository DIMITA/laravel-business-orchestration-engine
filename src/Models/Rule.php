<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class Rule extends Model
{
    protected $fillable = ['name', 'condition_ast', 'action'];

    protected $casts = [
        'condition_ast' => 'array',
        'action' => 'array',
    ];
}