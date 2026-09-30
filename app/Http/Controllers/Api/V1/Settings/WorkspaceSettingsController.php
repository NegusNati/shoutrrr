<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\WorkspaceRole;
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
use App\Support\AppShellData;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Session-only workspace settings. Mirrors the Inertia
 * Settings\WorkspaceSettingsController + the self-service actions on
 * WorkspaceController (leave/destroy/transferOwnership), returning JSON and
 * resolving "the workspace" from ResolveApiWorkspace instead of a route param.
 */
class WorkspaceSettingsController extends Controller
{
    public function overview(Request $request): JsonResponse
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
                return response()->json(
                    ['message' => __('The workspace photo could not be saved.'), 'errors' => ['photo' => [__('The workspace photo could not be saved.')]]],
                    422,
                );
            }

            $validated['logo'] = $path;

            if (is_string($oldLogo) && $oldLogo !== '' && ! str_starts_with($oldLogo, 'http') && ! str_starts_with($oldLogo, '/')) {
                FileStorage::disk($disk)->delete($oldLogo);
            }
        }

        $workspace->update($validated);

        return response()->json([
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'logo' => $workspace->logo,
                'owner_id' => $workspace->owner_id,
            ],
        ]);
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
            'pendingInvitations' => $workspace->invitations()->pending()->with('inviter')->get()
                ->map(fn (WorkspaceInvitation $i) => [
                    'id' => $i->id,
                    'email' => $i->email,
                    'role' => $i->role,
                    'invited_by' => $i->inviter?->name,
                    'expires_at' => $i->expires_at,
                    'created_at' => $i->created_at,
                ])->all(),
            'canManage' => $user->hasAllPermissions(['workspace.users.manage'], $workspace->id),
            'availableRoles' => ['member', 'admin'],
        ]);
    }

    public function invite(InviteMemberRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        if ($workspace->members()->whereHas('user', fn ($q) => $q->where('email', $request->validated('email')))->exists()) {
            return response()->json(
                ['message' => __('This user is already a member.'), 'errors' => ['email' => [__('This user is already a member.')]]],
                422,
            );
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
                'invited_by' => $user->name,
                'expires_at' => $invitation->expires_at,
                'created_at' => $invitation->created_at,
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
            return response()->json(
                ['message' => __('You cannot change your own role.'), 'errors' => ['role' => [__('You cannot change your own role.')]]],
                422,
            );
        }

        if ($membership->isOwner()) {
            return response()->json(
                ['message' => __('Use ownership transfer to change the owner.'), 'errors' => ['role' => [__('Use ownership transfer to change the owner.')]]],
                422,
            );
        }

        $membership->update(['role' => $request->validated('role')]);

        return response()->json(['member' => ['id' => $membership->id, 'role' => $membership->role->value]]);
    }

    public function removeMember(Request $request, WorkspaceMembership $membership): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $membership->workspace_id !== $workspace->id, 404);
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        if ($membership->isOwner()) {
            return response()->json(
                ['message' => __('Cannot remove the workspace owner.'), 'errors' => ['error' => [__('Cannot remove the workspace owner.')]]],
                422,
            );
        }

        if ($membership->user_id === $user->id) {
            return response()->json(
                ['message' => __('Use “leave workspace” to remove yourself.'), 'errors' => ['error' => [__('Use “leave workspace” to remove yourself.')]]],
                422,
            );
        }

        $member = $membership->user;
        $membership->delete();

        if ($member->current_workspace_id === $workspace->id) {
            $next = $member->workspaceMemberships()->first();
            $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
        }

        return response()->json(['removed' => true]);
    }

    public function cancelInvitation(Request $request, WorkspaceInvitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $invitation->workspace_id !== $workspace->id, 404);
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        $invitation->delete();

        return response()->json(['cancelled' => true]);
    }

    /**
     * Leave the current workspace. Sole owners of multi-member workspaces must
     * transfer ownership or delete the workspace first (mirrors the web
     * WorkspaceController::leave guard).
     */
    public function leave(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        $membership = $user->getMembershipForWorkspace($workspace->id);
        abort_if($membership === null, 404);

        if ($this->isSoleOwnerWithOtherMembers($workspace, $user->id)) {
            return response()->json(
                ['message' => __('Transfer ownership or delete the workspace before leaving.'), 'errors' => ['workspace' => [__('Transfer ownership or delete the workspace before leaving.')]]],
                422,
            );
        }

        DB::transaction(function () use ($membership, $user, $workspace): void {
            $membership->delete();

            if ($user->current_workspace_id === $workspace->id) {
                $next = $user->workspaceMemberships()->first();
                $user->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
            }
        });

        return response()->json(['workspaces' => app(AppShellData::class)->workspaces($user->fresh())]);
    }

    /**
     * Delete the current workspace. Same guards as the web controller: owner
     * only, another workspace must exist, and the initial workspace is
     * protected when subscriptions are enabled (it anchors the free tier).
     */
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
            return response()->json(
                ['message' => __('Create or join another workspace before deleting this one.'), 'errors' => ['workspace' => [__('Create or join another workspace before deleting this one.')]]],
                422,
            );
        }

        if ($workspace->is_initial && (bool) config('subscriptions.enabled')) {
            return response()->json(
                ['message' => __('The initial workspace of this instance cannot be deleted.'), 'errors' => ['workspace' => [__('The initial workspace of this instance cannot be deleted.')]]],
                422,
            );
        }

        DB::transaction(function () use ($workspace): void {
            // Reassign current workspace for any member who had this as their
            // current BEFORE the nullOnDelete FK cascade fires, so they land on
            // another of their workspaces when one exists.
            $affected = User::where('current_workspace_id', $workspace->id)->get();

            foreach ($affected as $member) {
                $next = $member->workspaceMemberships()
                    ->where('workspace_id', '!=', $workspace->id)
                    ->first();

                $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
            }

            $workspace->delete(); // cascades memberships + invitations via FK
        });

        return response()->json(['workspaces' => app(AppShellData::class)->workspaces($user->fresh())]);
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
            $target->update(['role' => WorkspaceRole::Owner]);
            $currentOwner->update(['role' => WorkspaceRole::Admin]);
            $workspace->update(['owner_id' => $target->user_id]);
        });

        return response()->json(['workspaces' => app(AppShellData::class)->workspaces($user->fresh())]);
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
