<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        unset($validated['photo']);

        $user->fill($validated);

        if ($request->hasFile('photo')) {
            $oldAvatarPath = $user->avatar_path;
            $disk = FileStorage::publicImageDiskName();
            $path = $request->file('photo')->store('profile-photos', $disk);

            if ($path === false) {
                return response()->json(
                    ['message' => __('The profile photo could not be saved.'), 'errors' => ['photo' => __('The profile photo could not be saved.')]],
                    422,
                );
            }

            $user->avatar_path = $path;

            if ($oldAvatarPath) {
                FileStorage::disk($disk)->delete($oldAvatarPath);
            }
        }

        if (config('auth.email_verification.enabled', false) && $user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json(['user' => $user->fresh()]);
    }

    public function destroy(ProfileDeleteRequest $request): JsonResponse
    {
        $user = $request->user();

        $ownedMemberships = $user->workspaceMemberships()
            ->where('role', WorkspaceRole::Owner->value)
            ->get();

        // Sole owners of multi-member workspaces must transfer ownership first;
        // sole-member workspaces are deleted along with the user.
        $blocking = $ownedMemberships->filter(
            fn (WorkspaceMembership $membership): bool => WorkspaceMembership::where('workspace_id', $membership->workspace_id)->count() > 1
        );

        if ($blocking->isNotEmpty()) {
            return response()->json(
                ['message' => __('You must transfer ownership of your workspaces before deleting your account.')],
                422,
            );
        }

        DB::transaction(function () use ($user, $ownedMemberships): void {
            Workspace::whereIn('id', $ownedMemberships->pluck('workspace_id'))
                ->each(fn (Workspace $workspace) => $workspace->delete());

            $user->delete();
        });

        // Stateful SPA requests have a session to invalidate; token or plain
        // actingAs requests reach here without one.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['deleted' => true]);
    }
}
