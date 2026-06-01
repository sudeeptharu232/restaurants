<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        $permissions = implode(',', app(PermissionService::class)->getPermissions());

        return [
            'name'          => 'sometimes|required|string|max:255',
            'email'         => "sometimes|required|string|email|max:255|unique:users,email,{$id}",
            'password'      => 'sometimes|nullable|string|min:8',
            'phone'         => 'nullable|string|max:20',
            'role'          => 'sometimes|required|string|in:manager,staff',
            'permissions'   => 'nullable|array',
            'permissions.*' => "string|in:{$permissions}",
            'is_active'     => 'sometimes|required|boolean',
        ];
    }
}
