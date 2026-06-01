<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppReportSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled'                  => 'required|boolean',
            'owner_whatsapp_number'    => 'required_if:enabled,true|nullable|string|max:20',
            'send_time'                => 'nullable|date_format:H:i',
            'timezone'                 => 'nullable|string|max:64',
            'include_sales_summary'    => 'nullable|boolean',
            'include_payment_summary'  => 'nullable|boolean',
            'include_due_summary'      => 'nullable|boolean',
            'include_top_products'     => 'nullable|boolean',
            'include_inventory_alerts' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'enabled.required'                       => 'The enabled field is required.',
            'owner_whatsapp_number.required_if'      => 'A WhatsApp phone number is required when reports are enabled.',
            'send_time.date_format'                  => 'Send time must be in HH:MM format (e.g. 22:00).',
        ];
    }
}
