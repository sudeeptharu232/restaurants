<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'sku', 'unit', 'minimum_stock', 'current_stock'])]
class InventoryItem extends Model
{
    use SoftDeletes;

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:2',
            'current_stock' => 'decimal:2',
        ];
    }

    /**
     * Get stock movements for this item.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
