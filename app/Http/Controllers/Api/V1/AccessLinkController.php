<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AccessLink\StoreAccessLinkRequest;
use App\Http\Resources\Api\V1\AccessLinkResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Jobs\WriteAuditLog;
use App\Models\AccessLink;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AccessLinkController extends Controller
{
    /**
     * GET /api/v1/access-links
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccessLink::class);

        $perPage = max(1, min((int) $request->query('per_page', '25'), 100));

        $links = AccessLink::with(['role', 'creator', 'createdUser'])
            ->when($request->query('status'), function ($query, $status) {
                match ($status) {
                    'open' => $query->open(),
                    'used' => $query->used(),
                    'expired' => $query->expired(),
                    default => null,
                };
            })
            ->when($request->query('search'), function ($query, $search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => AccessLinkResource::collection($links),
            'meta' => [
                'current_page' => $links->currentPage(),
                'last_page' => $links->lastPage(),
                'per_page' => $links->perPage(),
                'total' => $links->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/access-links
     */
    public function store(StoreAccessLinkRequest $request): JsonResponse
    {
        $this->authorize('create', AccessLink::class);

        $validated = $request->validated();
        $expiresInDays = $validated['expires_in_days'] ?? 7;

        $link = AccessLink::create([
            'id' => Str::uuid()->toString(),
            'token' => Str::random(48),
            'name' => $validated['name'],
            'role_id' => $validated['role_id'],
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        $link->load(['role', 'creator']);

        return response()->json([
            'data' => new AccessLinkResource($link),
        ], Response::HTTP_CREATED);
    }

    /**
     * DELETE /api/v1/access-links/{accessLink}
     */
    public function destroy(AccessLink $accessLink): JsonResponse
    {
        $this->authorize('delete', AccessLink::class);

        $accessLink->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * GET /api/v1/access-links/report
     */
    public function report(): JsonResponse
    {
        $this->authorize('viewAny', AccessLink::class);

        $total = AccessLink::count();
        $used = AccessLink::used()->count();
        $open = AccessLink::open()->count();
        $expired = AccessLink::expired()->count();

        return response()->json([
            'data' => [
                'total' => $total,
                'used' => $used,
                'open' => $open,
                'expired' => $expired,
            ],
        ]);
    }

    /**
     * POST /api/v1/access-links/redeem/{token}
     * Public route — no auth required.
     * POST (not GET) to prevent link-preview bots (LinkedIn, Slack) from consuming the token.
     */
    public function redeem(Request $request, string $token): JsonResponse
    {
        $link = AccessLink::where('token', $token)->first();

        if (! $link) {
            return response()->json([
                'type' => 'https://anypim.local/problems/access-links/not-found',
                'title' => 'Link Not Found',
                'detail' => 'Dieser Zugangslink existiert nicht.',
                'status' => Response::HTTP_NOT_FOUND,
            ], Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        if ($link->isUsed()) {
            return response()->json([
                'type' => 'https://anypim.local/problems/access-links/already-used',
                'title' => 'Link Already Used',
                'detail' => 'Dieser Zugangslink wurde bereits verwendet.',
                'status' => Response::HTTP_GONE,
            ], Response::HTTP_GONE, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        if ($link->isExpired()) {
            return response()->json([
                'type' => 'https://anypim.local/problems/access-links/expired',
                'title' => 'Link Expired',
                'detail' => 'Dieser Zugangslink ist abgelaufen.',
                'status' => Response::HTTP_GONE,
            ], Response::HTTP_GONE, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        // Safety check: never assign Admin role
        $role = Role::find($link->role_id);
        if (! $role || $role->name === 'Admin') {
            return response()->json([
                'type' => 'https://anypim.local/problems/access-links/invalid-role',
                'title' => 'Invalid Role',
                'detail' => 'Die konfigurierte Rolle ist ungültig.',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            ], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'Content-Type' => 'application/problem+json',
            ]);
        }

        // Use transaction + lockForUpdate to prevent race conditions (double-redeem)
        return DB::transaction(function () use ($request, $link, $role) {
            // Re-fetch with pessimistic lock
            $link = AccessLink::where('id', $link->id)->lockForUpdate()->first();

            if ($link->isUsed()) {
                return response()->json([
                    'type' => 'https://anypim.local/problems/access-links/already-used',
                    'title' => 'Link Already Used',
                    'detail' => 'Dieser Zugangslink wurde bereits verwendet.',
                    'status' => Response::HTTP_GONE,
                ], Response::HTTP_GONE, [
                    'Content-Type' => 'application/problem+json',
                ]);
            }

            // Generate credentials with unique email
            $slug = Str::slug($link->name, '.');
            do {
                $randomSuffix = Str::lower(Str::random(4));
                $email = "{$slug}.{$randomSuffix}@access.anypim.local";
            } while (User::where('email', $email)->exists());

            $plainPassword = Str::random(12);

            // Create user
            $user = User::create([
                'id' => Str::uuid()->toString(),
                'name' => $link->name,
                'email' => $email,
                'password' => $plainPassword,
                'language' => 'de',
                'is_active' => true,
            ]);

            $user->assignRole($role);

            // Audit: user created via access link
            WriteAuditLog::dispatch(
                auditableType: User::class,
                auditableId: $user->id,
                action: 'created',
                oldValues: null,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'language' => $user->language,
                    'is_active' => $user->is_active,
                    'roles' => [$role->name],
                    'method' => 'access_link',
                ],
                userId: $user->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            )->afterCommit();

            // Mark link as used
            $link->update([
                'used_at' => now(),
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit($request->userAgent() ?? '', 500),
            ]);

            // Create auth token for auto-login
            $sanctumToken = $user->createToken(
                name: 'pim-api',
                expiresAt: now()->addHours((int) config('sanctum.expiration_hours', 24)),
            );

            // Audit: user logged in via access link
            WriteAuditLog::dispatch(
                auditableType: User::class,
                auditableId: $user->id,
                action: 'logged_in',
                oldValues: null,
                newValues: ['method' => 'access_link'],
                userId: $user->id,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            )->afterCommit();

            $user->load('roles.permissions');

            return response()->json([
                'data' => [
                    'token' => $sanctumToken->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $sanctumToken->accessToken->expires_at?->toIso8601String(),
                    'user' => new UserResource($user),
                    'credentials' => [
                        'email' => $email,
                        'password' => $plainPassword,
                    ],
                ],
            ]);
        });
    }
}
