<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptStaffInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant'   => 'required|string|exists:tenants,id',
            'token'    => 'required|string',
            'name'     => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string|max:20',
        ];
    }
}
