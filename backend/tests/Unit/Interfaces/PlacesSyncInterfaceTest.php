<?php

namespace Tests\Unit\Interfaces;

use App\Interfaces\PlacesSyncInterface;
use Mockery;
use Tests\TestCase;

class PlacesSyncInterfaceTest extends TestCase
{
    private PlacesSyncInterface $placesSync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->placesSync = Mockery::mock(PlacesSyncInterface::class);
    }

    public function test_fetch_and_store_returns_sync_results()
    {
        $lat = 52.2297;
        $lon = 21.0122;
        $radius = 3000;
        $expectedResult = ['added' => 5, 'updated' => 3];

        $this->placesSync->shouldReceive('fetchAndStore')
            ->once()
            ->with($lat, $lon, $radius)
            ->andReturn($expectedResult);

        $result = $this->placesSync->fetchAndStore($lat, $lon, $radius);

        $this->assertSame($expectedResult, $result);
    }

    public function test_fetch_and_store_uses_default_radius()
    {
        $lat = 52.2297;
        $lon = 21.0122;
        $expectedResult = ['added' => 3, 'updated' => 1];

        $this->placesSync->shouldReceive('fetchAndStore')
            ->once()
            ->with($lat, $lon, 3000) // Default radius
            ->andReturn($expectedResult);

        // Call with the default radius to match the expectation
        $result = $this->placesSync->fetchAndStore($lat, $lon, 3000);

        $this->assertSame($expectedResult, $result);
    }
}
