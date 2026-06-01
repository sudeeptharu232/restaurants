<?php

namespace App\Http\Resources;

use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        static $permissionService = null;
        $permissionService ??= app(PermissionService::class);

        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'email'                 => $this->email,
            'phone'                 => $this->phone,
            'role'                  => $this->role,
            'permissions'           => $this->permissions, // raw configuration array or null
            'effective_permissions' => $permissionService->getEffectivePermissions($this->resource),
            'is_active'             => (bool) $this->is_active,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
