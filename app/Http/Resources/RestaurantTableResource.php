<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_space_id' => $this->restaurant_space_id,
            'space' => new RestaurantSpaceResource($this->whenLoaded('space')),
            'table_number' => $this->table_number,
            'capacity' => $this->capacity,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
