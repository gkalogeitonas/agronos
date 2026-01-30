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
        $this->baseUrl = rtrim(config('services.emqx.url', '/'), '/');
        // EMQX management API v5 is exposed under /api/v5 in your instance (see swagger)
        $this->basePath = trim(config('services.emqx.base_path'), '/');
        // Use the password_based built-in database authenticator identifier used by EMQX v5
        $this->authId = config('services.emqx.authenticator_id');

        // Basic auth credentials for management API (use app_id/app_secret names from config/services.php)
        $apiKey = config('services.emqx.api_key');
        $secretKey = config('services.emqx.secret_key');

        Log::info('EMQX Service initialized', [
            'api_key' => $apiKey,
            'secret_key_length' => $secretKey ? strlen($secretKey) : 0,
            'secret_key_preview' => $secretKey ? substr($secretKey, 0, 10).'...' : 'null',
        ]);

        $this->http = Http::withBasicAuth($apiKey, $secretKey)
            ->acceptJson();
    }

    protected function apiUrl(string $path = ''): string
    {
        $path = trim($path, '/');

        return $this->baseUrl.'/'.$this->basePath.($path ? '/'.$path : '');
    }

    /**
     * Create or update a user in the built_in_database authenticator.
     * Returns decoded response on success, or an array with status/body on failure.
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
     */
    public function deleteUser(string $username): bool
    {
        $url = $this->apiUrl("authentication/{$this->authId}/users/".rawurlencode($username));

        $response = $this->http->delete($url);

        return $response->successful();
    }

    /**
     * Authorize a username so it may only publish on its own topic prefix and deny broader publishes.
     * Example payload posted to EMQX:
     * [
     *   {
     *     "rules": [
     *       {"action":"publish","permission":"allow","topic":"devices/{username}/#"},
     *       {"action":"publish","permission":"deny","topic":"devices/#"}
     *     ],
     *     "username": "{username}"
     *   }
     * ]
     *
     * @param  string  $deviceTopicPrefix  example: devices/{username}/# or devices/device1/#
     * @return bool|array true on success, or array with status/body on failure
     */
    /**
     * Authorize a username with a set of rules.
     * If $rules is null, a default set is applied that allows publish to devices/{username}/# and denies devices/#.
     *
     * @param  array|null  $rules  Array of rule arrays, each with keys: action, permission, topic
     * @return bool|array
     */
    public function authorizeUser(string $username, ?array $rules = null)
    {
        $url = $this->apiUrl('authorization/sources/built_in_database/rules/users');

        $payload = [
            [
                'rules' => $rules,
                'username' => $username,
            ],
        ];

        $response = $this->http->post($url, $payload);

        if ($response->successful()) {
            return true;
        }

        try {
            $body = $response->json();
        } catch (\Throwable $e) {
            $body = $response->body();
        }

        Log::error('EMQX authorizePublishOnly failed', ['url' => $url, 'status' => $response->status(), 'body' => $body, 'payload' => $payload]);

        return ['status' => $response->status(), 'body' => $body];
    }
}
