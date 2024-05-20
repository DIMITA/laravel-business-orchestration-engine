<?php

namespace Dimita\BusinessOrchestration\Core;

use Dimita\BusinessOrchestration\Models\Dependency;

class DependencyEngine
{
    public function addDependency(string $sourceModel, string $targetModel, string $rule)
    {
        Dependency::create([
            'source_model' => $sourceModel,
            'target_model' => $targetModel,
            'rule' => $rule,
        ]);
    }

    public function checkDeletion(string $modelType, int $modelId): bool
    {
        $dependencies = Dependency::where('target_model', $modelType)->get();

        foreach ($dependencies as $dep) {
            // Check if there are instances
            $count = $dep->source_model::where('id', $modelId)->count();
            if ($count > 0) {
                return false; // Cannot delete
            }
        }

        return true;
    }

    public function getDependencies(string $modelType): array
    {
        return Dependency::where('source_model', $modelType)
            ->orWhere('target_model', $modelType)
            ->get()
            ->toArray();
    }
}