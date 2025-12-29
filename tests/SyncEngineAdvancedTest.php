<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\SyncLog;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class SyncEngineAdvancedTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_sync_models_with_conflict_detection()
    {
        $sync = app('business-orchestration')->sync();

        $source = Saga::create(['name' => 'Source', 'status' => 'COMPLETED', 'payload' => []]);
        $target = Saga::create(['name' => 'Target', 'status' => 'PENDING', 'payload' => []]);

        $result = $sync->sync($source, $target);

        $this->assertArrayHasKey('conflicts', $result);
        $this->assertArrayHasKey('synced_fields', $result);
        // name, status, and timestamps differ
        $this->assertGreaterThanOrEqual(2, count($result['conflicts']));
        $this->assertArrayHasKey('name', $result['conflicts']);
        $this->assertArrayHasKey('status', $result['conflicts']);
    }

    /** @test */
    public function sync_applies_source_data_to_target()
    {
        $sync = app('business-orchestration')->sync();

        $source = Saga::create(['name' => 'NewName', 'status' => 'RUNNING', 'payload' => []]);
        $target = Saga::create(['name' => 'OldName', 'status' => 'PENDING', 'payload' => []]);

        $sync->sync($source, $target);

        $target->refresh();
        $this->assertEquals('NewName', $target->name);
        $this->assertEquals('RUNNING', $target->status);
    }

    /** @test */
    public function it_can_detect_conflicts_between_models()
    {
        $sync = app('business-orchestration')->sync();

        $sourceData = ['name' => 'Source', 'status' => 'RUNNING', 'value' => 100];
        $targetData = ['name' => 'Target', 'status' => 'RUNNING', 'value' => 200];

        $conflicts = $sync->detectConflicts($sourceData, $targetData);

        $this->assertCount(2, $conflicts);
        $this->assertArrayHasKey('name', $conflicts);
        $this->assertArrayHasKey('value', $conflicts);
        $this->assertEquals('Source', $conflicts['name']['source']);
        $this->assertEquals('Target', $conflicts['name']['target']);
    }

    /** @test */
    public function conflict_strategy_latest_wins_uses_source()
    {
        $sync = app('business-orchestration')->sync();
        $sync->setConflictStrategy('latest_wins');

        $source = Saga::create(['name' => 'Latest', 'status' => 'COMPLETED', 'payload' => []]);
        $target = Saga::create(['name' => 'Old', 'status' => 'PENDING', 'payload' => []]);

        $sync->sync($source, $target);

        $target->refresh();
        $this->assertEquals('Latest', $target->name);
        $this->assertEquals('COMPLETED', $target->status);
    }

    /** @test */
    public function conflict_strategy_source_wins()
    {
        $sync = app('business-orchestration')->sync();
        $sync->setConflictStrategy('source_wins');

        $source = Saga::create(['name' => 'SourceValue', 'status' => 'RUNNING', 'payload' => []]);
        $target = Saga::create(['name' => 'TargetValue', 'status' => 'PENDING', 'payload' => []]);

        $sync->sync($source, $target);

        $target->refresh();
        $this->assertEquals('SourceValue', $target->name);
    }

    /** @test */
    public function conflict_strategy_target_wins()
    {
        $sync = app('business-orchestration')->sync();
        $sync->setConflictStrategy('target_wins');

        $source = Saga::create(['name' => 'SourceValue', 'status' => 'RUNNING', 'payload' => []]);
        $target = Saga::create(['name' => 'TargetValue', 'status' => 'PENDING', 'payload' => []]);

        $result = $sync->sync($source, $target);

        $target->refresh();
        $this->assertEquals('TargetValue', $target->name);
        $this->assertEquals('PENDING', $target->status);
    }

    /** @test */
    public function it_can_register_custom_conflict_handler()
    {
        $sync = app('business-orchestration')->sync();

        $sync->registerConflictHandler('name', function($values, $source, $target) {
            return strtoupper($values['source']);
        });

        $source = Saga::create(['name' => 'source', 'status' => 'RUNNING', 'payload' => []]);
        $target = Saga::create(['name' => 'target', 'status' => 'RUNNING', 'payload' => []]);

        $sync->sync($source, $target);

        $target->refresh();
        $this->assertEquals('SOURCE', $target->name);
    }

    /** @test */
    public function it_can_sync_selective_fields()
    {
        $sync = app('business-orchestration')->sync();

        $source = Saga::create(['name' => 'NewName', 'status' => 'COMPLETED', 'payload' => []]);
        $target = Saga::create(['name' => 'OldName', 'status' => 'PENDING', 'payload' => []]);

        $sync->sync($source, $target, ['name']); // Only sync name

        $target->refresh();
        $this->assertEquals('NewName', $target->name);
        $this->assertEquals('PENDING', $target->status); // Status should remain unchanged
    }

    /** @test */
    public function it_can_batch_sync_multiple_models()
    {
        $sync = app('business-orchestration')->sync();

        $source1 = Saga::create(['name' => 'Source1', 'status' => 'RUNNING', 'payload' => []]);
        $target1 = Saga::create(['name' => 'Target1', 'status' => 'PENDING', 'payload' => []]);

        $source2 = Saga::create(['name' => 'Source2', 'status' => 'COMPLETED', 'payload' => []]);
        $target2 = Saga::create(['name' => 'Target2', 'status' => 'PENDING', 'payload' => []]);

        $pairs = [
            ['source' => $source1, 'target' => $target1],
            ['source' => $source2, 'target' => $target2],
        ];

        $results = $sync->batchSync($pairs);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey(0, $results);
        $this->assertArrayHasKey(1, $results);
        $this->assertArrayHasKey('synced_fields', $results[0]);
        $this->assertArrayHasKey('synced_fields', $results[1]);
    }

    /** @test */
    public function batch_sync_handles_invalid_pairs()
    {
        $sync = app('business-orchestration')->sync();

        $pairs = [
            ['source' => Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []])],
            ['invalid' => 'pair'],
        ];

        $results = $sync->batchSync($pairs);

        $this->assertArrayHasKey('error', $results[0]);
        $this->assertArrayHasKey('error', $results[1]);
    }

    /** @test */
    public function it_can_create_checkpoints()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => ['amount' => 100]]);

        $checkpoint = $sync->checkpoint($model, 'before_update');

        $this->assertInstanceOf(SyncLog::class, $checkpoint);
        $this->assertEquals('checkpoint', $checkpoint->operation);
        $this->assertEquals('before_update', $checkpoint->metadata['checkpoint_name']);
        $this->assertEquals($model->toArray(), $checkpoint->metadata['snapshot']);
    }

    /** @test */
    public function it_can_restore_from_checkpoint()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Original', 'status' => 'PENDING', 'payload' => []]);
        $sync->checkpoint($model, 'backup');

        $model->name = 'Changed';
        $model->status = 'RUNNING';
        $model->save();

        $restored = $sync->restoreCheckpoint($model, 'backup');

        $this->assertEquals('Original', $restored->name);
        $this->assertEquals('PENDING', $restored->status);
    }

    /** @test */
    public function restore_checkpoint_returns_null_when_not_found()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $result = $sync->restoreCheckpoint($model, 'nonexistent');

        $this->assertNull($result);
    }

    /** @test */
    public function it_can_get_all_checkpoints()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->checkpoint($model, 'checkpoint1');
        $sync->checkpoint($model, 'checkpoint2');
        $sync->checkpoint($model, 'checkpoint3');

        $checkpoints = $sync->getCheckpoints($model);

        $this->assertCount(3, $checkpoints);
        $this->assertEquals('checkpoint3', $checkpoints[0]['name']); // Most recent first
        $this->assertEquals('checkpoint2', $checkpoints[1]['name']);
        $this->assertEquals('checkpoint1', $checkpoints[2]['name']);
    }

    /** @test */
    public function it_can_get_sync_status()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $sync->logChange($model, 'synced', ['name']);
        $sync->logChange($model, 'synced', ['status']);
        $sync->checkpoint($model, 'backup');

        $status = $sync->getSyncStatus($model);

        $this->assertEquals(3, $status['total_syncs']);
        $this->assertEquals(3, $status['current_version']);
        $this->assertArrayHasKey('operations', $status);
        $this->assertEquals(2, $status['operations']['synced']);
        $this->assertEquals(1, $status['operations']['checkpoint']);
    }

    /** @test */
    public function it_can_compare_two_models()
    {
        $sync = app('business-orchestration')->sync();

        $modelA = Saga::create(['name' => 'ModelA', 'status' => 'RUNNING', 'payload' => []]);
        $modelB = Saga::create(['name' => 'ModelB', 'status' => 'RUNNING', 'payload' => []]);

        $differences = $sync->compare($modelA, $modelB);

        $this->assertArrayHasKey('name', $differences);
        $this->assertEquals('ModelA', $differences['name']['source']);
        $this->assertEquals('ModelB', $differences['name']['target']);
    }

    /** @test */
    public function compare_can_use_selective_fields()
    {
        $sync = app('business-orchestration')->sync();

        $modelA = Saga::create(['name' => 'A', 'status' => 'RUNNING', 'payload' => []]);
        $modelB = Saga::create(['name' => 'B', 'status' => 'PENDING', 'payload' => []]);

        $differences = $sync->compare($modelA, $modelB, ['name']);

        $this->assertCount(1, $differences);
        $this->assertArrayHasKey('name', $differences);
        $this->assertArrayNotHasKey('status', $differences);
    }

    /** @test */
    public function it_can_apply_deltas_to_model()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Original', 'status' => 'PENDING', 'payload' => []]);

        $deltas = [
            [
                'operation' => 'update',
                'changed_fields' => ['name' => 'Updated', 'status' => 'RUNNING']
            ],
            [
                'operation' => 'update',
                'changed_fields' => ['current_step' => 5]
            ]
        ];

        $sync->applyDeltas($model, $deltas);

        $this->assertEquals('Updated', $model->name);
        $this->assertEquals('RUNNING', $model->status);
        $this->assertEquals(5, $model->current_step);
    }

    /** @test */
    public function it_logs_sync_operations_with_metadata()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $metadata = [
            'device_id' => 'mobile-123',
            'user_id' => 456,
            'app_version' => '2.1.0'
        ];

        $log = $sync->logChange($model, 'synced', ['name', 'status'], $metadata);

        $this->assertEquals('mobile-123', $log->metadata['device_id']);
        $this->assertEquals(456, $log->metadata['user_id']);
        $this->assertEquals('2.1.0', $log->metadata['app_version']);
    }

    /** @test */
    public function metadata_defaults_to_empty_array()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $log = $sync->logChange($model, 'synced', ['name']);

        $this->assertEquals([], $log->metadata);
    }

    /** @test */
    public function it_can_purge_old_sync_logs()
    {
        $sync = app('business-orchestration')->sync();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        // Create 10 sync logs
        for ($i = 0; $i < 10; $i++) {
            $sync->logChange($model, 'synced', ['name']);
        }

        $this->assertEquals(10, SyncLog::where('model_id', $model->id)->count());

        // Keep only last 5
        $deleted = $sync->purgeOldLogs(get_class($model), 5);

        $this->assertEquals(5, $deleted);
        $this->assertEquals(5, SyncLog::where('model_id', $model->id)->count());
    }

    /** @test */
    public function sync_logs_change_with_metadata()
    {
        $sync = app('business-orchestration')->sync();

        $source = Saga::create(['name' => 'Source', 'status' => 'RUNNING', 'payload' => []]);
        $target = Saga::create(['name' => 'Target', 'status' => 'PENDING', 'payload' => []]);

        $sync->sync($source, $target);

        $log = SyncLog::where('model_id', $target->id)->where('operation', 'synced')->first();

        $this->assertNotNull($log);
        $this->assertEquals($source->id, $log->metadata['source_id']);
        $this->assertEquals(get_class($source), $log->metadata['source_type']);
        $this->assertArrayHasKey('conflicts', $log->metadata);
    }

    /** @test */
    public function conflict_strategy_merge_combines_arrays()
    {
        $sync = app('business-orchestration')->sync();
        $sync->setConflictStrategy('merge');

        // Test with array merging
        $source = Saga::create(['name' => 'Test', 'status' => 'RUNNING', 'payload' => ['tags' => ['new']]]);
        $target = Saga::create(['name' => 'Test', 'status' => 'RUNNING', 'payload' => ['tags' => ['featured']]]);

        $conflicts = [
            'payload' => [
                'source' => ['tags' => ['new']],
                'target' => ['tags' => ['featured']]
            ]
        ];

        $resolved = $sync->resolveConflicts($conflicts, $source, $target);

        $this->assertIsArray($resolved['payload']);
    }

    /** @test */
    public function set_conflict_strategy_returns_self_for_chaining()
    {
        $sync = app('business-orchestration')->sync();

        $result = $sync->setConflictStrategy('source_wins');

        $this->assertSame($sync, $result);
    }

    /** @test */
    public function register_conflict_handler_returns_self_for_chaining()
    {
        $sync = app('business-orchestration')->sync();

        $result = $sync->registerConflictHandler('field', fn() => 'value');

        $this->assertSame($sync, $result);
    }
}
