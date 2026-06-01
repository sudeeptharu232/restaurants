<?php

namespace App\Services;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffInvitationService
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Create a new staff invitation.
     */
    public function createInvitation(array $data, User $actor): array
    {
        $email = $data['email'];
        $role = $data['role'] ?? 'staff';

        // Check if user already exists in the tenant
        if (User::where('email', $email)->exists()) {
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: A user with this email already exists in this workspace',
            ], 409));
        }

        // Prevent duplicate active invitations for the same email
        $activeInvite = StaffInvitation::where('email', $email)
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if ($activeInvite) {
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: An active pending invitation already exists for this email',
            ], 409));
        }

        // Only owners/super_admins can invite owners (usually not recommended, let's block owner invitation entirely for safety or enforce strict checks)
        if ($role === 'owner' && $actor->role !== 'owner' && $actor->role !== 'super_admin') {
            abort(response()->json([
                'success' => false,
                'message' => 'Forbidden: You cannot invite someone as an owner',
            ], 403));
        }

        // Validate custom permissions being granted
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            if (!$this->permissionService->canGrantPermissions($actor, $data['permissions'])) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Forbidden: You cannot grant permissions you do not possess',
                ], 403));
            }
        }

        $rawToken = Str::random(40);
        $hashedToken = hash('sha256', $rawToken);

        $invitation = DB::transaction(function () use ($data, $role, $hashedToken) {
            return StaffInvitation::create([
                'email'        => $data['email'],
                'phone'        => $data['phone'] ?? null,
                'token'        => $hashedToken,
                'role'         => $role,
                'permissions'  => $data['permissions'] ?? null,
                'expires_at'   => Carbon::now()->addDays(7),
                'status'       => 'pending',
                'is_accepted'  => false,
            ]);
        });

        return [
            'invitation' => $invitation,
            'raw_token'  => $rawToken,
        ];
    }

    /**
     * Accept a staff invitation and create the user.
     */
    public function acceptInvitation(string $rawToken, array $userData): User
    {
        $hashedToken = hash('sha256', $rawToken);

        $invitation = StaffInvitation::where('token', $hashedToken)->first();

        if (!$invitation) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unprocessable: Invalid invitation token',
            ], 422));
        }

        // Expire invitation automatically if needed
        if ($invitation->status === 'pending' && Carbon::now()->greaterThan($invitation->expires_at)) {
            $invitation->update(['status' => 'expired']);
        }

        if ($invitation->status !== 'pending') {
            abort(response()->json([
                'success' => false,
                'message' => "Unprocessable: This invitation is {$invitation->status}",
            ], 422));
        }

        // Check if user already exists (e.g. created during pending time)
        if (User::where('email', $invitation->email)->exists()) {
            $invitation->update(['status' => 'cancelled']);
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: A user with this email already exists in this workspace',
            ], 409));
        }

        return DB::transaction(function () use ($invitation, $userData) {
            $user = User::create([
                'name'         => $userData['name'],
                'email'        => $invitation->email,
                'password'     => Hash::make($userData['password']),
                'role'         => $invitation->role,
                'phone'        => $invitation->phone ?? ($userData['phone'] ?? null),
                'permissions'  => $invitation->permissions,
                'is_active'    => true,
            ]);

            $invitation->update([
                'status'      => 'accepted',
                'is_accepted' => true,
            ]);

            return $user;
        });
    }

    /**
     * Resend an invitation (generates new token and resets status).
     */
    public function resendInvitation(StaffInvitation $invitation, User $actor): array
    {
        if ($invitation->status === 'accepted') {
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: This invitation has already been accepted',
            ], 409));
        }

        $rawToken = Str::random(40);
        $hashedToken = hash('sha256', $rawToken);

        DB::transaction(function () use ($invitation, $hashedToken) {
            $invitation->update([
                'token'      => $hashedToken,
                'status'     => 'pending',
                'expires_at' => Carbon::now()->addDays(7),
            ]);
        });

        return [
            'invitation' => $invitation,
            'raw_token'  => $rawToken,
        ];
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(StaffInvitation $invitation, User $actor): StaffInvitation
    {
        if ($invitation->status === 'accepted') {
            abort(response()->json([
                'success' => false,
                'message' => 'Conflict: This invitation has already been accepted',
            ], 409));
        }

        $invitation->update(['status' => 'cancelled']);
        return $invitation;
    }

    /**
     * Mark all expired pending invitations as 'expired'.
     */
    public function expireOldInvitations(): int
    {
        return StaffInvitation::where('status', 'pending')
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => 'expired']);
    }
}
