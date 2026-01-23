<?php

namespace Tests\Unit\Http\Resources;

use App\DTO\Trip\TripPlaceVoteSummary;
use App\Http\Resources\TripPlaceVoteSummaryResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripPlaceVoteSummaryResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_trip_place_vote_summary_to_array(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 123,
            avg_score: 4.5,
            votes: 8,
            my_score: 4
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(123, $result['place_id']);
        $this->assertEquals(4.5, $result['avg_score']);
        $this->assertEquals(8, $result['votes']);
        $this->assertEquals(4, $result['my_score']);
    }

    #[Test]
    public function it_handles_null_my_score(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 456,
            avg_score: 3.8,
            votes: 12,
            my_score: null
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(456, $result['place_id']);
        $this->assertEquals(3.8, $result['avg_score']);
        $this->assertEquals(12, $result['votes']);
        $this->assertNull($result['my_score']);
    }

    #[Test]
    public function it_handles_zero_votes(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 789,
            avg_score: null,
            votes: 0,
            my_score: null
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(789, $result['place_id']);
        $this->assertNull($result['avg_score']);
        $this->assertEquals(0, $result['votes']);
        $this->assertNull($result['my_score']);
    }

    #[Test]
    public function it_handles_decimal_average_scores(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 101,
            avg_score: 4.75,
            votes: 4,
            my_score: 5
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(101, $result['place_id']);
        $this->assertEquals(4.75, $result['avg_score']);
        $this->assertEquals(4, $result['votes']);
        $this->assertEquals(5, $result['my_score']);
    }

    #[Test]
    public function it_handles_edge_case_scores(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 202,
            avg_score: 1.0,
            votes: 1,
            my_score: 1
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(202, $result['place_id']);
        $this->assertEquals(1.0, $result['avg_score']);
        $this->assertEquals(1, $result['votes']);
        $this->assertEquals(1, $result['my_score']);
    }

    #[Test]
    public function it_handles_perfect_scores(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 303,
            avg_score: 5.0,
            votes: 25,
            my_score: 5
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(303, $result['place_id']);
        $this->assertEquals(5.0, $result['avg_score']);
        $this->assertEquals(25, $result['votes']);
        $this->assertEquals(5, $result['my_score']);
    }

    #[Test]
    public function it_returns_correct_structure(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 404,
            avg_score: 3.5,
            votes: 10,
            my_score: 3
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $expectedKeys = ['place_id', 'avg_score', 'votes', 'my_score'];
        $this->assertEquals($expectedKeys, array_keys($result));
    }

    #[Test]
    public function it_handles_large_vote_counts(): void
    {
        $voteSummary = new TripPlaceVoteSummary(
            place_id: 505,
            avg_score: 4.2,
            votes: 98765,
            my_score: 4
        );

        $resource = new TripPlaceVoteSummaryResource($voteSummary);
        $result = $resource->toArray(request());

        $this->assertEquals(505, $result['place_id']);
        $this->assertEquals(4.2, $result['avg_score']);
        $this->assertEquals(98765, $result['votes']);
        $this->assertEquals(4, $result['my_score']);
    }
}
