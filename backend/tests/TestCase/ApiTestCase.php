<?php

declare(strict_types=1);

namespace Tests\TestCase;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Base test case for API related tests.
 * Provides common functionality for making API requests and asserting responses.
 */
abstract class ApiTestCase extends TestCase
{

    /**
     * The base API URL prefix.
     */
    protected string $apiUrl = '/api/v1';

    /**
     * Whether rate limiting is enabled for tests.
     * Set to true in child classes to enable rate limiting.
     *
     * @var bool
     */
    protected bool $enableRateLimiting = false;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRateLimitConfig = config('auth.guards.api.throttle');
        $this->setupRateLimiting();
        $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Make an API request with JSON headers.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $uri API endpoint URI (without /api/v1 prefix)
     * @param array<string, mixed> $data Request data
     * @param array<string, string> $headers Additional headers
     * @return TestResponse
     */
    protected function apiRequest(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $uri = '/' . ltrim($uri, '/');
        $uri = str_starts_with($uri, $this->apiUrl) ? $uri : $this->apiUrl . $uri;

        return $this->json($method, $uri, $data, $headers);
    }

    /**
     * Assert that the response has the given JSON structure.
     *
     * @param array<string, mixed> $structure Expected JSON structure
     * @param TestResponse $response Response to test
     */
    protected function assertResponseStructure(
        array|string $structure,
        TestResponse $response,
    ): void {
        $response->assertStatus(200);

        if (is_string($structure)) {
            $structure = [$structure => ['*']];
        }

        $response->assertJsonStructure($structure);
    }

    /**
     * Assert that the response has the given status code and optional JSON structure.
     *
     * @param TestResponse $response Response to test
     * @param int $status Expected HTTP status code
     * @param array<string, mixed>|null $structure Optional expected JSON structure
     */
    protected function assertJsonResponse(
        TestResponse $response,
        int          $status,
        ?array       $structure = null,
    ): void {
        $response->assertStatus($status);

        if ($structure !== null) {
            $this->assertResponseStructure($structure, $response);
        }
    }

    /**
     * Assert that the response contains the given validation errors.
     *
     * @param array<string> $errors Expected validation error keys
     * @param TestResponse $response Response to test
     */
    protected function assertValidationErrors(
        array        $errors,
        TestResponse $response
    ): void {
        $response->assertStatus(422)
            ->assertJsonValidationErrors($errors);
    }

    /**
     * Assert that the response has a successful status code (2xx).
     *
     * @param TestResponse $response
     * @return void
     */
    protected function assertSuccessResponse(TestResponse $response): void
    {
        $response->assertOk();
    }

    /**
     * Assert that the response has a created status code (201).
     *
     * @param TestResponse $response
     * @return void
     */
    protected function assertCreatedResponse(TestResponse $response): void
    {
        $response->assertCreated();
    }

    /**
     * Assert that the response has an unprocessable entity status code (422).
     *
     * @param TestResponse $response
     * @return void
     */
    protected function assertUnprocessableResponse(TestResponse $response): void
    {
        $response->assertUnprocessable();
    }

    /**
     * Set up rate limiting for tests.
     * This method should be called in the setUp method of test classes that test rate limiting.
     *
     * @return void
     */
    protected function setUpRateLimiting(): void
    {
        if ($this->enableRateLimiting) {
            config([
                'auth.guards.api.throttle' => [
                    'enabled' => true,
                    'max_attempts' => 60,
                    'decay_minutes' => 1,
                ]
            ]);
        } else {
            config([
                'auth.guards.api.throttle' => [
                    'enabled' => false,
                    'max_attempts' => 60,
                    'decay_minutes' => 1,
                ]
            ]);
        }

        $this->clearRateLimits();
    }

    /**
     * Clear rate limiting for tests.
     * This method should be called in the tearDown or setUp method of test classes that test rate limiting.
     *
     * @return void
     */
    protected function clearRateLimits(): void
    {
        try {
            $this->app['cache']->driver('file')->clear();
        } catch (Exception) {
        }
    }


    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        $this->clearRateLimits();

        if (property_exists($this, 'originalRateLimitConfig') && $this->originalRateLimitConfig) {
            config(['auth.guards.api.throttle' => $this->originalRateLimitConfig]);
        }

        parent::tearDown();
    }

    /**
     * Create a test user.
     *
     * @param array<string, mixed> $attributes
     * @return User
     */
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ], $attributes));
    }

    /**
     * Assert that the response indicates rate limiting is in effect.
     *
     * @param TestResponse $response
     * @return void
     */
    protected function assertRateLimited(TestResponse $response): void
    {
        $response->assertTooManyRequests();
    }

    /**
     * Assert that the response has a no content status code (204).
     *
     * @param TestResponse $response
     * @return void
     */
    protected function assertNoContentResponse(TestResponse $response): void
    {
        $response->assertNoContent();
    }

    /**
     * Assert that the response has an unauthorized status code (401).
     *
     * @param TestResponse $response
     * @return void
     */
    protected function assertUnauthorizedResponse(TestResponse $response): void
    {
        $response->assertUnauthorized();
    }
}
