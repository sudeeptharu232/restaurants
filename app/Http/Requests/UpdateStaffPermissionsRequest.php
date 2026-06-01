<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissions = implode(',', app(PermissionService::class)->getPermissions());

        return [
            'permissions'   => 'required|array',
            'permissions.*' => "string|in:{$permissions}",
        ];
    }
}
