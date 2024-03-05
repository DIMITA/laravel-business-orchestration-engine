<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTransition extends Model
{
    protected $fillable = ['name', 'from', 'to', 'guard_expression'];
}