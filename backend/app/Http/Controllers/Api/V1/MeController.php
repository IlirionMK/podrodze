<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\DeleteMeRequest;
use App\Http\Resources\MeResource;
use App\Services\MeService;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    public function __construct(
        private readonly MeService $service
    ) {}

    public function show(Request $request)
    {
        return new MeResource($this->service->get($request->user()));
    }

    public function update(UpdateMeRequest $request)
    {
        $user = $this->service->updateProfile($request->user(), $request->validated());
        return new MeResource($user);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $this->service->changePassword($request->user(), $request->validated());
        return response()->json(['ok' => true]);
    }

    public function destroy(DeleteMeRequest $request)
    {
        $this->service->deleteAccount($request->user(), $request->validated());
        return response()->json(['ok' => true]);
    }
}
