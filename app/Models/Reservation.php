<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['customer_id', 'restaurant_table_id', 'guest_name', 'guest_phone', 'party_size', 'reservation_time', 'status', 'notes'])]
class Reservation extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'party_size' => 'integer',
            'reservation_time' => 'datetime',
        ];
    }

    /**
     * Get the customer associated with the booking.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the restaurant table.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }
}
