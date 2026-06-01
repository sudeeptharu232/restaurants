<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['closing_date', 'opening_balance', 'cash_sales', 'digital_sales', 'expenses', 'expected_balance', 'actual_balance', 'discrepancy', 'closed_by_user_id', 'notes'])]
class DaybookClosing extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'opening_balance' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'digital_sales' => 'decimal:2',
            'expenses' => 'decimal:2',
            'expected_balance' => 'decimal:2',
            'actual_balance' => 'decimal:2',
            'discrepancy' => 'decimal:2',
        ];
    }

    /**
     * Get the staff user who closed the daybook.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}
