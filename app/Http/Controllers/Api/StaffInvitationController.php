<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptStaffInvitationRequest;
use App\Http\Requests\StoreStaffInvitationRequest;
use App\Http\Resources\StaffInvitationResource;
use App\Http\Resources\StaffResource;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Services\StaffInvitationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffInvitationController extends Controller
{
    use ApiResponse;

    protected StaffInvitationService $invitationService;

    public function __construct(StaffInvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Enforce role/permission authorization inline.
     */
    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();
        if (!$user) {
            abort(response()->json(['success' => false, 'message' => 'Unauthenticated'], 401));
        }

        if (!$user->is_active) {
            abort(response()->json(['success' => false, 'message' => 'Forbidden: Account suspended or inactive'], 403));
        }

        if (!$user->hasPermission($permission)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to execute this operation',
            ], 403));
        }
    }

    /**
     * GET /api/{tenant}/staff-invitations
     */
    public function index($tenant, Request $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        // Automatically expire old invitations before listing
        $this->invitationService->expireOldInvitations();

        $invitations = StaffInvitation::orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 30));

        return $this->success(
            StaffInvitationResource::collection($invitations)->response()->getData(true),
            'Staff invitations list retrieved successfully'
        );
    }

    /**
     * POST /api/{tenant}/staff-invitations
     */
    public function store($tenant, StoreStaffInvitationRequest $request): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $result = $this->invitationService->createInvitation($request->validated(), auth()->user());

        return response()->json([
            'success'   => true,
            'message'   => 'Staff invitation created successfully',
            'data'      => new StaffInvitationResource($result['invitation']),
            'raw_token' => $result['raw_token'],
            'link'      => url("/api/auth/accept-staff-invitation?token=" . $result['raw_token'] . "&tenant=" . $tenant),
        ], 201);
    }

    /**
     * DELETE /api/{tenant}/staff-invitations/{id}
     */
    public function destroy($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $invitation = StaffInvitation::findOrFail($id);
        $cancelled = $this->invitationService->cancelInvitation($invitation, auth()->user());

        return $this->success(
            new StaffInvitationResource($cancelled),
            'Staff invitation cancelled successfully'
        );
    }

    /**
     * POST /api/{tenant}/staff-invitations/{id}/resend
     */
    public function resend($tenant, $id): JsonResponse
    {
        $this->authorizePermission('manage_staff');

        $invitation = StaffInvitation::findOrFail($id);
        $result = $this->invitationService->resendInvitation($invitation, auth()->user());

        return response()->json([
            'success'   => true,
            'message'   => 'Staff invitation resent successfully',
            'data'      => new StaffInvitationResource($result['invitation']),
            'raw_token' => $result['raw_token'],
            'link'      => url("/api/auth/accept-staff-invitation?token=" . $result['raw_token'] . "&tenant=" . $tenant),
        ], 200);
    }

    /**
     * POST /api/auth/accept-staff-invitation
     * Public endpoint outside tenant path resolver.
     */
    public function accept(AcceptStaffInvitationRequest $request): JsonResponse
    {
        $tenantId = $request->input('tenant');

        // Resolve and initialize tenant connection
        $tenant = Tenant::findOrFail($tenantId);
        tenancy()->initialize($tenant);

        // Accept invitation
        $user = $this->invitationService->acceptInvitation(
            $request->input('token'),
            $request->only(['name', 'password', 'phone'])
        );

        return $this->success(
            new StaffResource($user),
            'Staff invitation accepted successfully. You can now login.',
            201
        );
    }
}
