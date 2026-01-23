<?php

namespace Tests\Traits;

trait HandlesCsrfTokens
{
    /**
     * Get a CSRF token from the application.
     *
     * @return string|null
     */
    protected function getCsrfToken()
    {
        $response = $this->get('/sanctum/csrf-cookie');
        return $response->cookie('XSRF-TOKEN');
    }

    /**
     * Add CSRF token to the request data.
     *
     * @param array $data
     * @return array
     */
    protected function withCsrfToken($data = [])
    {
        $token = $this->getCsrfToken();
        
        return array_merge($data, [
            '_token' => $token,
            'headers' => [
                'X-XSRF-TOKEN' => $token,
                'Referer' => config('app.url'),
                'Origin' => config('app.url'),
            ]
        ]);
    }
}
