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

                    if ($this->input('start_date') === null && $oldStart !== null) {
                        if ($newEnd < $oldStart) {
                            $fail("The end_date must be after or equal to the trip start_date ($oldStart).");
                        }
                    }
                }
            ],
        ];
    }
}
