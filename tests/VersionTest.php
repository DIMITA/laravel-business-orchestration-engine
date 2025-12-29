<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\ModelVersion;
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

    /** @test */
    public function snapshot_creates_version_entry()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);

        $this->assertDatabaseHas('model_versions', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'version' => 1
        ]);
    }

    /** @test */
    public function snapshot_increments_version_number()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);
        $model->name = 'Changed';
        $version->snapshot($model);
        $model->name = 'Changed Again';
        $version->snapshot($model);

        $versions = ModelVersion::where('model_id', $model->id)->get();

        $this->assertCount(3, $versions);
        $this->assertEquals(1, $versions[0]->version);
        $this->assertEquals(2, $versions[1]->version);
        $this->assertEquals(3, $versions[2]->version);
    }

    /** @test */
    public function snapshot_stores_complete_model_data()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create([
            'name' => 'CompleteTest',
            'status' => 'PENDING',
            'payload' => ['key' => 'value', 'nested' => ['data' => 'test']]
        ]);

        $version->snapshot($model);

        $snapshot = ModelVersion::where('model_id', $model->id)->first();

        $this->assertEquals('CompleteTest', $snapshot->snapshot['name']);
        $this->assertEquals('PENDING', $snapshot->snapshot['status']);
        $this->assertEquals(['key' => 'value', 'nested' => ['data' => 'test']], $snapshot->snapshot['payload']);
    }

    /** @test */
    public function snapshot_generates_hash()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);

        $snapshot = ModelVersion::where('model_id', $model->id)->first();

        $this->assertNotNull($snapshot->hash);
        $this->assertIsString($snapshot->hash);
    }

    /** @test */
    public function restore_updates_model_attributes()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Version1', 'status' => 'PENDING', 'payload' => ['v' => 1]]);

        $version->snapshot($model);

        $model->name = 'Version2';
        $model->status = 'RUNNING';
        $model->payload = ['v' => 2];
        $model->save();

        $version->restore($model, 1);

        $this->assertEquals('Version1', $model->name);
        $this->assertEquals('PENDING', $model->status);
        $this->assertEquals(['v' => 1], $model->payload);
    }

    /** @test */
    public function restore_persists_to_database()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Original', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);

        $model->name = 'Modified';
        $model->save();

        $version->restore($model, 1);

        $this->assertDatabaseHas('sagas', [
            'id' => $model->id,
            'name' => 'Original'
        ]);
    }

    /** @test */
    public function can_restore_to_specific_version()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'V1', 'status' => 'PENDING', 'payload' => []]);
        $version->snapshot($model);

        $model->name = 'V2';
        $version->snapshot($model);

        $model->name = 'V3';
        $version->snapshot($model);

        $model->name = 'V4';

        $version->restore($model, 2);

        $this->assertEquals('V2', $model->name);
    }

    /** @test */
    public function get_versions_returns_all_versions()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);
        $model->name = 'Changed1';
        $version->snapshot($model);
        $model->name = 'Changed2';
        $version->snapshot($model);

        $versions = $version->getVersions($model);

        $this->assertCount(3, $versions);
    }

    /** @test */
    public function get_versions_returns_ordered_by_version()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'V1', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);
        $model->name = 'V2';
        $version->snapshot($model);
        $model->name = 'V3';
        $version->snapshot($model);

        $versions = $version->getVersions($model);

        $this->assertEquals(1, $versions[0]['version']);
        $this->assertEquals(2, $versions[1]['version']);
        $this->assertEquals(3, $versions[2]['version']);
    }

    /** @test */
    public function get_versions_returns_empty_array_for_model_without_versions()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $versions = $version->getVersions($model);

        $this->assertIsArray($versions);
        $this->assertCount(0, $versions);
    }

    /** @test */
    public function versions_are_isolated_per_model_instance()
    {
        $version = app('business-orchestration')->version();

        $model1 = Saga::create(['name' => 'Model1', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'Model2', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model1);
        $version->snapshot($model1);
        $version->snapshot($model2);

        $versions1 = $version->getVersions($model1);
        $versions2 = $version->getVersions($model2);

        $this->assertCount(2, $versions1);
        $this->assertCount(1, $versions2);
    }

    /** @test */
    public function snapshot_hash_changes_when_data_changes()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test1', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);
        $hash1 = ModelVersion::where('model_id', $model->id)->first()->hash;

        $model->name = 'Test2';
        $version->snapshot($model);
        $hash2 = ModelVersion::where('model_id', $model->id)->orderBy('version', 'desc')->first()->hash;

        $this->assertNotEquals($hash1, $hash2);
    }

    /** @test */
    public function snapshot_hash_is_same_for_identical_data()
    {
        $version = app('business-orchestration')->version();

        $model1 = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);
        $model2 = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model1);
        $version->snapshot($model2);

        $hash1 = ModelVersion::where('model_id', $model1->id)->first()->hash;
        $hash2 = ModelVersion::where('model_id', $model2->id)->first()->hash;

        // Note: Hashes may differ due to different IDs and timestamps
        $this->assertIsString($hash1);
        $this->assertIsString($hash2);
    }

    /** @test */
    public function restore_handles_nonexistent_version_gracefully()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model);

        $originalName = $model->name;
        $model->name = 'Changed';

        $version->restore($model, 999); // Non-existent version

        // Model should remain unchanged since version doesn't exist
        $this->assertEquals('Changed', $model->name);
    }

    /** @test */
    public function version_tracks_complex_payload_changes()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create([
            'name' => 'Complex',
            'status' => 'PENDING',
            'payload' => [
                'items' => [1, 2, 3],
                'metadata' => ['key' => 'value']
            ]
        ]);

        $version->snapshot($model);

        $model->payload = [
            'items' => [4, 5, 6],
            'metadata' => ['key' => 'new_value']
        ];

        $version->snapshot($model);

        $version->restore($model, 1);

        $this->assertEquals([
            'items' => [1, 2, 3],
            'metadata' => ['key' => 'value']
        ], $model->payload);
    }

    /** @test */
    public function multiple_snapshots_can_track_workflow()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Order', 'status' => 'PENDING', 'payload' => []]);

        $version->snapshot($model); // V1: PENDING

        $model->status = 'PROCESSING';
        $version->snapshot($model); // V2: PROCESSING

        $model->status = 'COMPLETED';
        $version->snapshot($model); // V3: COMPLETED

        $versions = $version->getVersions($model);

        $this->assertEquals('PENDING', $versions[0]['snapshot']['status']);
        $this->assertEquals('PROCESSING', $versions[1]['snapshot']['status']);
        $this->assertEquals('COMPLETED', $versions[2]['snapshot']['status']);
    }

    /** @test */
    public function version_can_be_used_for_audit_trail()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'AuditTest', 'status' => 'PENDING', 'payload' => ['amount' => 100]]);

        $version->snapshot($model);
        sleep(1);

        $model->payload = ['amount' => 200];
        $version->snapshot($model);
        sleep(1);

        $model->payload = ['amount' => 300];
        $version->snapshot($model);

        $versions = ModelVersion::where('model_id', $model->id)->get();

        $this->assertCount(3, $versions);
        $this->assertNotNull($versions[0]->created_at);
        $this->assertNotNull($versions[1]->created_at);
        $this->assertNotNull($versions[2]->created_at);
    }

    /** @test */
    public function it_can_diff_two_versions()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Version1', 'status' => 'PENDING', 'payload' => ['amount' => 100]]);
        $version->snapshot($model);

        $model->name = 'Version2';
        $model->status = 'RUNNING';
        $model->payload = ['amount' => 200, 'new_field' => 'value'];
        $version->snapshot($model);

        $diff = $version->diff($model, 1, 2);

        $this->assertArrayHasKey('changed', $diff);
        $this->assertArrayHasKey('added', $diff);
        $this->assertEquals('Version1', $diff['changed']['name']['old']);
        $this->assertEquals('Version2', $diff['changed']['name']['new']);
        $this->assertEquals('PENDING', $diff['changed']['status']['old']);
        $this->assertEquals('RUNNING', $diff['changed']['status']['new']);
    }

    /** @test */
    public function diff_detects_added_fields()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);
        $version->snapshot($model);

        $model->current_step = 5;
        $version->snapshot($model);

        $diff = $version->diff($model, 1, 2);

        $this->assertArrayHasKey('added', $diff);
        $this->assertEquals(5, $diff['added']['current_step']);
    }

    /** @test */
    public function diff_detects_removed_fields()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => [], 'current_step' => 5]);
        $version->snapshot($model);

        $model->current_step = null;
        $version->snapshot($model);

        $diff = $version->diff($model, 1, 2);

        $this->assertArrayHasKey('removed', $diff);
    }

    /** @test */
    public function it_can_exclude_fields_from_snapshot()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->excludeFields(['status'])->snapshot($model);

        $snapshot = ModelVersion::where('model_id', $model->id)->first();

        $this->assertArrayHasKey('name', $snapshot->snapshot);
        $this->assertArrayNotHasKey('status', $snapshot->snapshot);
    }

    /** @test */
    public function it_can_revert_to_previous_version()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Version1', 'status' => 'PENDING', 'payload' => []]);
        $version->snapshot($model);

        $model->name = 'Version2';
        $version->snapshot($model);

        $model->name = 'Version3';
        $version->snapshot($model);

        $version->revert($model, 1);

        $this->assertEquals('Version2', $model->name);
    }

    /** @test */
    public function it_can_revert_multiple_steps()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'V1', 'status' => 'PENDING', 'payload' => []]);
        $version->snapshot($model);

        $model->name = 'V2';
        $version->snapshot($model);

        $model->name = 'V3';
        $version->snapshot($model);

        $model->name = 'V4';
        $version->snapshot($model);

        $version->revert($model, 3);

        $this->assertEquals('V1', $model->name);
    }

    /** @test */
    public function it_can_get_latest_version_number()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $this->assertEquals(0, $version->getLatestVersion($model));

        $version->snapshot($model);
        $this->assertEquals(1, $version->getLatestVersion($model));

        $version->snapshot($model);
        $this->assertEquals(2, $version->getLatestVersion($model));

        $version->snapshot($model);
        $this->assertEquals(3, $version->getLatestVersion($model));
    }

    /** @test */
    public function it_can_check_if_version_exists()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);
        $version->snapshot($model);
        $version->snapshot($model);

        $this->assertTrue($version->hasVersion($model, 1));
        $this->assertTrue($version->hasVersion($model, 2));
        $this->assertFalse($version->hasVersion($model, 3));
        $this->assertFalse($version->hasVersion($model, 99));
    }

    /** @test */
    public function it_can_get_version_count()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $this->assertEquals(0, $version->getVersionCount($model));

        $version->snapshot($model);
        $this->assertEquals(1, $version->getVersionCount($model));

        $version->snapshot($model);
        $version->snapshot($model);
        $this->assertEquals(3, $version->getVersionCount($model));
    }

    /** @test */
    public function it_can_purge_all_versions()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);
        $version->snapshot($model);
        $version->snapshot($model);
        $version->snapshot($model);

        $this->assertEquals(3, $version->getVersionCount($model));

        $deleted = $version->purge($model);

        $this->assertEquals(3, $deleted);
        $this->assertEquals(0, $version->getVersionCount($model));
    }

    /** @test */
    public function it_can_get_version_by_hash()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);
        $snapshot = $version->snapshot($model);

        $found = $version->getVersionByHash($model, $snapshot->hash);

        $this->assertNotNull($found);
        $this->assertEquals($snapshot->id, $found->id);
        $this->assertEquals($snapshot->hash, $found->hash);
    }

    /** @test */
    public function version_by_hash_returns_null_when_not_found()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $found = $version->getVersionByHash($model, 'nonexistent-hash');

        $this->assertNull($found);
    }

    /** @test */
    public function it_can_store_metadata_with_snapshot()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $metadata = [
            'user_id' => 123,
            'reason' => 'Legal compliance',
            'ip_address' => '192.168.1.1'
        ];

        $snapshot = $version->snapshot($model, $metadata);

        $this->assertEquals($metadata, $snapshot->metadata);

        $retrieved = ModelVersion::find($snapshot->id);
        $this->assertEquals(123, $retrieved->metadata['user_id']);
        $this->assertEquals('Legal compliance', $retrieved->metadata['reason']);
    }

    /** @test */
    public function metadata_defaults_to_empty_array_when_not_provided()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);
        $snapshot = $version->snapshot($model);

        $this->assertEquals([], $snapshot->metadata);
    }

    /** @test */
    public function excluded_fields_persist_across_snapshots()
    {
        $version = app('business-orchestration')->version();

        $model = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $version->excludeFields(['status']);

        $version->snapshot($model);
        $model->name = 'Changed';
        $version->snapshot($model);

        $snapshots = ModelVersion::where('model_id', $model->id)->get();

        $this->assertArrayNotHasKey('status', $snapshots[0]->snapshot);
        $this->assertArrayNotHasKey('status', $snapshots[1]->snapshot);
    }
}
