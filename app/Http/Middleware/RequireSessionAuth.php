<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-only endpoints (user settings, workspace invitations) belong to the
 * signed-in user rather than a workspace — Passport API keys must not reach
 * them, and rejecting here keeps a key from ever passing FormRequest
 * validation.
 */
class RequireSessionAuth
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->currentAccessToken() instanceof AccessToken) {
            abort(403, 'This endpoint is only available to the web session.');
        }

        return $next($request);
    }
}
