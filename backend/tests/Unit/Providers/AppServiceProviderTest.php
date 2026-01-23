<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_register_method_binds_interfaces_to_implementations()
    {
        $provider = new AppServiceProvider($this->app);
        $provider->register();

        // Test interface bindings
        $this->assertInstanceOf(\App\Services\ItineraryService::class, $this->app->make(\App\Interfaces\ItineraryServiceInterface::class));
        $this->assertInstanceOf(\App\Services\PreferenceService::class, $this->app->make(\App\Interfaces\PreferenceServiceInterface::class));
        $this->assertInstanceOf(\App\Services\TripService::class, $this->app->make(\App\Interfaces\TripInterface::class));
        $this->assertInstanceOf(\App\Services\PlaceService::class, $this->app->make(\App\Interfaces\PlaceInterface::class));
        $this->assertInstanceOf(\App\Services\PreferenceAggregatorService::class, $this->app->make(\App\Interfaces\PreferenceAggregatorServiceInterface::class));

        // Test AI interface bindings
        $this->assertInstanceOf(\App\Services\Ai\AiPlaceAdvisorService::class, $this->app->make(\App\Interfaces\Ai\AiPlaceAdvisorInterface::class));
        $this->assertInstanceOf(\App\Services\Ai\Candidates\DatabasePlacesCandidateProvider::class, $this->app->make(\App\Interfaces\Ai\PlacesCandidateProviderInterface::class));
        $this->assertInstanceOf(\App\Services\Ai\Reasoners\HeuristicPlaceReasoner::class, $this->app->make(\App\Interfaces\Ai\AiPlaceReasonerInterface::class));
    }

    public function test_register_method_registers_category_normalizer_as_singleton()
    {
        $provider = new AppServiceProvider($this->app);
        $provider->register();

        $instance1 = $this->app->make(\App\Services\Ai\CategoryNormalizer::class);
        $instance2 = $this->app->make(\App\Services\Ai\CategoryNormalizer::class);

        $this->assertSame($instance1, $instance2, 'CategoryNormalizer should be registered as singleton');
    }

    public function test_boot_method_in_local_environment()
    {
        config(['app.env' => 'local']);
        config(['app.url' => 'http://localhost:8000']);

        $provider = new AppServiceProvider($this->app);
        
        // This should not throw any exceptions
        $provider->boot();
        
        $this->assertTrue(true);
    }

    public function test_boot_method_in_production_environment()
    {
        config(['app.env' => 'production']);

        $provider = new AppServiceProvider($this->app);
        
        // This should not throw any exceptions
        $provider->boot();
        
        $this->assertTrue(true);
    }

    public function test_boot_method_configures_reset_password_url()
    {
        config(['app.frontend_url' => 'http://localhost:3000']);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getEmailForPasswordReset(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\ResetPassword::$createUrlCallback;
        
        // Call the callback to test it
        $url = $callback($notifiable, 'test-token');
        
        $expectedUrl = 'http://localhost:3000/password-reset/test-token?email=test@example.com';
        $this->assertEquals($expectedUrl, $url);
    }

    public function test_boot_method_trims_frontend_url_trailing_slash()
    {
        config(['app.frontend_url' => 'http://localhost:3000/']);

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getEmailForPasswordReset(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\ResetPassword::$createUrlCallback;
        
        // Call the callback to test it
        $url = $callback($notifiable, 'test-token');
        
        $expectedUrl = 'http://localhost:3000/password-reset/test-token?email=test@example.com';
        $this->assertEquals($expectedUrl, $url);
    }
}
