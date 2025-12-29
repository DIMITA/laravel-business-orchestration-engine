<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\Saga;
use Dimita\BusinessOrchestration\Models\Dependency;
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

    /** @test */
    public function dependency_can_be_added()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('ModelA', 'ModelB', 'depends_on');

        $this->assertDatabaseHas('dependencies', [
            'source_model' => 'ModelA',
            'target_model' => 'ModelB',
            'rule' => 'depends_on'
        ]);
    }

    /** @test */
    public function dependency_stores_source_and_target_correctly()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('Order', 'Customer', 'belongs_to');

        $dependency = Dependency::where('source_model', 'Order')->first();

        $this->assertEquals('Order', $dependency->source_model);
        $this->assertEquals('Customer', $dependency->target_model);
        $this->assertEquals('belongs_to', $dependency->rule);
    }

    /** @test */
    public function can_add_multiple_dependencies()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('Order', 'Customer', 'belongs_to');
        $dep->addDependency('Order', 'Product', 'has_many');
        $dep->addDependency('Product', 'Category', 'belongs_to');

        $this->assertCount(3, Dependency::all());
    }

    /** @test */
    public function dependency_prevents_deletion_when_instances_exist()
    {
        $dep = app('business-orchestration')->dependency();

        $saga = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $dep->addDependency(Saga::class, Saga::class, 'rule');

        $canDelete = $dep->checkDeletion(Saga::class, $saga->id);

        $this->assertFalse($canDelete);
    }

    /** @test */
    public function dependency_allows_deletion_when_no_instances_exist()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency(Saga::class, Saga::class, 'rule');

        $canDelete = $dep->checkDeletion(Saga::class, 999);

        $this->assertTrue($canDelete);
    }

    /** @test */
    public function can_get_dependencies_for_model()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('Order', 'Customer', 'belongs_to');
        $dep->addDependency('Order', 'Product', 'has_many');
        $dep->addDependency('Invoice', 'Customer', 'belongs_to');

        $dependencies = $dep->getDependencies('Order');

        $this->assertCount(2, $dependencies);
    }

    /** @test */
    public function get_dependencies_returns_both_source_and_target()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('Order', 'Customer', 'belongs_to');
        $dep->addDependency('Product', 'Order', 'belongs_to');

        $dependencies = $dep->getDependencies('Order');

        $this->assertCount(2, $dependencies);
    }

    /** @test */
    public function get_dependencies_returns_empty_array_when_none_exist()
    {
        $dep = app('business-orchestration')->dependency();

        $dependencies = $dep->getDependencies('NonExistentModel');

        $this->assertIsArray($dependencies);
        $this->assertCount(0, $dependencies);
    }

    /** @test */
    public function dependency_can_have_custom_rules()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('Model1', 'Model2', 'cascade_delete');
        $dep->addDependency('Model3', 'Model4', 'soft_delete');
        $dep->addDependency('Model5', 'Model6', 'prevent_delete');

        $this->assertDatabaseHas('dependencies', ['rule' => 'cascade_delete']);
        $this->assertDatabaseHas('dependencies', ['rule' => 'soft_delete']);
        $this->assertDatabaseHas('dependencies', ['rule' => 'prevent_delete']);
    }

    /** @test */
    public function dependency_check_works_with_real_model_instances()
    {
        $dep = app('business-orchestration')->dependency();

        $saga1 = Saga::create(['name' => 'Saga1', 'status' => 'PENDING', 'payload' => []]);
        $saga2 = Saga::create(['name' => 'Saga2', 'status' => 'PENDING', 'payload' => []]);

        $dep->addDependency(Saga::class, Saga::class, 'relationship');

        $this->assertFalse($dep->checkDeletion(Saga::class, $saga1->id));
        $this->assertFalse($dep->checkDeletion(Saga::class, $saga2->id));
    }

    /** @test */
    public function dependency_graph_can_be_complex()
    {
        $dep = app('business-orchestration')->dependency();

        // Create a dependency graph: A -> B -> C -> D
        $dep->addDependency('ModelA', 'ModelB', 'depends_on');
        $dep->addDependency('ModelB', 'ModelC', 'depends_on');
        $dep->addDependency('ModelC', 'ModelD', 'depends_on');

        $this->assertCount(3, Dependency::all());

        $depsA = $dep->getDependencies('ModelA');
        $depsB = $dep->getDependencies('ModelB');
        $depsC = $dep->getDependencies('ModelC');

        $this->assertCount(1, $depsA);
        $this->assertCount(2, $depsB);
        $this->assertCount(2, $depsC);
    }

    /** @test */
    public function dependency_supports_bidirectional_relationships()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('User', 'Profile', 'has_one');
        $dep->addDependency('Profile', 'User', 'belongs_to');

        $userDeps = $dep->getDependencies('User');
        $profileDeps = $dep->getDependencies('Profile');

        $this->assertCount(2, $userDeps);
        $this->assertCount(2, $profileDeps);
    }

    /** @test */
    public function dependency_persists_correctly_in_database()
    {
        $dep = app('business-orchestration')->dependency();

        $dep->addDependency('TestSource', 'TestTarget', 'test_rule');

        $dependency = Dependency::where('source_model', 'TestSource')->first();

        $this->assertInstanceOf(Dependency::class, $dependency);
        $this->assertEquals('TestSource', $dependency->source_model);
        $this->assertEquals('TestTarget', $dependency->target_model);
        $this->assertEquals('test_rule', $dependency->rule);
    }

    /** @test */
    public function can_check_deletion_for_non_existent_model()
    {
        $dep = app('business-orchestration')->dependency();

        $canDelete = $dep->checkDeletion('NonExistentModel', 1);

        $this->assertTrue($canDelete);
    }

    /** @test */
    public function dependency_handles_multiple_dependencies_for_same_model()
    {
        $dep = app('business-orchestration')->dependency();

        $saga = Saga::create(['name' => 'Test', 'status' => 'PENDING', 'payload' => []]);

        $dep->addDependency(Saga::class, 'ModelA', 'rule1');
        $dep->addDependency(Saga::class, 'ModelB', 'rule2');
        $dep->addDependency(Saga::class, 'ModelC', 'rule3');

        $dependencies = $dep->getDependencies(Saga::class);

        $this->assertCount(3, $dependencies);
    }
}
