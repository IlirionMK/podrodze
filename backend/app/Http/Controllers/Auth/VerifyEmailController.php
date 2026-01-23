<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): JsonResponse|RedirectResponse
    {
        $frontend = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');

        $user = User::findOrFail($id);

        // invalid hash
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            if (! $request->expectsJson()) {
                return redirect()->to($frontend . '/auth/verify-email?status=invalid');
            }

            return response()->json([
                'message' => 'Invalid verification link.',
                'code' => 'invalid_verification_link',
            ], 403);
        }

        // already verified
        if ($user->hasVerifiedEmail()) {
            if (! $request->expectsJson()) {
                return redirect()->to($frontend . '/auth/verify-email?status=already');
            }

            return response()->json([
                'message' => 'Email already verified.',
                'code' => 'already_verified',
            ], 200);
        }

        // verify now
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (! $request->expectsJson()) {
            return redirect()->to($frontend . '/auth/verify-email?status=verified');
        }

        return response()->json([
            'message' => 'Email verified successfully.',
            'code' => 'verified',
        ], 200);
    }
}
