<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\WorkflowInstance;
use Dimita\BusinessOrchestration\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

class WorkflowEngine
{
    protected array $workflows = [];
    protected array $beforeCallbacks = [];
    protected array $afterCallbacks = [];

    public function for(Model $model, ?string $initialState = 'initial'): WorkflowBuilder
    {
        $instance = WorkflowInstance::firstOrCreate([
            'model_type' => get_class($model),
            'model_id' => $model->id,
        ], [
            'state' => $initialState,
        ]);

        return new WorkflowBuilder($instance, $this);
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

    /**
     * Register a workflow definition
     */
    public function registerWorkflow(string $name, array $config): void
    {
        $this->workflows[$name] = $config;

        // Create transitions from config
        if (isset($config['transitions'])) {
            foreach ($config['transitions'] as $transition) {
                $this->defineTransition(
                    $transition['name'],
                    $transition['from'],
                    $transition['to'],
                    $transition['guard'] ?? null
                );
            }
        }
    }

    /**
     * Get available transitions for a given state
     */
    public function getEnabledTransitions(string $state): array
    {
        return WorkflowTransition::where('from', $state)->get()->toArray();
    }

    /**
     * Get workflow history for a model
     */
    public function getHistory(Model $model): array
    {
        $instance = WorkflowInstance::where([
            'model_type' => get_class($model),
            'model_id' => $model->id,
        ])->first();

        if (!$instance) {
            return [];
        }

        // History tracking would require a separate table
        // For now, return empty array
        // This can be enhanced with a transitions_history table
        return [];
    }

    /**
     * Register a callback to execute before transition
     */
    public function beforeTransition(string $transitionName, callable $callback): void
    {
        if (!isset($this->beforeCallbacks[$transitionName])) {
            $this->beforeCallbacks[$transitionName] = [];
        }
        $this->beforeCallbacks[$transitionName][] = $callback;
    }

    /**
     * Register a callback to execute after transition
     */
    public function afterTransition(string $transitionName, callable $callback): void
    {
        if (!isset($this->afterCallbacks[$transitionName])) {
            $this->afterCallbacks[$transitionName] = [];
        }
        $this->afterCallbacks[$transitionName][] = $callback;
    }

    /**
     * Execute before callbacks
     */
    public function executeBeforeCallbacks(string $transitionName, WorkflowInstance $instance): void
    {
        if (isset($this->beforeCallbacks[$transitionName])) {
            foreach ($this->beforeCallbacks[$transitionName] as $callback) {
                $callback($instance);
            }
        }

        // Fire Laravel event
        Event::dispatch('workflow.before_transition', [$transitionName, $instance]);
    }

    /**
     * Execute after callbacks
     */
    public function executeAfterCallbacks(string $transitionName, WorkflowInstance $instance): void
    {
        if (isset($this->afterCallbacks[$transitionName])) {
            foreach ($this->afterCallbacks[$transitionName] as $callback) {
                $callback($instance);
            }
        }

        // Fire Laravel event
        Event::dispatch('workflow.after_transition', [$transitionName, $instance]);
    }

    /**
     * Check if a workflow is in a specific state
     */
    public function isInState(Model $model, string $state): bool
    {
        $instance = WorkflowInstance::where([
            'model_type' => get_class($model),
            'model_id' => $model->id,
        ])->first();

        return $instance && $instance->state === $state;
    }

    /**
     * Get all possible states for a workflow
     */
    public function getAllStates(): array
    {
        $states = [];

        $transitions = WorkflowTransition::all();
        foreach ($transitions as $transition) {
            $states[$transition->from] = true;
            $states[$transition->to] = true;
        }

        return array_keys($states);
    }
}