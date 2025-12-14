<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Stateless API login: returns user + bearer token.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        try {
            $user = $request->authenticateUser();
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'invalid_credentials',
            ], 422);
        }

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Your email address is not verified.',
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 200);
    }

    /**
     * Stateless API logout: revoke ALL tokens for this user.
     */
    public function destroy(Request $request): Response
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->delete(); // matches your E2E expectation
        }

        return response()->noContent(); // 204
    }
}
