<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan' => new UserResource($this->whenLoaded('plan')), // or slug if simple
            'plan_id' => $this->subscription_plan_id,
            'status' => $this->status,
            'starts_at' => $this->starts_at ? $this->starts_at->toDateTimeString() : null,
            'ends_at' => $this->ends_at ? $this->ends_at->toDateTimeString() : null,
            'trial_ends_at' => $this->trial_ends_at ? $this->trial_ends_at->toDateTimeString() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
