<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\SyncLog;
use Illuminate\Database\Eloquent\Model;

class SyncEngine
{
    protected string $conflictStrategy = 'latest_wins';
    protected array $syncStrategies = [];
    protected array $conflictHandlers = [];

    /**
     * Log a change to a model
     */
    public function logChange(Model $model, string $operation, array $changedFields = [], ?array $metadata = null)
    {
        $version = SyncLog::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->max('version') ?? 0;

        return SyncLog::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'operation' => $operation,
            'changed_fields' => $changedFields,
            'version' => $version + 1,
            'metadata' => $metadata ?? [],
        ]);
    }

    /**
     * Get changes since a specific version
     */
    public function getDeltas(string $modelType, int $modelId, int $lastVersion): array
    {
        return SyncLog::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->where('version', '>', $lastVersion)
            ->orderBy('version')
            ->get()
            ->toArray();
    }

    /**
     * Synchronize changes between two models
     */
    public function sync(Model $source, Model $target, ?array $fields = null): array
    {
        $sourceData = $fields ? $source->only($fields) : $source->toArray();
        $targetData = $fields ? $target->only($fields) : $target->toArray();

        $conflicts = $this->detectConflicts($sourceData, $targetData);

        if (!empty($conflicts)) {
            $resolved = $this->resolveConflicts($conflicts, $source, $target);
            $target->fill($resolved);
        } else {
            $target->fill($sourceData);
        }

        $target->save();

        $this->logChange($target, 'synced', array_keys($sourceData), [
            'source_id' => $source->id,
            'source_type' => get_class($source),
            'conflicts' => count($conflicts),
        ]);

        return [
            'conflicts' => $conflicts,
            'synced_fields' => array_keys($sourceData),
        ];
    }

    /**
     * Apply deltas to a model
     */
    public function applyDeltas(Model $model, array $deltas): Model
    {
        foreach ($deltas as $delta) {
            if ($delta['operation'] === 'update') {
                $model->fill($delta['changed_fields']);
            } elseif ($delta['operation'] === 'delete') {
                $model->delete();
                return $model;
            }
        }

        $model->save();
        return $model;
    }

    /**
     * Detect conflicts between two sets of data
     */
    public function detectConflicts(array $sourceData, array $targetData): array
    {
        $conflicts = [];

        foreach ($sourceData as $key => $sourceValue) {
            if (isset($targetData[$key]) && $targetData[$key] !== $sourceValue) {
                $conflicts[$key] = [
                    'source' => $sourceValue,
                    'target' => $targetData[$key],
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Resolve conflicts using configured strategy
     */
    public function resolveConflicts(array $conflicts, Model $source, Model $target): array
    {
        $resolved = [];

        foreach ($conflicts as $field => $values) {
            // Check for custom handler for this field
            if (isset($this->conflictHandlers[$field])) {
                $resolved[$field] = $this->conflictHandlers[$field]($values, $source, $target);
                continue;
            }

            // Use default strategy
            $resolved[$field] = $this->applyStrategy($field, $values, $source, $target);
        }

        return $resolved;
    }

    /**
     * Apply conflict resolution strategy
     */
    protected function applyStrategy(string $field, array $values, Model $source, Model $target)
    {
        return match ($this->conflictStrategy) {
            'latest_wins' => $values['source'],
            'source_wins' => $values['source'],
            'target_wins' => $values['target'],
            'merge' => $this->mergeValues($values['source'], $values['target']),
            default => $values['source'],
        };
    }

    /**
     * Merge two values (for arrays and strings)
     */
    protected function mergeValues($source, $target)
    {
        if (is_array($source) && is_array($target)) {
            return array_merge($target, $source);
        }

        if (is_string($source) && is_string($target)) {
            return $target . ' ' . $source;
        }

        return $source;
    }

    /**
     * Set conflict resolution strategy
     */
    public function setConflictStrategy(string $strategy): self
    {
        $this->conflictStrategy = $strategy;
        return $this;
    }

    /**
     * Register a custom conflict handler for a specific field
     */
    public function registerConflictHandler(string $field, callable $handler): self
    {
        $this->conflictHandlers[$field] = $handler;
        return $this;
    }

    /**
     * Get sync status for a model
     */
    public function getSyncStatus(Model $model): array
    {
        $logs = SyncLog::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->orderBy('version', 'desc')
            ->get();

        return [
            'total_syncs' => $logs->count(),
            'last_sync' => $logs->first()?->created_at,
            'current_version' => $logs->max('version') ?? 0,
            'operations' => $logs->groupBy('operation')->map->count(),
        ];
    }

    /**
     * Batch synchronize multiple models
     */
    public function batchSync(array $pairs, ?array $fields = null): array
    {
        $results = [];

        foreach ($pairs as $index => $pair) {
            if (!isset($pair['source'], $pair['target'])) {
                $results[$index] = ['error' => 'Invalid pair format'];
                continue;
            }

            try {
                $results[$index] = $this->sync($pair['source'], $pair['target'], $fields);
            } catch (\Throwable $e) {
                $results[$index] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Create a sync checkpoint
     */
    public function checkpoint(Model $model, string $name): SyncLog
    {
        return $this->logChange($model, 'checkpoint', [], [
            'checkpoint_name' => $name,
            'snapshot' => $model->toArray(),
        ]);
    }

    /**
     * Restore from checkpoint
     */
    public function restoreCheckpoint(Model $model, string $checkpointName): ?Model
    {
        $checkpoint = SyncLog::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('operation', 'checkpoint')
            ->whereJsonContains('metadata->checkpoint_name', $checkpointName)
            ->orderBy('version', 'desc')
            ->first();

        if (!$checkpoint || !isset($checkpoint->metadata['snapshot'])) {
            return null;
        }

        $model->fill($checkpoint->metadata['snapshot']);
        $model->save();

        return $model;
    }

    /**
     * Compare two models and return differences
     */
    public function compare(Model $modelA, Model $modelB, ?array $fields = null): array
    {
        $dataA = $fields ? $modelA->only($fields) : $modelA->toArray();
        $dataB = $fields ? $modelB->only($fields) : $modelB->toArray();

        return $this->detectConflicts($dataA, $dataB);
    }

    /**
     * Get all checkpoints for a model
     */
    public function getCheckpoints(Model $model): array
    {
        return SyncLog::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('operation', 'checkpoint')
            ->orderBy('version', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'version' => $log->version,
                    'name' => $log->metadata['checkpoint_name'] ?? 'unnamed',
                    'created_at' => $log->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Purge old sync logs
     */
    public function purgeOldLogs(string $modelType, int $keepLastN = 100): int
    {
        $logs = SyncLog::where('model_type', $modelType)
            ->orderBy('version', 'desc')
            ->skip($keepLastN)
            ->pluck('id');

        return SyncLog::whereIn('id', $logs)->delete();
    }
}