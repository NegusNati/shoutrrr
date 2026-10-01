<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\InviteMemberRequest;
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
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class WorkspaceSettingsController extends Controller
{
    /**
     * @return array{workspace: array<string, mixed>, canManage: bool, isOwner: bool, canDelete: bool, deleteDisabledReason: ?string, timezone: string, timezones: array<int, string>}
     */
    protected function overviewPayload(User $user, Workspace $workspace): array
    {
        $schedule = PostingSchedule::query()->where('workspace_id', $workspace->id)->first();
        $timezone = $schedule !== null ? $schedule->timezone : 'UTC';

        $hasAnotherWorkspace = $user->workspaceMemberships()
            ->where('workspace_id', '!=', $workspace->id)
            ->exists();
        $isProtectedInitialWorkspace = $workspace->is_initial && (bool) config('subscriptions.enabled');

        return [
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
        ];
    }

    public function update(UpdateWorkspaceRequest $request): RedirectResponse
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
                return back()->withErrors(['photo' => 'The workspace photo could not be saved.']);
            }

            $validated['logo'] = $path;

            if (is_string($oldLogo) && $oldLogo !== '' && ! str_starts_with($oldLogo, 'http') && ! str_starts_with($oldLogo, '/')) {
                FileStorage::disk($disk)->delete($oldLogo);
            }
        }

        $workspace->update($validated);

        return back()->with('success', 'Workspace updated.');
    }

    public function updateTimezone(UpdateWorkspaceTimezoneRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        PostingSchedule::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            ['timezone' => $request->validated('timezone')],
        );

        return back()->with('success', 'Posting timezone saved.');
    }

    /**
     * @return array{pendingInvitations: Collection<int, array{id: string, email: string, role: string, invited_by: string|null, expires_at: CarbonImmutable, created_at: CarbonImmutable|null}>, canManage: bool, availableRoles: array<int, string>}
     */
    protected function membersPayload(User $user, Workspace $workspace): array
    {
        return [
            'pendingInvitations' => $workspace->invitations()->pending()->with('inviter')->get()->map(fn (WorkspaceInvitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'invited_by' => $i->inviter?->name,
                'expires_at' => $i->expires_at,
                'created_at' => $i->created_at,
            ]),
            'canManage' => $user->hasAllPermissions(['workspace.users.manage'], $workspace->id),
            'availableRoles' => ['member', 'admin'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function membersList(Workspace $workspace): array
    {
        return $workspace->members()->with('user')->get()->map(fn (WorkspaceMembership $m) => [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'name' => $m->user->name,
            'email' => $m->user->email,
            'avatar' => $m->user->avatar,
            'role' => $m->role->value,
            'is_owner' => $m->isOwner(),
            'created_at' => $m->created_at,
        ])->all();
    }

    public function inviteUser(InviteMemberRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null, 404);

        if ($workspace->members()->whereHas('user', fn ($q) => $q->where('email', $request->validated('email')))->exists()) {
            return back()->withErrors(['email' => 'This user is already a member.']);
        }

        $this->sendInvitation($workspace, $user, $request->validated('email'), $request->validated('role'));

        return back()->with('success', 'Invitation sent.');
    }

    /**
     * Create a pending invitation for the email and mail it. Returns the
     * invitation; members-already-present must be checked by the caller.
     */
    protected function sendInvitation(Workspace $workspace, User $user, string $email, string $role): WorkspaceInvitation
    {
        [$plain, $hash] = WorkspaceInvitation::generateToken();

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'invited_by' => $user->id,
            'email' => $email,
            'role' => $role,
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

        return $invitation;
    }

    public function updateMemberRole(UpdateMemberRoleRequest $request, WorkspaceMembership $membership): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $membership->workspace_id !== $workspace->id, 404);

        if ($membership->user_id === $user->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        if ($membership->isOwner()) {
            return back()->withErrors(['role' => 'Use ownership transfer to change the owner.']);
        }

        $membership->update(['role' => $request->validated('role')]);

        return back()->with('success', 'Member role updated.');
    }

    public function removeMember(Request $request, WorkspaceMembership $membership): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $membership->workspace_id !== $workspace->id, 404);
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        if ($membership->isOwner()) {
            return back()->withErrors(['error' => 'Cannot remove the workspace owner.']);
        }

        if ($membership->user_id === $user->id) {
            return back()->withErrors(['error' => 'Use “leave workspace” to remove yourself.']);
        }

        $member = $membership->user;
        $membership->delete();

        if ($member->current_workspace_id === $workspace->id) {
            $next = $member->workspaceMemberships()->first();
            $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
        }

        return back()->with('success', 'Member removed.');
    }

    public function cancelInvitation(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_if($workspace === null || $invitation->workspace_id !== $workspace->id, 404);
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }
}
