<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\EventStore;

class EventSourcingEngine
{
    public function storeEvent(string $aggregateId, string $eventType, array $payload)
    {
        $version = EventStore::where('aggregate_id', $aggregateId)->max('version') ?? 0;

        EventStore::create([
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $payload,
            'version' => $version + 1,
        ]);
    }

    public function rebuildAggregate(string $aggregateId, $aggregate)
    {
        $events = EventStore::where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get();

        foreach ($events as $event) {
            $aggregate->apply($event);
        }

        return $aggregate;
    }

    public function getEvents(string $aggregateId): array
    {
        return EventStore::where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get()
            ->toArray();
    }
}