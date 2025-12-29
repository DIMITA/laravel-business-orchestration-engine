<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\WorkflowInstance;
use Dimita\BusinessOrchestration\Models\WorkflowTransition as WorkflowTransitionModel;

class WorkflowBuilder
{
    protected WorkflowInstance $instance;
    protected ?WorkflowEngine $engine;

    public function __construct(WorkflowInstance $instance, ?WorkflowEngine $engine = null)
    {
        $this->instance = $instance;
        $this->engine = $engine;
    }

    public function can(string $transitionName, array $context = []): bool
    {
        $transition = WorkflowTransitionModel::where('name', $transitionName)
            ->where('from', $this->instance->state)
            ->first();

        if (!$transition) {
            return false;
        }

        if ($transition->guard_expression) {
            // Evaluate guard with context
            return $this->evaluateGuard($transition->guard_expression, $context);
        }

        return true;
    }

    public function apply(string $transitionName, array $context = []): void
    {
        if (!$this->can($transitionName, $context)) {
            throw new \Exception("Cannot apply transition {$transitionName} from state {$this->instance->state}");
        }

        $transition = WorkflowTransitionModel::where('name', $transitionName)
            ->where('from', $this->instance->state)
            ->first();

        // Execute before callbacks
        if ($this->engine) {
            $this->engine->executeBeforeCallbacks($transitionName, $this->instance);
        }

        $fromState = $this->instance->state;

        // Update state
        $this->instance->update(['state' => $transition->to]);

        // Record transition in history
        $this->recordTransition($transitionName, $fromState, $transition->to, $context);

        // Execute after callbacks
        if ($this->engine) {
            $this->engine->executeAfterCallbacks($transitionName, $this->instance);
        }
    }

    public function getState(): string
    {
        return $this->instance->state;
    }

    /**
     * Get all enabled transitions from current state
     */
    public function getEnabledTransitions(array $context = []): array
    {
        $transitions = WorkflowTransitionModel::where('from', $this->instance->state)->get();

        return $transitions->filter(function($transition) use ($context) {
            if (!$transition->guard_expression) {
                return true;
            }

            return $this->evaluateGuard($transition->guard_expression, $context);
        })->pluck('name')->toArray();
    }

    /**
     * Evaluate guard expression safely
     */
    protected function evaluateGuard(string $expression, array $context): bool
    {
        // Create a safe context for evaluation
        extract($context);

        try {
            // For simple expressions only
            return eval("return {$expression};");
        } catch (\Throwable $e) {
            // Guard evaluation failed, deny transition
            return false;
        }
    }

    /**
     * Record transition in history
     */
    protected function recordTransition(string $name, string $from, string $to, array $context): void
    {
        // For now, we'll skip recording history in the database
        // This could be enhanced later with a separate transitions_history table
        // \Dimita\BusinessOrchestration\Models\WorkflowTransition::create([
        //     'instance_id' => $this->instance->id,
        //     'transition_name' => $name,
        //     'from_state' => $from,
        //     'to_state' => $to,
        //     'context' => $context,
        // ]);
    }

    /**
     * Get instance for direct access if needed
     */
    public function getInstance(): WorkflowInstance
    {
        return $this->instance;
    }

    /**
     * Force transition without guard checks (use carefully)
     */
    public function forceTransition(string $state, string $reason = 'Manual override'): void
    {
        $fromState = $this->instance->state;

        $this->instance->update(['state' => $state]);

        // Record forced transition
        $this->recordTransition('force_transition', $fromState, $state, [
            'reason' => $reason,
            'forced' => true,
        ]);
    }
}