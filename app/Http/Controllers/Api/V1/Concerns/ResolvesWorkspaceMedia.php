<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\PostMedia;
use Illuminate\Support\Facades\Context;

/**
 * Shared media lookup for the API attachment endpoints, scoped to the caller's
 * bound workspace.
 */
trait ResolvesWorkspaceMedia
{
    protected function findMediaOrFail(PostMedia|string $media): PostMedia
    {
        if ($media instanceof PostMedia) {
            return $media;
        }

        return PostMedia::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', Context::get('workspace_id'))
            ->whereKey($media)
            ->firstOr(fn () => abort(404, 'No media with that id exists in this workspace.'));
    }
}
