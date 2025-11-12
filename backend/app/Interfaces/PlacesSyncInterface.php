<?php

namespace App\Interfaces;

interface PlacesSyncInterface
{
    /**
     * Fetch and store nearby interesting places from external API.
     *
     * @param float $lat
     * @param float $lon
     * @param int $radius
     * @return array{added: int, updated: int}
     */
    public function fetchAndStore(float $lat, float $lon, int $radius = 3000): array;
}
