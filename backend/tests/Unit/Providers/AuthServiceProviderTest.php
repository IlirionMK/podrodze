<?php

namespace Tests\Unit\Providers;

use App\Providers\AuthServiceProvider;
use Tests\TestCase;

class AuthServiceProviderTest extends TestCase
{
    public function test_boot_method_configures_verify_email_url()
    {
        config(['app.url' => 'http://localhost:8000']);
        config(['app.frontend_url' => 'http://localhost:3000']);
        config(['auth.verification.expire' => 60]);

        $provider = new AuthServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getKey(): int
            {
                return 123;
            }

            public function getEmailForVerification(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\VerifyEmail::$createUrlCallback;
        
        // Call the callback to test it
        $url = $callback($notifiable);
        
        // Verify the URL contains the expected components
        $this->assertStringContainsString('http://localhost:3000/auth/verify-email', $url);
        $this->assertStringContainsString('url=', $url);
        $this->assertStringContainsString('123', $url);
        $this->assertStringContainsString(sha1('test@example.com'), $url);
    }

    public function test_boot_method_uses_default_frontend_url_when_not_configured()
    {
        config(['app.url' => 'http://localhost:8000']);
        // Don't set frontend_url to test default
        config(['auth.verification.expire' => 60]);

        $provider = new AuthServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getKey(): int
            {
                return 123;
            }

            public function getEmailForVerification(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\VerifyEmail::$createUrlCallback;
        
        // Call the callback to test it
        $url = $callback($notifiable);
        
        // Should use default frontend_url (http://localhost:5173)
        $this->assertStringContainsString('http://localhost:5173/auth/verify-email', $url);
    }

    public function test_boot_method_trims_frontend_url_trailing_slash()
    {
        config(['app.url' => 'http://localhost:8000']);
        config(['app.frontend_url' => 'http://localhost:3000/']);
        config(['auth.verification.expire' => 60]);

        $provider = new AuthServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getKey(): int
            {
                return 123;
            }

            public function getEmailForVerification(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\VerifyEmail::$createUrlCallback;
        
        // Call the callback to test it
        $url = $callback($notifiable);
        
        // Should not have double slashes in the frontend URL part
        $this->assertStringNotContainsString('//auth/verify-email', $url);
        $this->assertStringContainsString('http://localhost:3000/auth/verify-email', $url);
    }

    public function test_boot_method_trims_api_url_trailing_slash()
    {
        config(['app.url' => 'http://localhost:8000/']);
        config(['app.frontend_url' => 'http://localhost:3000']);
        config(['auth.verification.expire' => 60]);

        $provider = new AuthServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getKey(): int
            {
                return 123;
            }

            public function getEmailForVerification(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\VerifyEmail::$createUrlCallback;
        
        // Call the callback to test it
        $url = $callback($notifiable);
        
        // Should not have double slashes in the API URL part
        $this->assertStringNotContainsString('%2F%2Fapi%2Fv1%2Femail%2Fverify', $url);
        $this->assertStringContainsString('%2Fapi%2Fv1%2Femail%2Fverify', $url);
    }

    public function test_boot_method_uses_default_verification_expire_time()
    {
        config(['app.url' => 'http://localhost:8000']);
        config(['app.frontend_url' => 'http://localhost:3000']);
        // Don't set auth.verification.expire to test default

        $provider = new AuthServiceProvider($this->app);
        $provider->boot();

        $notifiable = new class {
            public function getKey(): int
            {
                return 123;
            }

            public function getEmailForVerification(): string
            {
                return 'test@example.com';
            }
        };

        // Get the callback that was set by the provider
        $callback = \Illuminate\Auth\Notifications\VerifyEmail::$createUrlCallback;
        
        // Call the callback to test it - this should work with default expire time
        $url = $callback($notifiable);
        
        // Test passes if no exceptions are thrown and URL is generated
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }
}
