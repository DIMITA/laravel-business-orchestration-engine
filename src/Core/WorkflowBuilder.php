<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\WorkflowInstance;
use Dimita\BusinessOrchestration\Models\WorkflowTransition;

class WorkflowBuilder
{
    protected WorkflowInstance $instance;

    public function __construct(WorkflowInstance $instance)
    {
        $this->instance = $instance;
    }

    public function can(string $transitionName): bool
    {
        $transition = WorkflowTransition::where('name', $transitionName)
            ->where('from', $this->instance->state)
            ->first();

        if (!$transition) {
            return false;
        }

        if ($transition->guard_expression) {
            // Evaluate guard - for simplicity, assume it's PHP code
            // In real, use a safe evaluator
            return eval($transition->guard_expression);
        }

        return true;
    }

    public function apply(string $transitionName)
    {
        if (!$this->can($transitionName)) {
            throw new \Exception("Cannot apply transition $transitionName");
        }

        $transition = WorkflowTransition::where('name', $transitionName)->first();
        $this->instance->update(['state' => $transition->to]);
    }

    public function getState(): string
    {
        return $this->instance->state;
    }
}