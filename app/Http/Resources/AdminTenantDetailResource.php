<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTenantDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status ?? 'active',
            'email' => $this->email ?? null,
            'phone' => $this->phone ?? null,
            'domains' => $this->domains->pluck('domain'),
            'subscription' => $this->subscription ? new AdminSubscriptionResource($this->subscription) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
