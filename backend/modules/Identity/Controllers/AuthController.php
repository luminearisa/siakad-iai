<?php

namespace Modules\Identity\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Models\User;
use Modules\Identity\Requests\AssignRoleRequest;
use Modules\Identity\Requests\ChangePasswordRequest;
use Modules\Identity\Requests\LoginRequest;
use Modules\Identity\Resources\UserResource;
use Modules\Identity\Services\IdentityService;

class AuthController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected IdentityService $identityService
    ) {}

    /**
     * Authenticate user and issue API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->identityService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
            deviceName: $request->validated('device_name')
        );

        return $this->successResponse(
            data: [
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ],
            message: 'Login successful.'
        );
    }

    /**
     * Log out current user (revoke token).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->identityService->logout($request->user());

        return $this->successResponse(
            data: null,
            message: 'Logged out successfully.'
        );
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return $this->successResponse(
            data: new UserResource($user),
            message: 'User profile retrieved successfully.'
        );
    }

    /**
     * Change user password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->identityService->changePassword(
            user: $request->user(),
            newPassword: $request->validated('new_password')
        );

        return $this->successResponse(
            data: null,
            message: 'Password changed successfully.'
        );
    }

    /**
     * Assign roles to a user.
     */
    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->identityService->assignRoles(
            user: $user,
            roles: $request->validated('roles')
        );

        return $this->successResponse(
            data: new UserResource($updatedUser),
            message: 'Roles assigned successfully.'
        );
    }
}
