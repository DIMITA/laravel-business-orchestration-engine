<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Orchestra\Testbench\TestCase;

class EventSourcingTest extends TestCase
{
    use DatabaseMigrations;

    protected function getPackageProviders($app)
    {
        return [BusinessOrchestrationServiceProvider::class];
    }

    /** @test */
    public function it_can_access_event_sourcing_engine()
    {
        $es = app('business-orchestration')->eventSourcing();
        $this->assertInstanceOf(\Dimita\BusinessOrchestration\Core\EventSourcingEngine::class, $es);
    }

    /** @test */
    public function it_can_store_and_rebuild_events()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-1', 'Created', ['data' => 'test']);
        $es->storeEvent('agg-1', 'Updated', ['data' => 'updated']);

        $events = $es->getEvents('agg-1');
        $this->assertCount(2, $events);

        $aggregate = $es->rebuildAggregate('agg-1', function($state, $event) {
            if ($event['event_type'] === 'Created') {
                return ['data' => $event['payload']['data']];
            } elseif ($event['event_type'] === 'Updated') {
                $state['data'] = $event['payload']['data'];
                return $state;
            }
            return $state;
        });

        $this->assertEquals(['data' => 'updated'], $aggregate);
    }
}