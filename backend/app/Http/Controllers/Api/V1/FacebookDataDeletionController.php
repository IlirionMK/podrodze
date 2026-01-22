<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Facebook\FacebookSignedRequest;
use App\Services\MeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class FacebookDataDeletionController extends Controller
{
    public function __construct(
        private readonly MeService $meService
    ) {}

    public function handle(Request $request)
    {
        $signedRequest = (string) $request->input('signed_request', '');

        if ($signedRequest === '') {
            return response()->json(['error' => 'signed_request_missing'], 400);
        }

        try {
            $payload = FacebookSignedRequest::decodeAndVerify(
                $signedRequest,
                (string) config('services.facebook.client_secret')
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => 'signed_request_invalid'], 400);
        }

        $facebookUserId = $payload['user_id'] ?? null;
        if (!$facebookUserId) {
            return response()->json(['error' => 'facebook_user_id_missing'], 400);
        }

        $user = User::where('facebook_id', $facebookUserId)->first();

        if ($user) {
            $this->meService->deleteAccountAsSystem($user);
        }

        $code = Str::uuid()->toString();

        return response()->json([
            'url' => url("/api/v1/facebook/data-deletion/status/{$code}"),
            'confirmation_code' => $code,
        ]);
    }

    public function status(string $code)
    {
        return response()->json([
            'confirmation_code' => $code,
            'status' => 'completed',
        ]);
    }
}
