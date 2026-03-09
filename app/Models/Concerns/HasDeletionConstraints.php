<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasDeletionConstraints
{
    /**
     * Return an array of relationships that should be checked before deletion.
     *
     * Each entry maps a relationship method name to a human-readable label (German).
     * Example:
     *   return [
     *       'units'      => 'Einheiten',
     *       'attributes' => 'Attribute',
     *   ];
     *
     * @return array<string, string>
     */
    abstract public function deletionConstraints(): array;

    /**
     * Check all declared constraints and return dependency counts.
     *
     * @return array{has_dependencies: bool, total_count: int, dependencies: array<string, array{label: string, count: int}>}
     */
    public function checkDeletionConstraints(): array
    {
        $dependencies = [];
        $totalCount = 0;

        foreach ($this->deletionConstraints() as $relation => $label) {
            $count = $this->{$relation}()->count();

            if ($count > 0) {
                $dependencies[$relation] = [
                    'label' => $label,
                    'count' => $count,
                ];
                $totalCount += $count;
            }
        }

        return [
            'has_dependencies' => $totalCount > 0,
            'total_count' => $totalCount,
            'dependencies' => $dependencies,
        ];
    }
}
