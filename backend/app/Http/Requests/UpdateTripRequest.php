<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'name'       => ['sometimes','string','max:255'],
            'start_date' => ['sometimes','nullable','date'],
            'end_date'   => ['sometimes','nullable','date','after_or_equal:start_date'],
        ];
    }
}
