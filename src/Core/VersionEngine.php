<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\ModelVersion;
use Illuminate\Database\Eloquent\Model;

class VersionEngine
{
    public function snapshot(Model $model)
    {
        $version = ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->max('version') ?? 0;

        $snapshot = $model->toArray();
        $hash = md5(json_encode($snapshot));

        ModelVersion::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'version' => $version + 1,
            'snapshot' => $snapshot,
            'hash' => $hash,
        ]);
    }

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
    }

    public function getVersions(Model $model): array
    {
        return ModelVersion::where('model_type', get_class($model))
            ->where('model_id', $model->id)
            ->orderBy('version')
            ->get()
            ->toArray();
    }
}