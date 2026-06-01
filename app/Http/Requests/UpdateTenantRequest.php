<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|string|in:active,inactive,suspended',
            'owner_email' => 'sometimes|required|email|max:255',
            'phone' => 'sometimes|required|string|max:20',
        ];
    }
}
