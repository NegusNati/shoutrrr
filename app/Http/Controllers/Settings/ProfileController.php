<?php

namespace App\Http\Controllers\Settings;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Support\FileStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        unset($validated['photo']);

        $photoError = $this->applyProfileUpdate($user, $validated, $request->file('photo'));

        if ($photoError !== null) {
            return back()->withErrors(['photo' => $photoError]);
        }

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->deletionBlockReason($user) !== null) {
            return back()->withErrors([
                'password' => 'Transfer ownership or delete workspaces where you are the only owner before deleting your account.',
            ]);
        }

        $this->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Apply the validated profile fields plus an optional avatar upload.
     * Returns an error message when the photo could not be stored.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function applyProfileUpdate(User $user, array $validated, mixed $photo): ?string
    {
        $user->fill($validated);

        if ($photo !== null) {
            $oldAvatarPath = $user->avatar_path;
            $disk = FileStorage::publicImageDiskName();
            $path = $photo->store('profile-photos', $disk);

            if ($path === false) {
                return 'The profile photo could not be saved.';
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

        return null;
    }

    /**
     * Reason the account cannot be deleted right now, or null. Blocks while the
     * user is the sole owner of a workspace that still has other members.
     */
    protected function deletionBlockReason(User $user): ?string
    {
        $ownedMemberships = $user->workspaceMemberships()
            ->where('role', WorkspaceRole::Owner->value)
            ->get();

        $blocking = $ownedMemberships->filter(
            fn (WorkspaceMembership $membership): bool => WorkspaceMembership::where('workspace_id', $membership->workspace_id)->count() > 1
        );

        return $blocking->isNotEmpty()
            ? 'Transfer ownership or delete workspaces where you are the only owner before deleting your account.'
            : null;
    }

    /**
     * Delete the user plus every workspace they solely own, inside one
     * transaction. Callers must run deletionBlockReason() first.
     */
    protected function deleteAccount(User $user): void
    {
        $ownedMemberships = $user->workspaceMemberships()
            ->where('role', WorkspaceRole::Owner->value)
            ->get();

        DB::transaction(function () use ($user, $ownedMemberships): void {
            // After the guard, every workspace this user owns is single-member, so
            // deleting it is safe and clears the restricted owner_id foreign key.
            Workspace::whereIn('id', $ownedMemberships->pluck('workspace_id'))
                ->each(fn (Workspace $workspace) => $workspace->delete());

            Auth::logout();

            $user->delete();
        });
    }
}
