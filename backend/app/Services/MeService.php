<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class MeService
{
    public function get(User $user): User
    {
        return $user;
    }

    public function updateProfile(User $user, array $data): User
    {
        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = $data['name'];
        }

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $updates['email'] = $data['email'];
            $updates['email_verified_at'] = null;
        }

        if (!empty($updates)) {
            $user->fill($updates);
            $user->save();

            if (array_key_exists('email', $updates) && $user instanceof MustVerifyEmail) {
                $user->sendEmailVerificationNotification();
            }
        }

        return $user->fresh();
    }

    public function changePassword(User $user, array $data): void
    {
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['new_password']),
        ])->save();

        $user->tokens()->delete();
    }

    public function deleteAccount(User $user, array $data): void
    {
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
    }
    public function deleteAccountAsSystem(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
    }

}
