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
                $saga->update(['status' => 'FAILED']);
                throw $e;
            }
        }

        $saga->update(['status' => 'COMPLETED']);
    }

    protected function compensateSaga(Saga $saga)
    {
        $completedSteps = $saga->steps()->where('status', 'COMPLETED')->orderBy('id', 'desc')->get();

        foreach ($completedSteps as $step) {
            // Assume each step has a compensate method
            // For simplicity, mark as compensated
            $step->update(['status' => 'COMPENSATED', 'compensated_at' => now()]);
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
}