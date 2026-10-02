<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ConnectedAccounts\MetaConnectionController;
use App\Models\ConnectedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The SPA's connect-meta picker: the OAuth callback stashes the enumerated
 * Pages/IG assets server-side (same as the old server-rendered flow), then the SPA reads
 * them here and POSTs the selections back as JSON.
 */
class MetaConnectController extends MetaConnectionController
{
    public function pending(Request $request): JsonResponse
    {
        /** @var array{assets?: array<string, array{pageId: string, pageName: string, pageAccessToken: string, igUserId: ?string, igUsername: ?string, igAvatarUrl: ?string}>}|null $stash */
        $stash = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (! is_array($stash) || ! is_array($stash['assets'] ?? null)) {
            return response()->json(['message' => 'Your Facebook connection expired. Please try again.'], 404);
        }

        return response()->json(['assets' => $this->projectAssets($stash['assets'])]);
    }

    public function submit(Request $request): JsonResponse
    {
        $request->user()->can('create', ConnectedAccount::class) ?: abort(403);

        if (! $request->hasSession() || ! is_array($request->session()->get(self::SESSION_KEY))) {
            return response()->json(['message' => 'Your Facebook connection expired. Please try again.'], 404);
        }

        $created = $this->connectSelected($request);

        return response()->json(['connected' => $created], 201);
    }
}
