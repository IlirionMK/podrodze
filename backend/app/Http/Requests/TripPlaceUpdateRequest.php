<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripPlaceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'      => ['nullable', 'string', 'in:proposed,selected,rejected,planned'],
            'is_fixed'    => ['nullable', 'boolean'],
            'day'         => ['nullable', 'integer', 'min:1'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'note'        => ['nullable', 'string', 'max:255'],
        ];
    }
}
