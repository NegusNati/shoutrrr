<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Settings\ApiKeysController;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

class WorkspaceApiKeysController extends ApiKeysController
{
    public function list(Request $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        return response()->json(['apiKeys' => $this->keysPayload($workspace)]);
    }

    public function create(Request $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', 'in:read,write'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $expiresAt = ($validated['expires_at'] ?? null) !== null
            ? CarbonImmutable::parse($validated['expires_at'])
            : null;

        [$key, $plain] = $this->manager->issue($workspace, $user, $validated['name'], $validated['scope'], $expiresAt);

        // The plaintext key is shown exactly once — same contract as the web
        // flash.plainTextApiKey handoff.
        return response()->json([
            'apiKey' => [
                'id' => $key->id,
                'name' => $key->name,
                'last_four' => $key->last_four,
                'scope' => $key->scope,
                'expires_at' => $key->expires_at?->toIso8601String(),
            ],
            'plainTextApiKey' => $plain,
        ], 201);
    }

    public function remove(Request $request, string $apiKeyId): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        $apiKey = ApiKey::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($apiKeyId)
            ->firstOr(fn () => abort(404, 'No API key with that id exists in this workspace.'));

        $this->manager->revoke($apiKey);

        return response()->json(['message' => 'API key revoked.']);
    }

    private function apiWorkspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Workspace::query()->whereKey(Context::get('workspace_id'))->firstOrFail();

        return $workspace;
    }
}
