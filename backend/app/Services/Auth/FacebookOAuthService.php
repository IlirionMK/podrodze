<?php

namespace App\Services\Auth;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

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
            $facebookUser = Socialite::driver('facebook')
                ->stateless()
                ->fields(['id', 'name', 'email'])
                ->userFromToken($accessToken);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to fetch Facebook user: ' . $e->getMessage());
        }

        $facebookId = $facebookUser->getId() ?? ($facebookUser->user['id'] ?? null);
        if (!$facebookId) {
            throw new \RuntimeException('Facebook did not return a valid user ID.');
        }

        if (!$facebookUser->getEmail()) {
            throw new \RuntimeException('facebook_email_missing');
        }

        return $this->findOrCreateUser($facebookUser, (string) $facebookId);
    }

    protected function findOrCreateUser($facebookUser, string $facebookId): User
    {
        $email = $facebookUser->getEmail();
        $name = $facebookUser->getName() ?? 'Facebook User';

        $user = User::where('facebook_id', $facebookId)->first();

        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }

        $now = Carbon::now();

        if (!$user) {
            return User::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => bcrypt(Str::random(32)),
                'facebook_id'       => $facebookId,
                'email_verified_at' => $now,
            ]);
        }

        $updates = [];

        if (!$user->facebook_id) {
            $updates['facebook_id'] = $facebookId;
        }

        if (!$user->email_verified_at) {
            $updates['email_verified_at'] = $now;
        }

        if (!empty($updates)) {
            $user->update($updates);
            $user->refresh();
        }

        return $user;
    }
}
