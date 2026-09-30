<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\AppShellData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;

class WorkspacesController extends Controller
{
    public function index(Request $request, AppShellData $shell): JsonResponse
    {
        return response()->json($shell->workspaces($request->user()));
    }

    /**
     * Switch the session user's current workspace. Session-only: API keys are
     * bound to a single workspace at creation time, so "current workspace"
     * has no meaning for token-authenticated callers.
     */
    public function switch(Request $request): JsonResponse
    {
        if ($request->user()?->currentAccessToken() instanceof AccessToken) {
            abort(403, 'API keys are bound to a single workspace.');
        }

        $validated = $request->validate([
            'workspace_id' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->isMemberOfWorkspace($validated['workspace_id'])) {
            return response()->json([
                'message' => 'You do not have access to this workspace.',
                'errors' => ['workspace_id' => ['You do not have access to this workspace.']],
            ], 422);
        }

        $user->forceFill(['current_workspace_id' => $validated['workspace_id']])->save();

        return response()->json(app(AppShellData::class)->workspaces($user->fresh()));
    }

    /**
     * Create a workspace and make it current. Session-only, mirroring the web
     * workspace creation endpoint — API keys are bound to a workspace rather
     * than a user account, so they cannot create workspaces.
     */
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        if ($request->user()?->currentAccessToken() instanceof AccessToken) {
            abort(403, 'API keys cannot create workspaces.');
        }

        /** @var User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $user): void {
            $workspace = Workspace::create([
                'name' => $request->validated('name'),
                'slug' => $this->uniqueSlug((string) $request->validated('name')),
                'owner_id' => $user->id,
            ]);

            WorkspaceMembership::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        });

        return response()->json(
            app(AppShellData::class)->workspaces($user->fresh()),
            201,
        );
    }

    private function uniqueSlug(string $name): string
    {
        do {
            $slug = Str::slug($name).'-'.Str::lower(Str::random(5));
        } while (Workspace::where('slug', $slug)->exists());

        return $slug;
    }
}
