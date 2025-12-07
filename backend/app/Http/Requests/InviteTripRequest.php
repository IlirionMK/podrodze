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
            'email' => [
                'required',
                'email',
                'exists:users,email',
                function ($attribute, $value, $fail) {
                    if ($value === $this->user()?->email) {
                        $fail('You cannot invite yourself.');
                    }
                }
            ],

            'role' => ['nullable', 'string', 'in:member,editor'],

            'message' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Scribe API documentation for body parameters.
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' =>
                    'Email of the invited user. Must belong to an existing user. ' .
                    'Cannot be your own email.',
                'example' => 'friend@example.com',
            ],

            'role' => [
                'description' =>
                    'Role for the invited user. Optional. Must be one of: member, editor.',
                'example' => 'member',
            ],

            'message' => [
                'description' =>
                    'Optional message that will be included with the invitation. ' .
                    'Max length: 255 characters.',
                'example' => 'Hey! Join our trip plan.',
            ],
        ];
    }
}
