<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->route('customer');
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone,' . $customerId,
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'points' => 'nullable|integer|min:0',
        ];
    }
}
