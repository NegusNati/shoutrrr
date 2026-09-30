<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject mutating requests made with a read-only API key, independent of the
 * acting member's workspace role. Session (SPA) requests carry no token
 * scopes — their workspace role is enforced by controller policies instead.
 */
class RequireWriteScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->currentAccessToken() instanceof AccessToken
            && $user->tokenCant('write')) {
            abort(403, 'This API key is read-only.');
        }

        return $next($request);
    }
}
