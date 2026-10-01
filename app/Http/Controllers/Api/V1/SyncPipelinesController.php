<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesWorkspaceAccount;
use App\Http\Controllers\Settings\NativeTrackingController;
use App\Http\Controllers\Settings\SyncPipelinesController as WebSyncPipelinesController;
use App\Models\ConnectedAccountNativeWatch;
use App\Models\SyncPipeline;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

/**
 * Sync pipelines + native tracking for the SPA/API. Payload shapes mirror the
 * legacy Inertia `sync` page.
 */
class SyncPipelinesController extends WebSyncPipelinesController
{
    use ResolvesWorkspaceAccount;

    public function list(Request $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        return response()->json($this->pipelinesPayload($workspace));
    }

    public function create(Request $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        if (! $this->gate->canCreateSyncPipeline($workspace)) {
            $max = (int) config('subscriptions.max_sync_pipelines');
            throw ValidationException::withMessages([
                'name' => "You've reached your plan's limit of {$max} sync pipelines. Delete one to create another.",
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'source_connected_account_id' => ['required', 'string', $this->accountRule($workspace->id)],
            'destination_connected_account_ids' => ['required', 'array', 'min:1', 'max:3'],
            'destination_connected_account_ids.*' => [$this->accountRule($workspace->id), 'different:source_connected_account_id'],
            'track_source' => ['sometimes', 'boolean'],
        ]);

        $this->assertSourceNotDestination($validated['source_connected_account_id'], $validated['destination_connected_account_ids']);

        $pipeline = SyncPipeline::create([
            'workspace_id' => $workspace->id,
            'source_connected_account_id' => $validated['source_connected_account_id'],
            'name' => $validated['name'],
            'enabled' => $validated['enabled'] ?? true,
            'created_by' => $user->id,
        ]);
        $pipeline->destinations()->sync($validated['destination_connected_account_ids']);

        $trackedSource = $this->maybeTrackSource(
            $workspace,
            $user,
            $validated['source_connected_account_id'],
            (bool) ($validated['track_source'] ?? false),
        );

        return response()->json([
            'id' => $pipeline->id,
            'tracked_source' => $trackedSource,
        ], 201);
    }

    public function patch(Request $request, string $pipelineId): JsonResponse
    {
        $pipeline = $this->findPipelineOrFail($pipelineId);
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $pipeline->workspace_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'source_connected_account_id' => ['sometimes', 'string', $this->accountRule($pipeline->workspace_id)],
            'destination_connected_account_ids' => ['sometimes', 'array', 'min:1', 'max:3'],
            'destination_connected_account_ids.*' => [$this->accountRule($pipeline->workspace_id)],
        ]);

        $finalSource = $validated['source_connected_account_id'] ?? $pipeline->source_connected_account_id;
        $finalDestinations = array_key_exists('destination_connected_account_ids', $validated)
            ? $validated['destination_connected_account_ids']
            : $pipeline->destinations()->pluck('connected_accounts.id')->all();
        $this->assertSourceNotDestination((string) $finalSource, $finalDestinations);

        $pipeline->update(array_intersect_key($validated, array_flip(['name', 'enabled', 'source_connected_account_id'])));
        if (array_key_exists('destination_connected_account_ids', $validated)) {
            $pipeline->destinations()->sync($validated['destination_connected_account_ids']);
        }

        return response()->json(['id' => $pipeline->id, 'enabled' => $pipeline->enabled]);
    }

    public function remove(Request $request, string $pipelineId): JsonResponse
    {
        $pipeline = $this->findPipelineOrFail($pipelineId);
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $pipeline->workspace_id);

        $pipeline->delete();

        return response()->json(['message' => 'Sync pipeline deleted.']);
    }

    /**
     * Native tracking toggle for a connected account — same rules as the web
     * NativeTrackingController, scoped to the API workspace.
     */
    public function trackNative(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        app(NativeTrackingController::class)->trackAccount($workspace, $user, $account);

        return response()->json(['message' => 'Native tracking enabled.']);
    }

    public function untrackNative(Request $request, string $accountId): JsonResponse
    {
        $account = $this->findAccountOrFail($accountId);
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $this->authorizeManage($user, $workspace->id);

        ConnectedAccountNativeWatch::query()
            ->where('workspace_id', $workspace->id)
            ->where('connected_account_id', $account->id)
            ->delete();

        return response()->json(['message' => 'Native tracking disabled.']);
    }

    private function apiWorkspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Workspace::query()->whereKey(Context::get('workspace_id'))->firstOrFail();

        return $workspace;
    }

    private function findPipelineOrFail(string $pipelineId): SyncPipeline
    {
        return SyncPipeline::query()
            ->where('workspace_id', Context::get('workspace_id'))
            ->whereKey($pipelineId)
            ->firstOr(fn () => abort(404, 'No pipeline with that id exists in this workspace.'));
    }
}
