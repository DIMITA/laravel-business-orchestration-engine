<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\ModelVersion;
use Illuminate\Database\Eloquent\Model;

class VersionEngine
{
    protected array $excludedFields = ['created_at', 'updated_at'];
    protected array $includeHiddenFields = [];
    protected bool $autoSnapshot = false;

    /**
     * Create a version snapshot of the model
     */
    public function snapshot(Model $model, ?array $metadata = null)
    {
        $version = ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->max('version') ?? 0;

        $snapshot = $this->prepareSnapshot($model);
        $hash = md5(json_encode($snapshot));

        return ModelVersion::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'version' => $version + 1,
            'snapshot' => $snapshot,
            'hash' => $hash,
            'metadata' => $metadata ?? [],
        ]);
    }

    /**
     * Restore model to a specific version
     */
    public function restore(Model $model, int $version)
    {
        $snapshot = ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('version', $version)
            ->first();

        if ($snapshot) {
            $model->fill($snapshot->snapshot);
            $model->save();
        }

        return $model;
    }

    /**
     * Get all versions for a model
     */
    public function getVersions(Model $model): array
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->orderBy('version')
            ->get()
            ->toArray();
    }

    /**
     * Compare two versions and return differences
     */
    public function diff(Model $model, int $versionA, int $versionB): array
    {
        $snapshotA = ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('version', $versionA)
            ->first();

        $snapshotB = ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('version', $versionB)
            ->first();

        if (!$snapshotA || !$snapshotB) {
            return [];
        }

        return $this->calculateDiff($snapshotA->snapshot, $snapshotB->snapshot);
    }

    /**
     * Get the latest version number for a model
     */
    public function getLatestVersion(Model $model): int
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->max('version') ?? 0;
    }

    /**
     * Revert to previous version
     */
    public function revert(Model $model, int $steps = 1)
    {
        $currentVersion = $this->getLatestVersion($model);
        $targetVersion = max(1, $currentVersion - $steps);

        return $this->restore($model, $targetVersion);
    }

    /**
     * Delete all versions for a model
     */
    public function purge(Model $model): int
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->delete();
    }

    /**
     * Set fields to exclude from versioning
     */
    public function excludeFields(array $fields): self
    {
        $this->excludedFields = array_merge($this->excludedFields, $fields);
        return $this;
    }

    /**
     * Set hidden fields to include in versioning
     */
    public function includeHiddenFields(array $fields): self
    {
        $this->includeHiddenFields = $fields;
        return $this;
    }

    /**
     * Enable automatic snapshots on model updates
     */
    public function enableAutoSnapshot(): self
    {
        $this->autoSnapshot = true;
        return $this;
    }

    /**
     * Get version by hash
     */
    public function getVersionByHash(Model $model, string $hash): ?ModelVersion
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('hash', $hash)
            ->first();
    }

    /**
     * Check if a version exists
     */
    public function hasVersion(Model $model, int $version): bool
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->where('version', $version)
            ->exists();
    }

    /**
     * Get version count for a model
     */
    public function getVersionCount(Model $model): int
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->count();
    }

    /**
     * Prepare snapshot data from model
     */
    protected function prepareSnapshot(Model $model): array
    {
        $attributes = $model->attributesToArray();

        // Include hidden fields if specified
        if (!empty($this->includeHiddenFields)) {
            foreach ($this->includeHiddenFields as $field) {
                if (isset($model->$field)) {
                    $attributes[$field] = $model->$field;
                }
            }
        }

        // Exclude specified fields
        foreach ($this->excludedFields as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }

    /**
     * Calculate differences between two snapshots
     */
    protected function calculateDiff(array $snapshotA, array $snapshotB): array
    {
        $diff = [
            'added' => [],
            'removed' => [],
            'changed' => [],
        ];

        // Find added and changed fields
        foreach ($snapshotB as $key => $value) {
            if (!array_key_exists($key, $snapshotA)) {
                $diff['added'][$key] = $value;
            } elseif ($snapshotA[$key] !== $value) {
                $diff['changed'][$key] = [
                    'old' => $snapshotA[$key],
                    'new' => $value,
                ];
            }
        }

        // Find removed fields
        foreach ($snapshotA as $key => $value) {
            if (!array_key_exists($key, $snapshotB)) {
                $diff['removed'][$key] = $value;
            }
        }

        return $diff;
    }
}