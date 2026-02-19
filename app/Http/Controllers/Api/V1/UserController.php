<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    /**
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = min((int) $request->query('per_page', '25'), 100);

        $users = User::with('roles')
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->query('is_active') !== null, function ($query) use ($request) {
                $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->query('role'), function ($query, $role) {
                $query->role($role);
            })
            ->orderBy('name')
            ->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = User::create([
            'id' => Str::uuid()->toString(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'language' => $validated['language'] ?? 'de',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['role_ids'])) {
            $user->syncRoles(Role::whereIn('id', $validated['role_ids'])->get());
        }

        $user->load('roles.permissions');

        return response()->json([
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load('roles.permissions');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * PUT /api/v1/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        $updateData = collect($validated)
            ->only(['name', 'email', 'language', 'is_active'])
            ->filter(fn ($value) => $value !== null)
            ->toArray();

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        if (array_key_exists('role_ids', $validated)) {
            $user->syncRoles(Role::whereIn('id', $validated['role_ids'])->get());
        }

        $user->load('roles.permissions');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * DELETE /api/v1/users/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return response()->json([
                'type' => 'https://publixx-pim.local/problems/users/self-deletion',
                'title' => 'Self-Deletion Not Allowed',
                'detail' => 'You cannot delete your own account.',
                'status' => Response::HTTP_FORBIDDEN,
            ], Response::HTTP_FORBIDDEN, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
