<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class VersionTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_version_engine()
    {
        $version = app('business-orchestration')->version();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\VersionEngine::class, $version);
    }

    /** @test */
    public function it_can_snapshot_and_restore_model()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Original', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);
        $model->name = 'Changed';
        $version->restore($model, 1);

        $this->assertEquals('Original', $model->name);
    }
}