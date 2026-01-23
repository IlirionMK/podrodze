<?php

namespace Tests\Unit\Http\Resources;

use App\DTO\Trip\TripVote;
use App\Http\Resources\TripVoteResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripVoteResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_trip_vote_to_array(): void
    {
        $vote = new TripVote(
            place_id: 123,
            my_score: 4,
            avg_score: 4.5,
            votes: 8
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(123, $result['place_id']);
        $this->assertEquals(4, $result['my_score']);
        $this->assertEquals(4.5, $result['avg_score']);
        $this->assertEquals(8, $result['votes']);
    }

    #[Test]
    public function it_handles_null_my_score(): void
    {
        $vote = new TripVote(
            place_id: 456,
            my_score: null,
            avg_score: 3.8,
            votes: 12
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(456, $result['place_id']);
        $this->assertNull($result['my_score']);
        $this->assertEquals(3.8, $result['avg_score']);
        $this->assertEquals(12, $result['votes']);
    }

    #[Test]
    public function it_handles_null_average_score(): void
    {
        $vote = new TripVote(
            place_id: 789,
            my_score: 5,
            avg_score: null,
            votes: 1
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(789, $result['place_id']);
        $this->assertEquals(5, $result['my_score']);
        $this->assertNull($result['avg_score']);
        $this->assertEquals(1, $result['votes']);
    }

    #[Test]
    public function it_handles_zero_votes(): void
    {
        $vote = new TripVote(
            place_id: 101,
            my_score: null,
            avg_score: null,
            votes: 0
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(101, $result['place_id']);
        $this->assertNull($result['my_score']);
        $this->assertNull($result['avg_score']);
        $this->assertEquals(0, $result['votes']);
    }

    #[Test]
    public function it_handles_decimal_average_scores(): void
    {
        $vote = new TripVote(
            place_id: 202,
            my_score: 4,
            avg_score: 4.75,
            votes: 4
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(202, $result['place_id']);
        $this->assertEquals(4, $result['my_score']);
        $this->assertEquals(4.75, $result['avg_score']);
        $this->assertEquals(4, $result['votes']);
    }

    #[Test]
    public function it_handles_edge_case_scores(): void
    {
        $vote = new TripVote(
            place_id: 303,
            my_score: 1,
            avg_score: 1.0,
            votes: 1
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(303, $result['place_id']);
        $this->assertEquals(1, $result['my_score']);
        $this->assertEquals(1.0, $result['avg_score']);
        $this->assertEquals(1, $result['votes']);
    }

    #[Test]
    public function it_handles_perfect_scores(): void
    {
        $vote = new TripVote(
            place_id: 404,
            my_score: 5,
            avg_score: 5.0,
            votes: 25
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(404, $result['place_id']);
        $this->assertEquals(5, $result['my_score']);
        $this->assertEquals(5.0, $result['avg_score']);
        $this->assertEquals(25, $result['votes']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $vote = new TripVote(
            place_id: 505,
            my_score: 3,
            avg_score: 3.5,
            votes: 10
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $expectedKeys = ['place_id', 'my_score', 'avg_score', 'votes'];
        $this->assertEquals($expectedKeys, array_keys($result));
    }

    #[Test]
    public function it_handles_large_vote_counts(): void
    {
        $vote = new TripVote(
            place_id: 606,
            my_score: 4,
            avg_score: 4.2,
            votes: 98765
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(606, $result['place_id']);
        $this->assertEquals(4, $result['my_score']);
        $this->assertEquals(4.2, $result['avg_score']);
        $this->assertEquals(98765, $result['votes']);
    }

    #[Test]
    public function it_handles_string_numeric_values(): void
    {
        $vote = new TripVote(
            place_id: '707',
            my_score: '4',
            avg_score: '4.3',
            votes: '15'
        );

        $resource = new TripVoteResource($vote);
        $result = $resource->toArray(request());

        $this->assertEquals(707, $result['place_id']); // Should be cast to int
        $this->assertEquals(4, $result['my_score']); // Should be cast to int
        $this->assertEquals(4.3, $result['avg_score']); // Should be cast to float
        $this->assertEquals(15, $result['votes']); // Should be cast to int
    }
}
