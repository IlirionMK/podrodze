<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'min:2', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Body parameters for Scribe (API documentation).
     *
     * @return array
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Name of the trip.',
                'example' => 'Weekend in Wrocław',
            ],
            'start_date' => [
                'description' => 'Optional start date of the trip in YYYY-MM-DD format.',
                'example' => '2025-11-29',
            ],
            'end_date' => [
                'description' => 'Optional end date of the trip in YYYY-MM-DD format. Must be after or equal to start_date.',
                'example' => '2025-12-02',
            ],
        ];
    }
}
