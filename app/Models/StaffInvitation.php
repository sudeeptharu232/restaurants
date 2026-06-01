<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['email', 'token', 'role', 'expires_at', 'is_accepted', 'phone', 'permissions', 'status'])]
class StaffInvitation extends Model
{
    /**
     * Cast attributes dynamically.
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_accepted' => 'boolean',
            'permissions' => 'array',
        ];
    }
}
