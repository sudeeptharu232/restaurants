<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized at controller layer
    }

    public function rules(): array
    {
        $permissions = implode(',', app(PermissionService::class)->getPermissions());

        return [
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email',
            'password'      => 'required|string|min:8',
            'phone'         => 'nullable|string|max:20',
            'role'          => 'required|string|in:manager,staff',
            'permissions'   => 'nullable|array',
            'permissions.*' => "string|in:{$permissions}",
            'is_active'     => 'nullable|boolean',
        ];
    }
}
