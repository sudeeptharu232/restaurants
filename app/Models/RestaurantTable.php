<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['restaurant_space_id', 'table_number', 'capacity', 'status'])]
class RestaurantTable extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * Get the space that owns this table.
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(RestaurantSpace::class, 'restaurant_space_id');
    }

    /**
     * Get the reservations associated with this table.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
