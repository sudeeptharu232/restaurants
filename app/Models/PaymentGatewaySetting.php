<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['gateway_name', 'api_key', 'secret_key', 'sandbox_mode', 'is_active'])]
class PaymentGatewaySetting extends Model
{
    /**
     * Bind strictly to central database connection.
     */
    protected $connection = 'central';

    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'sandbox_mode' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
