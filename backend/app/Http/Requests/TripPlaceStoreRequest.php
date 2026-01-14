<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TripPlaceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // === Source selectors (choose exactly one) ===
            'place_id'        => ['nullable', 'integer', 'exists:places,id'],
            'google_place_id' => ['nullable', 'string', 'max:255'],

            // === Custom place fields (only if not using place_id/google_place_id) ===
            'name'     => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'lat'      => ['nullable', 'numeric'],
            'lon'      => ['nullable', 'numeric'],

            // === Pivot fields ===
            'status'      => ['nullable', 'string', 'in:proposed,selected,rejected,planned'],
            'is_fixed'    => ['nullable', 'boolean'],
            'day'         => ['nullable', 'integer', 'min:1'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'note'        => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasPlaceId  = $this->filled('place_id');
            $hasGoogleId = $this->filled('google_place_id');

            $hasCustomAny =
                $this->filled('name') ||
                $this->filled('category') ||
                !is_null($this->input('lat')) ||
                !is_null($this->input('lon'));

            $sourcesCount = (int) $hasPlaceId + (int) $hasGoogleId + (int) $hasCustomAny;

            // Must provide something
            if ($sourcesCount === 0) {
                $v->errors()->add(
                    'place_id',
                    'Provide either place_id, google_place_id, or custom place fields (name, category, lat, lon).'
                );
                return;
            }

            // Cannot mix
            if ($sourcesCount > 1) {
                $v->errors()->add(
                    'place_id',
                    'Provide only one source: place_id OR google_place_id OR custom place fields (name, category, lat, lon).'
                );
                return;
            }

            // If custom was selected -> require all custom fields
            if ($hasCustomAny && ! $hasPlaceId && ! $hasGoogleId) {
                foreach (['name', 'category', 'lat', 'lon'] as $field) {
                    if ($this->input($field) === null || trim((string) $this->input($field)) === '') {
                        $v->errors()->add($field, "The {$field} field is required when adding a custom place.");
                    }
                }
            }
        });
    }
}
