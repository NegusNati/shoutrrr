<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\PostTargetReply;
use Illuminate\Support\Facades\Context;

/**
 * Shared reply lookup for the API controllers. The /api/v1 group does not run
 * implicit route-model binding (the `reply` binder belongs to the web routes),
 * so each action resolves the id itself — scoped to the caller's bound
 * workspace so a foreign id is a 404 rather than a cross-tenant leak.
 */
trait ResolvesWorkspaceReply
{
    protected function findReplyOrFail(PostTargetReply|string $reply): PostTargetReply
    {
        if ($reply instanceof PostTargetReply) {
            return $reply;
        }

        return PostTargetReply::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', Context::get('workspace_id'))
            ->whereKey($reply)
            ->firstOr(fn () => abort(404, 'No reply with that id exists in this workspace.'));
    }
}
