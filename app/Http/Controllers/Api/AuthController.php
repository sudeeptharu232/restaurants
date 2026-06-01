<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\BusinessResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle central and tenant login requests.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->authenticate(
                $request->only(['email', 'password']),
                $request->input('tenant')
            );

            $response = [
                'user' => new UserResource($result['user']),
                'access_token' => $result['token'],
                'token_type' => 'Bearer',
            ];

            if ($result['tenant']) {
                $response['business'] = new BusinessResource($result['tenant']);
            }

            return $this->success($response, 'Logged in successfully');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Mock forgot password request.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->success([
            'email' => $request->input('email'),
            'reset_token' => 'mock-reset-token-' . bin2hex(random_bytes(8)),
        ], 'Password reset link sent successfully.');
    }

    /**
     * Get authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        return $this->success(new UserResource($user), 'Authenticated user profile retrieved');
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully');
    }
}
