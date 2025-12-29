<?php

namespace Dimita\BusinessOrchestration;

use Dimita\BusinessOrchestration\Core\SagaEngine;
use Dimita\BusinessOrchestration\Core\WorkflowEngine;
use Dimita\BusinessOrchestration\Core\SyncEngine;
use Dimita\BusinessOrchestration\Core\VersionEngine;
use Dimita\BusinessOrchestration\Core\EventSourcingEngine;
use Dimita\BusinessOrchestration\Core\RuleEngine;
use Dimita\BusinessOrchestration\Core\DependencyEngine;
use Illuminate\Support\ServiceProvider;

class BusinessOrchestrationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/business-orchestration.php', 'business-orchestration');

        // Register each engine as independent singleton only if enabled
        if (config('business-orchestration.engines.saga', true)) {
            $this->app->singleton(SagaEngine::class, function ($app) {
                return new SagaEngine();
            });
            $this->app->alias(SagaEngine::class, 'saga-engine');
        }

        if (config('business-orchestration.engines.workflow', true)) {
            $this->app->singleton(WorkflowEngine::class, function ($app) {
                return new WorkflowEngine();
            });
            $this->app->alias(WorkflowEngine::class, 'workflow-engine');
        }

        if (config('business-orchestration.engines.sync', true)) {
            $this->app->singleton(SyncEngine::class, function ($app) {
                return new SyncEngine();
            });
            $this->app->alias(SyncEngine::class, 'sync-engine');
        }

        if (config('business-orchestration.engines.version', true)) {
            $this->app->singleton(VersionEngine::class, function ($app) {
                return new VersionEngine();
            });
            $this->app->alias(VersionEngine::class, 'version-engine');
        }

        if (config('business-orchestration.engines.event_sourcing', true)) {
            $this->app->singleton(EventSourcingEngine::class, function ($app) {
                return new EventSourcingEngine();
            });
            $this->app->alias(EventSourcingEngine::class, 'event-sourcing-engine');
        }

        if (config('business-orchestration.engines.rule', true)) {
            $this->app->singleton(RuleEngine::class, function ($app) {
                return new RuleEngine();
            });
            $this->app->alias(RuleEngine::class, 'rule-engine');
        }

        if (config('business-orchestration.engines.dependency', true)) {
            $this->app->singleton(DependencyEngine::class, function ($app) {
                return new DependencyEngine();
            });
            $this->app->alias(DependencyEngine::class, 'dependency-engine');
        }

        // Register the main facade-style accessor
        $this->app->singleton('business-orchestration', function ($app) {
            return new class {
                public function saga(): SagaEngine
                {
                    if (!app()->bound(SagaEngine::class)) {
                        throw new \RuntimeException('SagaEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(SagaEngine::class);
                }

                public function workflow(): WorkflowEngine
                {
                    if (!app()->bound(WorkflowEngine::class)) {
                        throw new \RuntimeException('WorkflowEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(WorkflowEngine::class);
                }

                public function sync(): SyncEngine
                {
                    if (!app()->bound(SyncEngine::class)) {
                        throw new \RuntimeException('SyncEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(SyncEngine::class);
                }

                public function version(): VersionEngine
                {
                    if (!app()->bound(VersionEngine::class)) {
                        throw new \RuntimeException('VersionEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(VersionEngine::class);
                }

                public function eventSourcing(): EventSourcingEngine
                {
                    if (!app()->bound(EventSourcingEngine::class)) {
                        throw new \RuntimeException('EventSourcingEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(EventSourcingEngine::class);
                }

                public function rule(): RuleEngine
                {
                    if (!app()->bound(RuleEngine::class)) {
                        throw new \RuntimeException('RuleEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(RuleEngine::class);
                }

                public function dependency(): DependencyEngine
                {
                    if (!app()->bound(DependencyEngine::class)) {
                        throw new \RuntimeException('DependencyEngine is not enabled. Enable it in config/business-orchestration.php');
                    }
                    return app(DependencyEngine::class);
                }
            };
        });
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/business-orchestration.php' => config_path('business-orchestration.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }
}