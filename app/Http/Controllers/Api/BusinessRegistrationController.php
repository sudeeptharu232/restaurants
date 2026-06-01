<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterBusinessRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\BusinessResource;
use App\Services\BusinessRegistrationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BusinessRegistrationController extends Controller
{
    use ApiResponse;

    protected BusinessRegistrationService $registrationService;

    public function __construct(BusinessRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    /**
     * Handle business self-registration.
     */
    public function register(RegisterBusinessRequest $request): JsonResponse
    {
        $result = $this->registrationService->register($request->validated());

        return $this->success([
            'user' => $result['user'],
            'business' => new BusinessResource($result['tenant']),
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
        ], 'Business registered successfully', 201);
    }
}
