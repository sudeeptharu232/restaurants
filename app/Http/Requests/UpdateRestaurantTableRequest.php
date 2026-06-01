<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tableId = $this->route('table');
        return [
            'restaurant_space_id' => 'required|integer|exists:restaurant_spaces,id',
            'table_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('restaurant_tables')->where(function ($query) {
                    return $query->where('restaurant_space_id', $this->input('restaurant_space_id'));
                })->ignore($tableId)
            ],
            'capacity' => 'required|integer|min:1',
            'status' => 'required|string|in:vacant,occupied,reserved',
        ];
    }
}
