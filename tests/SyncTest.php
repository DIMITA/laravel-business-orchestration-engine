<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class SyncTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_sync_engine()
    {
        $sync = app('business-orchestration')->sync();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\SyncEngine::class, $sync);
    }

    /** @test */
    public function it_can_log_and_get_sync_deltas()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', ['name' => 'Test']);
        $sync->logChange($model, 'UPDATE', ['name' => 'Updated']);

        $deltas = $sync->getDeltas(get_class($model), $model->id, 0);

        $this->assertCount(2, $deltas);
        $this->assertEquals('INSERT', $deltas[0]['operation']);
        $this->assertEquals('UPDATE', $deltas[1]['operation']);
    }
}