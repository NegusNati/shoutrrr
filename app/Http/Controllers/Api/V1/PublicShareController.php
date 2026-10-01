<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Posts\ShareService;
use App\Support\PublicPostView;
use Illuminate\Http\JsonResponse;

/**
 * Public read of a share link for the SPA — the JSON equivalent of
 * App\Http\Controllers\PublicShareController::show. The token itself is the
 * capability, so no auth: revoked/expired links resolve to `post: null`
 * instead of a 404 so the page can render its "link unavailable" state.
 */
class PublicShareController extends Controller
{
    public function show(string $token, ShareService $shares): JsonResponse
    {
        $share = $shares->resolveActive($token);

        // Token proves authorization; bypass the workspace global scope for the
        // cross-workspace read (same escape hatch the web controller uses).
        $post = $share
            ?->post()
            ->withoutGlobalScopes()
            ->with(['targets.account', 'media'])
            ->first();

        return response()->json([
            'post' => $post !== null ? PublicPostView::make($post) : null,
        ]);
    }
}
