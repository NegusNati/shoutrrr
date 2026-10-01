<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Settings\WorkspaceSettingsController as WebWorkspaceSettingsController;
use App\Http\Requests\Workspace\InviteMemberRequest;
use App\Http\Requests\Workspace\UpdateMemberRoleRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceTimezoneRequest;
use App\Models\PostingSchedule;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMembership;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;

class WorkspaceSettingsController extends WebWorkspaceSettingsController
{
    public function showOverviewApi(Request $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->overviewPayload($user, $workspace));
    }

    public function updateWorkspace(UpdateWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        $validated = $request->validated();
        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            $oldLogo = $workspace->getRawOriginal('logo');
            $disk = FileStorage::publicImageDiskName();
            $path = $request->file('photo')->store('workspace-photos', $disk);

            if ($path === false) {
                throw ValidationException::withMessages(['photo' => 'The workspace photo could not be saved.']);
            }

            $validated['logo'] = $path;

            if (is_string($oldLogo) && $oldLogo !== '' && ! str_starts_with($oldLogo, 'http') && ! str_starts_with($oldLogo, '/')) {
                FileStorage::disk($disk)->delete($oldLogo);
            }
        }

        $workspace->update($validated);

        return response()->json(['message' => 'Workspace updated.']);
    }

    public function updateTimezoneApi(UpdateWorkspaceTimezoneRequest $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();

        PostingSchedule::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            ['timezone' => $request->validated('timezone')],
        );

        return response()->json(['message' => 'Posting timezone saved.']);
    }

    public function showMembersApi(Request $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'members' => $this->membersList($workspace),
            ...$this->membersPayload($user, $workspace),
        ]);
    }

    public function invite(InviteMemberRequest $request): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();

        if ($workspace->members()->whereHas('user', fn ($q) => $q->where('email', $request->validated('email')))->exists()) {
            throw ValidationException::withMessages(['email' => 'This user is already a member.']);
        }

        $invitation = $this->sendInvitation($workspace, $user, $request->validated('email'), $request->validated('role'));

        return response()->json(['id' => $invitation->id, 'message' => 'Invitation sent.'], 201);
    }

    public function updateRole(UpdateMemberRoleRequest $request, string $membershipId): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        $membership = $this->findMembershipOrFail($workspace, $membershipId);

        if ($membership->user_id === $user->id) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if ($membership->isOwner()) {
            throw ValidationException::withMessages(['role' => 'Use ownership transfer to change the owner.']);
        }

        $membership->update(['role' => $request->validated('role')]);

        return response()->json(['message' => 'Member role updated.']);
    }

    public function removeMemberApi(Request $request, string $membershipId): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);
        $membership = $this->findMembershipOrFail($workspace, $membershipId);

        if ($membership->isOwner()) {
            throw ValidationException::withMessages(['error' => 'Cannot remove the workspace owner.']);
        }

        if ($membership->user_id === $user->id) {
            throw ValidationException::withMessages(['error' => 'Use “leave workspace” to remove yourself.']);
        }

        $member = $membership->user;
        $membership->delete();

        if ($member->current_workspace_id === $workspace->id) {
            $next = $member->workspaceMemberships()->first();
            $member->forceFill(['current_workspace_id' => $next?->workspace_id])->save();
        }

        return response()->json(['message' => 'Member removed.']);
    }

    public function cancelInvitationApi(Request $request, string $invitationId): JsonResponse
    {
        $workspace = $this->apiWorkspace();
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasAllPermissions(['workspace.users.manage'], $workspace->id), 403);

        $invitation = WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($invitationId)
            ->firstOr(fn () => abort(404, 'No invitation with that id exists in this workspace.'));

        $invitation->delete();

        return response()->json(['message' => 'Invitation cancelled.']);
    }

    private function apiWorkspace(): Workspace
    {
        /** @var Workspace $workspace */
        $workspace = Workspace::query()->whereKey(Context::get('workspace_id'))->firstOrFail();

        return $workspace;
    }

    private function findMembershipOrFail(Workspace $workspace, string $membershipId): WorkspaceMembership
    {
        return WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($membershipId)
            ->firstOr(fn () => abort(404, 'No member with that id exists in this workspace.'));
    }
}
