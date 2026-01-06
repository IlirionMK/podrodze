<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\Activity\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminUserService
{
    public function __construct(
        private readonly ActivityLogger $logger
    ) {}

    public function paginateUsers(string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->orderByDesc('id');

        $search = trim($search);
        if ($search !== '') {
            $like = "%$search%";

            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        return $query->paginate($perPage);
    }

    public function setRole(User $target, string $role, ?User $actor): array
    {
        if ($actor instanceof User && $actor->getKey() === $target->getKey() && $role !== 'admin') {
            return [
                'ok' => false,
                'status' => 422,
                'payload' => ['message' => 'You cannot remove your own admin role.'],
            ];
        }

        $before = (string) $target->getAttribute('role');

        $target->forceFill(['role' => $role])->save();

        $this->logger->add($actor, 'admin.user.role_updated', $target, [
            'before' => $before,
            'after' => $role,
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'data' => [
                    'id' => $target->getKey(),
                    'role' => (string) $target->getAttribute('role'),
                ],
            ],
        ];
    }

    public function setBanned(User $target, bool $banned, ?User $actor): array
    {
        if ($actor instanceof User && $actor->getKey() === $target->getKey() && $banned === true) {
            return [
                'ok' => false,
                'status' => 422,
                'payload' => ['message' => 'You cannot ban your own account.'],
            ];
        }

        $before = $target->getAttribute('banned_at') !== null;

        $target->forceFill([
            'banned_at' => $banned ? now() : null,
        ])->save();

        if ($banned) {
            $target->tokens()->delete();
        }

        $this->logger->add($actor, 'admin.user.ban_updated', $target, [
            'before' => $before,
            'after' => $banned,
        ]);

        return [
            'ok' => true,
            'status' => 200,
            'payload' => [
                'data' => [
                    'id' => $target->getKey(),
                    'banned' => $target->getAttribute('banned_at') !== null,
                    'banned_at' => optional($target->getAttribute('banned_at'))?->toISOString(),
                ],
            ],
        ];
    }
}
