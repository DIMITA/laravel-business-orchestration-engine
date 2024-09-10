<?php

namespace Dimita\BusinessOrchestration\Jobs;

use Dimita\BusinessOrchestration\Models\SagaStep;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteSagaStep implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public SagaStep $step;

    public function __construct(SagaStep $step)
    {
        $this->step = $step;
    }

    public function handle()
    {
        // Assume the step_name is a class that implements Executable
        $stepClass = $this->step->step_name;
        $instance = new $stepClass();
        $instance->execute($this->step->saga->payload);
    }
}