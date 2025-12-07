<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $trip = $this->route('trip');

        return [
            'name' => ['sometimes', 'string', 'max:255'],

            'start_date' => ['sometimes', 'nullable', 'date'],

            'end_date' => [
                'sometimes',
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($trip) {
                    $newEnd   = $value;
                    $oldStart = $trip->start_date;

                    // Only validate based on old start date if start_date is not being changed
                    if ($this->input('start_date') === null && $oldStart !== null) {
                        if ($newEnd < $oldStart) {
                            $fail("The end_date must be after or equal to the trip start_date ($oldStart).");
                        }
                    }
                }
            ],
        ];
    }

    /**
     * Body parameters for API documentation (Scribe).
     *
     * @return array
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'New trip name. Optional field.',
                'example' => 'Updated trip name',
            ],

            'start_date' => [
                'description' => 'New start date in YYYY-MM-DD format. Optional.',
                'example' => '2025-11-30',
            ],

            'end_date' => [
                'description' =>
                    'New end date in YYYY-MM-DD format. Optional. ' .
                    'Must be after or equal to start_date. ' .
                    'If start_date is not provided in this request, it must not be earlier than the existing trip start_date.',
                'example' => '2025-12-21',
            ],
        ];
    }
}
