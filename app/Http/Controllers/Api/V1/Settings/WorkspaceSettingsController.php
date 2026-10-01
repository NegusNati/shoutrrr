<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\InviteMemberRequest;
use App\Http\Requests\Workspace\TransferOwnershipRequest;
use App\Http\Requests\Workspace\UpdateMemberRoleRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceTimezoneRequest;
use App\Models\PostingSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Notifications\WorkspaceInviteNotification;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Workspace settings (current-workspace bound): overview, members,
 * invitations, ownership transfer, leave, and delete.
 */
class WorkspaceSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        $schedule = PostingSchedule::query()->where('workspace_id', $workspace->id)->first();
        $timezone = $schedule !== null ? $schedule->timezone : 'UTC';

        $hasAnotherWorkspace = $user->workspaceMemberships()
            ->where('workspace_id', '!=', $workspace->id)
            ->exists();
        $isProtectedInitialWorkspace = $workspace->is_initial && (bool) config('subscriptions.enabled');

        return response()->json([
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'logo' => $workspace->logo,
                'owner_id' => $workspace->owner_id,
            ],
            'canManage' => $user->hasAllPermissions(['workspace.settings.manage'], $workspace->id),
            'isOwner' => $user->isOwnerOfWorkspace($workspace->id),
            'canDelete' => $hasAnotherWorkspace && ! $isProtectedInitialWorkspace,
            'deleteDisabledReason' => match (true) {
                ! $hasAnotherWorkspace => 'You can’t delete your only workspace.',
                $isProtectedInitialWorkspace => 'The initial workspace of this instance cannot be deleted.',
                default => null,
            },
            'timezone' => $timezone,
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateWorkspaceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        $validated = $request->validated();
        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            $oldLogo = $workspace->getRawOriginal('logo');
            $disk = FileStorage::publicImageDiskName();
            $path = $request->file('photo')->store('workspace-photos', $disk);

            if ($path === false) {
                throw ValidationException::withMessages([
                    'photo' => 'The workspace photo could not be saved.',
                ]);
            }

            $validated['logo'] = $path;

            if (is_string($oldLogo) && $oldLogo !== '' && ! str_starts_with($oldLogo, 'http') && ! str_starts_with($oldLogo, '/')) {
                FileStorage::disk($disk)->delete($oldLogo);
            }
        }

        $workspace->update($validated);

        return response()->json(['workspace' => $workspace->fresh()]);
    }

    public function updateTimezone(UpdateWorkspaceTimezoneRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        PostingSchedule::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            ['timezone' => $request->validated('timezone')],
        );

        return response()->json(['timezone' => $request->validated('timezone')]);
    }

    public function members(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        $pending = $workspace->invitations()->pending()->with('inviter')->get()->map(fn (WorkspaceInvitation $i) => [
            'id' => $i->id,
            'email' => $i->email,
            'role' => $i->role,
            'invited_by' => $i->inviter?->name,
            'expires_at' => $i->expires_at,
            'created_at' => $i->created_at,
        ]);

        return response()->json([
            'members' => $workspace->members()->with('user')->get()->map(fn (WorkspaceMembership $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'avatar' => $m->user->avatar,
                'role' => $m->role->value,
                'is_owner' => $m->isOwner(),
                'created_at' => $m->created_at,
            ])->all(),
            'pendingInvitations' => $pending,
            'canManage' => $user->hasAllPermissions(['workspace.users.manage'], $workspace->id),
            'availableRoles' => ['member', 'admin'],
        ]);
    }

    public function inviteUser(InviteMemberRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        if ($workspace->members()->whereHas('user', fn ($q) => $q->where('email', $request->validated('email')))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member.',
            ]);
        }

        [$plain, $hash] = WorkspaceInvitation::generateToken();

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'invited_by' => $user->id,
            'email' => $request->validated('email'),
            'role' => $request->validated('role'),
            'token' => $hash,
            'expires_at' => now()->addDays((int) config('kit.workspaces.invitation_ttl_days')),
        ]);

        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser !== null) {
            $existingUser->notify(new WorkspaceInviteNotification($invitation, $plain));
        } else {
            Notification::route('mail', $invitation->email)
                ->notify(new WorkspaceInviteNotification($invitation, $plain));
        }

        return response()->json([
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
            ],
        ], 201);
    }

    public function updateMemberRole(UpdateMemberRoleRequest $request, WorkspaceMembership $membership): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $membership->workspace_id !== $workspace->id, 404);

        if ($membership->user_id === $user->id) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role.',
            ]);
        }

        if ($membership->isOwner()) {
            throw ValidationException::withMessages([
                'role' => 'Use ownership transfer to change the owner.',
            ]);
        }

        $membership->update(['role' => $request->validated('role')]);

        return response()->json(['member' => $membership->fresh()]);
    }

    public function removeMember(Request $request, WorkspaceMembership $membership): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $membership->workspace_id !== $workspace->id, 404);
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        if ($membership->isOwner()) {
            throw ValidationException::withMessages([
                'error' => 'Cannot remove the workspace owner.',
            ]);
        }

        if ($membership->user_id === $user->id) {
            throw ValidationException::withMessages([
                'error' => 'Use “leave workspace” to remove yourself.',
            ]);
        }

        $member = $membership->user;
        $membership->delete();

        if ($member->current_workspace_id === $workspace->id) {
            $next = $member->workspaceMemberships()->first();
            $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
        }

        return response()->json(['deleted' => true]);
    }

    public function cancelInvitation(Request $request, WorkspaceInvitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $invitation->workspace_id !== $workspace->id, 404);
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        $invitation->delete();

        return response()->json(['deleted' => true]);
    }

    public function leave(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

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

        return response()->json([
            'left' => true,
            'next_workspace_id' => $user->fresh()->current_workspace_id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

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
            // Reassign current workspace for any member who had this as their
            // current, BEFORE the nullOnDelete FK cascade fires.
            $affected = User::where('current_workspace_id', $workspace->id)->get();

            foreach ($affected as $member) {
                $next = $member->workspaceMemberships()
                    ->where('workspace_id', '!=', $workspace->id)
                    ->first();

                $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
            }

            $workspace->delete();
        });

        return response()->json(['deleted' => true]);
    }

    public function transferOwnership(TransferOwnershipRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        if (! $user->isOwnerOfWorkspace($workspace->id)) {
            abort(403);
        }

        $target = WorkspaceMembership::where('id', $request->validated('membership_id'))
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $currentOwner = $user->getMembershipForWorkspace($workspace->id);

        abort_if($currentOwner === null, 403);

        DB::transaction(function () use ($workspace, $target, $currentOwner): void {
            // The unique(workspace_id) partial index on role=owner forbids two
            // owners at once — demote before promoting, not after.
            $currentOwner->update(['role' => 'admin']);
            $target->update(['role' => 'owner']);
            $workspace->update(['owner_id' => $target->user_id]);
        });

        return response()->json(['transferred' => true]);
    }

    private function isSoleOwnerWithOtherMembers(Workspace $workspace, string $userId): bool
    {
        return $workspace->members()
            ->where('role', 'owner')
            ->where('user_id', $userId)
            ->exists()
            && $workspace->members()->count() > 1;
    }
}
