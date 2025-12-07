<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * @OA\Post(
     * path="/forgot-password",
     * summary="Handle an incoming password reset link request.",
     * tags={"Authentication"},
     * @OA\RequestBody(
     * required=true,
     * description="User email to send the reset link to.",
     * @OA\JsonContent(
     * required={"email"},
     * @OA\Property(property="email", type="string", format="email", description="The user's email.", example="user@example.com")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Password reset link sent successfully. Note: This endpoint always returns a 200 status for security reasons, even if the email does not exist.",
     * @OA\JsonContent(
     * @OA\Property(property="status", type="string", example="We have emailed your password reset link!")
     * )
     * ),
     * @OA\Response(
     * response=422,
     * description="Validation error (e.g., invalid email format).",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="The given data was invalid."),
     * @OA\Property(property="errors", type="object",
     * @OA\Property(property="email", type="array", @OA\Items(type="string", example="The email field is required."))
     * )
     * )
     * )
     * )
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            // Laravel's default behavior is to throw ValidationException if the link cannot be sent,
            // which often includes 'User not found'.
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['status' => __($status)]);
    }
}
