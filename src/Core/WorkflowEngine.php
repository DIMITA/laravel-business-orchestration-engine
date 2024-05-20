<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\WorkflowInstance;
use Dimita\BusinessOrchestration\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Model;

class WorkflowEngine
{
    public function for(Model $model): WorkflowBuilder
    {
        $instance = WorkflowInstance::firstOrCreate([
            'model_type' => get_class($model),
            'model_id' => $model->id,
        ], [
            'state' => 'initial', // default state
        ]);

        return new WorkflowBuilder($instance);
    }

    public function defineTransition(string $name, string $from, string $to, ?string $guard = null)
    {
        WorkflowTransition::create([
            'name' => $name,
            'from' => $from,
            'to' => $to,
            'guard_expression' => $guard,
        ]);
    }
}