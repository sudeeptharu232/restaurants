<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_date'  => 'nullable|date|date_format:Y-m-d',
            'regenerate'   => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'report_date.date_format' => 'Report date must be in YYYY-MM-DD format.',
        ];
    }
}
