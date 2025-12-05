<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Authenticate user and return API token.
     *
     * @group Authentication
     * @unauthenticated
     *
     * @bodyParam email string required The user's email.
     * @bodyParam password string required The user's password.
     *
     * @response 200 {
     *   "user": { "id": 1, "name": "John Doe", "email": "john@example.com" },
     *   "token": "xxxxx"
     * }
     *
     * @response 422 {
     *   "message": "invalid_credentials"
     * }
     */
    public function store(LoginRequest $request)
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'invalid_credentials'
            ], 422);
        }

        $user  = $request->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * Logout the authenticated user and revoke the current API token.
     *
     * @group Authentication
     * @authenticated
     *
     * @response 204
     */
    public function destroy(Request $request): Response
    {
        if ($request->user()) {
            $currentToken = $request->user()->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }
        }

        try {
            Auth::guard('web')->logout();
        } catch (\Throwable $e) {}

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
