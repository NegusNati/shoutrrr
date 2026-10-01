<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\Workspace\WorkspaceInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invitation accept/deny for the SPA — the JSON equivalent of
 * WorkspaceInvitationResponseController. Kept outside the workspace-scoped
 * group: the invitee's current workspace is unrelated to the invited one.
 */
class WorkspaceInvitationsController extends Controller
{
    /**
     * Public read of an invitation for the SPA's accept page — the JSON
     * equivalent of the guest half of WorkspaceController::showInvitation.
     * Invalid or expired tokens 404 so the page can render its dead-link
     * state rather than leaking whether a token ever existed.
     */
    public function show(string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::findByToken($token);

        abort_unless($invitation !== null && $invitation->isValid(), 404);

        return response()->json([
            'id' => $invitation->id,
            'workspace_name' => $invitation->workspace->name,
            'role' => $invitation->role,
            'inviter_name' => $invitation->inviter()->value('name') ?? 'Someone',
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'user_exists' => User::where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, WorkspaceInvitation $invitation, WorkspaceInvitationService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->abortUnlessInvitee($user, $invitation);

        $result = $service->accept($invitation, $user);

        if (! $result->wasSuccessful()) {
            return response()->json(['message' => $result->message], 422);
        }

        $this->deleteInvitationNotifications($user, $invitation);

        return response()->json(['message' => $result->message]);
    }

    public function deny(Request $request, WorkspaceInvitation $invitation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->abortUnlessInvitee($user, $invitation);
        abort_unless($invitation->isValid(), 404);

        $this->deleteInvitationNotifications($user, $invitation);
        $invitation->delete();

        return response()->json(null, 204);
    }

    private function abortUnlessInvitee(User $user, WorkspaceInvitation $invitation): void
    {
        abort_unless(hash_equals(mb_strtolower($invitation->email), mb_strtolower($user->email)), 404);
    }

    private function deleteInvitationNotifications(User $user, WorkspaceInvitation $invitation): void
    {
        $user->notifications()
            ->where('data->event', NotificationType::WorkspaceInvite->value)
            ->where('data->invitation_id', $invitation->id)
            ->delete();
    }
}
