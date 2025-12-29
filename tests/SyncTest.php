<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\SyncLog;
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

    /** @test */
    public function sync_log_records_change()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', ['name' => 'Test']);

        $this->assertDatabaseHas('sync_log', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'operation' => 'INSERT'
        ]);
    }

    /** @test */
    public function sync_version_increments_automatically()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', []);
        $sync->logChange($model, 'UPDATE', []);
        $sync->logChange($model, 'UPDATE', []);

        $logs = SyncLog::where('model_id', $model->id)->orderBy('version')->get();

        $this->assertEquals(1, $logs[0]->version);
        $this->assertEquals(2, $logs[1]->version);
        $this->assertEquals(3, $logs[2]->version);
    }

    /** @test */
    public function sync_stores_changed_fields()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $changedFields = ['name' => 'NewName', 'status' => 'RUNNING'];

        $sync->logChange($model, 'UPDATE', $changedFields);

        $log = SyncLog::where('model_id', $model->id)->first();

        $this->assertEquals($changedFields, $log->changed_fields);
    }

    /** @test */
    public function get_deltas_returns_changes_after_version()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', ['v' => 1]);
        $sync->logChange($model, 'UPDATE', ['v' => 2]);
        $sync->logChange($model, 'UPDATE', ['v' => 3]);
        $sync->logChange($model, 'UPDATE', ['v' => 4]);

        $deltas = $sync->getDeltas(get_class($model), $model->id, 2);

        $this->assertCount(2, $deltas);
        $this->assertEquals(3, $deltas[0]['version']);
        $this->assertEquals(4, $deltas[1]['version']);
    }

    /** @test */
    public function get_deltas_returns_empty_when_up_to_date()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', []);
        $sync->logChange($model, 'UPDATE', []);

        $deltas = $sync->getDeltas(get_class($model), $model->id, 2);

        $this->assertIsArray($deltas);
        $this->assertCount(0, $deltas);
    }

    /** @test */
    public function sync_logs_are_isolated_by_model()
    {
        $sync = app('business-orchestration')->sync();

        $model1 = Saga::create(['name' => 'Model1', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'Model2', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model1, 'INSERT', []);
        $sync->logChange($model1, 'UPDATE', []);
        $sync->logChange($model2, 'INSERT', []);

        $deltas1 = $sync->getDeltas(get_class($model1), $model1->id, 0);
        $deltas2 = $sync->getDeltas(get_class($model2), $model2->id, 0);

        $this->assertCount(2, $deltas1);
        $this->assertCount(1, $deltas2);
    }

    /** @test */
    public function sync_can_track_insert_operations()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'New', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', ['name' => 'New', 'status' => 'PENDING']);

        $log = SyncLog::where('model_id', $model->id)->first();

        $this->assertEquals('INSERT', $log->operation);
        $this->assertEquals(['name' => 'New', 'status' => 'PENDING'], $log->changed_fields);
    }

    /** @test */
    public function sync_can_track_update_operations()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Original', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'UPDATE', ['name' => 'Updated']);

        $log = SyncLog::where('model_id', $model->id)->where('operation', 'UPDATE')->first();

        $this->assertEquals('UPDATE', $log->operation);
        $this->assertEquals(['name' => 'Updated'], $log->changed_fields);
    }

    /** @test */
    public function sync_can_track_delete_operations()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'ToDelete', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'DELETE', []);

        $log = SyncLog::where('model_id', $model->id)->where('operation', 'DELETE')->first();

        $this->assertEquals('DELETE', $log->operation);
    }

    /** @test */
    public function sync_returns_deltas_in_order()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', ['order' => 1]);
        $sync->logChange($model, 'UPDATE', ['order' => 2]);
        $sync->logChange($model, 'UPDATE', ['order' => 3]);

        $deltas = $sync->getDeltas(get_class($model), $model->id, 0);

        $this->assertEquals(1, $deltas[0]['changed_fields']['order']);
        $this->assertEquals(2, $deltas[1]['changed_fields']['order']);
        $this->assertEquals(3, $deltas[2]['changed_fields']['order']);
    }

    /** @test */
    public function sync_can_handle_complex_field_changes()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $complexFields = [
            'name' => 'Updated Name',
            'payload' => [
                'items' => [1, 2, 3],
                'metadata' => ['key' => 'value']
            ],
            'status' => 'RUNNING'
        ];

        $sync->logChange($model, 'UPDATE', $complexFields);

        $log = SyncLog::where('model_id', $model->id)->first();

        $this->assertEquals($complexFields, $log->changed_fields);
    }

    /** @test */
    public function sync_can_track_multiple_models_independently()
    {
        $sync = app('business-orchestration')->sync();

        $model1 = Saga::create(['name' => 'M1', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'M2', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model1, 'INSERT', ['v' => 1]);
        $sync->logChange($model2, 'INSERT', ['v' => 1]);
        $sync->logChange($model1, 'UPDATE', ['v' => 2]);
        $sync->logChange($model2, 'UPDATE', ['v' => 2]);

        $logs1 = SyncLog::where('model_id', $model1->id)->get();
        $logs2 = SyncLog::where('model_id', $model2->id)->get();

        $this->assertCount(2, $logs1);
        $this->assertCount(2, $logs2);
    }

    /** @test */
    public function sync_version_is_per_model_instance()
    {
        $sync = app('business-orchestration')->sync();

        $model1 = Saga::create(['name' => 'M1', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'M2', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model1, 'INSERT', []);
        $sync->logChange($model2, 'INSERT', []);
        $sync->logChange($model1, 'UPDATE', []);
        $sync->logChange($model2, 'UPDATE', []);

        $log1 = SyncLog::where('model_id', $model1->id)->orderBy('version', 'desc')->first();
        $log2 = SyncLog::where('model_id', $model2->id)->orderBy('version', 'desc')->first();

        $this->assertEquals(2, $log1->version);
        $this->assertEquals(2, $log2->version);
    }

    /** @test */
    public function sync_can_support_offline_sync_scenario()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Offline', 'status' => 'PENDING', 'payload' => []]);

        // Client last synced at version 0
        $lastSyncVersion = 0;

        // Server changes
        $sync->logChange($model, 'INSERT', ['name' => 'Offline']);
        $sync->logChange($model, 'UPDATE', ['status' => 'RUNNING']);
        $sync->logChange($model, 'UPDATE', ['status' => 'COMPLETED']);

        // Client requests deltas
        $deltas = $sync->getDeltas(get_class($model), $model->id, $lastSyncVersion);

        $this->assertCount(3, $deltas);
        $this->assertEquals('INSERT', $deltas[0]['operation']);
        $this->assertEquals('UPDATE', $deltas[1]['operation']);
        $this->assertEquals('UPDATE', $deltas[2]['operation']);
    }

    /** @test */
    public function sync_log_persists_in_database()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Persist', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'INSERT', ['test' => 'data']);

        $log = SyncLog::where('model_id', $model->id)->first();

        $this->assertInstanceOf(SyncLog::class, $log);
        $this->assertEquals(get_class($model), $log->model_type);
        $this->assertEquals($model->id, $log->model_id);
        $this->assertEquals('INSERT', $log->operation);
    }

    /** @test */
    public function sync_can_handle_empty_changed_fields()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'DELETE', []);

        $log = SyncLog::where('model_id', $model->id)->first();

        $this->assertEquals([], $log->changed_fields);
    }

    /** @test */
    public function get_deltas_for_nonexistent_model_returns_empty()
    {
        $sync = app('business-orchestration')->sync();

        $deltas = $sync->getDeltas('NonExistentModel', 999, 0);

        $this->assertIsArray($deltas);
        $this->assertCount(0, $deltas);
    }

    /** @test */
    public function sync_supports_incremental_sync()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Incremental', 'status' => 'PENDING', 'payload' => []]);

        // Initial sync
        $sync->logChange($model, 'INSERT', ['v' => 1]);
        $sync->logChange($model, 'UPDATE', ['v' => 2]);

        $deltas1 = $sync->getDeltas(get_class($model), $model->id, 0);
        $this->assertCount(2, $deltas1);

        // Client is now at version 2
        $clientVersion = 2;

        // More changes on server
        $sync->logChange($model, 'UPDATE', ['v' => 3]);
        $sync->logChange($model, 'UPDATE', ['v' => 4]);

        // Client requests only new changes
        $deltas2 = $sync->getDeltas(get_class($model), $model->id, $clientVersion);

        $this->assertCount(2, $deltas2);
        $this->assertEquals(3, $deltas2[0]['version']);
        $this->assertEquals(4, $deltas2[1]['version']);
    }
}