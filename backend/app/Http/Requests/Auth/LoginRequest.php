<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * Login user and issue an API token.
 *
 * @bodyParam email string required The user's email. Example: john@example.com
 * @bodyParam password string required The user's password. Example: secret123
 *
 * @response 200 {
 *   "user": {
 *     "id": 1,
 *     "name": "John Doe",
 *     "email": "john@example.com"
 *   },
 *   "token": "xxxxx"
 * }
 *
 * @response 422 {
 *   "message": "Invalid credentials",
 *   "errors": { "email": ["auth.failed"] }
 * }
 *
 * @response 429 {
 *   "message": "Too many attempts",
 *   "errors": { "email": ["auth.throttle"] }
 * }
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::lower($this->input('email')).'|'.$this->ip();
    }

    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => "User's email.",
                'example' => 'john@example.com',
            ],
            'password' => [
                'description' => "User's password.",
                'example' => 'secret123',
            ],
        ];
    }
}
