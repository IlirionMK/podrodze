<?php

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Tests for trip data validation.
 *
 * This class verifies that:
 * - Required fields are properly validated
 * - Date ranges are validated (start before end)
 * - Past dates are not allowed
 * - Input formats are enforced
 * - Appropriate validation messages are returned
 */
#[Group('validation')]
#[Group('trip')]
class TripValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public static function invalidTripDataProvider(): array
    {
        return [
            'missing name' => [
                ['start_date' => '2024-01-01', 'end_date' => '2024-01-10'],
                'name',
            ],
            'name too long' => [
                ['name' => str_repeat('a', 256), 'start_date' => '2024-01-01', 'end_date' => '2024-01-10'],
                'name',
            ],
            'invalid start date format' => [
                ['name' => 'Invalid Date', 'start_date' => 'not-a-date', 'end_date' => '2024-01-10'],
                'start_date',
            ],
            'end date before start date' => [
                ['name' => 'Invalid Range', 'start_date' => '2024-01-10', 'end_date' => '2024-01-01'],
                'end_date',
            ],
        ];
    }

    #[DataProvider('invalidTripDataProvider')]
    public function test_trip_validation($invalidData, $expectedError): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/trips', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors($expectedError);
    }

    public function test_trip_creation_requires_name(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/trips', [
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-10',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/trips', [
                'name' => 'Invalid Date Range',
                'start_date' => '2024-01-10',
                'end_date' => '2024-01-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_cannot_update_trip_with_invalid_date_range(): void
    {
        $trip = Trip::factory()->create([
            'owner_id' => $this->user->id,
            'start_date' => '2024-01-10',
            'end_date' => '2024-01-15',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/trips/{$trip->id}", [
                'end_date' => '2024-01-05', // Before start_date
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }
    public function test_trip_dates_cannot_be_in_the_past(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/trips', [
                'name' => 'Past Trip',
                'start_date' => now()->subDays(2)->format('Y-m-d'),
                'end_date' => now()->subDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }
}
