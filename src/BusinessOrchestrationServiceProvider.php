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

        // Register each engine as independent singleton
        $this->app->singleton(SagaEngine::class, function ($app) {
            return new SagaEngine();
        });

        $this->app->singleton(WorkflowEngine::class, function ($app) {
            return new WorkflowEngine();
        });

        $this->app->singleton(SyncEngine::class, function ($app) {
            return new SyncEngine();
        });

        $this->app->singleton(VersionEngine::class, function ($app) {
            return new VersionEngine();
        });

        $this->app->singleton(EventSourcingEngine::class, function ($app) {
            return new EventSourcingEngine();
        });

        $this->app->singleton(RuleEngine::class, function ($app) {
            return new RuleEngine();
        });

        $this->app->singleton(DependencyEngine::class, function ($app) {
            return new DependencyEngine();
        });

        // Register aliases for convenience
        $this->app->alias(SagaEngine::class, 'saga-engine');
        $this->app->alias(WorkflowEngine::class, 'workflow-engine');
        $this->app->alias(SyncEngine::class, 'sync-engine');
        $this->app->alias(VersionEngine::class, 'version-engine');
        $this->app->alias(EventSourcingEngine::class, 'event-sourcing-engine');
        $this->app->alias(RuleEngine::class, 'rule-engine');
        $this->app->alias(DependencyEngine::class, 'dependency-engine');

        // Register the main facade-style accessor
        $this->app->singleton('business-orchestration', function ($app) {
            return new class {
                public function saga(): SagaEngine
                {
                    return app(SagaEngine::class);
                }

                public function workflow(): WorkflowEngine
                {
                    return app(WorkflowEngine::class);
                }

                public function sync(): SyncEngine
                {
                    return app(SyncEngine::class);
                }

                public function version(): VersionEngine
                {
                    return app(VersionEngine::class);
                }

                public function eventSourcing(): EventSourcingEngine
                {
                    return app(EventSourcingEngine::class);
                }

                public function rule(): RuleEngine
                {
                    return app(RuleEngine::class);
                }

                public function dependency(): DependencyEngine
                {
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