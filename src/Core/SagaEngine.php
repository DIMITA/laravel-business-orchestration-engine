<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\SagaStep;
use Dimita\BusinessOrchestration\Drivers\DriverFactory;
use Dimita\BusinessOrchestration\Jobs\ExecuteSagaStep;
use Throwable;

class SagaEngine
{
    protected $driver;

    public function __construct()
    {
        $this->driver = DriverFactory::make(config('business-orchestration.drivers.default'));
    }

    public function startSaga(string $name, array $steps, array $payload = []): Saga
    {
        $saga = Saga::create([
            'name' => $name,
            'status' => 'PENDING',
            'payload' => $payload,
        ]);

        foreach ($steps as $stepName => $stepClass) {
            SagaStep::create([
                'saga_id' => $saga->id,
                'step_name' => $stepClass,
                'status' => 'PENDING',
            ]);
        }

        $this->executeSaga($saga);

        return $saga;
    }

    public function executeSaga(Saga $saga)
    {
        $saga->update(['status' => 'RUNNING']);

        $steps = $saga->steps()->where('status', 'PENDING')->orderBy('id')->get();

        foreach ($steps as $step) {
            try {
                $step->update(['status' => 'RUNNING']);
                // For testing, call directly instead of dispatch
                $job = new ExecuteSagaStep($step);
                $job->handle();
                $step->update(['status' => 'COMPLETED', 'executed_at' => now()]);
                $saga->update(['current_step' => $step->step_name]);
            } catch (Throwable $e) {
                $step->update(['status' => 'FAILED', 'error' => $e->getMessage()]);
                $this->compensateSaga($saga);
                throw $e;
            }
        }

        $saga->update(['status' => 'COMPLETED']);
    }

    protected function compensateSaga(Saga $saga)
    {
        $completedSteps = $saga->steps()->where('status', 'COMPLETED')->orderBy('id', 'desc')->get();

        foreach ($completedSteps as $step) {
            try {
                // If step class has a compensate method, call it
                $stepClass = $step->step_name;
                if (class_exists($stepClass)) {
                    $stepInstance = new $stepClass();
                    if (method_exists($stepInstance, 'compensate')) {
                        $stepInstance->compensate($saga->payload);
                    }
                }

                $step->update(['status' => 'COMPENSATED', 'compensated_at' => now()]);
            } catch (Throwable $e) {
                // Log compensation failure but continue with other steps
                $step->update([
                    'status' => 'COMPENSATION_FAILED',
                    'error' => 'Compensation failed: ' . $e->getMessage()
                ]);
            }
        }

        $saga->update(['status' => 'COMPENSATED']);
    }

    public function resumeSaga(int $sagaId)
    {
        $saga = Saga::find($sagaId);
        if ($saga && $saga->status === 'FAILED') {
            $this->executeSaga($saga);
        }
    }

    /**
     * Execute saga asynchronously using queues
     */
    public function startSagaAsync(string $name, array $steps, array $payload = []): Saga
    {
        $saga = Saga::create([
            'name' => $name,
            'status' => 'PENDING',
            'payload' => $payload,
        ]);

        foreach ($steps as $stepName => $stepClass) {
            SagaStep::create([
                'saga_id' => $saga->id,
                'step_name' => $stepClass,
                'status' => 'PENDING',
            ]);
        }

        // Dispatch to queue instead of executing immediately
        dispatch(function() use ($saga) {
            $this->executeSaga($saga);
        });

        return $saga;
    }

    /**
     * Get current saga status with detailed step information
     */
    public function getSagaStatus(int $sagaId): array
    {
        $saga = Saga::with('steps')->find($sagaId);

        if (!$saga) {
            return ['error' => 'Saga not found'];
        }

        return [
            'id' => $saga->id,
            'name' => $saga->name,
            'status' => $saga->status,
            'current_step' => $saga->current_step,
            'total_steps' => $saga->steps->count(),
            'completed_steps' => $saga->steps->where('status', 'COMPLETED')->count(),
            'failed_steps' => $saga->steps->where('status', 'FAILED')->count(),
            'steps' => $saga->steps->map(function($step) {
                return [
                    'name' => $step->step_name,
                    'status' => $step->status,
                    'executed_at' => $step->executed_at,
                    'compensated_at' => $step->compensated_at,
                    'error' => $step->error,
                ];
            })->toArray(),
        ];
    }

    /**
     * Cancel a running saga
     */
    public function cancelSaga(int $sagaId): bool
    {
        $saga = Saga::find($sagaId);

        if (!$saga || !in_array($saga->status, ['PENDING', 'RUNNING'])) {
            return false;
        }

        $saga->update(['status' => 'CANCELLED']);
        $saga->steps()->where('status', 'PENDING')->update(['status' => 'CANCELLED']);

        return true;
    }
}