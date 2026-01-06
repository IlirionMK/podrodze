<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * @OA\Post(
     * path="/register",
     * summary="Handle an incoming registration request (API Sanctum).",
     * tags={"Authentication"},
     * @OA\RequestBody(
     * required=true,
     * description="User details for registration.",
     * @OA\JsonContent(
     * required={"name", "email", "password", "password_confirmation"},
     * @OA\Property(property="name", type="string", description="The user's full name.", example="John Doe", maxLength=255),
     * @OA\Property(property="email", type="string", format="email", description="The user's email.", example="john@example.com", maxLength=255),
     * @OA\Property(property="password", type="string", format="password", description="The desired password.", example="secret123"),
     * @OA\Property(property="password_confirmation", type="string", format="password", description="Confirmation of the password.", example="secret123")
     * )
     * ),
     * @OA\Response(
     * response=201,
     * description="User registered successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="user", type="object",
     * @OA\Property(property="id", type="integer", example=2),
     * @OA\Property(property="name", type="string", example="John Doe"),
     * @OA\Property(property="email", type="string", example="john@example.com")
     * ),
     * @OA\Property(property="token", type="string", description="API Bearer Token.", example="xxxxxxxxxxx")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error (e.g., email already taken or password mismatch)."
     * ),
     * @OA\Response(
     * response=429,
     * description="Too many attempts."
     * )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $throttleKey = Str::lower('register').'|'.$request->ip();

        // allow 5 attempts per minute, 6th => 429
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));

            return response()->json([
                'message' => 'Too Many Attempts.',
            ], 429);
        }

        // count the attempt (valid or invalid)
        RateLimiter::hit($throttleKey, 60);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }
}
