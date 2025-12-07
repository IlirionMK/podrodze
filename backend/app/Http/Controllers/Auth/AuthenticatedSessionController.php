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
     * @OA\Post(
     * path="/login",
     * summary="Authenticate user and return API token.",
     * tags={"Authentication"},
     * @OA\RequestBody(
     * required=true,
     * description="User credentials for login.",
     * @OA\JsonContent(
     * required={"email", "password"},
     * @OA\Property(property="email", type="string", format="email", description="The user's email.", example="john@example.com"),
     * @OA\Property(property="password", type="string", format="password", description="The user's password.", example="secret123")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="User authenticated successfully.",
     * @OA\JsonContent(
     * @OA\Property(property="user", type="object",
     * @OA\Property(property="id", type="integer", example=1),
     * @OA\Property(property="name", type="string", example="John Doe"),
     * @OA\Property(property="email", type="string", example="john@example.com")
     * ),
     * @OA\Property(property="token", type="string", description="API Bearer Token.", example="xxxxx")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error or invalid credentials.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="invalid_credentials")
     * )
     * )
     * )
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
     * @OA\Post(
     * path="/logout",
     * summary="Logout the authenticated user and revoke the current API token.",
     * tags={"Authentication"},
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=204,
     * description="User successfully logged out (No Content)."
     * ),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
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
