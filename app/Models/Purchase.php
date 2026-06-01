<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['supplier_id', 'purchase_number', 'status', 'total_amount', 'purchase_date'])]
class Purchase extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'purchase_date' => 'date',
        ];
    }

    /**
     * Get the supplier who fulfilled this purchase.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get purchase line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
