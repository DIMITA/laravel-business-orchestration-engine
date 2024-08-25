<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Orchestra\Testbench\TestCase;

class ExampleTest extends TestCase
{
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
}