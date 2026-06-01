<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,id',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|max:20',
            'business_type' => 'required|string|in:restaurant,cafe,retail,general',
            'address' => 'required|string|max:255',
            'pan_or_vat_number' => 'nullable|string|max:50',
            'is_vat_registered' => 'nullable|boolean',
        ];
    }
}
