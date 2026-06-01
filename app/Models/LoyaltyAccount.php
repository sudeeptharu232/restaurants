<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['customer_id', 'card_number', 'points_balance', 'is_active'])]
class LoyaltyAccount extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'points_balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the customer associated with the account.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the loyalty ledger transactions.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
