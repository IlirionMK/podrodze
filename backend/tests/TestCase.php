<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseFrameworkTestCase;
use Tests\Traits\CreatesApplication;
use Tests\Traits\HandlesRateLimiting;

/**
 * Base test case class for all application tests.
 * This is the foundation class that all other test cases should extend.
 * It provides the basic testing environment setup and common testing traits.
 */
abstract class TestCase extends BaseFrameworkTestCase
{
    use CreatesApplication;
    use HandlesRateLimiting;
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

        // Additional setup can be added here that should run for all tests
    }
}
