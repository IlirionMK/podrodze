<?php

namespace Tests\Unit\DTO\Trip;

use App\DTO\Trip\TripVote;
use PHPUnit\Framework\TestCase;

class TripVoteTest extends TestCase
{
    public function test_it_creates_trip_vote()
    {
        $tripVote = new TripVote(
            place_id: 1,
            my_score: 5,
            avg_score: 4.5,
            votes: 10
        );

        $this->assertEquals(1, $tripVote->place_id);
        $this->assertEquals(5, $tripVote->my_score);
        $this->assertEquals(4.5, $tripVote->avg_score);
        $this->assertEquals(10, $tripVote->votes);
    }

    public function test_it_creates_from_aggregate()
    {
        $row = (object) [
            'avg_score' => 4.5678,
            'votes' => 15
        ];

        $tripVote = TripVote::fromAggregate(1, 5, $row);

        $this->assertEquals(1, $tripVote->place_id);
        $this->assertEquals(5, $tripVote->my_score);
        $this->assertEquals(4.57, $tripVote->avg_score); // Rounded to 2 decimal places
        $this->assertEquals(15, $tripVote->votes);
    }

    public function test_it_handles_null_values()
    {
        $tripVote = new TripVote(
            place_id: 1,
            my_score: null,
            avg_score: null,
            votes: 0
        );

        $this->assertNull($tripVote->my_score);
        $this->assertNull($tripVote->avg_score);
    }

    public function test_it_serializes_to_json()
    {
        $tripVote = new TripVote(
            place_id: 1,
            my_score: 5,
            avg_score: 4.5,
            votes: 10
        );

        $expected = [
            'place_id' => 1,
            'my_score' => 5,
            'avg_score' => 4.5,
            'votes' => 10,
        ];

        $this->assertEquals($expected, $tripVote->jsonSerialize());
    }
}
