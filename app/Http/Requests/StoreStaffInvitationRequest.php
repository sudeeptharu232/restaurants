<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissions = implode(',', app(PermissionService::class)->getPermissions());

        return [
            'email'         => 'required|string|email|max:255',
            'phone'         => 'nullable|string|max:20',
            'role'          => 'required|string|in:manager,staff',
            'permissions'   => 'nullable|array',
            'permissions.*' => "string|in:{$permissions}",
        ];
    }
}
