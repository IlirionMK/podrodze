<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\Trip;

class TripController extends Controller
{
    use AuthorizesRequests;


    public function index(Request $request)
    {
        $user = $request->user();

        $trips = Trip::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', function ($query) use ($user) {
                $query->where('trip_user.user_id', $user->id);
            })
            ->with(['members:id,name,email'])
            ->latest()
            ->paginate(10);

        return response()->json($trips);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $trip = Trip::create([
            'name' => $data['name'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'owner_id' => $request->user()->id,
        ]);

        return response()->json($trip, 201);
    }

    public function show(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        return $trip;
    }

    public function update(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $trip->update($data);

        return $trip->fresh();
    }

    public function destroy(Request $request, Trip $trip)
    {
        $this->authorize('delete', $trip);
        $trip->delete();

        return response()->noContent();
    }

    public function invite(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role'    => ['sometimes', 'string', 'in:member,editor'],
        ]);

        $userId = (int) $data['user_id'];
        $role   = $data['role'] ?? 'member';

        if ($trip->owner_id === $userId) {
            return response()->json(['message' => 'Owner is already a member'], 400);
        }


        if ($trip->members()->where('users.id', $userId)->exists()) {
            return response()->json(['message' => 'Already a member'], 200);
        }

        $trip->members()->syncWithoutDetaching([$userId => ['role' => $role]]);

        return response()->json(['message' => 'User invited'], 201);

    }
}
