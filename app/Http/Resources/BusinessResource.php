<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
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
            'name' => $this->name ?? $this->id,
            'domains' => $this->domains ? $this->domains->pluck('domain') : [],
            'status' => $this->status ?? 'active',
            'is_active' => (bool) ($this->is_active ?? true),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
