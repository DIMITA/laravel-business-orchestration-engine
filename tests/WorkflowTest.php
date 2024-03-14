<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
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
}