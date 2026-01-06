<?php

namespace App\Services\Auth;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;

class FacebookOAuthService
{
    public function getAuthUrl(): string
    {
        return Socialite::driver('facebook')
            ->stateless()
            ->scopes(['email', 'public_profile'])
            ->redirect()
            ->getTargetUrl();
    }

    public function authenticate(string $code): User
    {
        try {
            $tokenResponse = Socialite::driver('facebook')
                ->stateless()
                ->getAccessTokenResponse($code);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to exchange authorization code: ' . $e->getMessage());
        }

        if (!isset($tokenResponse['access_token'])) {
            throw new \RuntimeException('Facebook did not return an access token.');
        }

        $accessToken = $tokenResponse['access_token'];

        try {
            // Важно: fields() — чтобы запросить email явно (Facebook иногда не возвращает его без fields)
            $facebookUser = Socialite::driver('facebook')
                ->stateless()
                ->fields(['id', 'name', 'email'])
                ->userFromToken($accessToken);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to fetch Facebook user: ' . $e->getMessage());
        }

        // Facebook ID
        $facebookId = $facebookUser->getId() ?? ($facebookUser->user['id'] ?? null);
        if (!$facebookId) {
            throw new \RuntimeException('Facebook did not return a valid user ID.');
        }

        // В стиле Google: если email нет — ошибка (у Facebook это реально возможно)
        if (!$facebookUser->getEmail()) {
            throw new \RuntimeException('Facebook account does not provide an email address.');
        }

        return $this->findOrCreateUser($facebookUser, $facebookId);
    }

    protected function findOrCreateUser($facebookUser, string $facebookId): User
    {
        $email = $facebookUser->getEmail();

        $user = User::where('facebook_id', $facebookId)->first();

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            return User::create([
                'name'        => $facebookUser->getName() ?? 'Facebook User',
                'email'       => $email,
                'password'    => bcrypt(Str::random(32)),
                'facebook_id' => $facebookId,
            ]);
        }

        if (!$user->facebook_id) {
            $user->update(['facebook_id' => $facebookId]);
        }

        return $user;
    }
}
