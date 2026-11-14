<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\Api\ApiResponse;
use App\Support\Api\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->where('email', mb_strtolower(trim($request->validated('email'))))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw new ApiException('Invalid credentials provided.', 401, 'invalid_credentials');
        }

        if (! $user->isActive()) {
            throw new ApiException('This account is not active.', 403, 'account_inactive');
        }

        $deviceName = (string) ($request->validated('device_name') ?: 'api-token');
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->record(
            actor: $user,
            event: 'auth.login',
            description: "User {$user->email} logged in from {$request->ip()}.",
            subject: $user,
            properties: ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]
        );

        $user->load(['memberships.role', 'memberships.organization', 'memberships.rw', 'memberships.rt']);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'memberships' => fn ($q) => $q->active(),
            'memberships.role.permissions',
            'memberships.organization',
            'memberships.rw',
            'memberships.rt',
        ]);

        return ApiResponse::success(new UserResource($user));
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Revoke the current access token
        $user->currentAccessToken()?->delete();

        $this->audit->record(
            actor: $user,
            event: 'auth.logout',
            description: "User {$user->email} logged out.",
            subject: $user
        );

        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }
}
