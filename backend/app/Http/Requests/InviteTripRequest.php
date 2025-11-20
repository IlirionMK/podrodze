<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InviteTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'   => [
                'required',
                'email',
                'exists:users,email',
                function ($attribute, $value, $fail) {
                    if ($value === $this->user()?->email) {
                        $fail('You cannot invite yourself.');
                    }
                }
            ],

            'role'    => ['nullable', 'string', 'in:member,editor'],

            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
