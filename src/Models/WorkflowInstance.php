<?php

namespace Dimita\BusinessOrchestration\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    protected $fillable = ['model_type', 'model_id', 'state'];

    public function model()
    {
        return $this->morphTo();
    }
}