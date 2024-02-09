<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class TestStep1 {
    public function execute($payload) { return true; }
}

class TestStep2 {
    public function execute($payload) { return true; }
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
}