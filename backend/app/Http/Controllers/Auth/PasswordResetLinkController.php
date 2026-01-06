<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $validated['email']]);

        // Do not leak whether the email exists
        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['status' => __($status)], 200);
        }

        // Still return 200 to prevent user enumeration
        return response()->json([
            'status' => __('If your email address exists in our system, you will receive a password reset link.'),
        ], 200);
    }
}
