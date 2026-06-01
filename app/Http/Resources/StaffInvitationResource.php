<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'role'        => $this->role,
            'permissions' => $this->permissions,
            'status'      => $this->status,
            'is_accepted' => (bool) $this->is_accepted,
            'expires_at'  => $this->expires_at,
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
