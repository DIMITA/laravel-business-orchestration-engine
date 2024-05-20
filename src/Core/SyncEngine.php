<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\SyncLog;
use Illuminate\Database\Eloquent\Model;

class SyncEngine
{
    public function logChange(Model $model, string $operation, array $changedFields = [])
    {
        $version = SyncLog::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->max('version') ?? 0;

        SyncLog::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'operation' => $operation,
            'changed_fields' => $changedFields,
            'version' => $version + 1,
        ]);
    }

    public function getDeltas(string $modelType, int $modelId, int $lastVersion): array
    {
        return SyncLog::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->where('version', '>', $lastVersion)
            ->orderBy('version')
            ->get()
            ->toArray();
    }
}