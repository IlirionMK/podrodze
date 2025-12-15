<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

final class TripPlaceSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'based_on_place_id' => ['nullable', 'integer', 'exists:places,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('ai.suggestions.max_limit')],
            'radius_m' => ['nullable', 'integer', 'min:' . (int) config('ai.suggestions.min_radius_m'), 'max:' . (int) config('ai.suggestions.max_radius_m')],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }
}
