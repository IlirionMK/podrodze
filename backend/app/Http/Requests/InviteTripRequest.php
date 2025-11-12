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
            'email'   => ['required', 'email', 'exists:users,email'],
            'role'    => ['nullable', 'string', 'in:member,editor'],
            'message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
