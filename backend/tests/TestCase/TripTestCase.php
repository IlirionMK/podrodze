<?php

declare(strict_types=1);

namespace Tests\TestCase;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Base test case for trip-related tests.
 * Provides common functionality for testing trip management features.
 */
abstract class TripTestCase extends AuthenticatedTestCase
{
    protected User $owner;
    protected User $editor;
    protected User $member;
    protected User $otherUser;
    protected Trip $trip;
    protected array $defaultTripData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createUser();
        $this->editor = $this->createUser();
        $this->member = $this->createUser();
        $this->otherUser = $this->createUser();
        $this->trip = $this->createTrip(['owner_id' => $this->owner->id]);

        $this->defaultTripData = [
            'name' => 'Test Trip ' . Str::random(5),
            'description' => 'Test trip description',
            'start_date' => now()->addDays()->format('Y-m-d'),
            'end_date' => now()->addDays(7)->format('Y-m-d'),
        ];

        $this->actingAs($this->owner);
    }

    /**
     * Create a trip with the given attributes.
     *
     * @param array<string, mixed> $attributes Trip attributes to override defaults
     */
    protected function createTrip(array $attributes): Trip
    {
        return Trip::factory()->create($attributes);
    }

    /**
     * Create a trip with members.
     */
    /**
     * Create a trip with members and their roles
     *
     * @param array $members Array of users with roles, e.g., ['user1' => 'editor', 'user2' => 'member']
     * @param array $tripAttributes Additional trip attributes
     */
    protected function createTripWithMembers(array $members = [], array $tripAttributes = []): Trip
    {
        if (!isset($tripAttributes['owner_id'])) {
            $tripAttributes['owner_id'] = $this->owner->id;
        }

        $trip = $this->createTrip($tripAttributes);

        foreach ($members as $user => $role) {
            if (is_array($role)) {
                $userId = isset($role['user']) ?
                    (is_object($role['user']) ? $role['user']->getKey() : $role['user']) :
                    ($role['user_id'] ?? $this->createUser()->getKey());
                $roleValue = $role['role'] ?? 'member';
                $status = $role['status'] ?? 'accepted';
            } else {
                $userId = is_object($user) ? $user->getKey() : $user;
                $roleValue = $role;
                $status = 'accepted';
            }

            $trip->members()->attach($userId, [
                'status' => $status,
                'role' => $roleValue,
                'joined_at' => now()
            ]);
        }

        return $trip->load('members');
    }

    /**
     * Assert the trip structure in the response.
     */
    protected function assertTripStructure($response): void
    {
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'start_date',
                'end_date',
                'owner_id',
                'created_at',
                'updated_at',
            ]
        ]);
    }

    /**
     * Get valid trip data for testing.
     *
     * @param array<string, mixed> $overrides Attributes to override the default trip data
     * @return array<string, mixed> Merged trip data with overrides
     */
    protected function getValidTripData(array $overrides = []): array
    {
        return array_merge($this->defaultTripData, $overrides);
    }

    /**
     * Get invalid trip data for validation testing.
     */
    public static function invalidTripDataProvider(): array
    {
        return [
            'missing name' => [
                ['start_date' => now()->addDay()->format('Y-m-d'), 'end_date' => now()->addWeek()->format('Y-m-d')],
                'name',
            ],
            'name too long' => [
                ['name' => str_repeat('a', 256), 'start_date' => now()->addDay()->format('Y-m-d'), 'end_date' => now()->addWeek()->format('Y-m-d')],
                'name',
            ],
            'invalid start date' => [
                ['start_date' => 'invalid-date', 'end_date' => now()->addWeek()->format('Y-m-d')],
                'start_date',
            ],
            'end date before start date' => [
                ['start_date' => now()->addWeek()->format('Y-m-d'), 'end_date' => now()->addDay()->format('Y-m-d')],
                'end_date',
            ],
        ];
    }
}
