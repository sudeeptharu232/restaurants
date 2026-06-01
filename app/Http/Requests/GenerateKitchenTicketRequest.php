<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateKitchenTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // No strict fields required as KOT/BOT is generated automatically from order items
            'notes' => 'nullable|string',
        ];
    }
}
