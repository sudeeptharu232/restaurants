<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'address' => 'required|string|max:500',
            'business_type' => 'required|string|in:restaurant,cafe,retail,service,other',
            'pan_or_vat_number' => 'nullable|string|max:50',
            'is_vat_registered' => 'nullable|boolean',
        ];
    }
}
