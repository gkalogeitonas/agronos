<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmqxWebhook
{
    /**
     * Reject requests that do not carry the pre-shared secret EMQX sends in the X-EMQX-Secret header.
     * Every request is rejected while no secret is configured.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.emqx.webhook_secret');
        $providedSecret = $request->header('X-EMQX-Secret');

        if (! filled($secret) || ! is_string($providedSecret) || ! hash_equals($secret, $providedSecret)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook secret.');
        }

        return $next($request);
    }
}
