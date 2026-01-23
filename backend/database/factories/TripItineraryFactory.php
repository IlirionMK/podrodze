<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripItinerary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TripItinerary>
 */
class TripItineraryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TripItinerary::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'schedule' => [
                'days' => [
                    [
                        'date' => now()->format('Y-m-d'),
                        'activities' => [
                            [
                                'time' => '09:00',
                                'description' => 'Test activity',
                                'place_id' => null,
                            ]
                        ]
                    ]
                ]
            ],
            'day_count' => 1,
            'generated_at' => now(),
        ];
    }

    /**
     * Create an itinerary for a specific trip.
     */
    public function forTrip(Trip $trip): static
    {
        return $this->state(fn (array $attributes) => [
            'trip_id' => $trip->id,
        ]);
    }

    /**
     * Create an itinerary with custom schedule.
     */
    public function withSchedule(array $schedule): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule' => $schedule,
        ]);
    }

    /**
     * Create an itinerary with specific day count.
     */
    public function withDayCount(int $dayCount): static
    {
        return $this->state(fn (array $attributes) => [
            'day_count' => $dayCount,
        ]);
    }
}
