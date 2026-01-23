<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseFrameworkTestCase;
use Tests\Traits\CreatesApplication;
use Tests\Traits\HandlesRateLimiting;
use Tests\Traits\HandlesCsrfTokens;

/**
 * Base test case class for all application tests.
 *
 * This is the foundation class that all other test cases should extend.
 * It provides the basic testing environment setup and common testing traits.
 *
 * @package Tests
 * @uses \Illuminate\Foundation\Testing\TestCase
 * @see \Tests\Traits\CreatesApplication
 * @see \Tests\Traits\HandlesRateLimiting
 * @see \Tests\Traits\HandlesCsrfTokens
 */
abstract class TestCase extends BaseFrameworkTestCase
{
    use CreatesApplication;
    use HandlesRateLimiting;
    use HandlesCsrfTokens;
    use RefreshDatabase;

    /**
     * The base URL to use while testing the application.
     */
    protected string $baseUrl = 'http://localhost';

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable CSRF middleware for tests by default
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }
}
