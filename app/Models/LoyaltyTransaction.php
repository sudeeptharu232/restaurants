<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['loyalty_account_id', 'points', 'type', 'reference'])]
class LoyaltyTransaction extends Model
{
    /**
     * Disable updated_at column since loyalty transactions are write-once records.
     */
    const UPDATED_AT = null;

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /**
     * Get the loyalty account.
     */
    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class);
    }
}
