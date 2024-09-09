<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class DependencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_dependency_engine()
    {
        $dep = app('business-orchestration')->dependency();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\DependencyEngine::class, $dep);
    }

    /** @test */
    public function it_can_check_deletion_dependencies()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency(Saga::class, Saga::class, 'rule');

        // Since no instances, should allow deletion
        $this->assertTrue($dep->checkDeletion(Saga::class, 1));
    }
}