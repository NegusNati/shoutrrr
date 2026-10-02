<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\TransferOwnershipRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Session-only workspace lifecycle: leave, delete and ownership transfer.
 * Mirrors the legacy routes/workspace.php controller — the {workspace} param
 * may name any workspace the user belongs to, not only their current one, and
 * membership is enforced per action exactly as the web routes did.
 */
class WorkspaceLifecycleController extends Controller
{
    public function leave(Request $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $user->getMembershipForWorkspace($workspace->id);

        if (! $membership) {
            abort(404);
        }

        if ($this->isSoleOwnerWithOtherMembers($workspace, $user->id)) {
            throw ValidationException::withMessages([
                'workspace' => 'Transfer ownership or delete the workspace before leaving.',
            ]);
        }

        DB::transaction(function () use ($membership, $user, $workspace): void {
            $membership->delete();

            if ($user->current_workspace_id === $workspace->id) {
                $next = $user->workspaceMemberships()->first();
                $user->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
            }
        });

        return response()->json(['message' => 'You left the workspace.']);
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOwnerOfWorkspace($workspace->id)) {
            abort(403);
        }

        if (! $user->workspaceMemberships()->where('workspace_id', '!=', $workspace->id)->exists()) {
            throw ValidationException::withMessages([
                'workspace' => 'Create or join another workspace before deleting this one.',
            ]);
        }

        // The initial workspace anchors the cloud billing exemption; deleting it
        // would silently move the free tier to the next-oldest workspace.
        if ($workspace->is_initial && (bool) config('subscriptions.enabled')) {
            throw ValidationException::withMessages([
                'workspace' => 'The initial workspace of this instance cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($workspace): void {
            // Reassign current workspace for any member who had this as their current,
            // BEFORE the nullOnDelete FK cascade fires, so they land on another of their
            // workspaces when one exists (otherwise it becomes null).
            $affected = User::where('current_workspace_id', $workspace->id)->get();

            foreach ($affected as $member) {
                $next = $member->workspaceMemberships()
                    ->where('workspace_id', '!=', $workspace->id)
                    ->first();

                $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
            }

            $workspace->delete(); // cascades memberships + invitations via FK
        });

        return response()->json(['message' => 'Workspace deleted.']);
    }

    public function transfer(TransferOwnershipRequest $request, Workspace $workspace): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOwnerOfWorkspace($workspace->id)) {
            abort(403);
        }

        $target = WorkspaceMembership::where('id', $request->validated('membership_id'))
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $currentOwner = $user->getMembershipForWorkspace($workspace->id);

        abort_if($currentOwner === null, 403);

        DB::transaction(function () use ($workspace, $target, $currentOwner): void {
            $target->update(['role' => WorkspaceRole::Owner]);
            $currentOwner->update(['role' => WorkspaceRole::Admin]);
            $workspace->update(['owner_id' => $target->user_id]);
        });

        return response()->json(['message' => 'Ownership transferred.']);
    }

    private function isSoleOwnerWithOtherMembers(Workspace $workspace, string $userId): bool
    {
        $owners = WorkspaceMembership::where('workspace_id', $workspace->id)
            ->where('role', WorkspaceRole::Owner->value)
            ->pluck('user_id');

        $totalMembers = WorkspaceMembership::where('workspace_id', $workspace->id)->count();

        return $owners->count() === 1 && $owners->first() === $userId && $totalMembers > 1;
    }
}
