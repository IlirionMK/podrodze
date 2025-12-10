<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripPlaceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'place_id' => ['nullable', 'exists:places,id'],

            'name'     => ['required_without:place_id', 'string', 'max:255'],
            'category' => ['required_without:place_id', 'string', 'exists:categories,slug'],
            'lat'      => ['required_without:place_id', 'numeric'],
            'lon'      => ['required_without:place_id', 'numeric'],

            'status'      => ['nullable', 'string', 'in:proposed,selected,rejected,planned'],
            'is_fixed'    => ['nullable', 'boolean'],
            'day'         => ['nullable', 'integer', 'min:1'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'note'        => ['nullable', 'string', 'max:255'],
        ];
    }
}
