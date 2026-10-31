<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmqxService
{
    protected string $baseUrl;

    protected string $basePath;

    protected string $authId;

    /**
     * @var PendingRequest
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
            'api_key_set' => filled($apiKey),
            'secret_key_set' => filled($secretKey),
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
     * Create or update a user in the built_in_database authenticator, so its password always matches the one given.
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

        if ($response->status() === 409) {
            $url = $this->apiUrl("authentication/{$this->authId}/users/".rawurlencode($username));
            $response = $this->http->put($url, ['password' => $password]);
        }

        if ($response->successful()) {
            return $response->json();
        }

        // Log failure and return helpful debug info for Tinker
        try {
            $body = $response->json();
        } catch (\Throwable $e) {
            $body = $response->body();
        }

        Log::error('EMQX createUser failed', ['url' => $url, 'status' => $response->status(), 'body' => $body, 'username' => $username]);

        return [
            'status' => $response->status(),
            'body' => $body,
        ];
    }

    /**
     * Delete a user from the built_in_database authenticator. A user that is already gone counts as deleted.
     */
    public function deleteUser(string $username): bool
    {
        return $this->deleteIdempotently($this->apiUrl("authentication/{$this->authId}/users/".rawurlencode($username)));
    }

    /**
     * Delete the user's rules from the built_in_database authorization source. Rules that are already gone count as deleted.
     */
    public function deleteUserRules(string $username): bool
    {
        return $this->deleteIdempotently($this->apiUrl('authorization/sources/built_in_database/rules/users/'.rawurlencode($username)));
    }

    private function deleteIdempotently(string $url): bool
    {
        $response = $this->http->delete($url);

        return $response->successful() || $response->notFound();
    }

    /**
     * Create or replace the user's rules in the built_in_database authorization source,
     * allowing publish and subscribe under its topic base only (ADR-0007).
     *
     * @param  string  $topicBase  e.g. agronos/devices/{uuid}
     * @return true|array{status: int, body: mixed}
     */
    public function authorizeUser(string $username, string $topicBase): true|array
    {
        $url = $this->apiUrl('authorization/sources/built_in_database/rules/users/'.rawurlencode($username));

        $payload = [
            'username' => $username,
            'rules' => [
                ['action' => 'all', 'permission' => 'allow', 'topic' => "{$topicBase}/#"],
            ],
        ];

        $response = $this->http->put($url, $payload);

        if ($response->successful()) {
            return true;
        }

        try {
            $body = $response->json();
        } catch (\Throwable $e) {
            $body = $response->body();
        }

        Log::error('EMQX authorizeUser failed', ['url' => $url, 'status' => $response->status(), 'body' => $body, 'payload' => $payload]);

        return ['status' => $response->status(), 'body' => $body];
    }

    /**
     * Create or update an HTTP connector that posts JSON to the given URL.
     *
     * @param  array<string, string>  $headers  Extra headers sent with every request, e.g. a shared secret
     * @return 'created'|'updated'
     *
     * @throws RequestException
     */
    public function upsertWebhookConnector(string $name, string $url, array $headers = []): string
    {
        $config = [
            'url' => $url,
            'headers' => ['content-type' => 'application/json', ...$headers],
            'enable' => true,
        ];

        return $this->upsert("connectors/http:{$name}", 'connectors', ['type' => 'http', 'name' => $name, ...$config], $config);
    }

    /**
     * Create or update an HTTP action that posts the rule output (`${.}`) through the given connector.
     *
     * @return 'created'|'updated'
     *
     * @throws RequestException
     */
    public function upsertWebhookAction(string $name, string $connector): string
    {
        $config = [
            'connector' => $connector,
            'enable' => true,
            'parameters' => [
                'method' => 'post',
                'body' => '${.}',
                'headers' => ['content-type' => 'application/json'],
            ],
        ];

        return $this->upsert("actions/http:{$name}", 'actions', ['type' => 'http', 'name' => $name, ...$config], $config);
    }

    /**
     * Create or update a rule engine rule.
     *
     * @param  list<string>  $actions  Action references, e.g. `http:my_action`
     * @return 'created'|'updated'
     *
     * @throws RequestException
     */
    public function upsertRule(string $id, string $sql, array $actions): string
    {
        $config = ['sql' => $sql, 'actions' => $actions, 'enable' => true];

        return $this->upsert("rules/{$id}", 'rules', ['id' => $id, ...$config], $config);
    }

    /**
     * Update the resource when it exists, otherwise create it in its collection.
     *
     * @param  array<string, mixed>  $createBody
     * @param  array<string, mixed>  $updateBody
     * @return 'created'|'updated'
     *
     * @throws RequestException
     */
    private function upsert(string $resourcePath, string $collectionPath, array $createBody, array $updateBody): string
    {
        $existing = $this->http->get($this->apiUrl($resourcePath));

        if ($existing->notFound()) {
            $this->http->post($this->apiUrl($collectionPath), $createBody)->throw();

            return 'created';
        }

        $existing->throw();
        $this->http->put($this->apiUrl($resourcePath), $updateBody)->throw();

        return 'updated';
    }
}
