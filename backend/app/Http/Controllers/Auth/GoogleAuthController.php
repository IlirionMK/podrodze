<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Services\Auth\GoogleOAuthService;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleOAuthService $googleOAuth
    ) {}

    public function getAuthUrl()
    {
        return response()->json([
            'url' => $this->googleOAuth->getAuthUrl(),
        ]);
    }

    public function handleCallback(GoogleCallbackRequest $request)
    {
        try {
            $user = $this->googleOAuth->authenticate($request->code);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Google OAuth failed',
                'error'   => $e->getMessage(),
            ], 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }
}
