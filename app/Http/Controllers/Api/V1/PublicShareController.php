<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Posts\ShareService;
use App\Support\PublicPostView;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated share view — the token is the bearer secret, same as
 * the legacy /share/{token} page.
 */
class PublicShareController extends Controller
{
    public function show(string $token, ShareService $shares): JsonResponse
    {
        $share = $shares->resolveActive($token);

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
