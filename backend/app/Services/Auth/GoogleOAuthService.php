<?php

namespace App\Services\Auth;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class GoogleOAuthService
{
    public function getAuthUrl(): string
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    public function authenticate(string $code): User
    {
        try {
            $tokenResponse = Socialite::driver('google')
                ->stateless()
                ->getAccessTokenResponse($code);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to exchange authorization code: ' . $e->getMessage());
        }

        if (!isset($tokenResponse['access_token'])) {
            throw new \RuntimeException("Google did not return an access token.");
        }

        $accessToken = $tokenResponse['access_token'];

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($accessToken);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to fetch Google user: ' . $e->getMessage());
        }

        if (!$googleUser->getEmail()) {
            throw new \RuntimeException('Google account does not provide an email address.');
        }

        return $this->findOrCreateUser($googleUser);
    }

    protected function findOrCreateUser($googleUser): User
    {
        $googleId = $googleUser->getId() ?? ($googleUser->user['sub'] ?? null);

        if (!$googleId) {
            throw new \RuntimeException("Google did not return a valid user ID.");
        }

        $user = User::where('google_id', $googleId)->first();

        if (!$user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();
        }

        if (!$user) {
            return User::create([
                'name'      => $googleUser->getName() ?? 'Google User',
                'email'     => $googleUser->getEmail(),
                'password'  => bcrypt(Str::random(32)),
                'google_id' => $googleId,
            ]);
        }

        if (!$user->google_id) {
            $user->update(['google_id' => $googleId]);
        }

        return $user;
    }
}
