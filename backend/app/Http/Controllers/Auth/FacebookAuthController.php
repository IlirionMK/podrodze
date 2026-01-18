<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FacebookCallbackRequest;
use App\Services\Auth\FacebookOAuthService;

class FacebookAuthController extends Controller
{
    public function __construct(
        protected FacebookOAuthService $facebookOAuth
    ) {}

    public function getAuthUrl()
    {
        return response()->json([
            'url' => $this->facebookOAuth->getAuthUrl(),
        ]);
    }

    public function handleCallback(FacebookCallbackRequest $request)
    {
        try {
            $user = $this->facebookOAuth->authenticate($request->code);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            if ($msg === 'facebook_email_missing') {
                return response()->json([
                    'message' => 'Facebook OAuth failed',
                    'error'   => 'facebook_email_missing',
                ], 422);
            }

            return response()->json([
                'message' => 'Facebook OAuth failed',
                'error'   => $msg,
            ], 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }
}
