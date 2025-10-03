<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmqxService
{
    protected string $baseUrl;
    protected string $basePath;
    protected string $authId;
    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $http;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.emqx.url', 'http://emqx:8081'), '/');
        // EMQX management API v5 is exposed under /api/v5 in your instance (see swagger)
        $this->basePath = trim(config('services.emqx.base_path', '/api/v5'), '/');
        // Use the password_based built-in database authenticator identifier used by EMQX v5
        $this->authId = config('services.emqx.authenticator_id', 'password_based:built_in_database');

        // Basic auth credentials for management API (use app_id/app_secret names from config/services.php)
        $this->http = Http::withBasicAuth(config('services.emqx.api_key'), config('services.emqx.secret_key'))
                ->acceptJson();
    }

    protected function apiUrl(string $path = ''): string
    {
        $path = trim($path, '/');
        return $this->baseUrl . '/' . $this->basePath . ($path ? '/' . $path : '');
    }


    /**
     * Create or update a user in the built_in_database authenticator.
     * Returns decoded response on success, or an array with status/body on failure.
     *
     * @param string $username
     * @param string $password
     * @return mixed
     */
    public function createUser(string $username, string $password): mixed
    {
        $url = $this->apiUrl("authentication/{$this->authId}/users");

        // EMQX v5 expects user_id and password fields for this authenticator
        $payload = [
            'user_id' => $username,
            'password' => $password,
        ];

        $response = $this->http->post($url, $payload);
        if ($response->successful()) {
            return $response->json();
        }

        // Log failure and return helpful debug info for Tinker
        try {
            $body = $response->json();
        } catch (\Throwable $e) {
            $body = $response->body();
        }

        Log::error('EMQX createUser failed', ['url' => $url, 'status' => $response->status(), 'body' => $body, 'payload' => $payload]);

        return [
            'status' => $response->status(),
            'body' => $body,
        ];
    }

    /**
     * Delete a user from the built_in_database authenticator.
     *
     * @param string $username
     * @return bool
     */
    public function deleteUser(string $username): bool
    {
        $url = $this->apiUrl("authentication/{$this->authId}/users/" . rawurlencode($username));

        $response = $this->http->delete($url);

        return $response->successful();
    }
}
