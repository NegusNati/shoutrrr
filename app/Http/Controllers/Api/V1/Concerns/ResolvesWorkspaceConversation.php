<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Conversation;
use Illuminate\Support\Facades\Context;

/**
 * Shared conversation lookup for the API controllers — the /api/v1 group does
 * not run implicit route-model binding, so each action resolves the id itself,
 * scoped to the caller's bound workspace.
 */
trait ResolvesWorkspaceConversation
{
    protected function findConversationOrFail(Conversation|string $conversation): Conversation
    {
        if ($conversation instanceof Conversation) {
            return $conversation;
        }

        return Conversation::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', Context::get('workspace_id'))
            ->whereKey($conversation)
            ->firstOr(fn () => abort(404, 'No conversation with that id exists in this workspace.'));
    }
}
