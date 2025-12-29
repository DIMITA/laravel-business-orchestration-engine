<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\WorkflowTransition;
use Dimita\BusinessOrchestration\Models\WorkflowInstance;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class WorkflowTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_workflow_engine()
    {
        $workflow = app('business-orchestration')->workflow();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\WorkflowEngine::class, $workflow);
    }

    /** @test */
    public function it_can_define_and_apply_workflow_transition()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $this->assertTrue($builder->can('approve'));
        $builder->apply('approve');
        $this->assertEquals('approved', $builder->getState());
    }

    /** @test */
    public function workflow_instance_is_created_with_default_state()
    {
        $workflow = app('business-orchestration')->workflow();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $this->assertEquals('initial', $builder->getState());
    }

    /** @test */
    public function workflow_instance_is_reused_for_same_model()
    {
        $workflow = app('business-orchestration')->workflow();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder1 = $workflow->for($model);
        $builder2 = $workflow->for($model);

        $this->assertEquals($builder1->getState(), $builder2->getState());
    }

    /** @test */
    public function workflow_can_define_multiple_transitions()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');
        $workflow->defineTransition('reject', 'initial', 'rejected');
        $workflow->defineTransition('revise', 'approved', 'revision');

        $this->assertCount(3, WorkflowTransition::all());
    }

    /** @test */
    public function workflow_can_transition_through_multiple_states()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('submit', 'initial', 'submitted');
        $workflow->defineTransition('review', 'submitted', 'in_review');
        $workflow->defineTransition('approve', 'in_review', 'approved');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $builder->apply('submit');
        $this->assertEquals('submitted', $builder->getState());

        $builder->apply('review');
        $this->assertEquals('in_review', $builder->getState());

        $builder->apply('approve');
        $this->assertEquals('approved', $builder->getState());
    }

    /** @test */
    public function workflow_cannot_apply_invalid_transition()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $this->assertFalse($builder->can('invalid_transition'));
    }

    /** @test */
    public function workflow_cannot_transition_from_wrong_state()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'submitted', 'approved');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $this->assertFalse($builder->can('approve'));
    }

    /** @test */
    public function workflow_throws_exception_when_applying_invalid_transition()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot apply transition invalid_transition');

        $workflow = app('business-orchestration')->workflow();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $builder->apply('invalid_transition');
    }

    /** @test */
    public function workflow_can_check_multiple_transitions_from_same_state()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');
        $workflow->defineTransition('reject', 'initial', 'rejected');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);

        $this->assertTrue($builder->can('approve'));
        $this->assertTrue($builder->can('reject'));
    }

    /** @test */
    public function workflow_transition_stores_from_and_to_states()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');

        $transition = WorkflowTransition::where('name', 'approve')->first();

        $this->assertEquals('initial', $transition->from);
        $this->assertEquals('approved', $transition->to);
    }

    /** @test */
    public function workflow_can_define_transition_with_guard()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved', 'return true;');

        $transition = WorkflowTransition::where('name', 'approve')->first();

        $this->assertEquals('return true;', $transition->guard_expression);
    }

    /** @test */
    public function workflow_supports_multiple_models()
    {
        $workflow = app('business-orchestration')->workflow();

        $model1 = Saga::create(['name' => 'Model1', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'Model2', 'status' => 'PENDING', 'payload' => []]);

        $builder1 = $workflow->for($model1);
        $builder2 = $workflow->for($model2);

        // Both should start with the same initial state
        $this->assertEquals('initial', $builder1->getState());
        $this->assertEquals('initial', $builder2->getState());

        // Verify they are separate instances
        $instance1 = WorkflowInstance::where('model_id', $model1->id)->first();
        $instance2 = WorkflowInstance::where('model_id', $model2->id)->first();

        $this->assertNotEquals($instance1->id, $instance2->id);
    }

    /** @test */
    public function workflow_instance_persists_state_changes()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $builder = $workflow->for($model);
        $builder->apply('approve');

        $instance = WorkflowInstance::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->first();

        $this->assertEquals('approved', $instance->state);
    }

    /** @test */
    public function workflow_can_handle_complex_state_machine()
    {
        $workflow = app('business-orchestration')->workflow();

        // Draft -> Submitted -> Review -> (Approved | Rejected)
        // Rejected -> Revision -> Submitted
        $workflow->defineTransition('submit', 'draft', 'submitted');
        $workflow->defineTransition('review', 'submitted', 'in_review');
        $workflow->defineTransition('approve', 'in_review', 'approved');
        $workflow->defineTransition('reject', 'in_review', 'rejected');
        $workflow->defineTransition('revise', 'rejected', 'revision');
        $workflow->defineTransition('resubmit', 'revision', 'submitted');

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $instance = WorkflowInstance::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'state' => 'draft',
        ]);

        $builder = $workflow->for($model);

        $this->assertTrue($builder->can('submit'));
        $builder->apply('submit');
        $this->assertEquals('submitted', $builder->getState());

        $builder->apply('review');
        $this->assertEquals('in_review', $builder->getState());

        $builder->apply('reject');
        $this->assertEquals('rejected', $builder->getState());

        $builder->apply('revise');
        $this->assertEquals('revision', $builder->getState());

        $builder->apply('resubmit');
        $this->assertEquals('submitted', $builder->getState());
    }

    /** @test */
    public function workflow_state_is_isolated_per_model_instance()
    {
        $workflow = app('business-orchestration')->workflow();

        $workflow->defineTransition('approve', 'initial', 'approved');

        $model1 = Saga::create(['name' => 'Model1', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'Model2', 'status' => 'PENDING', 'payload' => []]);

        $builder1 = $workflow->for($model1);
        $builder2 = $workflow->for($model2);

        $builder1->apply('approve');

        $this->assertEquals('approved', $builder1->getState());
        $this->assertEquals('initial', $builder2->getState());
    }
}