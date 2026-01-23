<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserBanRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Resources\UserMiniResource;
use App\Models\User;
use App\Services\Admin\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $search = trim((string) $request->query('search', ''));

        $userIdRaw = $request->query('user_id', null);
        $userId = ($userIdRaw !== null && $userIdRaw !== '') ? (int) $userIdRaw : null;

        $role = trim((string) $request->query('role', ''));
        $role = $role !== '' ? $role : null;

        // banned: expected values '1' | '0' (or null/empty)
        $bannedRaw = $request->query('banned', null);
        $banned = ($bannedRaw !== null && $bannedRaw !== '') ? trim((string) $bannedRaw) : null;

        $users = $this->service->paginateUsers(
            search: $search,
            perPage: $perPage,
            userId: $userId,
            role: $role,
            banned: $banned
        );

        return UserMiniResource::collection($users);
    }

    public function setRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $result = $this->service->setRole(
            target: $user,
            role: (string) $request->validated()['role'],
            actor: $request->user()
        );

        return response()->json($result['payload'], $result['status']);
    }

    public function setBanned(UpdateUserBanRequest $request, User $user): JsonResponse
    {
        $result = $this->service->setBanned(
            target: $user,
            banned: (bool) $request->validated()['banned'],
            actor: $request->user()
        );

        return response()->json($result['payload'], $result['status']);
    }
}
