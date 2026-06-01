<?php
// tinker_staff.php
// Run this script using: php artisan tinker tinker_staff.php

use App\Models\Tenant;
use App\Models\User;
use App\Models\StaffInvitation;
use App\Services\PermissionService;

// Locate the default demo tenant
$tenant = Tenant::find('sajilo');
if (!$tenant) {
    echo "Tenant 'sajilo' not found. Please run php artisan db:seed first.\n";
    exit;
}

$tenant->run(function () use ($tenant) {
    $permissionService = app(PermissionService::class);

    echo "==================================================\n";
    echo "  GROWSTRO STAFF & ROLE MANAGEMENT (Tenant: {$tenant->id})\n";
    echo "==================================================\n\n";

    // 1. List current Users
    echo "--- 1. ACTIVE STAFF DIRECTORY ---\n";
    $users = User::all();
    foreach ($users as $index => $user) {
        $num = $index + 1;
        $status = $user->is_active ? 'ACTIVE' : 'SUSPENDED';
        echo "{$num}. {$user->name} ({$user->email})\n";
        echo "   Role: " . strtoupper($user->role) . " | Status: {$status}\n";
        echo "   Custom Overrides: " . ($user->permissions ? implode(', ', $user->permissions) : 'None') . "\n";
        
        $effective = $permissionService->getEffectivePermissions($user);
        echo "   Effective Permissions Count: " . count($effective) . "\n";
        echo "   - Permissions: " . implode(', ', array_slice($effective, 0, 5)) . (count($effective) > 5 ? '... and more' : '') . "\n\n";
    }

    // 2. List current Invitations
    echo "--- 2. PENDING & LIFE-CYCLE INVITATIONS ---\n";
    $invitations = StaffInvitation::all();
    if ($invitations->isEmpty()) {
        echo "No invitations sent yet.\n\n";
    } else {
        foreach ($invitations as $index => $invite) {
            $num = $index + 1;
            echo "{$num}. Email: {$invite->email} | Phone: " . ($invite->phone ?? 'N/A') . "\n";
            echo "   Role: " . strtoupper($invite->role) . " | Status: " . strtoupper($invite->status) . "\n";
            echo "   Token Hash: {$invite->token}\n";
            echo "   Expires: {$invite->expires_at->toDateTimeString()}\n\n";
        }
    }

    echo "==================================================\n";
    echo "  Staff & Permissions verification successful!\n";
    echo "==================================================\n";
});
