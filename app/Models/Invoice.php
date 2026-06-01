<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'order_id', 'customer_id', 'invoice_number', 'subtotal', 'discount', 
    'vat_amount', 'service_charge', 'total', 'taxable_amount', 
    'paid_amount', 'due_amount', 'status', 'pdf_path', 
    'invoice_date', 'due_date', 'notes'
])]
class Invoice extends Model
{
    use SoftDeletes;

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * Get the order that owns this invoice.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the customer associated with the invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the invoice items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the payments associated with this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
