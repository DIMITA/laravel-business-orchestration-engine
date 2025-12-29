<?php

namespace Dimita\BusinessOrchestration\Tests;

use Dimita\BusinessOrchestration\BusinessOrchestrationServiceProvider;
use Dimita\BusinessOrchestration\Models\EventStore;
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

    /** @test */
    public function event_can_be_stored()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('aggregate-123', 'UserCreated', ['name' => 'John', 'email' => 'john@example.com']);

        $this->assertDatabaseHas('event_store', [
            'aggregate_id' => 'aggregate-123',
            'event_type' => 'UserCreated'
        ]);
    }

    /** @test */
    public function event_version_increments_automatically()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-1', 'Event1', ['data' => 'first']);
        $es->storeEvent('agg-1', 'Event2', ['data' => 'second']);
        $es->storeEvent('agg-1', 'Event3', ['data' => 'third']);

        $events = EventStore::where('aggregate_id', 'agg-1')->orderBy('version')->get();

        $this->assertEquals(1, $events[0]->version);
        $this->assertEquals(2, $events[1]->version);
        $this->assertEquals(3, $events[2]->version);
    }

    /** @test */
    public function events_are_retrieved_in_order()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-1', 'Event1', ['order' => 1]);
        $es->storeEvent('agg-1', 'Event2', ['order' => 2]);
        $es->storeEvent('agg-1', 'Event3', ['order' => 3]);

        $events = $es->getEvents('agg-1');

        $this->assertEquals(1, $events[0]['payload']['order']);
        $this->assertEquals(2, $events[1]['payload']['order']);
        $this->assertEquals(3, $events[2]['payload']['order']);
    }

    /** @test */
    public function aggregate_can_be_rebuilt_from_empty_state()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-new', 'Initialized', ['value' => 0]);

        $aggregate = $es->rebuildAggregate('agg-new', function($state, $event) {
            if ($event['event_type'] === 'Initialized') {
                return ['value' => $event['payload']['value']];
            }
            return $state;
        });

        $this->assertEquals(['value' => 0], $aggregate);
    }

    /** @test */
    public function aggregate_rebuilds_with_multiple_events()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('counter-1', 'Initialized', ['count' => 0]);
        $es->storeEvent('counter-1', 'Incremented', ['amount' => 5]);
        $es->storeEvent('counter-1', 'Incremented', ['amount' => 3]);
        $es->storeEvent('counter-1', 'Decremented', ['amount' => 2]);

        $aggregate = $es->rebuildAggregate('counter-1', function($state, $event) {
            if ($event['event_type'] === 'Initialized') {
                return ['count' => $event['payload']['count']];
            } elseif ($event['event_type'] === 'Incremented') {
                $state['count'] += $event['payload']['amount'];
                return $state;
            } elseif ($event['event_type'] === 'Decremented') {
                $state['count'] -= $event['payload']['amount'];
                return $state;
            }
            return $state;
        });

        $this->assertEquals(['count' => 6], $aggregate);
    }

    /** @test */
    public function events_are_isolated_by_aggregate_id()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-1', 'Event', ['value' => 'A']);
        $es->storeEvent('agg-2', 'Event', ['value' => 'B']);
        $es->storeEvent('agg-1', 'Event', ['value' => 'C']);

        $events1 = $es->getEvents('agg-1');
        $events2 = $es->getEvents('agg-2');

        $this->assertCount(2, $events1);
        $this->assertCount(1, $events2);
    }

    /** @test */
    public function get_events_returns_empty_array_for_nonexistent_aggregate()
    {
        $es = app('business-orchestration')->eventSourcing();

        $events = $es->getEvents('nonexistent-aggregate');

        $this->assertIsArray($events);
        $this->assertCount(0, $events);
    }

    /** @test */
    public function event_stores_complex_payload()
    {
        $es = app('business-orchestration')->eventSourcing();

        $payload = [
            'user_id' => 123,
            'order' => [
                'id' => 456,
                'items' => [
                    ['sku' => 'ABC123', 'qty' => 2],
                    ['sku' => 'DEF456', 'qty' => 1]
                ],
                'total' => 99.99
            ],
            'metadata' => ['ip' => '127.0.0.1', 'user_agent' => 'Test']
        ];

        $es->storeEvent('complex-agg', 'ComplexEvent', $payload);

        $events = $es->getEvents('complex-agg');

        $this->assertEquals($payload, $events[0]['payload']);
    }

    /** @test */
    public function rebuild_aggregate_handles_null_initial_state()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-null', 'Created', ['value' => 'test']);

        $aggregate = $es->rebuildAggregate('agg-null', function($state, $event) {
            $this->assertNull($state);
            return ['value' => $event['payload']['value']];
        });

        $this->assertEquals(['value' => 'test'], $aggregate);
    }

    /** @test */
    public function events_maintain_order_across_multiple_aggregates()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-1', 'Event1', ['data' => 'A1']);
        $es->storeEvent('agg-2', 'Event1', ['data' => 'B1']);
        $es->storeEvent('agg-1', 'Event2', ['data' => 'A2']);
        $es->storeEvent('agg-2', 'Event2', ['data' => 'B2']);

        $events1 = $es->getEvents('agg-1');
        $events2 = $es->getEvents('agg-2');

        $this->assertEquals('A1', $events1[0]['payload']['data']);
        $this->assertEquals('A2', $events1[1]['payload']['data']);
        $this->assertEquals('B1', $events2[0]['payload']['data']);
        $this->assertEquals('B2', $events2[1]['payload']['data']);
    }

    /** @test */
    public function event_persists_in_database()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('persist-test', 'TestEvent', ['key' => 'value']);

        $event = EventStore::where('aggregate_id', 'persist-test')->first();

        $this->assertInstanceOf(EventStore::class, $event);
        $this->assertEquals('persist-test', $event->aggregate_id);
        $this->assertEquals('TestEvent', $event->event_type);
        $this->assertEquals(['key' => 'value'], $event->payload);
    }

    /** @test */
    public function rebuild_handles_aggregate_with_no_events()
    {
        $es = app('business-orchestration')->eventSourcing();

        $aggregate = $es->rebuildAggregate('no-events', function($state, $event) {
            return $state;
        });

        $this->assertNull($aggregate);
    }

    /** @test */
    public function events_can_represent_business_workflow()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('order-123', 'OrderCreated', ['customer_id' => 1, 'total' => 100]);
        $es->storeEvent('order-123', 'PaymentReceived', ['amount' => 100, 'method' => 'card']);
        $es->storeEvent('order-123', 'OrderShipped', ['tracking' => 'ABC123']);
        $es->storeEvent('order-123', 'OrderDelivered', ['delivered_at' => '2024-01-01']);

        $order = $es->rebuildAggregate('order-123', function($state, $event) {
            if ($event['event_type'] === 'OrderCreated') {
                return [
                    'customer_id' => $event['payload']['customer_id'],
                    'total' => $event['payload']['total'],
                    'status' => 'created'
                ];
            } elseif ($event['event_type'] === 'PaymentReceived') {
                $state['status'] = 'paid';
                return $state;
            } elseif ($event['event_type'] === 'OrderShipped') {
                $state['status'] = 'shipped';
                $state['tracking'] = $event['payload']['tracking'];
                return $state;
            } elseif ($event['event_type'] === 'OrderDelivered') {
                $state['status'] = 'delivered';
                return $state;
            }
            return $state;
        });

        $this->assertEquals('delivered', $order['status']);
        $this->assertEquals('ABC123', $order['tracking']);
    }

    /** @test */
    public function version_numbers_restart_for_each_aggregate()
    {
        $es = app('business-orchestration')->eventSourcing();

        $es->storeEvent('agg-1', 'Event', ['data' => 1]);
        $es->storeEvent('agg-2', 'Event', ['data' => 2]);
        $es->storeEvent('agg-1', 'Event', ['data' => 3]);
        $es->storeEvent('agg-2', 'Event', ['data' => 4]);

        $events1 = EventStore::where('aggregate_id', 'agg-1')->get();
        $events2 = EventStore::where('aggregate_id', 'agg-2')->get();

        $this->assertEquals(1, $events1[0]->version);
        $this->assertEquals(2, $events1[1]->version);
        $this->assertEquals(1, $events2[0]->version);
        $this->assertEquals(2, $events2[1]->version);
    }
}
