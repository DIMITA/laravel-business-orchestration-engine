<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\SagaStep;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class TestStep1 {
    public function execute($payload) { return true; }
}

class TestStep2 {
    public function execute($payload) { return true; }
}

class FailingTestStep {
    public function execute($payload) {
        throw new \Exception('Step failed intentionally');
    }
}

class SagaTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_saga_engine()
    {
        $saga = app('business-orchestration')->saga();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\SagaEngine::class, $saga);
    }

    /** @test */
    public function it_can_create_and_run_saga()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('TestSaga', [
            TestStep1::class => TestStep1::class,
            TestStep2::class => TestStep2::class,
        ], ['test' => 'data']);

        $this->assertInstanceOf(Saga::class, $saga);
        $this->assertEquals('COMPLETED', $saga->status);
    }

    /** @test */
    public function saga_starts_with_pending_status()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = Saga::create([
            'name' => 'TestSaga',
            'status' => 'PENDING',
            'payload' => ['test' => 'data'],
        ]);

        $this->assertEquals('PENDING', $saga->status);
        $this->assertEquals('TestSaga', $saga->name);
        $this->assertEquals(['test' => 'data'], $saga->payload);
    }

    /** @test */
    public function saga_creates_steps_correctly()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('TestSaga', [
            'step1' => TestStep1::class,
            'step2' => TestStep2::class,
        ], ['test' => 'data']);

        $this->assertCount(2, $saga->steps);
        $this->assertEquals(TestStep1::class, $saga->steps[0]->step_name);
        $this->assertEquals(TestStep2::class, $saga->steps[1]->step_name);
    }

    /** @test */
    public function saga_executes_steps_in_order()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('TestSaga', [
            'step1' => TestStep1::class,
            'step2' => TestStep2::class,
        ], ['test' => 'data']);

        $steps = $saga->fresh()->steps;
        $this->assertEquals('COMPLETED', $steps[0]->status);
        $this->assertEquals('COMPLETED', $steps[1]->status);
        $this->assertNotNull($steps[0]->executed_at);
        $this->assertNotNull($steps[1]->executed_at);
    }

    /** @test */
    public function saga_handles_step_failure_with_compensation()
    {
        $sagaEngine = app('business-orchestration')->saga();

        try {
            $saga = $sagaEngine->startSaga('FailingSaga', [
                'step1' => TestStep1::class,
                'failing_step' => FailingTestStep::class,
            ], ['test' => 'data']);
        } catch (\Exception $e) {
            $saga = Saga::where('name', 'FailingSaga')->first();
            $this->assertNotNull($saga);
            $this->assertEquals('COMPENSATED', $saga->status);

            $completedStep = $saga->steps()->where('step_name', TestStep1::class)->first();
            $this->assertEquals('COMPENSATED', $completedStep->status);
            $this->assertNotNull($completedStep->compensated_at);

            $failedStep = $saga->steps()->where('step_name', FailingTestStep::class)->first();
            $this->assertEquals('FAILED', $failedStep->status);
            $this->assertNotNull($failedStep->error);
        }
    }

    /** @test */
    public function saga_updates_current_step_during_execution()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('TestSaga', [
            'step1' => TestStep1::class,
            'step2' => TestStep2::class,
        ], ['test' => 'data']);

        $saga->refresh();
        $this->assertEquals(TestStep2::class, $saga->current_step);
    }

    /** @test */
    public function saga_can_be_resumed_after_failure()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = Saga::create([
            'name' => 'ResumableSaga',
            'status' => 'FAILED',
            'payload' => ['test' => 'data'],
        ]);

        SagaStep::create([
            'saga_id' => $saga->id,
            'step_name' => TestStep1::class,
            'status' => 'PENDING',
        ]);

        $sagaEngine->resumeSaga($saga->id);

        $saga->refresh();
        $this->assertEquals('COMPLETED', $saga->status);
    }

    /** @test */
    public function saga_does_not_resume_if_not_failed()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = Saga::create([
            'name' => 'CompletedSaga',
            'status' => 'COMPLETED',
            'payload' => ['test' => 'data'],
        ]);

        $sagaEngine->resumeSaga($saga->id);

        $saga->refresh();
        $this->assertEquals('COMPLETED', $saga->status);
    }

    /** @test */
    public function saga_stores_payload_correctly()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $payload = [
            'user_id' => 123,
            'order_id' => 456,
            'items' => ['item1', 'item2'],
            'metadata' => ['key' => 'value']
        ];

        $saga = $sagaEngine->startSaga('PayloadSaga', [
            TestStep1::class => TestStep1::class,
        ], $payload);

        $this->assertEquals($payload, $saga->payload);
    }

    /** @test */
    public function saga_can_run_with_empty_payload()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('EmptyPayloadSaga', [
            TestStep1::class => TestStep1::class,
        ], []);

        $this->assertInstanceOf(Saga::class, $saga);
        $this->assertEquals('COMPLETED', $saga->status);
        $this->assertEquals([], $saga->payload);
    }

    /** @test */
    public function saga_can_run_with_single_step()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('SingleStepSaga', [
            TestStep1::class => TestStep1::class,
        ], ['test' => 'data']);

        $this->assertCount(1, $saga->steps);
        $this->assertEquals('COMPLETED', $saga->status);
    }

    /** @test */
    public function saga_can_run_with_multiple_steps()
    {
        $sagaEngine = app('business-orchestration')->saga();

        $saga = $sagaEngine->startSaga('MultiStepSaga', [
            'step1' => TestStep1::class,
            'step2' => TestStep2::class,
            'step3' => TestStep1::class,
            'step4' => TestStep2::class,
        ], ['test' => 'data']);

        $this->assertCount(4, $saga->steps);
        $this->assertEquals('COMPLETED', $saga->status);
    }

    /** @test */
    public function saga_status_transitions_are_correct()
    {
        $saga = Saga::create([
            'name' => 'StatusSaga',
            'status' => 'PENDING',
            'payload' => [],
        ]);

        $this->assertEquals('PENDING', $saga->status);

        $saga->update(['status' => 'RUNNING']);
        $this->assertEquals('RUNNING', $saga->status);

        $saga->update(['status' => 'COMPLETED']);
        $this->assertEquals('COMPLETED', $saga->status);
    }

    /** @test */
    public function saga_step_has_correct_relationship_with_saga()
    {
        $saga = Saga::create([
            'name' => 'RelationshipSaga',
            'status' => 'PENDING',
            'payload' => [],
        ]);

        $step = SagaStep::create([
            'saga_id' => $saga->id,
            'step_name' => TestStep1::class,
            'status' => 'PENDING',
        ]);

        $this->assertTrue($saga->steps->contains($step));
        $this->assertEquals($saga->id, $step->saga_id);
    }

    /** @test */
    public function compensated_saga_marks_all_completed_steps_as_compensated()
    {
        $sagaEngine = app('business-orchestration')->saga();

        try {
            $saga = $sagaEngine->startSaga('CompensationSaga', [
                'step1' => TestStep1::class,
                'step2' => TestStep2::class,
                'failing_step' => FailingTestStep::class,
            ], ['test' => 'data']);
        } catch (\Exception $e) {
            $saga = Saga::where('name', 'CompensationSaga')->first();
            $compensatedSteps = $saga->steps()->where('status', 'COMPENSATED')->get();

            $this->assertCount(2, $compensatedSteps);
            foreach ($compensatedSteps as $step) {
                $this->assertNotNull($step->compensated_at);
            }
        }
    }
}