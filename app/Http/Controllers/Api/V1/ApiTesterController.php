<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

class ApiTesterController extends Controller
{
    public function routes(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\User::class);

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/'))
            ->map(fn ($route) => [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => '/' . $route->uri(),
                'name' => $route->getName(),
                'action' => $route->getActionName(),
            ])
            ->sortBy('uri')
            ->values();

        return response()->json(['data' => $routes]);
    }
}
