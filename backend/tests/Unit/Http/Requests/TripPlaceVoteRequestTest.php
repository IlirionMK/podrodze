<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\TripPlaceVoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TripPlaceVoteRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test route to test the request
        Route::post('/test-route', function (TripPlaceVoteRequest $request) {
            return response()->json($request->validated());
        })->middleware('api');
    }

    #[Test]
    public function it_requires_score()
    {
        $response = $this->postJson('/test-route', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('score');
    }

    #[Test]
    public function it_validates_score_is_an_integer()
    {
        $response = $this->postJson('/test-route', [
            'score' => 'not-an-integer',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('score');
    }

    #[Test]
    public function it_validates_score_min_value()
    {
        $response = $this->postJson('/test-route', [
            'score' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('score');
    }

    #[Test]
    public function it_validates_score_max_value()
    {
        $response = $this->postJson('/test-route', [
            'score' => 6,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('score');
    }

    #[Test]
    public function it_accepts_valid_scores()
    {
        foreach (range(1, 5) as $score) {
            $response = $this->postJson('/test-route', [
                'score' => $score,
            ]);

            $response->assertStatus(200);
            $this->assertEquals($score, $response->json('score'));
        }
    }

    #[Test]
    public function it_rejects_extra_fields()
    {
        $response = $this->postJson('/test-route', [
            'score' => 5,
            'extra_field' => 'should not be here',
        ]);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('extra_field', $response->json());
    }

    #[Test]
    public function it_validates_score_is_required_even_if_other_fields_are_present()
    {
        $response = $this->postJson('/test-route', [
            'other_field' => 'value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('score');
    }
}
