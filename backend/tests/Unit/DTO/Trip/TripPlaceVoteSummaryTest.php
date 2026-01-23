<?php

namespace Tests\Unit\DTO\Trip;

use App\DTO\Trip\TripPlaceVoteSummary;
use PHPUnit\Framework\TestCase;

class TripPlaceVoteSummaryTest extends TestCase
{
    public function test_it_creates_vote_summary()
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 1,
            avg_score: 4.5,
            votes: 10,
            my_score: 5
        );

        $this->assertEquals(1, $voteSummary->place_id);
        $this->assertEquals(4.5, $voteSummary->avg_score);
        $this->assertEquals(10, $voteSummary->votes);
        $this->assertEquals(5, $voteSummary->my_score);
    }

    public function test_it_creates_from_row()
    {
        $row = (object) [
            'place_id' => 1,
            'avg_score' => 4.567,
            'votes' => 10,
            'my_score' => 5
        ];

        $voteSummary = TripPlaceVoteSummary::fromRow($row);

        $this->assertEquals(1, $voteSummary->place_id);
        $this->assertEquals(4.57, $voteSummary->avg_score); // Rounded to 2 decimal places
        $this->assertEquals(10, $voteSummary->votes);
        $this->assertEquals(5, $voteSummary->my_score);
    }

    public function test_it_handles_null_values()
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 1,
            avg_score: null,
            votes: 0,
            my_score: null
        );

        $this->assertNull($voteSummary->avg_score);
        $this->assertEquals(0, $voteSummary->votes);
        $this->assertNull($voteSummary->my_score);
    }

    public function test_it_serializes_to_json()
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 1,
            avg_score: 4.5,
            votes: 10,
            my_score: 5
        );

        $expected = [
            'place_id' => 1,
            'avg_score' => 4.5,
            'votes' => 10,
            'my_score' => 5,
        ];

        $this->assertEquals($expected, $voteSummary->jsonSerialize());
    }
}
