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