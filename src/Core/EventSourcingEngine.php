<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\EventStore;
use Illuminate\Support\Facades\Event;

class EventSourcingEngine
{
    protected array $projectors = [];
    protected array $reactors = [];

    public function storeEvent(string $aggregateId, string $eventType, array $payload, ?array $metadata = null)
    {
        $version = EventStore::where('aggregate_id', $aggregateId)->max('version') ?? 0;

        $storedEvent = EventStore::create([
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'version' => $version + 1,
            'metadata' => $metadata ?? [],
        ]);

        // Dispatch to projectors
        $this->dispatchToProjectors($storedEvent);

        // Dispatch to reactors
        $this->dispatchToReactors($storedEvent);

        // Fire Laravel event
        Event::dispatch('event-sourcing.stored', [$storedEvent]);

        return $storedEvent;
    }

    public function rebuildAggregate(string $aggregateId, callable $reducer, $initialState = null)
    {
        $events = EventStore::where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get();

        $state = $initialState;
        foreach ($events as $event) {
            $state = $reducer($state, $event);
        }

        return $state;
    }

    public function getEvents(string $aggregateId, ?int $fromVersion = null): array
    {
        $query = EventStore::where('aggregate_id', $aggregateId)
            ->orderBy('version');

        if ($fromVersion !== null) {
            $query->where('version', '>', $fromVersion);
        }

        return $query->get()->toArray();
    }

    /**
     * Register a projector class
     */
    public function addProjector(string $projectorClass): void
    {
        if (!in_array($projectorClass, $this->projectors)) {
            $this->projectors[] = $projectorClass;
        }
    }

    /**
     * Register a reactor class
     */
    public function addReactor(string $reactorClass): void
    {
        if (!in_array($reactorClass, $this->reactors)) {
            $this->reactors[] = $reactorClass;
        }
    }

    /**
     * Dispatch event to all registered projectors
     */
    protected function dispatchToProjectors(EventStore $event): void
    {
        foreach ($this->projectors as $projectorClass) {
            if (class_exists($projectorClass)) {
                $projector = new $projectorClass();

                // Call method based on event type (e.g., onMoneyAdded)
                $methodName = 'on' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', class_basename($event->event_type))));

                if (method_exists($projector, $methodName)) {
                    $projector->$methodName($event);
                }
            }
        }
    }

    /**
     * Dispatch event to all registered reactors
     */
    protected function dispatchToReactors(EventStore $event): void
    {
        foreach ($this->reactors as $reactorClass) {
            if (class_exists($reactorClass)) {
                $reactor = new $reactorClass();

                // Call method based on event type
                $methodName = 'on' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', class_basename($event->event_type))));

                if (method_exists($reactor, $methodName)) {
                    // Reactors typically handle side effects asynchronously
                    dispatch(function() use ($reactor, $methodName, $event) {
                        $reactor->$methodName($event);
                    })->afterResponse();
                }
            }
        }
    }

    /**
     * Replay all events through projectors
     */
    public function replay(?string $aggregateId = null, ?array $projectors = null): int
    {
        $query = EventStore::orderBy('version');

        if ($aggregateId) {
            $query->where('aggregate_id', $aggregateId);
        }

        $events = $query->get();
        $originalProjectors = $this->projectors;

        // If specific projectors provided, use only those
        if ($projectors) {
            $this->projectors = $projectors;
        }

        $count = 0;
        foreach ($events as $event) {
            $this->dispatchToProjectors($event);
            $count++;
        }

        // Restore original projectors
        $this->projectors = $originalProjectors;

        return $count;
    }

    /**
     * Get events by type
     */
    public function getEventsByType(string $eventType, ?int $limit = null): array
    {
        $query = EventStore::where('event_type', $eventType)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->toArray();
    }

    /**
     * Get latest version for an aggregate
     */
    public function getLatestVersion(string $aggregateId): int
    {
        return EventStore::where('aggregate_id', $aggregateId)->max('version') ?? 0;
    }

    /**
     * Create a snapshot of an aggregate state
     */
    public function snapshot(string $aggregateId, $state): void
    {
        // This could be enhanced with a snapshots table for performance
        // For now, we store it as a special event
        $this->storeEvent(
            $aggregateId,
            '_snapshot',
            ['state' => $state],
            ['is_snapshot' => true, 'version' => $this->getLatestVersion($aggregateId)]
        );
    }

    /**
     * Get the latest snapshot for performance optimization
     */
    public function getLatestSnapshot(string $aggregateId)
    {
        $snapshot = EventStore::where('aggregate_id', $aggregateId)
            ->where('event_type', '_snapshot')
            ->orderBy('version', 'desc')
            ->first();

        return $snapshot ? $snapshot->payload['state'] ?? null : null;
    }
}