<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ConnectedAccounts\LinkedInPageConnectionController;
use App\Models\ConnectedAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The SPA's connect-linkedin picker: the OAuth callback stashes the person +
 * administered organizations server-side, then the SPA reads them here and
 * POSTs the selections back as JSON.
 */
class LinkedInConnectController extends LinkedInPageConnectionController
{
    public function pending(Request $request): JsonResponse
    {
        $stash = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (! is_array($stash)) {
            return response()->json(['message' => 'Your LinkedIn connection expired. Please try again.'], 404);
        }

        return response()->json([
            'person' => $stash['person'] ?? null,
            'organizations' => array_values((array) ($stash['organizations'] ?? [])),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $request->user()->can('create', ConnectedAccount::class) ?: abort(403);

        if (! $request->hasSession()) {
            return response()->json(['message' => 'Your LinkedIn connection expired. Please try again.'], 404);
        }

        $created = $this->connectSelected($request);

        if ($created === null) {
            return response()->json(['message' => 'Your LinkedIn connection expired. Please try again.'], 404);
        }

        return response()->json(['connected' => $created], 201);
    }
}
