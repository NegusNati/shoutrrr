<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP analogue of WorkspaceTool::bindWorkspace. Resolves the caller's
 * workspace — bound API key for token requests, current_workspace_id for
 * session (SPA) requests — enforces active-key + live-membership, and installs
 * the same workspace_id Context the web path sets so model scopes and policies
 * match.
 */
class ResolveApiWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $accessToken = $user?->currentAccessToken();

        $workspaceId = $accessToken instanceof AccessToken
            ? $this->workspaceFromApiKey($user, $accessToken)
            : $this->workspaceFromSession($user);

        Context::add('workspace_id', $workspaceId);
        $user->current_workspace_id = $workspaceId; // in-memory only

        return $next($request);
    }

    /**
     * @param  AccessToken<mixed>  $accessToken
     */
    private function workspaceFromApiKey(User $user, AccessToken $accessToken): string
    {
        $apiKey = ApiKey::query()
            ->where('access_token_id', $accessToken->oauth_access_token_id)
            ->first();

        if ($apiKey === null || ! $apiKey->isActive()) {
            abort(401, 'This API key is not valid.');
        }

        if (! $user->isMemberOfWorkspace($apiKey->workspace_id)) {
            abort(403, 'You are no longer a member of this workspace.');
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        return $apiKey->workspace_id;
    }

    private function workspaceFromSession(?User $user): string
    {
        abort_if($user === null, 401, 'Unauthenticated.');

        $workspaceId = $user->current_workspace_id;

        if ($workspaceId === null || ! $user->isMemberOfWorkspace($workspaceId)) {
            abort(403, 'You are no longer a member of this workspace.');
        }

        return $workspaceId;
    }
}
