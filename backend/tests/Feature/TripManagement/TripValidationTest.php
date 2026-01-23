<?php

declare(strict_types=1);

namespace Tests\Feature\TripManagement;

use App\Models\Trip;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase\TripTestCase;

/**
 * Tests for trip data validation.
 *
 * This class verifies that:
 * - Required trip fields are validated
 * - Date ranges are properly validated
 * - Invalid data is rejected with appropriate errors
 * - Custom validation rules work as expected
 *
 * @covers \App\Http\Requests\TripRequest
 */
#[Group('validation')]
#[Group('trip')]
class TripValidationTest extends TripTestCase
{
    protected bool $enableRateLimiting = false;

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
        $response = $this->actingAsUser($this->owner)
            ->postJson('/api/v1/trips', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors($expectedError);
    }

}
