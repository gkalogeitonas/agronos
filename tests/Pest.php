<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
| Browser tests always load the compiled assets from public/build. A running Vite dev
| server writes public/hot, and its CORS rules reject the test server's 127.0.0.1 origin,
| which leaves every page blank. Run `ddev npm run build` before the browser suite.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Vite::useHotFile(storage_path('framework/vite.hot.ignored')))
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fake the EMQX management API endpoints that provisioning a device user touches.
 */
function fakeEmqxProvisioning(string $username, int $userStatus = 201, int $rulesStatus = 204): void
{
    config([
        'services.emqx.url' => 'http://emqx.test',
        'services.emqx.base_path' => '/api/v5',
        'services.emqx.authenticator_id' => 'password_based:built_in_database',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'http://emqx.test/api/v5/authentication/password_based:built_in_database/users' => Http::response(['user_id' => $username], $userStatus),
        "http://emqx.test/api/v5/authentication/password_based:built_in_database/users/{$username}" => Http::response(['user_id' => $username], 200),
        "http://emqx.test/api/v5/authorization/sources/built_in_database/rules/users/{$username}" => Http::response(null, $rulesStatus),
    ]);
}

/**
 * Whether the device user's rules were written to allow only the given topic base.
 */
function emqxRulesWritten(string $username, string $topicBase): bool
{
    return Http::recorded(fn (Request $request) => $request->method() === 'PUT'
        && str_ends_with($request->url(), "/rules/users/{$username}")
        && $request['rules'] === [['action' => 'all', 'permission' => 'allow', 'topic' => "{$topicBase}/#"]]
    )->isNotEmpty();
}

/**
 * The password carried by the last request that created or updated an EMQX user, or null when none was sent.
 */
function emqxPasswordSent(): ?string
{
    $recorded = Http::recorded(fn (Request $request) => str_contains($request->url(), '/authentication/'))->last();

    return $recorded ? $recorded[0]['password'] : null;
}
