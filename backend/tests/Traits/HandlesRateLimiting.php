<?php

declare(strict_types=1);

namespace Tests\Traits;

use Exception;

/**
 * Trait HandlesRateLimiting
 *
 * Provides rate limiting functionality for tests.
 */
trait HandlesRateLimiting
{
    /**
     * The original rate limit configuration.
     *
     */
    protected ?array $originalRateLimitConfig = null;

    /**
     * Enable rate limiting for tests.
     *
     * @param int $maxAttempts Maximum number of attempts
     * @param int $decayMinutes Decay time in minutes
     * @return void
     */
    protected function enableRateLimiting(int $maxAttempts = 60, int $decayMinutes = 1): void
    {
        config([
            'auth.guards.api.throttle' => [
                'enabled' => true,
                'max_attempts' => $maxAttempts,
                'decay_minutes' => $decayMinutes,
            ]
        ]);
    }

    /**
     * Disable rate limiting for tests.
     *
     * @return void
     */
    protected function disableRateLimiting(): void
    {
        config([
            'auth.guards.api.throttle' => [
                'enabled' => false,
            ]
        ]);
    }

    /**
     * Clear rate limiting cache.
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
     * Set up rate limiting for tests.
     *
     * @return void
     */
    protected function setupRateLimiting(): void
    {
        if (property_exists($this, 'enableRateLimiting') && $this->enableRateLimiting) {
            $maxAttempts = property_exists($this, 'maxAttempts') ? $this->maxAttempts : 60;
            $decayMinutes = property_exists($this, 'decayMinutes') ? $this->decayMinutes : 1;
            $this->enableRateLimiting($maxAttempts, $decayMinutes);
        } else {
            $this->disableRateLimiting();
        }
    }
}
