<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\User\StoreUserRequest;
use App\Http\Requests\Api\V1\User\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Traits\AuditsChanges;
use App\Http\Traits\ChecksDeletionConstraints;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use ChecksDeletionConstraints;
    use AuditsChanges;

    /**
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = max(1, min((int) $request->query('per_page', '25'), 100));

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
            'password' => $validated['password'],
            'language' => $validated['language'] ?? 'de',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['role_ids'])) {
            $this->guardRoleAssignment($request->user(), null, $validated['role_ids']);
            $user->syncRoles(Role::whereIn('id', $validated['role_ids'])->get());
        }

        $user->load('roles.permissions');

        $this->audit('created', User::class, $user->id, null, [
            'name' => $user->name,
            'email' => $user->email,
            'language' => $user->language,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);

        return response()->json([
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Erzwingt die Sicherheitsregeln fuer die Rollen-Zuweisung.
     *
     * Ohne diesen Guard konnte ein beliebig niedrig privilegierter Nutzer sich
     * ueber PUT /users/{eigeneId} selbst die Admin-Rolle zuweisen (die
     * UserPolicy::update() gibt fuer den eigenen Datensatz bedingungslos true zurueck).
     *
     * Regeln:
     *  - Rollen der EIGENEN Person duerfen ueber diesen Endpoint nie geaendert werden
     *    (verhindert Selbst-Hochstufung). $target === null = Neuanlage, dort greift die Regel nicht.
     *  - Rollen aendern erfordert die 'users.edit'-Berechtigung.
     *  - Die Admin-Rolle darf nur ein Admin vergeben (analog AccessLinkController).
     *
     * @param  array<int, string>  $roleIds
     */
    private function guardRoleAssignment(User $actor, ?User $target, array $roleIds): void
    {
        if ($target !== null) {
            if ($actor->id === $target->id) {
                abort(Response::HTTP_FORBIDDEN, 'Eigene Rollen koennen ueber diesen Endpoint nicht geaendert werden.');
            }
            if (! $actor->can('users.edit')) {
                abort(Response::HTTP_FORBIDDEN, 'Keine Berechtigung, Rollen zu aendern.');
            }
        }

        $assignsAdmin = Role::whereIn('id', $roleIds)->where('name', 'Admin')->exists();
        if ($assignsAdmin && ! $actor->hasRole('Admin')) {
            abort(Response::HTTP_FORBIDDEN, 'Nur Administratoren duerfen die Admin-Rolle vergeben.');
        }
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

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'language' => $user->language,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->toArray(),
        ];

        $updateData = collect($validated)
            ->only(['name', 'email', 'language', 'is_active'])
            ->filter(fn ($value) => $value !== null)
            ->toArray();

        if (! empty($validated['password'])) {
            $updateData['password'] = $validated['password'];
        }

        $user->update($updateData);

        if (array_key_exists('role_ids', $validated)) {
            $this->guardRoleAssignment($request->user(), $user, $validated['role_ids']);
            $user->syncRoles(Role::whereIn('id', $validated['role_ids'])->get());
        }

        $user->load('roles.permissions');

        $newValues = [
            'name' => $user->name,
            'email' => $user->email,
            'language' => $user->language,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->toArray(),
        ];

        if (! empty($validated['password'])) {
            $newValues['password_changed'] = true;
        }

        $this->audit('updated', User::class, $user->id, $oldValues, $newValues);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function dependencies(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->dependenciesResponse($user);
    }

    /**
     * DELETE /api/v1/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return response()->json([
                'type' => 'https://anypim.local/problems/users/self-deletion',
                'title' => 'Self-Deletion Not Allowed',
                'detail' => 'You cannot delete your own account.',
                'status' => Response::HTTP_FORBIDDEN,
            ], Response::HTTP_FORBIDDEN, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        $snapshot = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray(),
        ];

        $response = $this->destroyWithConstraintCheck($request, $user);

        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT) {
            $this->audit('deleted', User::class, $user->id, $snapshot, null);
        }

        return $response;
    }
}
