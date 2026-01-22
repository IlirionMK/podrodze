<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
