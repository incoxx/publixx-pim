<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Models\Concerns\HasDeletionConstraints;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ChecksDeletionConstraints
{
    /**
     * Return dependency information for a model (pre-check endpoint).
     */
    protected function dependenciesResponse(Model $model): JsonResponse
    {
        if (!in_array(HasDeletionConstraints::class, class_uses_recursive($model))) {
            return response()->json(['data' => [
                'id' => $model->getKey(),
                'type' => $model->getTable(),
                'has_dependencies' => false,
                'total_count' => 0,
                'dependencies' => [],
            ]]);
        }

        $result = $model->checkDeletionConstraints();

        return response()->json(['data' => array_merge([
            'id' => $model->getKey(),
            'type' => $model->getTable(),
        ], $result)]);
    }

    /**
     * Delete a model with constraint checking.
     *
     * If the model has dependencies and ?force=true is not set, returns 409.
     * If ?force=true is set or there are no dependencies, deletes the model.
     */
    protected function destroyWithConstraintCheck(Request $request, Model $model): JsonResponse
    {
        if (in_array(HasDeletionConstraints::class, class_uses_recursive($model))) {
            $result = $model->checkDeletionConstraints();

            if ($result['has_dependencies'] && !$request->boolean('force')) {
                return response()->json([
                    'type' => 'deletion_constraint',
                    'title' => 'Löschung nicht möglich',
                    'status' => 409,
                    'detail' => "Dieses Objekt hat {$result['total_count']} abhängige Datensätze. Verwenden Sie ?force=true um die Löschung zu erzwingen.",
                    'dependencies' => $result['dependencies'],
                ], 409);
            }
        }

        $model->delete();

        return response()->json(null, 204);
    }
}
