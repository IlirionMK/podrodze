<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Tests for trip location management.
 *
 * This class verifies that:
 * - Location coordinates are properly validated
 * - Trip start location can be updated
 * - Invalid location data is rejected
 * - Partial updates are handled correctly
 * - Location data is properly formatted in responses
 */
#[Group('location')]
#[Group('trip')]
class TripLocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Trip $trip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_latitude' => null,
            'start_longitude' => null,
        ]);
    }

    public static function invalidLocationDataProvider(): array
    {
        return [
            'invalid latitude (too high)' => [100, 0, 'start_latitude'],
            'invalid latitude (too low)' => [-91, 0, 'start_latitude'],
            'invalid longitude (too high)' => [0, 181, 'start_longitude'],
            'invalid longitude (too low)' => [0, -181, 'start_longitude'],
            'non-numeric latitude' => ['invalid', 0, 'start_latitude'],
            'non-numeric longitude' => [0, 'invalid', 'start_longitude'],
        ];
    }

    #[DataProvider('invalidLocationDataProvider')]
    public function test_validate_location_coordinates($latitude, $longitude, $errorField): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
                'start_latitude' => $latitude,
                'start_longitude' => $longitude,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors($errorField);
    }

    public function test_can_update_trip_with_valid_location(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
                'start_latitude' => 51.1079,
                'start_longitude' => 17.0385,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Start location updated',
                'data' => [
                    'start_latitude' => '51.107900',
                    'start_longitude' => '17.038500',
                ]
            ]);

        $this->trip->refresh();
        $this->assertEquals('51.107900', $this->trip->start_latitude);
        $this->assertEquals('17.038500', $this->trip->start_longitude);
    }
    public function test_cannot_update_location_with_missing_coordinates(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
                'start_latitude' => null,
                'start_longitude' => 17.0385,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_latitude']);
    }

    public function test_can_update_only_specific_location_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/trips/{$this->trip->id}/start-location", [
                'start_latitude' => 51.1079,
                'start_longitude' => 17.0385,
                'name' => 'New Location Name'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('trips', [
            'id' => $this->trip->id,
            'name' => 'New Location Name'
        ]);
    }

}
