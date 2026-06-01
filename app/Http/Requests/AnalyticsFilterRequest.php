<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsFilterRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'period' => 'nullable|string|in:today,week,month,year,custom',
            'group_by' => 'nullable|string|in:day,week,month',
            'gateway' => 'nullable|string|in:cash,bank,qr,credit,esewa,khalti,fonepay',
            'status' => 'nullable|string',
        ];
    }
}
