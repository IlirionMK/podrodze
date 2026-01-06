<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],

            'start_date' => ['sometimes', 'nullable', 'date'],

            'end_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],

        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var \App\Models\Trip|null $trip */
            $trip = $this->route('trip');

            $start = $this->input('start_date') ?? ($trip?->start_date);

            $end = $this->input('end_date');

            if ($end === null || $start === null) {
                return;
            }

            try {
                $startDate = Carbon::parse($start)->startOfDay();
                $endDate   = Carbon::parse($end)->startOfDay();
            } catch (\Throwable $e) {
                return;
            }

            if ($endDate->lt($startDate)) {
                $validator->errors()->add('end_date', 'The end_date must be after or equal to start_date.');
            }
        });
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
                    'New end date in YYYY-MM-DD format. Optional. Must be after or equal to start_date. ' .
                    'If start_date is not provided in this request, it must not be earlier than the existing trip start_date.',
                'example' => '2025-12-21',
            ],
        ];
    }
}
