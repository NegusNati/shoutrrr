<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * 404s the conversation-media endpoints when the platform cannot carry a DM
 * attachment (today: Bluesky). Not in `Route::bind('conversation', ...)` — that
 * binder is shared with index/thread/read/archive/respond, which Bluesky needs.
 * The /api/v1 routes pass a raw {conversationId} string instead of a bound
 * model, resolved workspace-scoped via the Context ResolveApiWorkspace sets.
 */
class EnsureConversationSupportsDirectMessageMedia
{
    public function handle(Request $request, Closure $next): Response
    {
        $conversation = $request->route('conversation');

        if (! $conversation instanceof Conversation && is_string($request->route('conversationId'))) {
            $conversation = Conversation::query()
                ->withoutGlobalScopes()
                ->where('workspace_id', Context::get('workspace_id'))
                ->whereKey($request->route('conversationId'))
                ->first();
        }

        abort_unless(
            $conversation instanceof Conversation && $conversation->platform->supportsDirectMessageMedia(),
            404,
        );

        return $next($request);
    }
}
