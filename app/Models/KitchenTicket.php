<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['order_id', 'ticket_number', 'status', 'printed_at', 'type'])]
class KitchenTicket extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }
    /**
     * Get the order that owns this ticket.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get individual items inside the kitchen ticket.
     */
    public function items(): HasMany
    {
        return $this->hasMany(KitchenTicketItem::class);
    }
}
