<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Core\SagaEngine;
use Dimita\BusinessOrchestration\Core\WorkflowEngine;
use Dimita\BusinessOrchestration\Core\SyncEngine;
use Dimita\BusinessOrchestration\Core\VersionEngine;
use Dimita\BusinessOrchestration\Core\EventSourcingEngine;
use Dimita\BusinessOrchestration\Core\RuleEngine;
use Dimita\BusinessOrchestration\Core\DependencyEngine;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function engines_can_be_resolved_independently_by_class_name()
    {
        $saga = app(SagaEngine::class);
        $workflow = app(WorkflowEngine::class);
        $sync = app(SyncEngine::class);
        $version = app(VersionEngine::class);
        $eventSourcing = app(EventSourcingEngine::class);
        $rule = app(RuleEngine::class);
        $dependency = app(DependencyEngine::class);

        $this->assertInstanceOf(SagaEngine::class, $saga);
        $this->assertInstanceOf(WorkflowEngine::class, $workflow);
        $this->assertInstanceOf(SyncEngine::class, $sync);
        $this->assertInstanceOf(VersionEngine::class, $version);
        $this->assertInstanceOf(EventSourcingEngine::class, $eventSourcing);
        $this->assertInstanceOf(RuleEngine::class, $rule);
        $this->assertInstanceOf(DependencyEngine::class, $dependency);
    }

    /** @test */
    public function engines_can_be_resolved_by_alias()
    {
        $saga = app('saga-engine');
        $workflow = app('workflow-engine');
        $sync = app('sync-engine');
        $version = app('version-engine');
        $eventSourcing = app('event-sourcing-engine');
        $rule = app('rule-engine');
        $dependency = app('dependency-engine');

        $this->assertInstanceOf(SagaEngine::class, $saga);
        $this->assertInstanceOf(WorkflowEngine::class, $workflow);
        $this->assertInstanceOf(SyncEngine::class, $sync);
        $this->assertInstanceOf(VersionEngine::class, $version);
        $this->assertInstanceOf(EventSourcingEngine::class, $eventSourcing);
        $this->assertInstanceOf(RuleEngine::class, $rule);
        $this->assertInstanceOf(DependencyEngine::class, $dependency);
    }

    /** @test */
    public function engines_are_singletons()
    {
        $saga1 = app(SagaEngine::class);
        $saga2 = app(SagaEngine::class);

        $this->assertSame($saga1, $saga2);

        $rule1 = app(RuleEngine::class);
        $rule2 = app(RuleEngine::class);

        $this->assertSame($rule1, $rule2);
    }

    /** @test */
    public function main_orchestration_facade_works()
    {
        $orchestration = app('business-orchestration');

        $saga = $orchestration->saga();
        $workflow = $orchestration->workflow();
        $sync = $orchestration->sync();
        $version = $orchestration->version();
        $eventSourcing = $orchestration->eventSourcing();
        $rule = $orchestration->rule();
        $dependency = $orchestration->dependency();

        $this->assertInstanceOf(SagaEngine::class, $saga);
        $this->assertInstanceOf(WorkflowEngine::class, $workflow);
        $this->assertInstanceOf(SyncEngine::class, $sync);
        $this->assertInstanceOf(VersionEngine::class, $version);
        $this->assertInstanceOf(EventSourcingEngine::class, $eventSourcing);
        $this->assertInstanceOf(RuleEngine::class, $rule);
        $this->assertInstanceOf(DependencyEngine::class, $dependency);
    }

    /** @test */
    public function engines_from_facade_match_direct_resolution()
    {
        $orchestration = app('business-orchestration');

        $sagaFromFacade = $orchestration->saga();
        $sagaDirect = app(SagaEngine::class);

        $this->assertSame($sagaFromFacade, $sagaDirect);

        $ruleFromFacade = $orchestration->rule();
        $ruleDirect = app('rule-engine');

        $this->assertSame($ruleFromFacade, $ruleDirect);
    }

    /** @test */
    public function developer_can_inject_engines_via_constructor()
    {
        $controller = new class(app(SagaEngine::class), app(RuleEngine::class)) {
            public $saga;
            public $rule;

            public function __construct(SagaEngine $saga, RuleEngine $rule)
            {
                $this->saga = $saga;
                $this->rule = $rule;
            }
        };

        $this->assertInstanceOf(SagaEngine::class, $controller->saga);
        $this->assertInstanceOf(RuleEngine::class, $controller->rule);
    }

    /** @test */
    public function developer_can_use_only_specific_engines()
    {
        // Use case: Developer only needs VersionEngine and RuleEngine
        $version = app(VersionEngine::class);
        $rule = app(RuleEngine::class);

        $this->assertInstanceOf(VersionEngine::class, $version);
        $this->assertInstanceOf(RuleEngine::class, $rule);

        // They don't need to use the full orchestration
        // This demonstrates independent usage
    }
}
